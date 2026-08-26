<?php

declare(strict_types=1);

namespace App\Question\Enums;

enum QuestionStatus: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case OFFLINE = 'offline';
}
