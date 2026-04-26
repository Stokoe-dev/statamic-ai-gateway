<?php

namespace Stokoe\AiGateway\Tests\Property;

use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\Attributes\Test;
use Stokoe\AiGateway\Tests\TestCase;
use Stokoe\AiGateway\Tools\AssetUploadTool;

/**
 * Feature: gateway-content-expansion, Property 1: Asset size validation rejects oversized payloads
 *
 * For any base64-encoded payload and any configured max_asset_size limit,
 * the AssetUploadTool SHALL reject the upload with validation_failed if and
 * only if the decoded payload size exceeds the limit.
 *
 * **Validates: Requirements 1.4**
 */
class AssetSizeValidationPropertyTest extends TestCase
{
    use TestTrait;

    /**
     * Property 1a: Payloads exceeding max_asset_size are rejected.
     */
    #[Test]
    public function oversized_payloads_are_rejected(): void
    {
        $this->forAll(
            Generators::choose(1, 100), // max size in bytes
            Generators::choose(1, 50),  // overshoot amount
        )
            ->withMaxSize(50)
            ->__invoke(function (int $maxSize, int $overshoot): void {
                config(['ai_gateway.max_asset_size' => $maxSize]);
                config(['ai_gateway.allowed_asset_extensions' => ['txt']]);

                $decoded = str_repeat('x', $maxSize + $overshoot);
                $base64 = base64_encode($decoded);

                $tool = new AssetUploadTool();
                $response = $tool->execute([
                    'container' => 'test',
                    'path' => 'file.txt',
                    'file' => $base64,
                ]);

                $data = json_decode($response->toJsonResponse()->getContent(), true);

                $this->assertFalse($data['ok'], 'Oversized payload should be rejected');
                $this->assertSame('validation_failed', $data['error']['code']);
            });
    }

    /**
     * Property 1b: Payloads at exactly max_asset_size pass size validation.
     *
     * Note: These will fail at the container-existence check (resource_not_found),
     * which proves they passed size validation.
     */
    #[Test]
    public function payloads_at_limit_pass_size_validation(): void
    {
        $this->forAll(
            Generators::choose(10, 200), // max size in bytes
        )
            ->withMaxSize(50)
            ->__invoke(function (int $maxSize): void {
                config(['ai_gateway.max_asset_size' => $maxSize]);
                config(['ai_gateway.allowed_asset_extensions' => ['txt']]);

                // Exactly at the limit
                $decoded = str_repeat('x', $maxSize);
                $base64 = base64_encode($decoded);

                $tool = new AssetUploadTool();
                $response = $tool->execute([
                    'container' => 'test',
                    'path' => 'file.txt',
                    'file' => $base64,
                ]);

                $data = json_decode($response->toJsonResponse()->getContent(), true);

                // Should NOT be validation_failed for size — it should pass through
                // to the container check (resource_not_found)
                if (! $data['ok']) {
                    $this->assertNotSame(
                        'validation_failed',
                        $data['error']['code'],
                        "Payload at exactly max size ({$maxSize} bytes) should not be rejected for size"
                    );
                }
            });
    }

    /**
     * Property 1c: Payloads strictly under max_asset_size pass size validation.
     *
     * Note: These will fail at the container-existence check (resource_not_found),
     * which proves they passed size validation.
     */
    #[Test]
    public function payloads_under_limit_pass_size_validation(): void
    {
        $this->forAll(
            Generators::choose(10, 200), // max size in bytes
            Generators::choose(1, 9),    // how far under the limit
        )
            ->withMaxSize(50)
            ->__invoke(function (int $maxSize, int $underBy): void {
                config(['ai_gateway.max_asset_size' => $maxSize]);
                config(['ai_gateway.allowed_asset_extensions' => ['txt']]);

                $payloadSize = max(1, $maxSize - $underBy);
                $decoded = str_repeat('x', $payloadSize);
                $base64 = base64_encode($decoded);

                $tool = new AssetUploadTool();
                $response = $tool->execute([
                    'container' => 'test',
                    'path' => 'file.txt',
                    'file' => $base64,
                ]);

                $data = json_decode($response->toJsonResponse()->getContent(), true);

                // Should NOT be validation_failed for size
                if (! $data['ok']) {
                    $this->assertNotSame(
                        'validation_failed',
                        $data['error']['code'],
                        "Payload under max size ({$payloadSize} < {$maxSize} bytes) should not be rejected for size"
                    );
                }
            });
    }
}
