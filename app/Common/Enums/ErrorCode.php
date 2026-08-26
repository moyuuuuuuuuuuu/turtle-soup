<?php

declare(strict_types=1);

namespace App\Common\Enums;

use App\Common\Contracts\ErrorCodeInterface;
use App\Common\Exceptions\BusinessException;
use Throwable;

enum ErrorCode: string implements ErrorCodeInterface
{
    case SYSTEM_ERROR = 'system.error';
    case SYSTEM_BUSY = 'system.busy';
    case SYSTEM_MAINTENANCE = 'system.maintenance';
    case PARAM_ERROR = 'request.param_error';
    case PARAM_MISSING = 'request.param_missing';
    case REQUEST_METHOD_ERROR = 'request.method_not_allowed';
    case REQUEST_FREQUENCY = 'request.too_frequent';
    case DATA_NOT_FOUND = 'data.not_found';
    case DATA_ALREADY_EXISTS = 'data.already_exists';
    case DATA_STATUS_ERROR = 'data.status_invalid';
    case THIRD_PARTY_ERROR = 'third_party.error';
    case CONFIG_ERROR = 'system.config_error';

    public function code(): string
    {
        return $this->value;
    }

    public function message(): string
    {
        return match ($this) {
            self::SYSTEM_ERROR => '系统内部错误',
            self::SYSTEM_BUSY => '系统繁忙，请稍后重试',
            self::SYSTEM_MAINTENANCE => '系统维护中，请稍后再试',
            self::PARAM_ERROR => '请求参数错误',
            self::PARAM_MISSING => '缺少必要参数',
            self::REQUEST_METHOD_ERROR => '请求方式不支持',
            self::REQUEST_FREQUENCY => '请求过于频繁，请稍后再试',
            self::DATA_NOT_FOUND => '数据不存在',
            self::DATA_ALREADY_EXISTS => '数据已存在',
            self::DATA_STATUS_ERROR => '数据状态异常，无法操作',
            self::THIRD_PARTY_ERROR => '第三方服务异常',
            self::CONFIG_ERROR => '系统配置错误，请联系管理员',
        };
    }

    public function httpStatus(): int
    {
        return match ($this) {
            self::PARAM_ERROR,
            self::PARAM_MISSING => 422,
            self::REQUEST_METHOD_ERROR => 405,
            self::REQUEST_FREQUENCY => 429,
            self::DATA_NOT_FOUND => 404,
            self::DATA_ALREADY_EXISTS,
            self::DATA_STATUS_ERROR => 409,
            self::THIRD_PARTY_ERROR => 502,
            self::SYSTEM_MAINTENANCE => 503,
            default => 500,
        };
    }

    public function module(): ErrorModule
    {
        return match ($this) {
            self::PARAM_ERROR,
            self::PARAM_MISSING,
            self::REQUEST_METHOD_ERROR,
            self::REQUEST_FREQUENCY => ErrorModule::REQUEST,
            default => ErrorModule::SYSTEM,
        };
    }

    public function severity(): ErrorSeverity
    {
        return match ($this) {
            self::PARAM_ERROR,
            self::PARAM_MISSING,
            self::REQUEST_METHOD_ERROR,
            self::REQUEST_FREQUENCY,
            self::DATA_NOT_FOUND,
            self::DATA_ALREADY_EXISTS,
            self::DATA_STATUS_ERROR => ErrorSeverity::INFO,
            self::SYSTEM_BUSY,
            self::SYSTEM_MAINTENANCE,
            self::THIRD_PARTY_ERROR => ErrorSeverity::WARNING,
            self::CONFIG_ERROR => ErrorSeverity::ERROR,
            self::SYSTEM_ERROR => ErrorSeverity::CRITICAL,
        };
    }

    public function isReportable(): bool
    {
        return match ($this->severity()) {
            ErrorSeverity::DEBUG,
            ErrorSeverity::INFO => false,
            default => true,
        };
    }

    public function notificationPolicy(): NotificationPolicy
    {
        return match ($this) {
            self::SYSTEM_ERROR => NotificationPolicy::IMMEDIATE,
            self::SYSTEM_BUSY,
            self::SYSTEM_MAINTENANCE,
            self::THIRD_PARTY_ERROR,
            self::CONFIG_ERROR => NotificationPolicy::THRESHOLD,
            default => NotificationPolicy::NEVER,
        };
    }

    public function throw(
        string $extra = '',
        mixed $data = null,
        ?Throwable $previous = null,
    ): never {
        $message = $extra === '' ? $this->message() : $this->message() . '：' . $extra;

        throw new BusinessException(
            message: $message,
            errorCode: $this,
            data: $data,
            previous: $previous,
        );
    }

    public function toResponse(mixed $data = null): array
    {
        return [
            'code' => $this->value,
            'message' => $this->message(),
            'data' => $data,
        ];
    }
}
