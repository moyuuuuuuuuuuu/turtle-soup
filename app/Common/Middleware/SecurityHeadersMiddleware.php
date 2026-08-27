<?php

declare(strict_types=1);

namespace App\Common\Middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cross-Origin-Resource-Policy' => 'same-site',
        ];

        if ((string) config('app.env') === 'production' && $request->header('x-forwarded-proto') === 'https') {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        return $handler($request)->withHeaders($headers);
    }
}
