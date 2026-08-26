<?php

declare(strict_types=1);

namespace App\Question\Business;

use App\Question\DTO\QuestionData;
use App\Question\Enums\QuestionStatus;
use App\Question\Formats\QuestionFormat;
use App\Question\Models\Question;
use App\Question\Repositories\QuestionRepository;
use plugin\saiadmin\exception\ApiException;

final class QuestionBusiness
{
    public function __construct(private readonly QuestionRepository $repository = new QuestionRepository())
    {
    }

    public function page(array $filters, int $page, int $pageSize): array
    {
        $result = $this->repository->search($filters)->paginate($pageSize, ['*'], 'page', $page);

        return [
            'items' => array_map(fn (Question $question) => QuestionFormat::detail($question, false), $result->items()),
            'total' => $result->total(),
        ];
    }

    public function detail(int $id, bool $includeBottom): array
    {
        return QuestionFormat::detail($this->required($id), $includeBottom);
    }

    public function create(array $payload, int $adminId): array
    {
        $data = QuestionData::fromArray($payload);
        $this->validate($data, false);

        return QuestionFormat::detail($this->repository->create($data, $adminId), true);
    }

    public function update(int $id, array $payload, int $adminId): array
    {
        $question = $this->required($id);
        $data = QuestionData::fromArray($payload);
        $this->validate($data, false);
        if ($data->version !== (int) $question->version) {
            throw new ApiException('question.version_conflict');
        }
        $updated = $this->repository->update($question, $data, $adminId);
        if ((int) $updated->version === (int) $question->version) {
            throw new ApiException('question.version_conflict');
        }

        return QuestionFormat::detail($updated, true);
    }

    public function publish(int $id): void
    {
        $question = $this->required($id);
        $data = QuestionData::fromArray(QuestionFormat::detail($question, true));
        $this->validate($data, true);
        $question->update(['status' => QuestionStatus::PUBLISHED->value, 'published_at' => date('Y-m-d H:i:s')]);
    }

    public function offline(int $id): void
    {
        $question = $this->required($id);
        if ($question->status !== QuestionStatus::PUBLISHED->value) {
            throw new ApiException('question.status_invalid');
        }
        $question->update(['status' => QuestionStatus::OFFLINE->value]);
    }

    public function deleteDraft(int $id): void
    {
        $question = $this->required($id);
        if ($question->status !== QuestionStatus::DRAFT->value) {
            throw new ApiException('question.status_invalid');
        }
        $question->delete();
    }

    private function required(int $id): Question
    {
        return $this->repository->find($id) ?? throw new ApiException('question.not_found');
    }

    private function validate(QuestionData $data, bool $forPublish): void
    {
        if ($data->difficulty < 1 || $data->difficulty > 5 || $data->minPlayers < 1 || $data->maxPlayers < $data->minPlayers) {
            throw new ApiException('question.content_incomplete');
        }
        if (!$forPublish) {
            return;
        }
        $zh = array_values(array_filter($data->translations, fn (array $item) => ($item['language'] ?? '') === 'zh-CN'))[0] ?? [];
        $requiredPoints = array_filter($data->points, fn (array $item) => (bool) ($item['is_required'] ?? false));
        $levels = array_unique(array_map(fn (array $item) => (int) ($item['level'] ?? 0), $data->hints));
        if (trim((string) ($zh['title'] ?? '')) === '' || trim((string) ($zh['surface'] ?? '')) === '' || trim((string) ($zh['bottom'] ?? '')) === '' || $requiredPoints === [] || array_diff([1, 2, 3], $levels) !== []) {
            throw new ApiException('question.content_incomplete');
        }
    }
}
