<?php

declare(strict_types=1);

namespace App\Question\Models;

use App\Common\Models\PersistenceModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class QuestionPoint extends PersistenceModel
{
    protected $table = 'turtle_question_points';
    public function translations(): HasMany
    {
        return $this->hasMany(QuestionPointTranslation::class, 'point_id');
    }
}
