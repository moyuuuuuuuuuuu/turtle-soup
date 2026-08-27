<?php

declare(strict_types=1);

namespace App\Question\Business;

use App\Question\DTO\QuestionData;
use App\Question\Enums\QuestionRiskLevel;
use App\Question\Enums\QuestionRiskType;
use App\Question\Enums\QuestionStatus;
use App\Question\Formats\QuestionFormat;
use App\Question\Models\Question;
use App\Question\Repositories\QuestionRepository;
use App\Question\Support\QuestionPublishValidator;
use DateTimeImmutable;
use plugin\saiadmin\exception\ApiException;
use Throwable;

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

    /** @return array<string, mixed> */
    public function copy(int $id, int $adminId): array
    {
        $source = $this->required($id);
        $payload = QuestionFormat::detail($source, true);
        $payload['source_type'] = 'manual';
        $payload['risk_reviewed_by'] = null;
        $payload['risk_reviewed_at'] = null;
        $payload['is_featured'] = false;
        $payload['featured_sort'] = 0;
        $payload['featured_starts_at'] = null;
        $payload['featured_ends_at'] = null;
        $data = QuestionData::fromArray($payload);

        try {
            return QuestionFormat::detail($this->repository->create($data, $adminId), true);
        } catch (Throwable $throwable) {
            throw new ApiException('question.copy_failed', previous: $throwable);
        }
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

    /** @return array<string, mixed> */
    public function publish(int $id, int $version, bool $riskConfirmed, int $adminId): array
    {
        $question = $this->required($id);
        if ((int) $question->getAttribute('version') !== $version) {
            throw new ApiException('question.version_conflict');
        }
        $data = QuestionData::fromArray(QuestionFormat::detail($question, true));
        $this->validate($data, true);
        $riskLevel = QuestionRiskLevel::tryFrom($data->riskLevel) ?? QuestionRiskLevel::SAFE;
        if ($riskLevel->requiresConfirmation() && (!$riskConfirmed || $data->riskNote === null)) {
            throw new ApiException('question.risk_confirmation_required');
        }
        $published = $this->repository->publish(
            $question,
            $version,
            $adminId,
            $riskLevel->requiresConfirmation(),
        );
        if (!$published) {
            throw new ApiException('question.version_conflict');
        }

        return QuestionFormat::detail($published, true);
    }

    /** @return list<array<string, mixed>> */
    public function history(int $id): array
    {
        $this->required($id);

        return $this->repository->history($id);
    }

    /** @return array<string, mixed> */
    public function historyDetail(int $id, int $versionId, bool $includeBottom): array
    {
        $this->required($id);
        $version = $this->repository->findVersion($id, $versionId)
            ?? throw new ApiException('question.version_not_found');
        $snapshot = (array) $version->snapshot;
        if (!$includeBottom) {
            foreach ($snapshot['translations'] ?? [] as $index => $translation) {
                unset($translation['bottom']);
                $snapshot['translations'][$index] = $translation;
            }
        }

        return [
            'id' => $version->id,
            'version' => $version->version,
            'published_by' => $version->published_by,
            'published_at' => $version->published_at,
            'snapshot' => $snapshot,
        ];
    }

    /** @return array<string, mixed> */
    public function restore(int $id, int $versionId, int $currentVersion, int $adminId): array
    {
        $question = $this->required($id);
        if ((int) $question->getAttribute('version') !== $currentVersion) {
            throw new ApiException('question.version_conflict');
        }
        $version = $this->repository->findVersion($id, $versionId)
            ?? throw new ApiException('question.version_not_found');
        $payload = (array) $version->snapshot;
        $payload['version'] = $currentVersion;
        $data = QuestionData::fromArray($payload);
        $restored = $this->repository->update($question, $data, $adminId);
        if ((int) $restored->getAttribute('version') === $currentVersion) {
            throw new ApiException('question.version_conflict');
        }

        return QuestionFormat::detail($restored, true);
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
        if (QuestionRiskLevel::tryFrom($data->riskLevel) === null) {
            throw new ApiException('question.content_incomplete');
        }
        $validRiskTypes = array_map(fn (QuestionRiskType $type) => $type->value, QuestionRiskType::cases());
        if (array_diff($data->riskTypes, $validRiskTypes) !== []) {
            throw new ApiException('question.content_incomplete');
        }
        if ($data->difficulty < 1 || $data->difficulty > 5 || $data->minPlayers < 1 || $data->maxPlayers < $data->minPlayers) {
            throw new ApiException('question.content_incomplete');
        }
        $featuredStart = $this->dateTime($data->featuredStartsAt);
        $featuredEnd = $this->dateTime($data->featuredEndsAt);
        if (($data->featuredStartsAt !== null && $featuredStart === null)
            || ($data->featuredEndsAt !== null && $featuredEnd === null)
            || ($featuredStart !== null && $featuredEnd !== null && $featuredStart >= $featuredEnd)) {
            throw new ApiException('question.content_incomplete');
        }
        if (!$forPublish) {
            return;
        }
        if (!QuestionPublishValidator::canPublishChinese($data)) {
            throw new ApiException('question.translation_incomplete');
        }
    }

    private function dateTime(?string $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);

        return $date instanceof DateTimeImmutable && $date->format('Y-m-d H:i:s') === $value ? $date : null;
    }
}
