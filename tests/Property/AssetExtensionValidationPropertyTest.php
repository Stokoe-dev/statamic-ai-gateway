<?php

namespace Stokoe\AiGateway\Tests\Property;

use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\Attributes\Test;
use Stokoe\AiGateway\Tests\TestCase;
use Stokoe\AiGateway\Tools\AssetUploadTool;

/**
 * Feature: gateway-content-expansion, Property 2: Asset extension validation rejects disallowed extensions
 *
 * For any file path and any configured allowed_asset_extensions list,
 * the AssetUploadTool SHALL reject the upload with validation_failed if and
 * only if the file's extension is not in the allowed list.
 *
 * **Validates: Requirements 1.5**
 */
class AssetExtensionValidationPropertyTest extends TestCase
{
    use TestTrait;

    /**
     * All possible extensions we draw from for both allowed and disallowed pools.
     */
    private const ALL_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf',
        'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'md',
        'mp4', 'webm', 'mp3', 'exe', 'bat', 'sh', 'php',
        'py', 'rb', 'pl', 'zip', 'tar', 'gz', 'bmp', 'tiff',
    ];

    /**
     * Property 2a: Files with extensions NOT in the allowed list are rejected
     * with validation_failed.
     *
     * Strategy: Generate a random allowed-extensions list (subset of ALL_EXTENSIONS),
     * then pick an extension guaranteed NOT to be in that list, and verify rejection.
     */
    #[Test]
    public function disallowed_extensions_are_rejected(): void
    {
        $this->forAll(
            Generators::subset(self::ALL_EXTENSIONS),
            Generators::elements(self::ALL_EXTENSIONS),
            Generators::elements(['images/', 'docs/', 'uploads/', 'media/', '']),
            Generators::elements(['file', 'document', 'photo', 'asset', 'upload']),
        )
            ->withMaxSize(50)
            ->__invoke(function (array $allowedList, string $extension, string $pathPrefix, string $filename): void {
                // Ensure the allowed list is non-empty (otherwise all extensions are disallowed trivially)
                if (empty($allowedList)) {
                    $allowedList = ['jpg'];
                }

                // Only test when the extension is NOT in the allowed list
                if (in_array($extension, $allowedList, true)) {
                    return;
                }

                config(['ai_gateway.max_asset_size' => 1048576]);
                config(['ai_gateway.allowed_asset_extensions' => $allowedList]);

                $base64 = base64_encode('test content');
                $path = "{$pathPrefix}{$filename}.{$extension}";

                $tool = new AssetUploadTool();
                $response = $tool->execute([
                    'container' => 'test',
                    'path' => $path,
                    'file' => $base64,
                ]);

                $data = json_decode($response->toJsonResponse()->getContent(), true);

                $this->assertFalse($data['ok'], "Extension '{$extension}' should be rejected when allowed list is [" . implode(', ', $allowedList) . ']');
                $this->assertSame('validation_failed', $data['error']['code']);
            });
    }

    /**
     * Property 2b: Files with extensions IN the allowed list pass extension validation.
     *
     * They will fail at the container-existence check (resource_not_found),
     * proving they passed extension validation.
     *
     * Strategy: Generate a random allowed-extensions list, then pick an extension
     * from that list, and verify it does NOT fail with validation_failed.
     */
    #[Test]
    public function allowed_extensions_pass_validation(): void
    {
        $this->forAll(
            Generators::subset(self::ALL_EXTENSIONS),
            Generators::elements(['images/', 'docs/', 'uploads/', 'media/', '']),
            Generators::elements(['file', 'document', 'photo', 'asset', 'upload']),
        )
            ->withMaxSize(50)
            ->__invoke(function (array $allowedList, string $pathPrefix, string $filename): void {
                // Ensure the allowed list is non-empty so we have something to pick from
                if (empty($allowedList)) {
                    $allowedList = ['jpg', 'png', 'txt'];
                }

                // Pick a random extension from the allowed list
                $extension = $allowedList[array_rand($allowedList)];

                config(['ai_gateway.max_asset_size' => 1048576]);
                config(['ai_gateway.allowed_asset_extensions' => $allowedList]);

                $base64 = base64_encode('test content');
                $path = "{$pathPrefix}{$filename}.{$extension}";

                $tool = new AssetUploadTool();
                $response = $tool->execute([
                    'container' => 'test',
                    'path' => $path,
                    'file' => $base64,
                ]);

                $data = json_decode($response->toJsonResponse()->getContent(), true);

                // Should NOT be validation_failed — it should pass through
                // to the container check (resource_not_found)
                if (! $data['ok']) {
                    $this->assertNotSame(
                        'validation_failed',
                        $data['error']['code'],
                        "Extension '{$extension}' should pass extension validation when allowed list is [" . implode(', ', $allowedList) . ']'
                    );
                }
            });
    }

    /**
     * Property 2c: Extension matching is case-insensitive.
     *
     * The tool lowercases the file extension before checking, so 'JPG' should
     * pass when 'jpg' is in the allowed list.
     */
    #[Test]
    public function extension_matching_is_case_insensitive(): void
    {
        $this->forAll(
            Generators::elements(['jpg', 'png', 'gif', 'pdf', 'txt', 'md', 'csv']),
            Generators::elements(['images/', 'docs/', '']),
            Generators::elements(['file', 'photo', 'doc']),
        )
            ->withMaxSize(50)
            ->__invoke(function (string $extension, string $pathPrefix, string $filename): void {
                $upperExtension = strtoupper($extension);

                config(['ai_gateway.max_asset_size' => 1048576]);
                config(['ai_gateway.allowed_asset_extensions' => [$extension]]);

                $base64 = base64_encode('test content');
                $path = "{$pathPrefix}{$filename}.{$upperExtension}";

                $tool = new AssetUploadTool();
                $response = $tool->execute([
                    'container' => 'test',
                    'path' => $path,
                    'file' => $base64,
                ]);

                $data = json_decode($response->toJsonResponse()->getContent(), true);

                // Should NOT be validation_failed — uppercase extension should match lowercase allowed
                if (! $data['ok']) {
                    $this->assertNotSame(
                        'validation_failed',
                        $data['error']['code'],
                        "Extension '{$upperExtension}' should pass when '{$extension}' is allowed (case-insensitive)"
                    );
                }
            });
    }
}
