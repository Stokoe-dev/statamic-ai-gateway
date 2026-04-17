<?php

namespace Stokoe\AiGateway\Exceptions;

use RuntimeException;

class ToolNotFoundException extends RuntimeException
{
    protected string $errorCode = 'tool_not_found';
    protected int $httpStatus = 404;

    public function __construct(string $toolName)
    {
        parent::__construct("Tool '{$toolName}' is not registered.");
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
