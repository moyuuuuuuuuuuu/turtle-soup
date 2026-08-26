<?php

declare(strict_types=1);

namespace App\Question\Models;

use App\Common\Models\PersistenceModel;

final class QuestionPointTranslation extends PersistenceModel
{
    protected $table = 'turtle_question_point_translations';
}
