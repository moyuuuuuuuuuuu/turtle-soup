<?php

declare(strict_types=1);

namespace App\Question\Models;

use App\Common\Models\PersistenceModel;

/**
 * @property string $title
 * @property string $surface
 * @property string $language
 */
final class QuestionTranslation extends PersistenceModel
{
    protected $table = 'turtle_question_translations';
    public $timestamps = true;
}
