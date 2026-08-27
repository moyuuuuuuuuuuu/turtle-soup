<?php

declare(strict_types=1);

namespace App\Game\WebSocket;

use App\Auth\Entities\PlayerContext;
use App\Auth\Models\User;
use App\Auth\Services\PlayerPrincipalService;
use App\Game\Business\GameBusiness;
use App\Room\Business\RoomBusiness;
use Throwable;
use Workerman\Connection\TcpConnection;
use Workerman\Timer;

final class GameWebSocket
{
    /** @var array<string, array<int, TcpConnection>> */
    private static array $roomConnections = [];
    /** @var array<int, array<string, string>> */
    private static array $connectionRooms = [];
    /** @var array<int, PlayerContext> */
    private static array $connectionContexts = [];

    public function onConnect(TcpConnection $connection): void
    {
        self::$connectionRooms[$connection->id] = [];
    }

    public function onClose(TcpConnection $connection): void
    {
        $rooms = array_values(self::$connectionRooms[$connection->id] ?? []);
        $context = self::$connectionContexts[$connection->id] ?? null;
        foreach ($rooms as $roomId) {
            unset(self::$roomConnections[$roomId][$connection->id]);
            if (self::$roomConnections[$roomId] === []) {
                unset(self::$roomConnections[$roomId]);
            }
        }
        unset(self::$connectionRooms[$connection->id]);
        unset(self::$connectionContexts[$connection->id]);
        if ($context?->isUser() && $rooms !== []) {
            Timer::add(45, function () use ($context, $rooms): void {
                foreach ($rooms as $roomId) {
                    if ($this->isUserOnline($roomId, (int) $context->userId)) {
                        continue;
                    }
                    try {
                        (new RoomBusiness())->leave($context, $roomId);
                        $this->broadcastRoomSnapshots($roomId, 'offline-timeout');
                    } catch (Throwable) {
                    }
                }
            }, [], false);
        }
    }

    private function isUserOnline(string $roomId, int $userId): bool
    {
        foreach (self::$roomConnections[$roomId] ?? [] as $connection) {
            $context = self::$connectionContexts[$connection->id] ?? null;
            if ($context instanceof PlayerContext
                && $context->isUser()
                && (int) $context->userId === $userId) {
                return true;
            }
        }

        return false;
    }

    public function onMessage(TcpConnection $connection, string $raw): void
    {
        $requestId = '';
        try {
            $message = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            $event = (string) ($message['event'] ?? '');
            $requestId = (string) ($message['request_id'] ?? '');
            $payload = (array) ($message['data'] ?? []);
            if ($requestId === '' && $event !== 'v1.ping') {
                throw new \InvalidArgumentException('request.param_missing');
            }
            if ($event === 'v1.auth') {
                $context = (new PlayerPrincipalService())->authenticate((string) ($payload['token'] ?? ''));
                self::$connectionContexts[$connection->id] = $context;
                $this->send($connection, 'v1.authenticated', $requestId, ['identity' => $context->isUser() ? 'user' : 'anonymous']);

                return;
            }
            if ($event === 'v1.ping') {
                $context = $this->context($connection);
                if ($context->isUser()) {
                    foreach (array_values(self::$connectionRooms[$connection->id] ?? []) as $roomId) {
                        try {
                            (new RoomBusiness())->touch($context, $roomId);
                        } catch (Throwable) {
                        }
                    }
                }
                $this->send($connection, 'v1.pong', $requestId, []);

                return;
            }
            $context = $this->context($connection);
            if (str_starts_with($event, 'v1.room.')) {
                $this->handleRoom($connection, $context, $event, $requestId, $payload);

                return;
            }
            $this->handleGame($connection, $context, $event, $requestId, $payload);
        } catch (Throwable $exception) {
            $this->send($connection, 'v1.game.error', $requestId, [
                'code' => $exception->getMessage() ?: 'system.error',
                'retryable' => str_starts_with($exception->getMessage(), 'ai.'),
            ]);
        }
    }

    /** @param array<string, mixed> $payload */
    private function handleGame(TcpConnection $connection, PlayerContext $context, string $event, string $requestId, array $payload): void
    {
        $business = new GameBusiness();
        $gameId = (string) ($payload['game_id'] ?? '');
        $result = match ($event) {
            'v1.game.join' => $business->snapshot($context, $gameId),
            'v1.game.question' => $business->ask($context, $gameId, $requestId, (string) ($payload['question'] ?? '')),
            'v1.game.hint' => $business->hint($context, $gameId, $requestId, (int) ($payload['level'] ?? 0)),
            'v1.game.guess' => $business->guess($context, $gameId, $requestId, (string) ($payload['guess'] ?? '')),
            'v1.game.abandon' => $business->abandon($context, $gameId),
            default => throw new \InvalidArgumentException('request.param_error'),
        };
        $out = match ($event) {
            'v1.game.question' => 'v1.game.answer',
            'v1.game.guess' => ($result['status'] ?? '') === 'solved' ? 'v1.game.solved' : 'v1.game.finished',
            'v1.game.abandon' => 'v1.game.finished',
            default => 'v1.game.snapshot',
        };
        $roomId = (string) ($result['room_id'] ?? '');
        if ($roomId !== '') {
            $this->attach($connection, $roomId);
            $business = new RoomBusiness();
            $business->touch($context, $roomId);
            $this->broadcast($roomId, $out, $requestId, $result);
        } else {
            $this->send($connection, $out, $requestId, $result);
        }
    }

