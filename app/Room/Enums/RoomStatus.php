<?php

declare(strict_types=1);

namespace App\Room\Enums;

enum RoomStatus: string
{
    case WAITING = 'waiting';
    case PLAYING = 'playing';
    case FINISHED = 'finished';
    case CLOSED = 'closed';
}
