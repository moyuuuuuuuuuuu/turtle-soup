<?php

declare(strict_types=1);

namespace Tests\Ai;

use PHPUnit\Framework\TestCase;

final class GameWorkflowPackageTest extends TestCase
{
    public function testQuestionValidatorReadsAnswerFromParsedData(): void
    {
        $workflow = $this->workflow('question');

        self::assertStringContainsString("data.get('answer') in ('yes', 'no', 'irrelevant', 'partial')", $workflow);
        self::assertStringNotContainsString('valid = answer in', $workflow);
    }

    public function testBothJudgeWorkflowsDisableReasoningOutput(): void
    {
        foreach (['question', 'guess'] as $type) {
            $workflow = $this->workflow($type);
            self::assertMatchesRegularExpression('/name: thinkingType.*?value: disabled/s', $workflow);
            self::assertStringContainsString('不得为空', $workflow);
        }
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
