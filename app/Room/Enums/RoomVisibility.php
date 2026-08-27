<?php

declare(strict_types=1);

namespace App\Room\Enums;

enum RoomVisibility: string
{
    case PRIVATE = 'private';
    case PUBLIC = 'public';
}
