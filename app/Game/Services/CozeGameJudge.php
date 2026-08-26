<?php

declare(strict_types=1);

namespace App\Game\Services;

use App\Ai\Services\CozeContentParser;
use App\Game\Contracts\GameJudgeInterface;
use RuntimeException;

final class CozeGameJudge implements GameJudgeInterface
{
    /** @param null|callable(string):CozeContentParser $parserFactory */
    public function __construct(private readonly mixed $parserFactory = null)
    {
    }

    public function judgeQuestion(array $context, string $question): array
    {
        $result = $this->parser('question')->parse([
            'surface' => (string) ($context['surface'] ?? ''),
            'bottom' => (string) ($context['bottom'] ?? ''),
            'language' => (string) ($context['language'] ?? 'zh-CN'),
            'key_points' => $context['points'] ?? [],
            'player_question' => $question,
        ]);
        $answer = (string) ($result['answer'] ?? '');
        if (!in_array($answer, ['yes', 'no', 'irrelevant', 'partial'], true)
            || !is_string($result['reply'] ?? null)
            || !is_array($result['matched_point_keys'] ?? null)) {
            throw new RuntimeException('ai.invalid_response');
        }

        return [
            'answer' => $answer,
            'reply' => (string) $result['reply'],
            'matched_point_keys' => array_values(array_map('strval', $result['matched_point_keys'])),
            'safety_note' => (string) ($result['safety_note'] ?? ''),
        ];
    }

    public function judgeGuess(array $context, string $guess): array
    {
        $result = $this->parser('guess')->parse([
            'surface' => (string) ($context['surface'] ?? ''),
            'bottom' => (string) ($context['bottom'] ?? ''),
            'language' => (string) ($context['language'] ?? 'zh-CN'),
            'key_points' => $context['points'] ?? [],
            'player_guess' => $guess,
        ]);
        if (!is_bool($result['is_solved'] ?? null)
            || !is_string($result['summary'] ?? null)
            || !is_array($result['matched_point_keys'] ?? null)) {
            throw new RuntimeException('ai.invalid_response');
        }

        return [
            'is_solved' => $result['is_solved'],
            'summary' => (string) $result['summary'],
            'matched_point_keys' => array_values(array_map('strval', $result['matched_point_keys'])),
            'safety_note' => (string) ($result['safety_note'] ?? ''),
        ];
    }

    private function parser(string $type): CozeContentParser
    {
        if (is_callable($this->parserFactory)) {
            return ($this->parserFactory)($type);
        }
        $settings = (array) config('ai.game_judge');
        $settings['workflow_id'] = (string) ($settings[$type . '_workflow_id'] ?? '');
        $settings['workflow_version'] = 'turtle_' . $type . '_judge_v1';

        return new CozeContentParser(settings: $settings);
    }
}
