<?php

declare(strict_types=1);

namespace App\Ai\Business;

use App\Ai\DTO\ContentParseInput;
use App\Ai\Models\AiParseTask;
use App\Ai\Services\ContentParserFactory;
use App\Ai\Support\ContentParseValidator;
use App\Question\Business\QuestionBusiness;
use App\Question\Models\Tag;
use plugin\saiadmin\exception\ApiException;
use support\Db;
use Throwable;
use Webman\RedisQueue\Client;

final class ContentParseBusiness
{
    public function create(array $payload, int $adminId): array
    {
        $input = ContentParseInput::fromArray($payload);
        $task = AiParseTask::create([
            'public_id' => $this->publicId(),
            'status' => 'pending',
            'progress' => 0,
            'workflow_version' => config('ai.content_parser.workflow_version'),
            'request_payload' => $input->toArray(),
            'created_by' => $adminId,
        ]);
        Client::send('turtle_content_parse', ['task_id' => $task->id]);

        return $this->format($task);
    }

    public function get(string $publicId): array
    {
        return $this->format($this->required($publicId));
    }

    public function retry(string $publicId): array
    {
        $task = $this->required($publicId);
        if ($task->status !== 'failed') {
            throw new ApiException('ai.task_status_invalid');
        }
        $task->update(['status' => 'pending', 'progress' => 0, 'error_code' => null, 'error_message' => null]);
        Client::send('turtle_content_parse', ['task_id' => $task->id]);

        return $this->format($task->refresh());
    }

    public function process(int $taskId): void
    {
        $task = AiParseTask::find($taskId);
        if (!$task || $task->status !== 'pending') {
            return;
        }
        $task->update(['status' => 'processing', 'progress' => 20]);
        try {
            $result = ContentParserFactory::make()->parse($task->request_payload);
            $task->update([
                'status' => 'succeeded',
                'progress' => 100,
                'result_payload' => ContentParseValidator::validate($result),
            ]);
        } catch (Throwable $exception) {
            $code = str_starts_with($exception->getMessage(), 'ai.') ? $exception->getMessage() : 'ai.workflow_timeout';
            $task->update([
                'status' => 'failed',
                'progress' => 100,
                'error_code' => $code,
                'error_message' => 'AI 解析失败，请稍后重试',
            ]);
        }
    }

    public function adopt(string $publicId, int $adminId): array
    {
        return Db::transaction(function () use ($publicId, $adminId): array {
            /** @var AiParseTask|null $task */
            $task = AiParseTask::query()->where('public_id', $publicId)->lockForUpdate()->first();
            if (!$task instanceof AiParseTask) {
                throw new ApiException('ai.task_not_found');
            }
            if ($task->status !== 'succeeded' || !is_array($task->result_payload)) {
                throw new ApiException('ai.task_status_invalid');
            }
            if ($task->question_id) {
                throw new ApiException('ai.task_already_adopted');
            }
            $payload = ContentParseValidator::validate($task->result_payload);
            $payload['source_type'] = 'ai';
            $payload['tag_ids'] = $this->resolveTagIds($payload['suggested_tags']);
            $question = (new QuestionBusiness())->create($payload, $adminId);
            $task->update(['question_id' => $question['id']]);

            return $question;
        });
    }

    private function required(string $publicId): AiParseTask
    {
        return AiParseTask::where('public_id', $publicId)->first() ?? throw new ApiException('ai.task_not_found');
    }

    private function format(AiParseTask $task): array
    {
        return [
            'id' => $task->public_id,
            'status' => $task->status,
            'progress' => $task->progress,
            'workflow_version' => $task->workflow_version,
            'result' => $task->result_payload,
            'error' => $task->error_code ? ['code' => $task->error_code, 'message' => $task->error_message] : null,
            'question_id' => $task->question_id,
            'created_at' => $task->create_time,
            'updated_at' => $task->update_time,
        ];
    }

    private function publicId(): string
    {
        return strtoupper(bin2hex(random_bytes(13)));
    }

    /** @param list<array{name:string, slug:string}> $suggestions
     *  @return list<int>
     */
    private function resolveTagIds(array $suggestions): array
    {
        $slugs = array_column($suggestions, 'slug');
        $ids = Tag::query()->whereIn('slug', $slugs)->pluck('id', 'slug')->all();

        return array_values(array_map(
            static fn (string $slug): int => (int) $ids[$slug],
            array_values(array_filter($slugs, static fn (string $slug): bool => isset($ids[$slug]))),
        ));
    }
}
