<?php

declare(strict_types=1);

namespace App\Common\Middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

final class CorsMiddleware implements MiddlewareInterface
{
    /** @param null|list<string> $allowedOrigins */
    public function __construct(private readonly ?array $allowedOrigins = null)
    {
    }

    public function process(Request $request, callable $handler): Response
    {
        $origin = trim((string) $request->header('origin'));
        $allowedOrigins = $this->allowedOrigins ?? (array) config('cors.allowed_origins', []);
        $allowOrigin = in_array('*', $allowedOrigins, true) || in_array($origin, $allowedOrigins, true)
            ? ($origin !== '' ? $origin : '*')
            : '';

        $response = strtoupper($request->method()) === 'OPTIONS'
            ? new Response(204)
            : $handler($request);

        if ($allowOrigin === '') {
            return $response;
        }

        return $response->withHeaders([
            'Access-Control-Allow-Origin' => $allowOrigin,
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Authorization, Content-Type, X-Request-Id, X-Anonymous-Token, X-Device-Id, X-Device-Name, X-Platform',
            'Access-Control-Expose-Headers' => 'X-Request-Id',
            'Access-Control-Max-Age' => '86400',
            'Vary' => 'Origin',
        ]);
    }
}
