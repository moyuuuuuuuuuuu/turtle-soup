<?php

declare(strict_types=1);

namespace Tests\Common;

use App\Common\Middleware\SecurityHeadersMiddleware;
use PHPUnit\Framework\TestCase;
use Webman\Http\Request;
use Webman\Http\Response;

final class SecurityHeadersMiddlewareTest extends TestCase
{
    public function testAddsBrowserSecurityHeaders(): void
    {
        $request = new Request("GET /api/v1/health HTTP/1.1\r\nHost: hgt.test\r\n\r\n");

        $response = (new SecurityHeadersMiddleware())->process($request, static fn () => new Response(200));

        self::assertSame('nosniff', $response->getHeader('X-Content-Type-Options'));
        self::assertSame('DENY', $response->getHeader('X-Frame-Options'));
        self::assertSame('strict-origin-when-cross-origin', $response->getHeader('Referrer-Policy'));
        self::assertSame('same-origin', $response->getHeader('Cross-Origin-Opener-Policy'));
        self::assertSame('same-site', $response->getHeader('Cross-Origin-Resource-Policy'));
        self::assertStringContainsString('camera=()', (string) $response->getHeader('Permissions-Policy'));
    }
}
