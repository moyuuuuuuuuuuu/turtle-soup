<?php

declare(strict_types=1);

namespace App\Game\Services;

use App\Game\Contracts\GameJudgeInterface;

final class MockGameJudge implements GameJudgeInterface
{
    public function judgeQuestion(array $context, string $question): array
    {
        $matched = [];
        foreach (($context['points'] ?? []) as $point) {
            if ($this->overlap($question, (string)($point['content'] ?? '')) >= 2) {
                $matched[] = (string)$point['key'];
            }
        }
        $answer = $matched !== [] ? 'partial' : (preg_match('/(吗|是否|是不是|有没有|会不会)/u', $question) ? 'no' : 'irrelevant');
        return ['answer' => $answer,'reply' => match($answer) {
            'partial' => '方向接近，但还需要继续推理。','no' => '不是。',default => '这与谜底关系不大。'
        },'matched_point_keys' => $matched];
    }
    public function judgeGuess(array $context, string $guess): array
    {
        $matched = [];
        foreach (($context['points'] ?? []) as $point) {
            if ($this->overlap($guess, (string)($point['content'] ?? '')) >= 2) {
                $matched[] = (string)$point['key'];
            }
        }
        $required = array_values(array_filter(($context['points'] ?? []), fn (array $p) => (bool)($p['required'] ?? false)));
        $solved = $required !== [] && count($matched) >= max(1, (int)ceil(count($required) * 0.75));
        return ['is_solved' => $solved,'matched_point_keys' => $matched,'summary' => $solved ? '你还原了故事的关键真相。' : '仍有关键因果没有还原。'];
    }
    private function overlap(string $left, string $right): int
    {
        $count = 0;
        foreach (array_unique(preg_split('//u', $left, -1, PREG_SPLIT_NO_EMPTY) ?: []) as $char) {
            if (mb_strpos($right, $char) !== false && preg_match('/[\p{Han}]/u', $char)) {
                $count++;
            }
        } return $count;
    }
}
