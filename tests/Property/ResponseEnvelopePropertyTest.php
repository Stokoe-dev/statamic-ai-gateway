<?php

namespace Stokoe\AiGateway\Tests\Property;

use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Stokoe\AiGateway\Support\ToolResponse;

/**
 * Feature: ai-gateway, Property 10: Response envelope consistency
 *
 * For any ToolResponse, the JSON output contains a top-level `ok` boolean.
 * Success responses contain `ok: true`, `tool`, `result`, and `meta`.
 * Error responses contain `ok: false`, `tool`, `error` (with `code` and `message`), and `meta`.
 *
 * **Validates: Requirements 18.1, 18.2, 18.3**
 */
class ResponseEnvelopePropertyTest extends TestCase
{
    use TestTrait;

    /**
     * Property 10a: Success responses always contain ok:true, tool, result, and meta.
     */
    #[Test]
    public function success_response_envelope_has_required_keys(): void
    {
        $this->forAll(
            Generators::string(),
            Generators::associative([
                'status' => Generators::elements(['created', 'updated', 'cleared']),
            ]),
            Generators::string(),
        )
            ->withMaxSize(50)
            ->__invoke(function (string $tool, array $result, string $requestId): void {
                $meta = ['request_id' => $requestId];
                $response = ToolResponse::success($tool, $result, $meta);
                $json = $response->toJsonResponse();
                $data = json_decode($json->getContent(), true);

                $this->assertArrayHasKey('ok', $data);
                $this->assertTrue($data['ok'], 'Success response must have ok: true');
                $this->assertArrayHasKey('tool', $data);
                $this->assertSame($tool, $data['tool']);
                $this->assertArrayHasKey('result', $data);
                $this->assertIsArray($data['result']);
                $this->assertArrayHasKey('meta', $data);
                $this->assertIsArray($data['meta']);
                $this->assertSame(200, $json->getStatusCode());
            });
    }

    /**
     * Property 10b: Error responses always contain ok:false, tool, error (with code and message), and meta.
     */
    #[Test]
    public function error_response_envelope_has_required_keys(): void
    {
        $this->forAll(
            Generators::string(),
            Generators::elements([
                'unauthorized', 'forbidden', 'tool_not_found', 'tool_disabled',
                'validation_failed', 'resource_not_found', 'conflict',
                'rate_limited', 'execution_failed', 'internal_error',
            ]),
            Generators::string(),
            Generators::elements([400, 401, 403, 404, 409, 422, 429, 500]),
            Generators::string(),
        )
            ->withMaxSize(50)
            ->__invoke(function (string $tool, string $code, string $message, int $httpStatus, string $requestId): void {
                $meta = ['request_id' => $requestId];
                $response = ToolResponse::error($tool, $code, $message, $httpStatus, $meta);
                $json = $response->toJsonResponse();
                $data = json_decode($json->getContent(), true);

                $this->assertArrayHasKey('ok', $data);
                $this->assertFalse($data['ok'], 'Error response must have ok: false');
                $this->assertArrayHasKey('tool', $data);
                $this->assertSame($tool, $data['tool']);
                $this->assertArrayHasKey('error', $data);
                $this->assertIsArray($data['error']);
                $this->assertArrayHasKey('code', $data['error']);
                $this->assertSame($code, $data['error']['code']);
                $this->assertArrayHasKey('message', $data['error']);
                $this->assertSame($message, $data['error']['message']);
                $this->assertArrayHasKey('meta', $data);
                $this->assertIsArray($data['meta']);
                $this->assertSame($httpStatus, $json->getStatusCode());
            });
    }

    /**
     * Property 10c: Confirmation-required responses contain ok:false, tool, error, confirmation, and meta.
     */
    #[Test]
    public function confirmation_response_envelope_has_required_keys(): void
    {
        $this->forAll(
            Generators::string(),
            Generators::string(),
            Generators::string(),
            Generators::string(),
        )
            ->withMaxSize(50)
            ->__invoke(function (string $tool, string $token, string $expiresAt, string $requestId): void {
                $meta = ['request_id' => $requestId];
                $operationSummary = ['tool' => $tool, 'target' => 'static', 'environment' => 'production'];
                $response = ToolResponse::confirmationRequired($tool, $token, $expiresAt, $operationSummary, $meta);
                $json = $response->toJsonResponse();
                $data = json_decode($json->getContent(), true);

                $this->assertArrayHasKey('ok', $data);
                $this->assertFalse($data['ok'], 'Confirmation response must have ok: false');
                $this->assertArrayHasKey('tool', $data);
                $this->assertSame($tool, $data['tool']);
                $this->assertArrayHasKey('error', $data);
                $this->assertSame('confirmation_required', $data['error']['code']);
                $this->assertArrayHasKey('message', $data['error']);
                $this->assertArrayHasKey('confirmation', $data);
                $this->assertArrayHasKey('token', $data['confirmation']);
                $this->assertSame($token, $data['confirmation']['token']);
                $this->assertArrayHasKey('expires_at', $data['confirmation']);
                $this->assertSame($expiresAt, $data['confirmation']['expires_at']);
                $this->assertArrayHasKey('operation_summary', $data['confirmation']);
                $this->assertArrayHasKey('meta', $data);
                $this->assertIsArray($data['meta']);
                $this->assertSame(200, $json->getStatusCode());
            });
    }

    /**
     * Property 10d: The ok field is always a boolean for any response type.
     */
    #[Test]
    public function ok_field_is_always_boolean(): void
    {
        $this->forAll(
            Generators::elements(['success', 'error', 'confirmation']),
            Generators::string(),
        )
            ->withMaxSize(50)
            ->__invoke(function (string $type, string $tool): void {
                $response = match ($type) {
                    'success' => ToolResponse::success($tool, ['status' => 'created']),
                    'error' => ToolResponse::error($tool, 'internal_error', 'fail', 500),
                    'confirmation' => ToolResponse::confirmationRequired($tool, 'tok', '2026-01-01T00:00:00Z', []),
                };

                $json = $response->toJsonResponse();
                $data = json_decode($json->getContent(), true);

                $this->assertIsBool($data['ok']);
            });
    }
}
