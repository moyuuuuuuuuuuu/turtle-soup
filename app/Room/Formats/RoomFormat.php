<?php

declare(strict_types=1);

namespace App\Room\Formats;

use App\Room\Models\Room;

final class RoomFormat
{
    /** @return array<string, mixed> */
    public static function snapshot(Room $room, int $viewerId): array
    {
        $members = $room->members->where('status', 'active')->values();

        return [
            'id' => (string) $room->public_id,
            'invite_code' => (string) $room->invite_code,
            'name' => (string) $room->name,
            'status' => (string) $room->status,
            'visibility' => (string) $room->visibility,
            'max_players' => (int) $room->max_players,
            'member_count' => $members->count(),
            'owner_user_id' => (int) $room->owner_user_id,
            'is_owner' => (int) $room->owner_user_id === $viewerId,
            'game_id' => $room->game?->public_id,
            'members' => $members->map(static fn ($member): array => [
                'user_id' => (int) $member->user_id,
                'username' => (string) ($member->user->username ?? '玩家'),
                'avatar_url' => $member->user->avatar_url,
                'role' => (string) $member->role,
                'is_ready' => (bool) $member->is_ready,
                'is_self' => (int) $member->user_id === $viewerId,
            ])->all(),
            'messages' => $room->messages->map(static fn ($message): array => [
                'sequence' => (int) $message->sequence,
                'user_id' => (int) $message->user_id,
                'username' => (string) ($message->user->username ?? '玩家'),
                'avatar_url' => $message->user->avatar_url,
                'content' => (string) $message->content,
                'create_time' => (string) $message->create_time,
            ])->all(),
            'create_time' => (string) $room->create_time,
        ];
    }
}
