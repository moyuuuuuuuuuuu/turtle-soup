<?php

declare(strict_types=1);

namespace App\Game\WebSocket;

use App\Auth\Business\AnonymousSessionBusiness;
use App\Auth\Models\AnonymousSession;
use App\Game\Business\GameBusiness;
use Throwable;
use Workerman\Connection\TcpConnection;

final class GameWebSocket
{
    public function onConnect(TcpConnection $connection): void
    {
        $connection->sessionId = null;
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
                $session = (new AnonymousSessionBusiness())->authenticate((string)($payload['token'] ?? ''));
                $connection->sessionId = (int)$session->id;
                $this->send($connection, 'v1.authenticated', $requestId, ['session_id' => $session->public_id]);
                return;
            }
            if ($event === 'v1.ping') {
                $this->session($connection);
                $this->send($connection, 'v1.pong', $requestId, []);
                return;
            }
            $session = $this->session($connection);
            $business = new GameBusiness();
            $gameId = (string)($payload['game_id'] ?? '');
            $result = match($event) {
                'v1.game.join' => $business->snapshot($session, $gameId),'v1.game.question' => $business->ask($session, $gameId, $requestId, (string)($payload['question'] ?? '')),'v1.game.hint' => $business->hint($session, $gameId, $requestId, (int)($payload['level'] ?? 0)),'v1.game.guess' => $business->guess($session, $gameId, $requestId, (string)($payload['guess'] ?? '')),default => throw new \InvalidArgumentException('request.param_error')
            };
            $out = match($event) {
                'v1.game.question' => 'v1.game.answer','v1.game.guess' => (($result['status'] ?? '') === 'solved' ? 'v1.game.solved' : 'v1.game.finished'),default => 'v1.game.snapshot'
            };
            $this->send($connection, $out, $requestId, $result);
        } catch (Throwable $exception) {
            $this->send($connection, 'v1.game.error', $requestId, ['code' => $exception->getMessage() ?: 'system.error','retryable' => str_starts_with($exception->getMessage(), 'ai.')]);
        }
    }
    private function session(TcpConnection $connection): AnonymousSession
    {
        if (!$connection->sessionId) {
            throw new \RuntimeException('auth.anonymous_invalid');
        }$session = AnonymousSession::find($connection->sessionId);
        if (!$session instanceof AnonymousSession) {
            throw new \RuntimeException('auth.anonymous_invalid');
        }return $session;
    }
    private function send(TcpConnection $connection, string $event, string $requestId, array $data): void
    {
        $connection->send(json_encode(['event' => $event,'request_id' => $requestId,'data' => $data,'timestamp' => time()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
