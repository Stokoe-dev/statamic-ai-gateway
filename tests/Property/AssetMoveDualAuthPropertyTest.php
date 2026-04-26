<?php

namespace Stokoe\AiGateway\Tests\Property;

use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Contracts\Assets\AssetContainer as AssetContainerContract;
use Statamic\Contracts\Assets\AssetContainerRepository;
use Stokoe\AiGateway\Exceptions\ToolAuthorizationException;
use Stokoe\AiGateway\Policies\ToolPolicy;
use Stokoe\AiGateway\Tests\TestCase;
use Stokoe\AiGateway\Tools\AssetMoveTool;

/**
 * Feature: gateway-content-expansion, Property 12: Asset move dual-container authorization
 *
 * For any source container, destination container, and allowlist configuration,
 * the AssetMoveTool SHALL reject the request if either the source container or
 * the destination container is not in the allowed_asset_containers allowlist.
 *
 * **Validates: Requirements 17.7**
 */
class AssetMoveDualAuthPropertyTest extends TestCase
{
    private const CONTAINER_NAMES = [
        'assets', 'images', 'documents', 'media', 'uploads', 'files',
        'photos', 'videos', 'audio', 'pdfs', 'icons', 'logos',
        'banners', 'thumbnails', 'avatars', 'backgrounds', 'archive',
        'public', 'private', 'temp', 'staging', 'production',
    ];

    private const ITERATIONS = 100;

    /**
     * Property 12a: When the source container is NOT in the allowlist,
     * ToolPolicy::targetAllowed() returns false for the source container.
     *
     * The source container check happens via resolveTarget() → ToolPolicy in the
     * controller pipeline. We test ToolPolicy::targetAllowed() directly.
     */
    #[Test]
    public function source_container_not_in_allowlist_is_rejected(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $sourceContainer = $this->randomContainerName();
            $allowlist = $this->randomAllowlistExcluding($sourceContainer);

            config(['ai_gateway.allowed_asset_containers' => $allowlist]);

            $policy = new ToolPolicy();

            $this->assertFalse(
                $policy->targetAllowed('asset', $sourceContainer),
                "Iteration {$i}: Source container '{$sourceContainer}' should be rejected "
                . "when allowlist is [" . implode(', ', $allowlist) . "]"
            );
        }
    }

    /**
     * Property 12b: When the destination container is NOT in the allowlist,
     * AssetMoveTool::execute() throws ToolAuthorizationException.
     *
     * The destination container check happens internally in execute() when
     * destination_container differs from source_container.
     */
    #[Test]
    public function destination_container_not_in_allowlist_is_rejected(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $sourceContainer = $this->randomContainerName();
            $destContainer = $this->randomContainerNameExcluding($sourceContainer);

            // Source IS in allowlist, destination is NOT
            $allowlist = [$sourceContainer];
            config(['ai_gateway.allowed_asset_containers' => $allowlist]);

            $tool = new AssetMoveTool();

            $threw = false;
            try {
                $tool->execute([
                    'source_container' => $sourceContainer,
                    'source_path' => 'test/file.jpg',
                    'destination_path' => 'moved/file.jpg',
                    'destination_container' => $destContainer,
                ]);
            } catch (ToolAuthorizationException $e) {
                $threw = true;
                $this->assertStringContainsString(
                    $destContainer,
                    $e->getMessage(),
                    "Iteration {$i}: Exception message should mention the rejected destination container"
                );
            }

            $this->assertTrue(
                $threw,
                "Iteration {$i}: Destination container '{$destContainer}' not in allowlist "
                . "[{$sourceContainer}] should throw ToolAuthorizationException"
            );
        }
    }

    /**
     * Property 12c: When BOTH containers are in the allowlist, the request
     * passes authorization checks (may fail at container existence, which is fine).
     *
     * We verify no ToolAuthorizationException is thrown. The tool will return
     * resource_not_found because the containers don't actually exist, which
     * proves authorization passed.
     */
    #[Test]
    public function both_containers_in_allowlist_passes_authorization(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $sourceContainer = $this->randomContainerName();
            $destContainer = $this->randomContainerName();

            // Both containers in allowlist
            $allowlist = array_unique([$sourceContainer, $destContainer, ...$this->randomSubset(self::CONTAINER_NAMES, random_int(0, 3))]);
            config(['ai_gateway.allowed_asset_containers' => array_values($allowlist)]);

            $tool = new AssetMoveTool();

            try {
                $response = $tool->execute([
                    'source_container' => $sourceContainer,
                    'source_path' => 'test/file.jpg',
                    'destination_path' => 'moved/file.jpg',
                    'destination_container' => $destContainer,
                ]);

                $data = json_decode($response->toJsonResponse()->getContent(), true);

                // Should NOT be an authorization error — it should pass through
                // to the container existence check (resource_not_found) or succeed
                if (! $data['ok']) {
                    $this->assertNotSame(
                        'target_not_allowed',
                        $data['error']['code'] ?? null,
                        "Iteration {$i}: Both containers in allowlist should not produce authorization error"
                    );
                }
            } catch (ToolAuthorizationException $e) {
                $this->fail(
                    "Iteration {$i}: Both containers '{$sourceContainer}' and '{$destContainer}' "
                    . "are in allowlist [" . implode(', ', $allowlist) . "] but got ToolAuthorizationException: "
                    . $e->getMessage()
                );
            }
        }
    }

    /**
     * Property 12d: When the source container is in the allowlist,
     * ToolPolicy::targetAllowed() returns true for the source container.
     */
    #[Test]
    public function source_container_in_allowlist_is_allowed(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $sourceContainer = $this->randomContainerName();
            $extras = $this->randomSubset(self::CONTAINER_NAMES, random_int(0, 5));
            $allowlist = array_values(array_unique([$sourceContainer, ...$extras]));

            config(['ai_gateway.allowed_asset_containers' => $allowlist]);

            $policy = new ToolPolicy();

            $this->assertTrue(
                $policy->targetAllowed('asset', $sourceContainer),
                "Iteration {$i}: Source container '{$sourceContainer}' should be allowed "
                . "when in allowlist [" . implode(', ', $allowlist) . "]"
            );
        }
    }

    private function randomContainerName(): string
    {
        return self::CONTAINER_NAMES[array_rand(self::CONTAINER_NAMES)];
    }

    private function randomContainerNameExcluding(string $exclude): string
    {
        $candidates = array_values(array_filter(
            self::CONTAINER_NAMES,
            fn (string $name) => $name !== $exclude
        ));

        return $candidates[array_rand($candidates)];
    }

    /**
     * Generate an allowlist that definitely does NOT contain the given container.
     *
     * @return string[]
     */
    private function randomAllowlistExcluding(string $excluded): array
    {
        $candidates = array_values(array_filter(
            self::CONTAINER_NAMES,
            fn (string $name) => $name !== $excluded
        ));

        $count = random_int(0, min(5, count($candidates)));

        if ($count === 0) {
            return [];
        }

        $keys = array_rand($candidates, $count);
        if (! is_array($keys)) {
            $keys = [$keys];
        }

        return array_map(fn ($k) => $candidates[$k], $keys);
    }

    /**
     * @return string[]
     */
    private function randomSubset(array $items, int $count): array
    {
        if ($count === 0 || empty($items)) {
            return [];
        }

        $count = min($count, count($items));
        $keys = array_rand($items, $count);
        if (! is_array($keys)) {
            $keys = [$keys];
        }

        return array_map(fn ($k) => $items[$k], $keys);
    }
}
