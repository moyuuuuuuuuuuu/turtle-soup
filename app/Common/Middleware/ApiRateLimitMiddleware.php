<?php

declare(strict_types=1);

namespace App\Common\Middleware;

use App\Common\Enums\ErrorCode;
use support\Log;
use support\Redis;
use Throwable;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

final class ApiRateLimitMiddleware implements MiddlewareInterface
{
    /**
     * @param null|callable(string, int): int $increment
     * @param null|list<array{methods: list<string>, paths: list<string>, limit: int, window: int}> $rules
     */
    public function __construct(private readonly mixed $increment = null, private readonly ?array $rules = null)
    {
    }

    public function process(Request $request, callable $handler): Response
    {
        if (!(bool) config('api_rate_limit.enabled', true)) {
            return $handler($request);
        }

        $rule = $this->matchRule(strtoupper($request->method()), $request->path());
        if ($rule === null) {
            return $handler($request);
        }

        $key = $this->key($request, $rule['window']);
        try {
            $count = $this->increment !== null
                ? (int) ($this->increment)($key, $rule['window'])
                : $this->incrementRedis($key, $rule['window']);
        } catch (Throwable $throwable) {
            Log::warning('API rate limiter unavailable', [
                'path' => $request->path(),
                'exception' => $throwable::class,
            ]);
            return $handler($request);
        }

        if ($count > $rule['limit']) {
            ErrorCode::REQUEST_FREQUENCY->throw();
        }

        return $handler($request);
    }

    /** @return null|array{limit: int, window: int} */
    private function matchRule(string $method, string $path): ?array
    {
        foreach ($this->rules ?? (array) config('api_rate_limit.rules', []) as $rule) {
            if (in_array($method, (array) ($rule['methods'] ?? []), true)
                && in_array($path, (array) ($rule['paths'] ?? []), true)) {
                return ['limit' => (int) $rule['limit'], 'window' => (int) $rule['window']];
            }
        }

        return null;
    }

    private function key(Request $request, int $window): string
    {
        $credential = (string) $request->header('authorization', '');
        if ($credential === '') {
            $credential = (string) $request->header('x-anonymous-token', '');
        }
        $identity = $credential !== '' ? hash('sha256', $credential) : $request->getRealIp();
        $bucket = intdiv(time(), max(1, $window));

        return (string) config('api_rate_limit.prefix', 'hgt:api-rate:')
            . hash('sha256', $request->path() . '|' . $identity . '|' . $bucket);
    }

    private function incrementRedis(string $key, int $window): int
    {
        return (int) Redis::eval(
            "local count = redis.call('INCR', KEYS[1]); if count == 1 then redis.call('EXPIRE', KEYS[1], ARGV[1]) end; return count",
            1,
            $key,
            max(1, $window),
        );
    }
}
