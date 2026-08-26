<?php

declare(strict_types=1);

namespace Tests\Game;

use App\Game\Formats\GameFormat;
use App\Game\Models\Game;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

final class GameFormatTest extends TestCase
{
    public function testPlayingSnapshotNeverContainsAnswerOrReasoningPoints(): void
    {
        $snapshot = GameFormat::snapshot($this->game('playing'));

        self::assertNull($snapshot['bottom']);
        self::assertNull($snapshot['points']);
    }

    public function testFinishedAndAbandonedSnapshotsRevealFrozenAnswer(): void
    {
        foreach (['solved', 'finished', 'abandoned'] as $status) {
            $snapshot = GameFormat::snapshot($this->game($status));
            self::assertSame('冻结汤底', $snapshot['bottom']);
            self::assertSame('point_1', $snapshot['points'][0]['key']);
        }
    }

    private function game(string $status): Game
    {
        $game = new Game();
        $game->forceFill([
            'public_id' => '01K00000000000000000000000',
            'status' => $status,
            'difficulty' => 3,
            'question_limit' => 12,
            'question_count' => 2,
            'hint_count' => 0,
            'question_snapshot' => [
                'title' => '测试题',
                'surface' => '测试汤面',
                'bottom' => '冻结汤底',
                'risk_level' => 'safe',
                'points' => [['key' => 'point_1', 'content' => '关键点']],
            ],
        ]);
        $game->setRelation('messages', new Collection());
        $game->setRelation('hints', new Collection());
        $game->setRelation('points', new Collection());
        $game->setRelation('guess', null);

        return $game;
    }
}
