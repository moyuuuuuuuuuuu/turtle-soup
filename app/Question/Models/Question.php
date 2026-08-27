<?php

declare(strict_types=1);

namespace App\Question\Models;

use App\Game\Models\Game;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use plugin\saiadmin\basic\eloquent\BaseModel;

/**
 * @property int $id
 * @property string $public_id
 * @property int $difficulty
 * @property null|int $question_limit
 * @property string $source_type
 * @property string|null $source_url
 * @property string|null $source_author
 * @property string|null $source_license
 * @property string|null $source_hash
 * @property string|null $content_hash
 * @property string $risk_level
 * @property array<int, string>|null $risk_types
 * @property string|null $risk_note
 * @property-read Collection<int, QuestionTranslation> $translations
 * @property-read Collection<int, Tag> $tags
 */
final class Question extends BaseModel
{
    protected $table = 'turtle_questions';

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'published_at' => 'datetime',
            'risk_reviewed_at' => 'datetime',
            'risk_types' => 'array',
            'is_featured' => 'boolean',
            'featured_starts_at' => 'datetime:Y-m-d H:i:s',
            'featured_ends_at' => 'datetime:Y-m-d H:i:s',
        ]);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(QuestionTranslation::class);
    }

    public function points(): HasMany
    {
        return $this->hasMany(QuestionPoint::class)->orderBy('sort');
    }

    public function hints(): HasMany
    {
        return $this->hasMany(QuestionHint::class)->orderBy('level');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'turtle_question_tags');
    }

    /** @return HasMany<Game, $this> */
    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }

    /** @return HasMany<QuestionVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(QuestionVersion::class);
    }
}
