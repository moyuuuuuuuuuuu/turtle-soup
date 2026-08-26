<?php

declare(strict_types=1);

namespace App\Common\Formats;

use App\Common\Contracts\ErrorCodeInterface;

final class ResponseFormat
{
    /** @return array{code: string, message: string, data: mixed, request_id: string, timestamp: int} */
    public static function success(mixed $data = null, string $requestId = ''): array
    {
        return [
            'code' => 'success',
            'message' => 'success',
            'data' => $data,
            'request_id' => $requestId,
            'timestamp' => time(),
        ];
    }

    /** @return array{code: string, message: string, data: mixed, request_id: string, timestamp: int} */
    public static function error(
        ErrorCodeInterface $errorCode,
        mixed $data = null,
        string $requestId = '',
        ?string $message = null,
    ): array {
        return [
            'code' => $errorCode->code(),
            'message' => $message ?? $errorCode->message(),
            'data' => $data,
            'request_id' => $requestId,
            'timestamp' => time(),
        ];
    }
}
