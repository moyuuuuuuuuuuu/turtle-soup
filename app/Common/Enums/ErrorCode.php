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
    case AUTH_ANONYMOUS_INVALID = 'auth.anonymous_invalid';
    case GAME_NOT_FOUND = 'game.not_found';
    case GAME_STATUS_INVALID = 'game.status_invalid';
    case GAME_QUESTION_LIMIT_REACHED = 'game.question_limit_reached';
    case GAME_HINT_UNAVAILABLE = 'game.hint_unavailable';
    case GAME_REQUEST_DUPLICATE = 'game.request_duplicate';
    case QUESTION_NOT_FOUND = 'question.not_found';
    case QUESTION_CONTENT_INCOMPLETE = 'question.content_incomplete';
    case QUESTION_STATUS_INVALID = 'question.status_invalid';
    case QUESTION_VERSION_CONFLICT = 'question.version_conflict';
    case QUESTION_ANSWER_FORBIDDEN = 'question.answer_forbidden';
    case QUESTION_COPY_FAILED = 'question.copy_failed';
    case QUESTION_VERSION_NOT_FOUND = 'question.version_not_found';
    case QUESTION_RISK_CONFIRMATION_REQUIRED = 'question.risk_confirmation_required';
    case QUESTION_TRANSLATION_INCOMPLETE = 'question.translation_incomplete';
    case TAG_NOT_FOUND = 'tag.not_found';
    case TAG_IN_USE = 'tag.in_use';
    case TAG_SLUG_INVALID = 'tag.slug_invalid';
    case AI_WORKFLOW_TIMEOUT = 'ai.workflow_timeout';
    case AI_INVALID_RESPONSE = 'ai.invalid_response';
    case AI_AUTH_FAILED = 'ai.auth_failed';
    case AI_WORKFLOW_FAILED = 'ai.workflow_failed';

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
            self::AUTH_ANONYMOUS_INVALID => '匿名会话无效或已过期',
            self::GAME_NOT_FOUND => '游戏不存在',
            self::GAME_STATUS_INVALID => '当前游戏状态不允许此操作',
            self::GAME_QUESTION_LIMIT_REACHED => '提问次数已用完，请提交最终猜测',
            self::GAME_HINT_UNAVAILABLE => '该级提示不可用或已经使用',
            self::GAME_REQUEST_DUPLICATE => '请求已处理，请勿重复提交',
            self::QUESTION_NOT_FOUND => '题目不存在',
            self::QUESTION_CONTENT_INCOMPLETE => '题目内容不完整',
            self::QUESTION_STATUS_INVALID => '题目状态不允许当前操作',
            self::QUESTION_VERSION_CONFLICT => '题目已被其他操作更新',
            self::QUESTION_ANSWER_FORBIDDEN => '无权查看或修改题目汤底',
            self::QUESTION_COPY_FAILED => '复制题目失败',
            self::QUESTION_VERSION_NOT_FOUND => '题目历史版本不存在',
            self::QUESTION_RISK_CONFIRMATION_REQUIRED => '该题目需要确认内容风险后才能发布',
            self::QUESTION_TRANSLATION_INCOMPLETE => '题目中文内容不完整',
            self::TAG_NOT_FOUND => '标签不存在',
            self::TAG_IN_USE => '标签正在被题目使用',
            self::TAG_SLUG_INVALID => '标签名称或标识不合法',
            self::AI_WORKFLOW_TIMEOUT => 'AI 工作流超时',
            self::AI_INVALID_RESPONSE => 'AI 返回内容不符合约定',
            self::AI_AUTH_FAILED => 'AI 服务鉴权失败',
            self::AI_WORKFLOW_FAILED => 'AI 工作流执行失败',
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
            self::GAME_NOT_FOUND => 404,
            self::AUTH_ANONYMOUS_INVALID => 401,
            self::QUESTION_NOT_FOUND => 404,
            self::DATA_ALREADY_EXISTS,
            self::DATA_STATUS_ERROR => 409,
            self::GAME_STATUS_INVALID,
            self::GAME_QUESTION_LIMIT_REACHED,
            self::GAME_HINT_UNAVAILABLE,
            self::GAME_REQUEST_DUPLICATE => 409,
            self::QUESTION_STATUS_INVALID,
            self::QUESTION_VERSION_CONFLICT,
            self::QUESTION_COPY_FAILED,
            self::QUESTION_RISK_CONFIRMATION_REQUIRED,
            self::TAG_IN_USE => 409,
            self::QUESTION_VERSION_NOT_FOUND,
            self::TAG_NOT_FOUND => 404,
            self::QUESTION_ANSWER_FORBIDDEN => 403,
            self::QUESTION_CONTENT_INCOMPLETE => 422,
            self::QUESTION_TRANSLATION_INCOMPLETE,
            self::TAG_SLUG_INVALID => 422,
            self::AI_WORKFLOW_TIMEOUT,
            self::AI_INVALID_RESPONSE,
            self::AI_AUTH_FAILED,
            self::AI_WORKFLOW_FAILED => 502,
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
            self::QUESTION_NOT_FOUND,
            self::QUESTION_CONTENT_INCOMPLETE,
            self::QUESTION_STATUS_INVALID,
            self::QUESTION_VERSION_CONFLICT,
            self::QUESTION_ANSWER_FORBIDDEN,
            self::QUESTION_COPY_FAILED,
            self::QUESTION_VERSION_NOT_FOUND,
            self::QUESTION_RISK_CONFIRMATION_REQUIRED,
            self::QUESTION_TRANSLATION_INCOMPLETE,
            self::TAG_NOT_FOUND,
            self::TAG_IN_USE,
            self::TAG_SLUG_INVALID => ErrorModule::QUESTION,
            self::AUTH_ANONYMOUS_INVALID => ErrorModule::AUTH,
            self::GAME_NOT_FOUND,
            self::GAME_STATUS_INVALID,
            self::GAME_QUESTION_LIMIT_REACHED,
            self::GAME_HINT_UNAVAILABLE,
            self::GAME_REQUEST_DUPLICATE => ErrorModule::GAME,
            self::AI_WORKFLOW_TIMEOUT,
            self::AI_INVALID_RESPONSE,
            self::AI_AUTH_FAILED,
            self::AI_WORKFLOW_FAILED => ErrorModule::AI,
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
            self::AUTH_ANONYMOUS_INVALID,
            self::GAME_NOT_FOUND,
            self::GAME_STATUS_INVALID,
            self::GAME_QUESTION_LIMIT_REACHED,
            self::GAME_HINT_UNAVAILABLE,
            self::GAME_REQUEST_DUPLICATE => ErrorSeverity::INFO,
            self::QUESTION_NOT_FOUND,
            self::QUESTION_CONTENT_INCOMPLETE,
            self::QUESTION_STATUS_INVALID,
            self::QUESTION_VERSION_CONFLICT,
            self::QUESTION_ANSWER_FORBIDDEN,
            self::QUESTION_COPY_FAILED,
            self::QUESTION_VERSION_NOT_FOUND,
            self::QUESTION_RISK_CONFIRMATION_REQUIRED,
            self::QUESTION_TRANSLATION_INCOMPLETE,
            self::TAG_NOT_FOUND,
            self::TAG_IN_USE,
            self::TAG_SLUG_INVALID => ErrorSeverity::INFO,
            self::SYSTEM_BUSY,
            self::SYSTEM_MAINTENANCE,
            self::THIRD_PARTY_ERROR => ErrorSeverity::WARNING,
            self::AI_WORKFLOW_TIMEOUT => ErrorSeverity::WARNING,
            self::AI_INVALID_RESPONSE => ErrorSeverity::ERROR,
            self::AI_AUTH_FAILED => ErrorSeverity::ERROR,
            self::AI_WORKFLOW_FAILED => ErrorSeverity::WARNING,
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
            self::AI_WORKFLOW_TIMEOUT,
            self::AI_INVALID_RESPONSE,
            self::AI_AUTH_FAILED,
            self::AI_WORKFLOW_FAILED,
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
