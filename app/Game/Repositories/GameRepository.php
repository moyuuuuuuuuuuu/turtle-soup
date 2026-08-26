<?php

declare(strict_types=1);

namespace App\Game\Repositories;

use App\Game\Models\Game;
use App\Game\Models\GameDiscoveredPoint;
use App\Game\Models\GameGuess;
use App\Game\Models\GameHint;
use App\Game\Models\GameMessage;

final class GameRepository
{
    public function find(string $publicId, int $sessionId, bool $lock = false): ?Game
    {
        $q = Game::query()->where('public_id', $publicId)->where('anonymous_session_id', $sessionId);
        if ($lock) {
            $q->lockForUpdate();
        } $game = $q->first();
        return $game instanceof Game ? $game : null;
    }
    public function hydrated(Game $game): Game
    {
        return $game->fresh(['messages','hints','points','guess']) ?? $game;
    }
    public function duplicate(Game $game, string $requestId): ?GameMessage
    {
        $m = GameMessage::query()->where('game_id', $game->id)->where('request_id', $requestId)->first();
        return $m instanceof GameMessage ? $m : null;
    }
    public function message(Game $game, string $requestId, string $role, string $type, string $content, array $metadata = []): GameMessage
    {
        $sequence = (int)$game->next_sequence;
        $message = GameMessage::create(['game_id' => $game->id,'sequence' => $sequence,'request_id' => $requestId,'role' => $role,'type' => $type,'content' => $content,'metadata' => $metadata]);
        $game->update(['next_sequence' => $sequence + 1]);
        return $message;
    }
    public function discover(Game $game, array $keys): void
    {
        foreach (array_unique($keys) as $key) {
            GameDiscoveredPoint::query()->firstOrCreate(['game_id' => $game->id,'point_key' => (string)$key], ['confidence' => 1,'discovered_at' => date('Y-m-d H:i:s')]);
        }
    }
    public function hint(Game $game, int $level, string $requestId): void
    {
        GameHint::create(['game_id' => $game->id,'level' => $level,'request_id' => $requestId,'used_at' => date('Y-m-d H:i:s')]);
    }
    public function guess(Game $game, string $requestId, string $content, array $result): void
    {
        GameGuess::create(['game_id' => $game->id,'request_id' => $requestId,'content' => $content,'is_solved' => (bool)$result['is_solved'],'matched_points' => $result['matched_point_keys'] ?? [],'summary' => $result['summary'] ?? '','submitted_at' => date('Y-m-d H:i:s')]);
    }
}
