<?php

declare(strict_types=1);

namespace Tests\Common;

use App\Common\Middleware\CorsMiddleware;
use PHPUnit\Framework\TestCase;
use Webman\Http\Request;
use Webman\Http\Response;

final class CorsMiddlewareTest extends TestCase
{
    public function testPreflightFromAllowedOriginReturnsCorsHeaders(): void
    {
        $request = new Request("OPTIONS /api/v1/games HTTP/1.1\r\nHost: hgt.test\r\nOrigin: http://localhost:5173\r\n\r\n");
        $middleware = new CorsMiddleware(['http://localhost:5173']);

        $response = $middleware->process($request, static fn () => new Response(500));

        self::assertSame(204, $response->getStatusCode());
        self::assertSame('http://localhost:5173', $response->getHeader('Access-Control-Allow-Origin'));
        $allowedHeaders = (string) $response->getHeader('Access-Control-Allow-Headers');
        self::assertStringContainsString('Authorization', $allowedHeaders);
        self::assertStringContainsString('X-Anonymous-Token', $allowedHeaders);
        self::assertStringContainsString('X-Device-Id', $allowedHeaders);
        self::assertStringContainsString('X-Device-Name', $allowedHeaders);
        self::assertStringContainsString('X-Platform', $allowedHeaders);
    }

    public function testDisallowedOriginDoesNotReceiveCorsHeaders(): void
    {
        $request = new Request("GET /api/v1/health HTTP/1.1\r\nHost: hgt.test\r\nOrigin: https://example.invalid\r\n\r\n");
        $middleware = new CorsMiddleware(['http://localhost:5173']);

        $response = $middleware->process($request, static fn () => new Response(200));

        self::assertNull($response->getHeader('Access-Control-Allow-Origin'));
    }
}
