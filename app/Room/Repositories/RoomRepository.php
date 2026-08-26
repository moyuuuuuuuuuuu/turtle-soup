<?php

declare(strict_types=1);

namespace App\Room\Repositories;

use App\Room\Models\Room;
use App\Room\Models\RoomMember;
use App\Room\Models\RoomMessage;

final class RoomRepository
{
    public function find(string $publicId, bool $lock = false): ?Room
    {
        $query = Room::query()->where('public_id', $publicId);
        if ($lock) {
            $query->lockForUpdate();
        }
        $room = $query->first();

        return $room instanceof Room ? $room : null;
    }

    public function findByInvite(string $inviteCode, bool $lock = false): ?Room
    {
        $query = Room::query()->where('invite_code', strtoupper(trim($inviteCode)));
        if ($lock) {
            $query->lockForUpdate();
        }
        $room = $query->first();

        return $room instanceof Room ? $room : null;
    }

    public function member(Room $room, int $userId, bool $activeOnly = true): ?RoomMember
    {
        $query = RoomMember::query()->where('room_id', $room->id)->where('user_id', $userId);
        if ($activeOnly) {
            $query->where('status', 'active');
        }
        $member = $query->first();

        return $member instanceof RoomMember ? $member : null;
    }

    public function hydrated(Room $room): Room
    {
        return $room->fresh(['members.user', 'messages.user']) ?? $room;
    }

    public function appendMessage(Room $room, int $userId, string $requestId, string $content): RoomMessage
    {
        $existing = RoomMessage::query()->where('room_id', $room->id)->where('request_id', $requestId)->first();
        if ($existing instanceof RoomMessage) {
            return $existing;
        }
        $sequence = (int) RoomMessage::query()->where('room_id', $room->id)->lockForUpdate()->max('sequence') + 1;

        $message = RoomMessage::create([
            'room_id' => $room->id,
            'user_id' => $userId,
            'sequence' => $sequence,
            'request_id' => $requestId,
            'content' => $content,
        ]);

        return $message instanceof RoomMessage ? $message : throw new \RuntimeException('room.message_create_failed');
    }
}
