<?php

declare(strict_types=1);

namespace App\Question\Models;

use App\Common\Models\PersistenceModel;

/**
 * @property int $id
 * @property int $question_id
 * @property int $version
 * @property array<string, mixed> $snapshot
 * @property int|null $published_by
 * @property mixed $published_at
 */
final class QuestionVersion extends PersistenceModel
{
    protected $table = 'turtle_question_versions';

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'snapshot' => 'array',
            'published_at' => 'datetime:Y-m-d H:i:s',
        ]);
    }
}
