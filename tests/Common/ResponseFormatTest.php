<?php

declare(strict_types=1);

namespace Tests\Common;

use App\Common\Enums\ErrorCode;
use App\Common\Formats\ResponseFormat;
use PHPUnit\Framework\TestCase;

final class ResponseFormatTest extends TestCase
{
    public function testSuccessEnvelopeContainsCorrelationFields(): void
    {
        $response = ResponseFormat::success(['ready' => true], 'request-1');

        self::assertSame('success', $response['code']);
        self::assertSame(['ready' => true], $response['data']);
        self::assertSame('request-1', $response['request_id']);
        self::assertIsInt($response['timestamp']);
    }

    public function testErrorEnvelopeUsesStableEnglishCode(): void
    {
        $response = ResponseFormat::error(ErrorCode::PARAM_ERROR, requestId: 'request-2');

        self::assertSame('request.param_error', $response['code']);
        self::assertSame(422, ErrorCode::PARAM_ERROR->httpStatus());
        self::assertSame('request-2', $response['request_id']);
    }
}
