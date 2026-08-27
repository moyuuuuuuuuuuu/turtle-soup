<?php

declare(strict_types=1);

namespace App\Room\Formats;

use App\Room\Models\Room;

final class RoomFormat
{
    /** @return array<string, mixed> */
    public static function snapshot(Room $room, int $viewerId, array $mutedUserIds = []): array
    {
        $members = $room->members->where('status', 'active')->values();
        $question = $room->game?->question;
        $translations = $question?->translations;
        $translation = $translations?->firstWhere('language', (string) $room->content_locale)
            ?? $translations?->firstWhere('language', 'zh-CN')
            ?? $translations?->first();

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
            'question_id' => $room->game?->question?->public_id,
            'question' => $question ? [
                'id' => (string) $question->public_id,
                'title' => (string) ($translation?->title ?? ''),
                'surface' => (string) ($translation?->surface ?? ''),
                'difficulty' => (int) $question->difficulty,
                'language' => (string) ($translation?->language ?? $room->content_locale),
                'risk_level' => (string) $question->risk_level,
                'risk_warning' => $question->risk_level === 'caution' ? '该题目包含可能令人不适的情节，请确认后继续。' : null,
                'tags' => $question->tags->map(static fn ($tag): array => ['id' => (int) $tag->id, 'name' => (string) $tag->name])->values()->all(),
            ] : null,
            'game_id' => $room->game?->public_id,
            'members' => $members->map(static fn ($member): array => [
                'user_id' => (int) $member->user_id,
                'username' => (string) ($member->user->username ?? '玩家'),
                'avatar_url' => $member->user->avatar_url,
                'role' => (string) $member->role,
                'is_ready' => (bool) $member->is_ready,
                'is_self' => (int) $member->user_id === $viewerId,
                'is_muted' => in_array((int) $member->user_id, $mutedUserIds, true),
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
