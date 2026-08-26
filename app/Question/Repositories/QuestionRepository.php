<?php

declare(strict_types=1);

namespace App\Question\Repositories;

use App\Question\DTO\QuestionData;
use App\Question\Models\Question;
use App\Question\Models\QuestionHint;
use App\Question\Models\QuestionPoint;
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
                'status' => 'draft',
                'source_type' => $data->sourceType,
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
                    'status' => 'draft',
                    'source_type' => $data->sourceType,
                    'min_players' => $data->minPlayers,
                    'max_players' => $data->maxPlayers,
                    'version' => $data->version + 1,
                    'updated_by' => $adminId,
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
