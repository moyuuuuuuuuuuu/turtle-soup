<?php

declare(strict_types=1);

namespace Tests\Game;

use App\Game\WebSocket\GameWebSocket;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Workerman\Connection\TcpConnection;

final class GameWebSocketTest extends TestCase
{
    public function testRegisteredHistoryKeepsOwnerFallbackAndRoomCreationBackfillsOwnerPlayer(): void
    {
        $gameBusiness = file_get_contents(dirname(__DIR__, 2).'/app/Game/Business/GameBusiness.php');
        $roomBusiness = file_get_contents(dirname(__DIR__, 2).'/app/Room/Business/RoomBusiness.php');

        self::assertIsString($gameBusiness);
        self::assertIsString($roomBusiness);
        self::assertStringContainsString("->where('user_id', \$context->userId)", $gameBusiness);
        self::assertStringContainsString("->orWhereHas('players'", $gameBusiness);
        self::assertStringContainsString("['game_id' => (int) \$game->id, 'user_id' => \$userId]", $roomBusiness);
    }

    protected function tearDown(): void
    {
        $reflection = new ReflectionClass(GameWebSocket::class);
        foreach (['roomConnections', 'connectionRooms'] as $propertyName) {
            $property = $reflection->getProperty($propertyName);
            $property->setValue(null, []);
        }
    }

    public function testConnectionRequiresAuthenticationBeforePing(): void
    {
        $sent = [];
        $connection = $this->createMock(TcpConnection::class);
        $connection->id = 101;
        $connection->expects(self::once())
            ->method('send')
            ->willReturnCallback(static function (string $payload) use (&$sent): void {
                $sent[] = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            });

        $socket = new GameWebSocket();
        $socket->onConnect($connection);
        $socket->onMessage($connection, json_encode(['event' => 'v1.ping'], JSON_THROW_ON_ERROR));

        self::assertSame('v1.game.error', $sent[0]['event']);
        self::assertSame('auth.anonymous_invalid', $sent[0]['data']['code']);
        self::assertFalse($sent[0]['data']['retryable']);
    }

    public function testMissingRequestIdReturnsCorrelatedProtocolError(): void
    {
        $sent = [];
        $connection = $this->createMock(TcpConnection::class);
        $connection->id = 102;
        $connection->expects(self::once())
            ->method('send')
            ->willReturnCallback(static function (string $payload) use (&$sent): void {
                $sent[] = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            });

        $socket = new GameWebSocket();
        $socket->onConnect($connection);
        $socket->onMessage($connection, json_encode(['event' => 'v1.room.join'], JSON_THROW_ON_ERROR));

        self::assertSame('v1.game.error', $sent[0]['event']);
        self::assertSame('', $sent[0]['request_id']);
        self::assertSame('request.param_missing', $sent[0]['data']['code']);
    }

    public function testClosingUnauthenticatedConnectionCleansRegistryWithoutSchedulingLeave(): void
    {
        $connection = $this->createMock(TcpConnection::class);
        $connection->id = 103;

        $socket = new GameWebSocket();
        $socket->onConnect($connection);
        $socket->onClose($connection);

        $reflection = new ReflectionClass(GameWebSocket::class);
        $property = $reflection->getProperty('connectionRooms');
        self::assertArrayNotHasKey(103, $property->getValue());
    }
}
