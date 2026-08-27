<?php

declare(strict_types=1);

namespace App\Question\Repositories;

use App\Question\DTO\QuestionData;
use App\Question\Models\Question;
use App\Question\Models\QuestionHint;
use App\Question\Models\QuestionPoint;
use App\Question\Models\QuestionVersion;
use Illuminate\Database\Eloquent\Builder;
use support\Db;

final class QuestionRepository
{
    public function search(array $filters): Builder
    {
        $query = Question::query()->with(['translations', 'tags']);
        foreach (['status', 'difficulty', 'source_type'] as $field) {
            if (($filters[$field] ?? '') !== '') {
                $query->where($field, $filters[$field]);
            }
        }
        if (($filters['is_featured'] ?? '') !== '') {
            $query->where('is_featured', filter_var($filters['is_featured'], FILTER_VALIDATE_BOOL));
        }
        if (($filters['language'] ?? '') !== '') {
            $query->whereHas('translations', fn (Builder $item) => $item->where('language', $filters['language']));
        }
        if (($filters['tag_id'] ?? '') !== '') {
            $query->whereHas('tags', fn (Builder $item) => $item->where('turtle_tags.id', (int) $filters['tag_id']));
        }
        if (($filters['keyword'] ?? '') !== '') {
            $keyword = '%' . $filters['keyword'] . '%';
            $query->whereHas('translations', fn (Builder $item) => $item
                ->whereLike('title', $keyword)
                ->orWhereLike('surface', $keyword));
        }

        return $query->orderByDesc('id');
    }

    public function find(int $id): ?Question
    {
        return Question::with([
            'translations',
            'points.translations',
            'hints.translations',
            'tags',
        ])->find($id);
    }

    public function create(QuestionData $data, int $adminId): Question
    {
        return Db::transaction(function () use ($data, $adminId): Question {
            $question = Question::create([
                'public_id' => $this->publicId(),
                'difficulty' => $data->difficulty,
                'question_limit' => $data->questionLimit,
                'status' => 'draft',
                'source_type' => $data->sourceType,
                'risk_level' => $data->riskLevel,
                'risk_types' => $data->riskTypes,
                'risk_note' => $data->riskNote,
                'is_featured' => $data->isFeatured,
                'featured_sort' => $data->featuredSort,
                'featured_starts_at' => $data->featuredStartsAt,
                'featured_ends_at' => $data->featuredEndsAt,
                'min_players' => $data->minPlayers,
                'max_players' => $data->maxPlayers,
                'version' => 1,
                'created_by' => $adminId,
                'updated_by' => $adminId,
            ]);
            $this->replaceRelations($question, $data);

            return $this->find((int) $question->id);
        });
    }

    public function update(Question $question, QuestionData $data, int $adminId): Question
    {
        return Db::transaction(function () use ($question, $data, $adminId): Question {
            $updated = Question::whereKey($question->id)
                ->where('version', $data->version)
                ->update([
                    'difficulty' => $data->difficulty,
                    'question_limit' => $data->questionLimit,
                    'status' => 'draft',
                    'source_type' => $data->sourceType,
                    'risk_level' => $data->riskLevel,
                    'risk_types' => $data->riskTypes,
                    'risk_note' => $data->riskNote,
                    'is_featured' => $data->isFeatured,
                    'featured_sort' => $data->featuredSort,
                    'featured_starts_at' => $data->featuredStartsAt,
                    'featured_ends_at' => $data->featuredEndsAt,
                    'risk_reviewed_by' => null,
                    'risk_reviewed_at' => null,
                    'min_players' => $data->minPlayers,
                    'max_players' => $data->maxPlayers,
                    'version' => $data->version + 1,
                    'updated_by' => $adminId,
                    'published_at' => null,
                ]);
            if ($updated !== 1) {
                return $question;
            }
            $question->translations()->delete();
            foreach ($question->points as $point) {
                $point->translations()->delete();
            }
            foreach ($question->hints as $hint) {
                $hint->translations()->delete();
            }
            $question->hints()->delete();
            $question->points()->delete();
            $this->replaceRelations($question, $data);

            return $this->find((int) $question->id);
        });
    }

    public function publish(Question $question, int $version, int $adminId, bool $reviewRisk): ?Question
    {
        return Db::transaction(function () use ($question, $version, $adminId, $reviewRisk): ?Question {
            $now = date('Y-m-d H:i:s');
            $newVersion = $version + 1;
            $attributes = [
                'status' => 'published',
                'version' => $newVersion,
                'published_at' => $now,
                'updated_by' => $adminId,
            ];
            if ($reviewRisk) {
                $attributes['risk_reviewed_by'] = $adminId;
                $attributes['risk_reviewed_at'] = $now;
            }
            $questionId = (int) $question->getKey();
            $updated = Question::whereKey($questionId)->where('version', $version)->update($attributes);
            if ($updated !== 1) {
                return null;
            }
            $published = $this->find($questionId);
            QuestionVersion::create([
                'question_id' => $questionId,
                'version' => $newVersion,
                'snapshot' => $published?->toArray() ?? [],
                'published_by' => $adminId,
                'published_at' => $now,
            ]);

            return $published;
        });
    }

    /** @return list<array<string, mixed>> */
    public function history(int $questionId): array
    {
        return QuestionVersion::query()
            ->where('question_id', $questionId)
            ->orderByDesc('version')
            ->get(['id', 'question_id', 'version', 'published_by', 'published_at'])
            ->toArray();
    }

    public function findVersion(int $questionId, int $versionId): ?QuestionVersion
    {
        $version = QuestionVersion::query()
            ->where('question_id', $questionId)
            ->whereKey($versionId)
            ->first();

        return $version instanceof QuestionVersion ? $version : null;
    }

    private function replaceRelations(Question $question, QuestionData $data): void
    {
        $question->translations()->createMany($data->translations);
        foreach ($data->points as $pointData) {
            $translations = (array) ($pointData['translations'] ?? []);
            unset($pointData['translations'], $pointData['id']);
            $point = $question->points()->create($pointData);
            $point->translations()->createMany($translations);
        }
        foreach ($data->hints as $hintData) {
            $translations = (array) ($hintData['translations'] ?? []);
            unset($hintData['translations'], $hintData['id'], $hintData['target_point_id']);
            $hint = $question->hints()->create($hintData);
            $hint->translations()->createMany($translations);
        }
        $question->tags()->sync($data->tagIds);
    }

    private function publicId(): string
    {
        $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
        $id = '';
        for ($i = 0; $i < 26; $i++) {
            $id .= $alphabet[random_int(0, 31)];
        }

        return $id;
    }
}
