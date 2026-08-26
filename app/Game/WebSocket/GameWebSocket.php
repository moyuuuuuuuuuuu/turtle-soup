<?php

declare(strict_types=1);

namespace App\Game\WebSocket;

use App\Auth\Entities\PlayerContext;
use App\Auth\Services\PlayerPrincipalService;
use App\Game\Business\GameBusiness;
use Throwable;
use Workerman\Connection\TcpConnection;

final class GameWebSocket
{
    public function onConnect(TcpConnection $connection): void
    {
        $connection->playerContext = null;
    }
    public function onMessage(TcpConnection $connection, string $raw): void
    {
        $requestId = '';
        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            $event = (string)($data['event'] ?? '');
            $requestId = (string)($data['request_id'] ?? '');
            $payload = (array)($data['data'] ?? []);
            if ($event === 'v1.auth') {
                $context = (new PlayerPrincipalService())->authenticate((string)($payload['token'] ?? ''));
                $connection->playerContext = $context;
                $this->send($connection, 'v1.authenticated', $requestId, ['identity' => $context->isUser() ? 'user' : 'anonymous']);
                return;
            }
            if ($event === 'v1.ping') {
                $this->context($connection);
                $this->send($connection, 'v1.pong', $requestId, []);
                return;
            }
            $context = $this->context($connection);
            $business = new GameBusiness();
            $gameId = (string)($payload['game_id'] ?? '');
            $result = match($event) {
                'v1.game.join' => $business->snapshot($context, $gameId),'v1.game.question' => $business->ask($context, $gameId, $requestId, (string)($payload['question'] ?? '')),'v1.game.hint' => $business->hint($context, $gameId, $requestId, (int)($payload['level'] ?? 0)),'v1.game.guess' => $business->guess($context, $gameId, $requestId, (string)($payload['guess'] ?? '')),default => throw new \InvalidArgumentException('request.param_error')
            };
            $out = match($event) {
                'v1.game.question' => 'v1.game.answer','v1.game.guess' => (($result['status'] ?? '') === 'solved' ? 'v1.game.solved' : 'v1.game.finished'),default => 'v1.game.snapshot'
            };
            $this->send($connection, $out, $requestId, $result);
        } catch (Throwable $exception) {
            $this->send($connection, 'v1.game.error', $requestId, ['code' => $exception->getMessage() ?: 'system.error','retryable' => str_starts_with($exception->getMessage(), 'ai.')]);
        }
    }
    private function context(TcpConnection $connection): PlayerContext
    {
        if (!$connection->playerContext instanceof PlayerContext) {
            throw new \RuntimeException('auth.anonymous_invalid');
        }
        (new PlayerPrincipalService())->validate($connection->playerContext);
        return $connection->playerContext;
    }
    private function send(TcpConnection $connection, string $event, string $requestId, array $data): void
    {
        $connection->send(json_encode(['event' => $event,'request_id' => $requestId,'data' => $data,'timestamp' => time()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
