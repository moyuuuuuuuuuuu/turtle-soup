<?php

declare(strict_types=1);

namespace App\Game\Enums;

enum GameStatus: string
{
    case CREATED = 'created';
    case PLAYING = 'playing';
    case SOLVED = 'solved';
    case FINISHED = 'finished';
    case ABANDONED = 'abandoned';
}
