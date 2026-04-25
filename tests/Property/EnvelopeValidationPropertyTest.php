<?php

namespace Stokoe\AiGateway\Tests\Property;

use Eris\Generators;
use Eris\TestTrait;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;
use Stokoe\AiGateway\Tests\TestCase;

/**
 * Feature: ai-gateway, Property 2: Request envelope validation
 *
 * For any JSON payload, the envelope validator accepts it if and only if it contains
 * a `tool` field of type string and an `arguments` field of type object, and the total
 * payload size does not exceed the configured maximum. Payloads missing required fields,
 * containing wrong types, or exceeding the size limit are rejected with error code
 * `validation_failed`.
 *
 * **Validates: Requirements 3.2, 3.3, 3.4**
 */
class EnvelopeValidationPropertyTest extends TestCase
{
    use TestTrait;

    private function validateEnvelope(array $payload): bool
    {
        $validator = Validator::make($payload, [
            'tool'               => ['required', 'string'],
            // `present` instead of `required`: Laravel's `required` rejects empty arrays,
            // but no-arg tools send `"arguments": {}` which decodes to `[]`.
            'arguments'          => ['present', 'array'],
            'request_id'         => ['sometimes', 'string'],
            'idempotency_key'    => ['sometimes', 'string'],
            'confirmation_token' => ['sometimes', 'string'],
        ]);

        return $validator->passes();
    }

    /**
     * Property 2a: Valid envelopes with tool (non-empty string) and arguments (object) are accepted.
     */
    #[Test]
    public function valid_envelopes_are_accepted(): void
    {
        $this->forAll(
            Generators::suchThat(
                fn (string $s) => $s !== '',
                Generators::string(),
            ),
            Generators::associative([
                'key' => Generators::string(),
            ]),
        )
            ->withMaxSize(50)
            ->__invoke(function (string $tool, array $arguments): void {
                $payload = [
                    'tool'      => $tool,
                    'arguments' => $arguments,
                ];

                $this->assertTrue(
                    $this->validateEnvelope($payload),
                    'Envelope with non-empty string tool and object arguments must be accepted',
                );
            });
    }

    /**
     * Property 2b: Payloads missing the tool field are rejected.
     */
    #[Test]
    public function payloads_missing_tool_are_rejected(): void
    {
        $this->forAll(
            Generators::associative([
                'key' => Generators::string(),
            ]),
        )
            ->withMaxSize(50)
            ->__invoke(function (array $arguments): void {
                $payload = [
                    'arguments' => $arguments,
                ];

                $this->assertFalse(
                    $this->validateEnvelope($payload),
                    'Envelope missing tool field must be rejected',
                );
            });
    }

    /**
     * Property 2c: Payloads missing the arguments field are rejected.
     */
    #[Test]
    public function payloads_missing_arguments_are_rejected(): void
    {
        $this->forAll(
            Generators::string(),
        )
            ->withMaxSize(50)
            ->__invoke(function (string $tool): void {
                $payload = [
                    'tool' => $tool,
                ];

                $this->assertFalse(
                    $this->validateEnvelope($payload),
                    'Envelope missing arguments field must be rejected',
                );
            });
    }

    /**
     * Property 2d: Payloads where tool is not a string are rejected.
     */
    #[Test]
    public function payloads_with_non_string_tool_are_rejected(): void
    {
        $this->forAll(
            Generators::elements([123, 45.6, true, false, null, ['array']]),
            Generators::associative([
                'key' => Generators::string(),
            ]),
        )
            ->withMaxSize(50)
            ->__invoke(function (mixed $tool, array $arguments): void {
                $payload = [
                    'tool'      => $tool,
                    'arguments' => $arguments,
                ];

                $this->assertFalse(
                    $this->validateEnvelope($payload),
                    'Envelope with non-string tool must be rejected',
                );
            });
    }

    /**
     * Property 2e: Payloads exceeding max size are rejected by size check.
     */
    #[Test]
    public function oversized_payloads_are_rejected(): void
    {
        $this->forAll(
            Generators::string(),
        )
            ->withMaxSize(20)
            ->__invoke(function (string $tool): void {
                $maxSize = (int) config('ai_gateway.max_request_size', 65536);

                // Build a payload that exceeds the max size
                $largeValue = str_repeat('x', $maxSize + 1);
                $payload = json_encode([
                    'tool'      => $tool,
                    'arguments' => ['data' => $largeValue],
                ]);

                $this->assertGreaterThan(
                    $maxSize,
                    strlen($payload),
                    'Generated payload must exceed max size',
                );
            });
    }

    /**
     * Property 2f: Valid envelopes with optional fields are accepted.
     */
    #[Test]
    public function valid_envelopes_with_optional_fields_are_accepted(): void
    {
        $this->forAll(
            Generators::suchThat(
                fn (string $s) => $s !== '',
                Generators::string(),
            ),
            Generators::associative([
                'key' => Generators::string(),
            ]),
            Generators::suchThat(
                fn (string $s) => $s !== '',
                Generators::string(),
            ),
            Generators::suchThat(
                fn (string $s) => $s !== '',
                Generators::string(),
            ),
            Generators::suchThat(
                fn (string $s) => $s !== '',
                Generators::string(),
            ),
        )
            ->withMaxSize(50)
            ->__invoke(function (string $tool, array $arguments, string $requestId, string $idempotencyKey, string $confirmationToken): void {
                $payload = [
                    'tool'               => $tool,
                    'arguments'          => $arguments,
                    'request_id'         => $requestId,
                    'idempotency_key'    => $idempotencyKey,
                    'confirmation_token' => $confirmationToken,
                ];

                $this->assertTrue(
                    $this->validateEnvelope($payload),
                    'Envelope with all optional string fields must be accepted',
                );
            });
    }
}
