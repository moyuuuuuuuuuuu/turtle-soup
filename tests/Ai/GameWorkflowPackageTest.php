<?php

declare(strict_types=1);

namespace Tests\Ai;

use PHPUnit\Framework\TestCase;

final class GameWorkflowPackageTest extends TestCase
{
    public function testQuestionValidatorReadsAnswerFromParsedData(): void
    {
        $workflow = $this->workflow('question');

        self::assertStringContainsString("data.get('answer')", $workflow);
        self::assertStringNotContainsString('valid = answer in', $workflow);
    }

    public function testBothJudgeWorkflowsSafelyFallbackToReasoningOutput(): void
    {
        foreach (['question', 'guess'] as $type) {
            $workflow = $this->workflow($type);
            self::assertStringContainsString('args.params.get(\\"reasoning_raw\\")', $workflow);
            self::assertStringContainsString('path: reasoning_content', $workflow);
            self::assertStringContainsString('不得为空', $workflow);
        }
    }

    public function testQuestionWorkflowCanNormalizeExplicitFieldsFromReasoningText(): void
    {
        $workflow = $this->workflow('question');

        self::assertStringContainsString('answer(?:字段)?', $workflow);
        self::assertStringContainsString('matched_point_keys(?:字段)?', $workflow);
        self::assertStringContainsString('answer.group(1).lower()', $workflow);
        self::assertStringContainsString('json.loads(point_keys.group(1))', $workflow);
    }

    private function workflow(string $type): string
    {
        $name = "turtle_{$type}_judge_v1";
        $path = dirname(__DIR__, 2)."/workflows/{$name}/package/Workflow-{$name}-draft/workflow/{$name}-draft.yaml";
        $contents = file_get_contents($path);
        self::assertIsString($contents);

        return $contents;
    }
}
