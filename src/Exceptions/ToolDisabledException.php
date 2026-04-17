<?php

namespace Stokoe\AiGateway\Exceptions;

use RuntimeException;

class ToolDisabledException extends RuntimeException
{
    protected string $errorCode = 'tool_disabled';
    protected int $httpStatus = 403;

    public function __construct(string $toolName)
    {
        parent::__construct("Tool '{$toolName}' is disabled.");
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}
