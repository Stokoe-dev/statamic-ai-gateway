<?php

namespace Stokoe\AiGateway\Tests\Property;

use Eris\Generators;
use Eris\TestTrait;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Stokoe\AiGateway\Support\AuditLogger;
use Stokoe\AiGateway\Tests\TestCase;

/**
 * Feature: ai-gateway, Property 11: Audit log completeness and safety
 *
 * For any request context, the AuditLogger output includes all required fields
 * (request_id, tool, status, http_status, target_type, target_identifier, environment, duration_ms)
 * and includes idempotency_key when provided. The output never contains bearer tokens,
 * confirmation tokens, or raw request payloads.
 *
 * **Validates: Requirements 19.2, 19.4, 19.5**
 */
class AuditLogPropertyTest extends TestCase
{
    use TestTrait;

    private const REQUIRED_FIELDS = [
        'request_id',
        'tool',
        'status',
        'http_status',
        'target_type',
        'target_identifier',
        'environment',
        'duration_ms',
    ];

    private const SENSITIVE_KEYS = [
        'bearer_token',
        'authorization',
        'token',
        'confirmation_token',
        'raw_payload',
        'payload',
        'password',
        'secret',
    ];

    /**
     * Property 11a: Audit log output always includes all required fields.
     */
    #[Test]
    public function audit_log_includes_all_required_fields(): void
    {
        $this->forAll(
            Generators::string(),  // request_id
            Generators::elements(['entry.create', 'entry.update', 'cache.clear', 'global.update']),
            Generators::elements(['succeeded', 'failed', 'rejected']),
            Generators::elements([200, 400, 401, 403, 404, 409, 422, 429, 500]),
            Generators::elements(['entry', 'global', 'cache', 'navigation', 'taxonomy']),
            Generators::string(),  // target_identifier
            Generators::elements(['production', 'staging', 'local', 'testing']),
            Generators::pos(),     // duration_ms
        )
            ->withMaxSize(50)
            ->__invoke(function (
                string $requestId,
                string $tool,
                string $status,
                int $httpStatus,
                string $targetType,
                string $targetIdentifier,
                string $environment,
                int $durationMs,
            ): void {
                $context = [
                    'request_id'        => $requestId,
                    'tool'              => $tool,
                    'status'            => $status,
                    'http_status'       => $httpStatus,
                    'target_type'       => $targetType,
                    'target_identifier' => $targetIdentifier,
                    'environment'       => $environment,
                    'duration_ms'       => $durationMs,
                ];

                $logged = $this->captureLogEntry($context);

                foreach (self::REQUIRED_FIELDS as $field) {
                    $this->assertArrayHasKey($field, $logged, "Required field '{$field}' missing from audit log");
                }
            });
    }

    /**
     * Property 11b: Audit log includes idempotency_key when provided.
     */
    #[Test]
    public function audit_log_includes_idempotency_key_when_provided(): void
    {
        $this->forAll(
            Generators::string(),  // request_id
            Generators::elements(['entry.create', 'cache.clear']),
            Generators::string(),  // idempotency_key
        )
            ->withMaxSize(50)
            ->__invoke(function (string $requestId, string $tool, string $idempotencyKey): void {
                $context = [
                    'request_id'        => $requestId,
                    'tool'              => $tool,
                    'status'            => 'succeeded',
                    'http_status'       => 200,
                    'target_type'       => 'entry',
                    'target_identifier' => 'pages/home',
                    'environment'       => 'production',
                    'duration_ms'       => 42,
                    'idempotency_key'   => $idempotencyKey,
                ];

                $logged = $this->captureLogEntry($context);

                $this->assertArrayHasKey('idempotency_key', $logged);
                $this->assertSame($idempotencyKey, $logged['idempotency_key']);
            });
    }

    /**
     * Property 11c: Audit log never contains sensitive data.
     */
    #[Test]
    public function audit_log_never_contains_sensitive_data(): void
    {
        $this->forAll(
            Generators::string(),  // request_id
            Generators::elements(['entry.create', 'cache.clear']),
            Generators::string(),  // bearer_token value
            Generators::string(),  // confirmation_token value
            Generators::string(),  // raw_payload value
        )
            ->withMaxSize(50)
            ->__invoke(function (
                string $requestId,
                string $tool,
                string $bearerToken,
                string $confirmationToken,
                string $rawPayload,
            ): void {
                // Intentionally inject sensitive keys into context
                $context = [
                    'request_id'         => $requestId,
                    'tool'               => $tool,
                    'status'             => 'succeeded',
                    'http_status'        => 200,
                    'target_type'        => 'entry',
                    'target_identifier'  => 'pages/home',
                    'environment'        => 'testing',
                    'duration_ms'        => 10,
                    'bearer_token'       => $bearerToken,
                    'confirmation_token' => $confirmationToken,
                    'raw_payload'        => $rawPayload,
                    'authorization'      => 'Bearer ' . $bearerToken,
                    'token'              => $bearerToken,
                    'payload'            => $rawPayload,
                    'password'           => 'secret123',
                    'secret'             => 'key456',
                ];

                $logged = $this->captureLogEntry($context);

                foreach (self::SENSITIVE_KEYS as $key) {
                    $this->assertArrayNotHasKey($key, $logged, "Sensitive key '{$key}' found in audit log");
                }
            });
    }

    /**
     * Capture the log entry that AuditLogger would write.
     */
    private function captureLogEntry(array $context): array
    {
        $captured = null;

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message, array $entry) use (&$captured) {
                $captured = $entry;
                return $message === 'ai_gateway.audit';
            });

        $logger = new AuditLogger();
        $logger->log($context);

        $this->assertNotNull($captured, 'AuditLogger did not write a log entry');

        return $captured;
    }
}
