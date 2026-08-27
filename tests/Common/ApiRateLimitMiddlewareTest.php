<?php

declare(strict_types=1);

namespace Tests\Common;

use App\Common\Exceptions\BusinessException;
use App\Common\Middleware\ApiRateLimitMiddleware;
use PHPUnit\Framework\TestCase;
use Webman\Http\Request;
use Webman\Http\Response;

final class ApiRateLimitMiddlewareTest extends TestCase
{
    public function testRejectsRequestAboveConfiguredLimit(): void
    {
        $request = new Request("POST /api/v1/auth/login/password HTTP/1.1\r\nHost: hgt.test\r\n\r\n");
        $middleware = new ApiRateLimitMiddleware(static fn (): int => 11, [[
            'methods' => ['POST'],
            'paths' => ['/api/v1/auth/login/password'],
            'limit' => 10,
            'window' => 60,
        ]]);

        $this->expectException(BusinessException::class);
        $middleware->process($request, static fn () => new Response(200));
    }

    public function testDoesNotLimitUnmatchedReadEndpoint(): void
    {
        $request = new Request("GET /api/v1/questions HTTP/1.1\r\nHost: hgt.test\r\n\r\n");
        $middleware = new ApiRateLimitMiddleware(static fn (): int => 999);

        self::assertSame(200, $middleware->process($request, static fn () => new Response(200))->getStatusCode());
    }
}
