<?php

declare(strict_types=1);

namespace App\Ai\Services;

use App\Ai\Contracts\ContentParserInterface;

final class MockContentParser implements ContentParserInterface
{
    public function parse(array $input): array
    {
        return [
            'difficulty' => 3,
            'translations' => [[
                'language' => 'zh-CN',
                'title' => '待人工确认的 AI 草稿',
                'surface' => mb_substr((string) $input['story'], 0, 120),
                'bottom' => (string) $input['story'],
            ]],
            'points' => [[
                'weight' => 1,
                'is_required' => true,
                'sort' => 1,
                'translations' => [['language' => 'zh-CN', 'content' => '请人工补充关键推理点']],
            ]],
            'hints' => array_map(fn (int $level) => [
                'level' => $level,
                'translations' => [['language' => 'zh-CN', 'content' => "第 {$level} 级提示待人工确认"]],
            ], [1, 2, 3]),
            'suggested_tags' => [],
            'quality_warnings' => ['当前使用 Mock 解析器，结果仅用于联调。'],
        ];
    }
}
