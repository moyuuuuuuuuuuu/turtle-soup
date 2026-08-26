<?php

declare(strict_types=1);

namespace app\queue\redis;

use App\Auth\Services\SmtpMailer;
use Webman\RedisQueue\Consumer;

final class PlayerEmail implements Consumer
{
    public string $queue = 'player_email';
    public string $connection = 'default';

    public function consume($data): void
    {
        (new SmtpMailer())->send((string) ($data['to'] ?? ''), (string) ($data['subject'] ?? ''), (string) ($data['body'] ?? ''));
    }
}
