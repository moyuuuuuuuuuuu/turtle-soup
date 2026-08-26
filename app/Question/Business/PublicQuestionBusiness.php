<?php

declare(strict_types=1);

namespace App\Question\Business;

use App\Common\Enums\ErrorCode;
use App\Question\Models\Question;
use Illuminate\Database\Eloquent\Builder;

final class PublicQuestionBusiness
{
    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function page(array $filters, int $page, int $size): array
    {
        $query = $this->query($filters);
        $result = $query->paginate($size, ['*'], 'page', $page);

        return ['items' => array_map([$this, 'format'], $result->items()), 'pagination' => ['page' => $page, 'page_size' => $size, 'total' => $result->total()]];
    }

    /** @return array<string, mixed> */
    public function detail(string $publicId, string $language): array
    {
        $question = $this->query(['language' => $language])->where('public_id', $publicId)->first();
        if (!$question instanceof Question) {
            ErrorCode::QUESTION_NOT_FOUND->throw();
        }

        return $this->format($question, $language);
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function random(array $filters): array
    {
        $question = $this->query($filters)->inRandomOrder()->first();
        if (!$question instanceof Question) {
            ErrorCode::QUESTION_NOT_FOUND->throw();
        }

        return $this->format($question, (string) ($filters['language'] ?? 'zh-CN'));
    }

    /** @param array<string, mixed> $filters @return Builder<Question> */
    private function query(array $filters): Builder
    {
        /** @var Builder<Question> $query */
        $query = Question::query()->with(['translations', 'tags'])->where('status', 'published')->whereIn('risk_level', ['safe', 'caution']);
        if (($filters['difficulty'] ?? '') !== '') {
            $query->where('difficulty', (int) $filters['difficulty']);
        }
        if (($filters['tag_id'] ?? '') !== '') {
            $query->whereHas('tags', fn ($item) => $item->where('turtle_tags.id', (int) $filters['tag_id']));
        }
        if (($filters['keyword'] ?? '') !== '') {
            $keyword = '%' . $filters['keyword'] . '%';
            $query->whereHas('translations', fn ($item) => $item->where('language', (string) ($filters['language'] ?? 'zh-CN'))->where(fn ($text) => $text->whereLike('title', $keyword)->orWhereLike('surface', $keyword)));
        }

        return $query->orderByDesc('published_at');
    }

    /** @return array<string, mixed> */
    private function format(Question $question, string $language = 'zh-CN'): array
    {
        $translations = $question->getRelation('translations');
        $tags = $question->getRelation('tags');
        $translation = $translations->firstWhere('language', $language) ?? $translations->firstWhere('language', 'zh-CN');

        return ['id' => $question->getAttribute('public_id'), 'title' => $translation?->title, 'surface' => $translation?->surface, 'difficulty' => (int) $question->getAttribute('difficulty'), 'language' => $translation?->language, 'risk_level' => $question->getAttribute('risk_level'), 'risk_warning' => $question->getAttribute('risk_level') === 'caution' ? '该题目包含可能令人不适的情节，请确认后继续。' : null, 'tags' => $tags->map(fn ($tag) => ['id' => $tag->id, 'name' => $tag->name])->values()->all()];
    }
}
