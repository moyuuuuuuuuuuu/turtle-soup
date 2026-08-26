<?php

declare(strict_types=1);

namespace App\Game\Formats;

use App\Game\Models\Game;

final class GameFormat
{
    public static function snapshot(Game $game): array
    {
        $finished = in_array($game->status, ['solved', 'finished', 'abandoned'], true);
        $snapshot = (array) $game->question_snapshot;

        return [
            'id' => $game->public_id,
            'mode' => $game->room_id ? 'multiplayer' : 'single',
            'room_id' => $game->relationLoaded('room') ? $game->room?->public_id : null,
            'status' => $game->status,
            'difficulty' => (int) $game->difficulty,
            'question_limit' => (int) $game->question_limit,
            'question_count' => (int) $game->question_count,
            'remaining_questions' => max(0, (int) $game->question_limit - (int) $game->question_count),
            'hint_count' => (int) $game->hint_count,
            'title' => $snapshot['title'] ?? '',
            'surface' => $snapshot['surface'] ?? '',
            'risk_level' => $snapshot['risk_level'] ?? 'safe',
            'messages' => $game->messages->map(static fn ($message): array => [
                'sequence' => (int) $message->sequence,
                'user_id' => $message->user_id ? (int) $message->user_id : null,
                'role' => $message->role,
                'type' => $message->type,
                'content' => $message->content,
                'metadata' => $message->metadata,
            ])->all(),
            'used_hints' => $game->hints->pluck('level')->map(static fn ($value): int => (int) $value)->all(),
            'discovered_points' => $game->points->pluck('point_key')->all(),
            'bottom' => $finished ? ($snapshot['bottom'] ?? null) : null,
            'points' => $finished ? ($snapshot['points'] ?? []) : null,
            'guess' => $finished && $game->guess ? [
                'content' => $game->guess->content,
                'is_solved' => (bool) $game->guess->is_solved,
                'summary' => $game->guess->summary,
            ] : null,
        ];
    }
}
