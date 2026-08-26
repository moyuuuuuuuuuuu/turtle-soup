<?php

declare(strict_types=1);

namespace App\Game\Services;

use App\Game\Contracts\GameJudgeInterface;

final class GameJudgeFactory
{
    public static function make(): GameJudgeInterface
    {
        return config('ai.game_judge.driver') === 'coze'
            ? new CozeGameJudge()
            : new MockGameJudge();
    }
}
