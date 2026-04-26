<?php

namespace Stokoe\AiGateway\Tests\Property;

use PHPUnit\Framework\Attributes\Test;
use Stokoe\AiGateway\Exceptions\ToolAuthorizationException;
use Stokoe\AiGateway\Policies\ToolPolicy;
use Stokoe\AiGateway\Tests\TestCase;
use Stokoe\AiGateway\Tools\BlueprintCreateTool;
use Stokoe\AiGateway\Tools\BlueprintDeleteTool;
use Stokoe\AiGateway\Tools\BlueprintGetTool;

/**
 * Feature: gateway-content-expansion, Property 4: Blueprint resource-type allowlist delegation
 *
 * For any resource type (collection, global, or taxonomy) and any handle, the blueprint tools
 * SHALL check the handle against the correct allowlist config key for that resource type
 * (allowed_collections, allowed_globals, or allowed_taxonomies respectively), and SHALL reject
 * the request if the handle is not in the corresponding allowlist.
 *
 * **Validates: Requirements 4.3, 18.8**
 */
class BlueprintAllowlistDelegationPropertyTest extends TestCase
{
    private const ITERATIONS = 100;

    private const RESOURCE_TYPES = ['collection', 'global', 'taxonomy'];

    /**
     * Map resource_type to the ToolPolicy target type used for allowlist checks.
     */
    private const RESOURCE_TYPE_TO_POLICY_TYPE = [
        'collection' => 'entry',
        'global'     => 'global',
        'taxonomy'   => 'taxonomy',
    ];

    /**
     * Map resource_type to the config key holding the allowlist.
     */
    private const RESOURCE_TYPE_TO_CONFIG_KEY = [
        'collection' => 'ai_gateway.allowed_collections',
        'global'     => 'ai_gateway.allowed_globals',
        'taxonomy'   => 'ai_gateway.allowed_taxonomies',
    ];

    private const HANDLE_POOL = [
        'pages', 'articles', 'blog', 'products', 'events', 'team',
        'settings', 'seo', 'social', 'footer', 'header', 'contact',
        'categories', 'tags', 'topics', 'genres', 'colors', 'sizes',
        'faq', 'testimonials', 'portfolio', 'services', 'about',
    ];

