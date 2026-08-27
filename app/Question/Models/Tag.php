<?php

declare(strict_types=1);

namespace App\Question\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use plugin\saiadmin\basic\eloquent\BaseModel;

/**
 * @property int $id
 * @property string $name
 */
final class Tag extends BaseModel
{
    protected $table = 'turtle_tags';

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'turtle_question_tags');
    }
}
