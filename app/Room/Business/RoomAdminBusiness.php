<?php

declare(strict_types=1);

namespace App\Room\Business;

use App\Common\Enums\ErrorCode;
use App\Room\Models\Room;

final class RoomAdminBusiness
{
    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function page(array $filters, int $page, int $size): array
    {
        $query = Room::query()->withCount(['members as member_count' => static fn ($members) => $members->where('status', 'active')]);
        if ($keyword = trim((string) ($filters['keyword'] ?? ''))) {
            $query->where(static fn ($builder) => $builder->where('name', 'like', "%{$keyword}%")->orWhere('public_id', $keyword)->orWhere('invite_code', strtoupper($keyword)));
        }
        if ($status = (string) ($filters['status'] ?? '')) {
            $query->where('status', $status);
        }
        $total = $query->count();

        return ['items' => $query->orderByDesc('id')->forPage($page, $size)->get()->toArray(), 'total' => $total, 'page' => $page, 'pageSize' => $size];
    }

    /** @return array<string, mixed> */
    public function read(int $id): array
    {
        $room = Room::query()->with(['members.user', 'messages.user', 'game'])->find($id);
        if (!$room instanceof Room) {
            ErrorCode::ROOM_NOT_FOUND->throw();
        }

        return $room->toArray();
    }

    public function close(int $id): void
    {
        $room = Room::find($id);
        if (!$room instanceof Room) {
            ErrorCode::ROOM_NOT_FOUND->throw();
        }
        if (!in_array($room->status, ['finished', 'closed'], true)) {
            $room->update(['status' => 'closed', 'finished_at' => date('Y-m-d H:i:s')]);
        }
    }
}
