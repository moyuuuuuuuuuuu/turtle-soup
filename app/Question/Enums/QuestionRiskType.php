<?php

declare(strict_types=1);

namespace App\Question\Enums;

enum QuestionRiskType: string
{
    case DEATH = 'death';
    case VIOLENCE = 'violence';
    case GORE = 'gore';
    case SELF_HARM = 'self_harm';
    case SEXUAL = 'sexual';
    case CHILD_SAFETY = 'child_safety';
    case DISCRIMINATION = 'discrimination';
    case ILLEGAL = 'illegal';
    case SUBSTANCE = 'substance';
    case OTHER = 'other';
}
