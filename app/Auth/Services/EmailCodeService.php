<?php

declare(strict_types=1);

namespace App\Auth\Services;

use App\Auth\Models\EmailCode;
use App\Common\Enums\ErrorCode;
use Webman\RedisQueue\Client;

final class EmailCodeService
{
    private const PURPOSES = ['register', 'login', 'reset_password', 'change_email'];

    /** @param null|callable(array<string,mixed>):void $dispatcher */
    public function __construct(private readonly mixed $dispatcher = null)
    {
    }

    public function send(string $email, string $purpose, string $ip): void
    {
        $this->assertConfigured();
        $email = self::normalizeEmail($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($purpose, self::PURPOSES, true)) {
            ErrorCode::PARAM_ERROR->throw();
        }
        $now = date('Y-m-d H:i:s');
        if (EmailCode::query()->where('email_normalized', $email)->where('purpose', $purpose)->where('create_time', '>', date('Y-m-d H:i:s', time() - 60))->exists()
            || EmailCode::query()->where('email_normalized', $email)->where('purpose', $purpose)->where('create_time', '>', date('Y-m-d H:i:s', time() - 3600))->count() >= 5
            || EmailCode::query()->where('request_ip_hash', self::hashIp($ip))->where('purpose', $purpose)->where('create_time', '>', date('Y-m-d H:i:s', time() - 3600))->count() >= 20) {
            ErrorCode::AUTH_EMAIL_CODE_RATE_LIMITED->throw();
        }
        EmailCode::query()->where('email_normalized', $email)->where('purpose', $purpose)->whereNull('consumed_at')->update(['consumed_at' => $now]);
        $code = (string) random_int(100000, 999999);
        EmailCode::create(['email_normalized' => $email, 'purpose' => $purpose, 'code_hash' => $this->hashCode($email, $purpose, $code), 'request_ip_hash' => self::hashIp($ip), 'expires_at' => date('Y-m-d H:i:s', time() + 600)]);
        $payload = ['to' => $email, 'subject' => '海龟汤邮箱验证码', 'body' => "你的验证码是 {$code}，10 分钟内有效。请勿转发给他人。"];
        if (is_callable($this->dispatcher)) {
            ($this->dispatcher)($payload);
        } else {
            Client::send('player_email', $payload);
        }
    }

    public function verify(string $email, string $purpose, string $code): void
    {
        $this->assertConfigured();
        $email = self::normalizeEmail($email);
        $record = EmailCode::query()->where('email_normalized', $email)->where('purpose', $purpose)->whereNull('consumed_at')->orderByDesc('id')->first();
        if (!$record instanceof EmailCode || strtotime((string) $record->expires_at) <= time()) {
            ErrorCode::AUTH_EMAIL_CODE_EXPIRED->throw();
        }
        if ((int) $record->attempts >= 5 || !hash_equals((string) $record->code_hash, $this->hashCode($email, $purpose, $code))) {
            $record->increment('attempts');
            ErrorCode::AUTH_EMAIL_CODE_INVALID->throw();
        }
        $record->update(['consumed_at' => date('Y-m-d H:i:s')]);
    }

    public function notify(string $email, string $subject, string $body): void
    {
        $payload = ['to' => self::normalizeEmail($email), 'subject' => $subject, 'body' => $body];
        if (is_callable($this->dispatcher)) {
            ($this->dispatcher)($payload);
        } else {
            Client::send('player_email', $payload);
        }
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }
    private function hashCode(string $email, string $purpose, string $code): string
    {
        return hash_hmac('sha256', $email.'|'.$purpose.'|'.$code, (string) config('player_auth.email_code_secret'));
    }
    private static function hashIp(string $ip): string
    {
        return hash('sha256', $ip);
    }

    private function assertConfigured(): void
    {
        if ((string) config('player_auth.email_code_secret') === '') {
            ErrorCode::CONFIG_ERROR->throw();
        }
    }
}
