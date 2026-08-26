<?php

declare(strict_types=1);

namespace App\Common\Middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

final class RequestIdMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        $requestId = trim((string) $request->header('x-request-id'));
        if ($requestId === '' || strlen($requestId) > 128) {
            $requestId = bin2hex(random_bytes(16));
        }

        $request->properties['request_id'] = $requestId;

        return $handler($request)->withHeader('X-Request-Id', $requestId);
    }
}
