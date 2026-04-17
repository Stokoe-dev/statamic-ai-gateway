<?php

namespace Stokoe\AiGateway\Exceptions;

use RuntimeException;

class ToolAuthorizationException extends RuntimeException
{
    protected string $errorCode = 'forbidden';
    protected int $httpStatus = 403;

    public function __construct(string $message = 'Access denied.')
    {
        parent::__construct($message);
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
