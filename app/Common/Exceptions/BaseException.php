<?php

declare(strict_types=1);

namespace App\Common\Exceptions;

use App\Common\Contracts\ErrorCodeInterface;
use RuntimeException;
use Throwable;

abstract class BaseException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ErrorCodeInterface $errorCode,
        public readonly mixed $data = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function httpStatus(): int
    {
        return $this->errorCode->httpStatus();
    }
}
