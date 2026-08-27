<?php

declare(strict_types=1);

namespace Tests\Room;

use PHPUnit\Framework\TestCase;

final class RoomLifecycleContractTest extends TestCase
{
    public function testIdleRoomCleanupIsConfiguredAndKeepsActiveMembersAlive(): void
    {
        $business = file_get_contents(base_path('app/Room/Business/RoomBusiness.php'));
        $webSocket = file_get_contents(base_path('app/Game/WebSocket/GameWebSocket.php'));
        $process = file_get_contents(base_path('config/process.php'));

        self::assertStringContainsString('public function closeIdleRooms(int $idleSeconds): int', $business);
        self::assertStringContainsString("where('last_active_at', '>=', \$cutoff)", $business);
        self::assertStringContainsString("'status' => 'abandoned'", $business);
        self::assertStringContainsString('public function touch(PlayerContext $context, string $id): void', $business);
        self::assertStringContainsString('(new RoomBusiness())->touch($context, $roomId);', $webSocket);
        self::assertStringContainsString("'room.idle-cleanup'", $process);
    }

    public function testOwnerDepartureTransfersOwnershipOrClosesTheEmptyRoom(): void
    {
        $business = file_get_contents(dirname(__DIR__, 2) . '/app/Room/Business/RoomBusiness.php');

        self::assertIsString($business);
        self::assertStringContainsString('->inRandomOrder()', $business);
        self::assertStringContainsString("'role' => 'owner'", $business);
        self::assertStringContainsString("'owner_user_id' => \$successor->user_id", $business);
        self::assertStringContainsString("'status' => 'closed'", $business);
        self::assertStringContainsString("\$successorName . '的房间'", $business);
    }

    public function testLeavingAndKickedConnectionsAreDetachedBeforeSnapshotsBroadcast(): void
    {
        $webSocket = file_get_contents(dirname(__DIR__, 2) . '/app/Game/WebSocket/GameWebSocket.php');

        self::assertIsString($webSocket);
        self::assertStringContainsString("\$this->detach(\$connection, \$roomId);", $webSocket);
        self::assertStringContainsString("\$this->detachUser(\$roomId, \$targetUserId);", $webSocket);
        self::assertStringContainsString("self::\$connectionRooms[\$connection->id][\$roomId]", $webSocket);
    }

    public function testMultiplayerAbandonmentBroadcastsTheFinishedGameSnapshot(): void
    {
        $webSocket = file_get_contents(dirname(__DIR__, 2) . '/app/Game/WebSocket/GameWebSocket.php');

        self::assertIsString($webSocket);
        self::assertStringContainsString("'v1.game.abandon' => \$business->abandon", $webSocket);
        self::assertStringContainsString("'v1.game.abandon' => 'v1.game.finished'", $webSocket);
        self::assertStringContainsString("\$this->broadcast(\$roomId, \$out, \$requestId, \$result);", $webSocket);
    }

    public function testOwnerCanCloseARevealedRoomAndDeactivateAllMemberships(): void
    {
        $business = file_get_contents(dirname(__DIR__, 2) . '/app/Room/Business/RoomBusiness.php');

        self::assertIsString($business);
        self::assertStringContainsString("if (\$room->status === 'closed')", $business);
        self::assertStringContainsString("->where('status', 'active')", $business);
        self::assertStringContainsString("->update(['status' => 'left', 'is_ready' => false, 'left_at' => \$now])", $business);
    }

    public function testOwnerCanUpdateRoomVisibilityAndBroadcastTheSnapshot(): void
    {
        $business = file_get_contents(dirname(__DIR__, 2) . '/app/Room/Business/RoomBusiness.php');
        $webSocket = file_get_contents(dirname(__DIR__, 2) . '/app/Game/WebSocket/GameWebSocket.php');

        self::assertIsString($business);
        self::assertIsString($webSocket);
        self::assertStringContainsString('public function updateVisibility(', $business);
        self::assertStringContainsString('$this->assertOwner($room, $userId);', $business);
        self::assertStringContainsString("\$room->update(['visibility' => \$roomVisibility->value]);", $business);
        self::assertStringContainsString("if (\$event === 'v1.room.visibility.update')", $webSocket);
        self::assertStringContainsString('$this->broadcastRoomSnapshots($roomId, $requestId);', $webSocket);
    }

    public function testLeavingMemberIsAnnouncedToRemainingRoomMembers(): void
    {
        $webSocket = file_get_contents(dirname(__DIR__, 2) . '/app/Game/WebSocket/GameWebSocket.php');

        self::assertIsString($webSocket);
        self::assertStringContainsString("in_array(\$reason, ['manual', 'switch_question'], true)", $webSocket);
        self::assertStringContainsString("\$this->broadcast(\$roomId, 'v1.room.member.left'", $webSocket);
        self::assertStringContainsString("'username' => \$user instanceof User ? \$user->username : '玩家'", $webSocket);
        self::assertStringContainsString("'reason' => \$reason", $webSocket);
    }
}
