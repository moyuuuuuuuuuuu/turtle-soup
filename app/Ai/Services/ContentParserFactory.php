<?php

declare(strict_types=1);

namespace App\Ai\Services;

use App\Ai\Contracts\ContentParserInterface;

final class ContentParserFactory
{
    public static function make(): ContentParserInterface
    {
        return config('ai.content_parser.driver') === 'coze'
            ? new CozeContentParser()
            : new MockContentParser();
    }
}