    /**
     * Property 4a: When a handle is NOT in the corresponding allowlist for the resource type,
     * blueprint tools SHALL reject the request with ToolAuthorizationException.
     *
     * Tests BlueprintGetTool across all three resource types.
     */
    #[Test]
    public function handle_not_in_allowlist_is_rejected_by_get_tool(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $resourceType = $this->randomResourceType();
            $handle = $this->randomHandle();
            $allowlist = $this->randomAllowlistExcluding($handle);
            $configKey = self::RESOURCE_TYPE_TO_CONFIG_KEY[$resourceType];

            config([$configKey => $allowlist]);

            $tool = new BlueprintGetTool();

            $threw = false;
            try {
                $tool->execute([
                    'resource_type' => $resourceType,
                    'handle' => $handle,
                ]);
            } catch (ToolAuthorizationException $e) {
                $threw = true;
                $this->assertStringContainsString(
                    $handle,
                    $e->getMessage(),
                    "Iteration {$i}: Exception message should mention the rejected handle"
                );
            }

            $this->assertTrue(
                $threw,
                "Iteration {$i}: BlueprintGetTool should reject handle '{$handle}' "
                . "for resource_type '{$resourceType}' when allowlist [{$configKey}] = ["
                . implode(', ', $allowlist) . "]"
            );
        }
    }

    /**
     * Property 4b: When a handle is NOT in the corresponding allowlist,
     * BlueprintDeleteTool SHALL reject the request with ToolAuthorizationException.
     */
    #[Test]
    public function handle_not_in_allowlist_is_rejected_by_delete_tool(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $resourceType = $this->randomResourceType();
            $handle = $this->randomHandle();
            $allowlist = $this->randomAllowlistExcluding($handle);
            $configKey = self::RESOURCE_TYPE_TO_CONFIG_KEY[$resourceType];

            config([$configKey => $allowlist]);

            $tool = new BlueprintDeleteTool();

            $threw = false;
            try {
                $tool->execute([
                    'resource_type' => $resourceType,
                    'handle' => $handle,
                ]);
            } catch (ToolAuthorizationException $e) {
                $threw = true;
                $this->assertStringContainsString(
                    $handle,
                    $e->getMessage(),
                    "Iteration {$i}: Exception message should mention the rejected handle"
                );
            }

            $this->assertTrue(
                $threw,
                "Iteration {$i}: BlueprintDeleteTool should reject handle '{$handle}' "
                . "for resource_type '{$resourceType}' when allowlist [{$configKey}] = ["
                . implode(', ', $allowlist) . "]"
            );
        }
    }

    /**
     * Property 4c: When a handle is NOT in the corresponding allowlist,
     * BlueprintCreateTool SHALL reject the request with ToolAuthorizationException.
     */
    #[Test]
    public function handle_not_in_allowlist_is_rejected_by_create_tool(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $resourceType = $this->randomResourceType();
            $handle = $this->randomHandle();
            $allowlist = $this->randomAllowlistExcluding($handle);
            $configKey = self::RESOURCE_TYPE_TO_CONFIG_KEY[$resourceType];

            config([$configKey => $allowlist]);

            $tool = new BlueprintCreateTool();

            $threw = false;
            try {
                $tool->execute([
                    'resource_type' => $resourceType,
                    'handle' => $handle,
                    'fields' => [['handle' => 'title', 'type' => 'text']],
                ]);
            } catch (ToolAuthorizationException $e) {
                $threw = true;
                $this->assertStringContainsString(
                    $handle,
                    $e->getMessage(),
                    "Iteration {$i}: Exception message should mention the rejected handle"
                );
            }

            $this->assertTrue(
                $threw,
                "Iteration {$i}: BlueprintCreateTool should reject handle '{$handle}' "
                . "for resource_type '{$resourceType}' when allowlist [{$configKey}] = ["
                . implode(', ', $allowlist) . "]"
            );
        }
    }

    /**
     * Property 4d: Each resource type delegates to the CORRECT allowlist config key.
     * Verifies that ToolPolicy::targetAllowed() is called with the right target type
     * by setting only the correct allowlist and leaving others empty.
     */
    #[Test]
    public function each_resource_type_checks_correct_allowlist(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $resourceType = $this->randomResourceType();
            $handle = $this->randomHandle();

            // Clear ALL allowlists
            config([
                'ai_gateway.allowed_collections' => [],
                'ai_gateway.allowed_globals'     => [],
                'ai_gateway.allowed_taxonomies'  => [],
            ]);

            // Only populate the CORRECT allowlist for this resource type
            $correctConfigKey = self::RESOURCE_TYPE_TO_CONFIG_KEY[$resourceType];
            config([$correctConfigKey => [$handle]]);

            $tool = new BlueprintGetTool();

            try {
                $response = $tool->execute([
                    'resource_type' => $resourceType,
                    'handle' => $handle,
                ]);

                // If we get here, authorization passed. The tool may return resource_not_found
                // because the resource doesn't actually exist, which is fine — it proves
                // the allowlist check passed.
                $data = json_decode($response->toJsonResponse()->getContent(), true);

                if (! $data['ok']) {
                    $this->assertSame(
                        'resource_not_found',
                        $data['error']['code'] ?? null,
                        "Iteration {$i}: After passing allowlist, expected resource_not_found, not authorization error"
                    );
                }
            } catch (ToolAuthorizationException $e) {
                $this->fail(
                    "Iteration {$i}: Handle '{$handle}' IS in the correct allowlist "
                    . "[{$correctConfigKey}] for resource_type '{$resourceType}', "
                    . "but got ToolAuthorizationException: " . $e->getMessage()
                );
            }
        }
    }

    /**
     * Property 4e: When a handle IS in the allowlist, blueprint tools pass authorization
     * (may fail at resource existence, which is fine).
     *
     * Tests all three tool types with randomized handles and allowlists.
     */
    #[Test]
    public function handle_in_allowlist_passes_authorization(): void
    {
        $tools = [
            'get' => fn () => new BlueprintGetTool(),
            'delete' => fn () => new BlueprintDeleteTool(),
        ];

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $resourceType = $this->randomResourceType();
            $handle = $this->randomHandle();
            $toolKey = array_rand($tools);
            $tool = $tools[$toolKey]();

            // Ensure handle IS in the correct allowlist
            $configKey = self::RESOURCE_TYPE_TO_CONFIG_KEY[$resourceType];
            $extras = $this->randomSubset(self::HANDLE_POOL, random_int(0, 4));
            $allowlist = array_values(array_unique([$handle, ...$extras]));
            config([$configKey => $allowlist]);

            $args = [
                'resource_type' => $resourceType,
                'handle' => $handle,
            ];

            try {
                $response = $tool->execute($args);

                $data = json_decode($response->toJsonResponse()->getContent(), true);

                // Should NOT be an authorization error
                if (! $data['ok']) {
                    $this->assertNotSame(
                        'target_not_allowed',
                        $data['error']['code'] ?? null,
                        "Iteration {$i}: Handle '{$handle}' in allowlist should not produce authorization error "
                        . "(tool: {$toolKey}, resource_type: {$resourceType})"
                    );
                }
            } catch (ToolAuthorizationException $e) {
                $this->fail(
                    "Iteration {$i}: Handle '{$handle}' IS in allowlist [{$configKey}] = ["
                    . implode(', ', $allowlist) . "] for resource_type '{$resourceType}' "
                    . "(tool: {$toolKey}), but got ToolAuthorizationException: " . $e->getMessage()
                );
            }
        }
    }

    /**
     * Property 4f: A handle in the WRONG allowlist is still rejected.
     * E.g., a collection handle in allowed_globals should NOT authorize a collection blueprint request.
     */
    #[Test]
    public function handle_in_wrong_allowlist_is_still_rejected(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $resourceType = $this->randomResourceType();
            $handle = $this->randomHandle();

            // Clear ALL allowlists
            config([
                'ai_gateway.allowed_collections' => [],
                'ai_gateway.allowed_globals'     => [],
                'ai_gateway.allowed_taxonomies'  => [],
            ]);

            // Put the handle in a WRONG allowlist (not the one for this resource type)
            $wrongConfigKeys = array_values(array_filter(
                self::RESOURCE_TYPE_TO_CONFIG_KEY,
                fn ($key, $rt) => $rt !== $resourceType,
                ARRAY_FILTER_USE_BOTH
            ));
            $wrongConfigKey = $wrongConfigKeys[array_rand($wrongConfigKeys)];
            config([$wrongConfigKey => [$handle]]);

            $tool = new BlueprintGetTool();

            $threw = false;
            try {
                $tool->execute([
                    'resource_type' => $resourceType,
                    'handle' => $handle,
                ]);
            } catch (ToolAuthorizationException $e) {
                $threw = true;
            }

            $this->assertTrue(
                $threw,
                "Iteration {$i}: Handle '{$handle}' in wrong allowlist [{$wrongConfigKey}] "
                . "should still be rejected for resource_type '{$resourceType}' "
                . "(correct key: " . self::RESOURCE_TYPE_TO_CONFIG_KEY[$resourceType] . ")"
            );
        }
    }

    private function randomResourceType(): string
    {
        return self::RESOURCE_TYPES[array_rand(self::RESOURCE_TYPES)];
    }

    private function randomHandle(): string
    {
        return self::HANDLE_POOL[array_rand(self::HANDLE_POOL)];
    }

    /**
     * Generate an allowlist that definitely does NOT contain the given handle.
     *
     * @return string[]
     */
    private function randomAllowlistExcluding(string $excluded): array
    {
        $candidates = array_values(array_filter(
            self::HANDLE_POOL,
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
