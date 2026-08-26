<?php

declare(strict_types=1);

namespace App\Question\Models;

use App\Common\Models\PersistenceModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class QuestionHint extends PersistenceModel
{
    protected $table = 'turtle_question_hints';
    public function translations(): HasMany
    {
        return $this->hasMany(QuestionHintTranslation::class, 'hint_id');
    }
}
