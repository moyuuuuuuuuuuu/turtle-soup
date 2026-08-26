<?php

declare(strict_types=1);

namespace app\queue\redis;

use App\Ai\Business\ContentParseBusiness;
use Webman\RedisQueue\Consumer;

final class TurtleContentParse implements Consumer
{
    public string $queue = 'turtle_content_parse';
    public string $connection = 'default';

    public function consume($data): void
    {
        (new ContentParseBusiness())->process((int) ($data['task_id'] ?? 0));
    }
}
