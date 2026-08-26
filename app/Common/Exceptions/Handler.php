<?php

declare(strict_types=1);

namespace App\Common\Exceptions;

use App\Common\Enums\ErrorCode;
use App\Common\Formats\ResponseFormat;
use App\Common\Support\RequestContext;
use support\exception\Handler as WebmanHandler;
use Throwable;
use Webman\Http\Request;
use Webman\Http\Response;

final class Handler extends WebmanHandler
{
    public function report(Throwable $exception): void
    {
        if ($exception instanceof BaseException && !$exception->errorCode->isReportable()) {
            return;
        }

        $currentRequest = request();
        $errorCode = $exception instanceof BaseException
            ? $exception->errorCode
            : ErrorCode::SYSTEM_ERROR;

        $this->logger->log($errorCode->severity()->value, 'application_exception', [
            'code' => $errorCode->code(),
            'module' => $errorCode->module()->value,
            'notification_policy' => $errorCode->notificationPolicy()->value,
            'request_id' => $currentRequest ? RequestContext::requestId($currentRequest) : '',
            'request' => $currentRequest ? $currentRequest->method() . ' ' . $currentRequest->path() : '',
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);
    }

    public function render(Request $request, Throwable $exception): Response
    {
        if (!str_starts_with($request->path(), '/api/')) {
            return parent::render($request, $exception);
        }

        $known = $exception instanceof BaseException;
        $errorCode = $known ? $exception->errorCode : ErrorCode::SYSTEM_ERROR;
        $message = $known ? $exception->getMessage() : $errorCode->message();
        $data = $known ? $exception->data : null;

        return json(
            ResponseFormat::error($errorCode, $data, RequestContext::requestId($request), $message),
            JSON_UNESCAPED_UNICODE,
        )->withStatus($errorCode->httpStatus());
    }
}