    /** @param array<string, mixed> $payload */
    private function handleRoom(TcpConnection $connection, PlayerContext $context, string $event, string $requestId, array $payload): void
    {
        $business = new RoomBusiness();
        $roomId = (string) ($payload['room_id'] ?? '');
        if ($event === 'v1.room.join') {
            $result = $business->snapshot($context, $roomId);
            $this->attach($connection, $roomId);
            $this->send($connection, 'v1.room.snapshot', $requestId, $result);
            $this->broadcastRoomSnapshots($roomId, $requestId);

            return;
        }
        $business->snapshot($context, $roomId);
        $business->touch($context, $roomId);
        if (in_array($event, ['v1.room.typing.start', 'v1.room.typing.stop'], true)) {
            $user = User::query()->find($context->userId);
            $this->broadcast($roomId, 'v1.room.member.typing', $requestId, [
                'room_id' => $roomId,
                'user_id' => $context->userId,
                'username' => $user instanceof User ? $user->username : '玩家',
                'is_typing' => $event === 'v1.room.typing.start',
                'expires_in_ms' => 4000,
            ], $connection->id);

            return;
        }
        if ($event === 'v1.room.leave') {
            $business->leave($context, $roomId);
            $this->send($connection, 'v1.room.left', $requestId, ['room_id' => $roomId]);
            $this->detach($connection, $roomId);
            $this->broadcastRoomSnapshots($roomId, $requestId);

            return;
        }
        if ($event === 'v1.room.member.mute') {
            $business->mute($context, $roomId, (int) ($payload['user_id'] ?? 0), (bool) ($payload['muted'] ?? true));
            $this->broadcastRoomSnapshots($roomId, $requestId);

            return;
        }
        if ($event === 'v1.room.member.kick') {
            $targetUserId = (int) ($payload['user_id'] ?? 0);
            $business->kick($context, $roomId, $targetUserId);
            $this->broadcast($roomId, 'v1.room.member.kicked', $requestId, [
                'room_id' => $roomId,
                'user_id' => $targetUserId,
            ]);
            $this->detachUser($roomId, $targetUserId);
            $this->broadcastRoomSnapshots($roomId, $requestId);

            return;
        }
        match ($event) {
            'v1.room.chat' => $business->chat($context, $roomId, $requestId, (string) ($payload['content'] ?? '')),
            'v1.room.ready' => $business->ready($context, $roomId, (bool) ($payload['ready'] ?? true)),
            'v1.room.start' => $business->start($context, $roomId),
            default => throw new \InvalidArgumentException('request.param_error'),
        };
        $this->broadcastRoomSnapshots($roomId, $requestId);
    }

    private function attach(TcpConnection $connection, string $roomId): void
    {
        self::$roomConnections[$roomId][$connection->id] = $connection;
        self::$connectionRooms[$connection->id][$roomId] = $roomId;
    }

    private function detach(TcpConnection $connection, string $roomId): void
    {
        unset(self::$roomConnections[$roomId][$connection->id], self::$connectionRooms[$connection->id][$roomId]);
        if ((self::$roomConnections[$roomId] ?? []) === []) {
            unset(self::$roomConnections[$roomId]);
        }
    }

    private function detachUser(string $roomId, int $userId): void
    {
        foreach (self::$roomConnections[$roomId] ?? [] as $connection) {
            $context = self::$connectionContexts[$connection->id] ?? null;
            if ($context instanceof PlayerContext
                && $context->isUser()
                && (int) $context->userId === $userId) {
                $this->detach($connection, $roomId);
            }
        }
    }

    private function broadcastRoomSnapshots(string $roomId, string $requestId): void
    {
        foreach (self::$roomConnections[$roomId] ?? [] as $connection) {
            try {
                $context = $this->context($connection);
                $snapshot = (new RoomBusiness())->snapshot($context, $roomId);
                $this->send($connection, 'v1.room.snapshot', $requestId, $snapshot);
            } catch (Throwable) {
                $this->detach($connection, $roomId);
            }
        }
    }

    /** @param array<string, mixed> $data */
    private function broadcast(string $roomId, string $event, string $requestId, array $data, ?int $excludeConnectionId = null): void
    {
        foreach (self::$roomConnections[$roomId] ?? [] as $connection) {
            if ($excludeConnectionId !== null && $connection->id === $excludeConnectionId) {
                continue;
            }
            $this->send($connection, $event, $requestId, $data);
        }
    }

    private function context(TcpConnection $connection): PlayerContext
    {
        $context = self::$connectionContexts[$connection->id] ?? null;
        if (!$context instanceof PlayerContext) {
            throw new \RuntimeException('auth.anonymous_invalid');
        }
        (new PlayerPrincipalService())->validate($context);

        return $context;
    }

    /** @param array<string, mixed> $data */
    private function send(TcpConnection $connection, string $event, string $requestId, array $data): void
    {
        $connection->send(json_encode([
            'event' => $event,
            'request_id' => $requestId,
            'data' => $data,
            'timestamp' => time(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
