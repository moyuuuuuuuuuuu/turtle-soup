<?php

declare(strict_types=1);

namespace App\Common\Contracts;

use App\Common\Enums\ErrorModule;
use App\Common\Enums\ErrorSeverity;
use App\Common\Enums\NotificationPolicy;
use Throwable;

interface ErrorCodeInterface
{
    public function code(): string;

    public function message(): string;

    public function httpStatus(): int;

    public function module(): ErrorModule;

    public function severity(): ErrorSeverity;

    public function isReportable(): bool;

    public function notificationPolicy(): NotificationPolicy;

    public function throw(
        string $extra = '',
        mixed $data = null,
        ?Throwable $previous = null,
    ): never;

    /** @return array{code: string, message: string, data: mixed} */
    public function toResponse(mixed $data = null): array;
}
