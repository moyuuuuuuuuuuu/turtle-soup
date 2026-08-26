<?php

declare(strict_types=1);

namespace App\Common\Support;

use Webman\Http\Request;

final class RequestContext
{
    public static function requestId(?Request $request = null): string
    {
        $request ??= request();

        return (string) ($request->properties['request_id'] ?? '');
    }
}
