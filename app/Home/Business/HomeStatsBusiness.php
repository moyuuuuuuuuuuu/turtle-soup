<?php

declare(strict_types=1);

namespace App\Home\Business;

use App\Game\Models\Game;
use App\Game\Models\GamePlayer;
use App\Question\Models\Question;

final class HomeStatsBusiness
{
    /** @return array{question_count:int,today_online:int,success_rate:float,average_duration_seconds:int|null} */
    public function stats(): array
    {
        $today = date('Y-m-d 00:00:00');
        $registeredPlayers = GamePlayer::query()
            ->where('joined_at', '>=', $today)
            ->distinct()
            ->count('user_id');
        $anonymousPlayers = Game::query()
            ->whereNull('user_id')
            ->whereNotNull('anonymous_session_id')
            ->where('create_time', '>=', $today)
            ->distinct()
            ->count('anonymous_session_id');

        $finished = Game::query()->whereIn('status', ['solved', 'finished', 'abandoned'])->count();
        $solved = Game::query()->where('status', 'solved')->count();
        $durationRow = Game::query()
            ->whereIn('status', ['solved', 'finished', 'abandoned'])
            ->whereNotNull('started_at')
            ->whereNotNull('finished_at')
            ->whereColumn('finished_at', '>=', 'started_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, started_at, finished_at)) AS average_duration')
            ->first();
        $averageDuration = $durationRow?->getAttribute('average_duration');

        return [
            'question_count' => Question::query()
                ->where('status', 'published')
                ->whereIn('risk_level', ['safe', 'caution'])
                ->count(),
            'today_online' => $registeredPlayers + $anonymousPlayers,
            'success_rate' => $finished > 0 ? round($solved * 100 / $finished, 1) : 0.0,
            'average_duration_seconds' => $averageDuration === null ? null : (int) round((float) $averageDuration),
        ];
    }
}
