<?php

namespace Stokoe\AiGateway\Exceptions;

use RuntimeException;

class ToolValidationException extends RuntimeException
{
    protected string $errorCode = 'validation_failed';
    protected int $httpStatus = 422;
    protected ?array $details;

    public function __construct(string $message = 'Validation failed.', ?array $details = null)
    {
        parent::__construct($message);
        $this->details = $details;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    public function getDetails(): ?array
    {
        return $this->details;
    }
}
