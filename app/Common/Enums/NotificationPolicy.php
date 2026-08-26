<?php

declare(strict_types=1);

namespace App\Common\Enums;

enum NotificationPolicy: string
{
    case NEVER = 'never';
    case THRESHOLD = 'threshold';
    case IMMEDIATE = 'immediate';
}
