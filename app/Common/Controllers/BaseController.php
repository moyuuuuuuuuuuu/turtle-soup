<?php

declare(strict_types=1);

namespace App\Common\Controllers;

use App\Common\Contracts\ErrorCodeInterface;
use App\Common\Formats\ResponseFormat;
use support\Response;

abstract class BaseController
{
    protected function success(mixed $data = null, string $requestId = ''): Response
    {
        return json(ResponseFormat::success($data, $requestId));
    }

    protected function error(
        ErrorCodeInterface $errorCode,
        mixed $data = null,
        string $requestId = '',
        ?string $message = null,
    ): Response {
        return json(
            ResponseFormat::error($errorCode, $data, $requestId, $message),
            JSON_UNESCAPED_UNICODE,
        )->withStatus($errorCode->httpStatus());
    }
}
