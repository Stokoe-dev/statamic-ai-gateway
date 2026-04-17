<?php

namespace Stokoe\AiGateway\Support;

use Illuminate\Http\JsonResponse;

class ToolResponse
{
    private bool $ok;
    private string $tool;
    private int $httpStatus;
    private array $payload;
    private array $meta;

    private function __construct(bool $ok, string $tool, int $httpStatus, array $payload, array $meta)
    {
        $this->ok = $ok;
        $this->tool = $tool;
        $this->httpStatus = $httpStatus;
        $this->payload = $payload;
        $this->meta = $meta;
    }

    public static function success(string $tool, array $result, array $meta = []): self
    {
        return new self(true, $tool, 200, ['result' => $result], $meta);
    }

    public static function error(
        string $tool,
        string $code,
        string $message,
        int $httpStatus,
        array $meta = [],
        ?array $details = null,
    ): self {
        $error = ['code' => $code, 'message' => $message];

        if ($details !== null) {
            $error['details'] = $details;
        }

        return new self(false, $tool, $httpStatus, ['error' => $error], $meta);
    }

    public static function confirmationRequired(
        string $tool,
        string $token,
        string $expiresAt,
        array $operationSummary,
        array $meta = [],
    ): self {
        return new self(false, $tool, 200, [
            'error' => [
                'code' => 'confirmation_required',
                'message' => 'This operation requires explicit confirmation in production.',
            ],
            'confirmation' => [
                'token' => $token,
                'expires_at' => $expiresAt,
                'operation_summary' => $operationSummary,
            ],
        ], $meta);
    }

    public function toJsonResponse(): JsonResponse
    {
        $data = array_merge(
            ['ok' => $this->ok, 'tool' => $this->tool],
            $this->payload,
            ['meta' => $this->meta],
        );

        return new JsonResponse($data, $this->httpStatus);
    }

    public function isOk(): bool
    {
        return $this->ok;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}
