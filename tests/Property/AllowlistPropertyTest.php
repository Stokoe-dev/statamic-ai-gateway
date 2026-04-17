<?php

namespace Stokoe\AiGateway\Tests\Property;

use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\Attributes\Test;
use Stokoe\AiGateway\Policies\ToolPolicy;
use Stokoe\AiGateway\Tests\TestCase;

/**
 * Feature: ai-gateway, Property 5: Allowlist enforcement
 *
 * For any target type (collection, global, navigation, taxonomy, cache target)
 * and for any target identifier string, the ToolPolicy allows the target if and only if
 * the identifier appears in the corresponding allowlist array in configuration.
 * All identifiers not in the allowlist are rejected.
 *
 * **Validates: Requirements 7.1, 7.2, 7.3, 7.4, 7.5, 7.6**
 */
class AllowlistPropertyTest extends TestCase
{
    use TestTrait;

    /**
     * Mapping from target type to config key for test setup.
     */
    private const CONFIG_MAP = [
        'entry'      => 'ai_gateway.allowed_collections',
        'global'     => 'ai_gateway.allowed_globals',
        'navigation' => 'ai_gateway.allowed_navigations',
        'taxonomy'   => 'ai_gateway.allowed_taxonomies',
        'cache'      => 'ai_gateway.allowed_cache_targets',
    ];

    /**
     * Property 5a: Targets in the allowlist are allowed.
     */
    #[Test]
    public function targets_in_allowlist_are_allowed(): void
    {
        $this->forAll(
            Generators::elements(array_keys(self::CONFIG_MAP)),
            Generators::suchThat(
                fn ($s) => is_string($s) && strlen($s) > 0 && strlen($s) <= 30,
                Generators::string()
            ),
        )
            ->withMaxSize(50)
            ->__invoke(function (string $targetType, string $target): void {
                $configKey = self::CONFIG_MAP[$targetType];
                config([$configKey => [$target, 'other_target']]);

                $policy = new ToolPolicy();

                $this->assertTrue(
                    $policy->targetAllowed($targetType, $target),
                    "Target '{$target}' should be allowed for type '{$targetType}'"
                );
            });
    }

    /**
     * Property 5b: Targets not in the allowlist are denied.
     */
    #[Test]
    public function targets_not_in_allowlist_are_denied(): void
    {
        $this->forAll(
            Generators::elements(array_keys(self::CONFIG_MAP)),
            Generators::suchThat(
                fn ($s) => is_string($s) && strlen($s) > 0 && strlen($s) <= 30,
                Generators::string()
            ),
        )
            ->withMaxSize(50)
            ->__invoke(function (string $targetType, string $target): void {
                $configKey = self::CONFIG_MAP[$targetType];
                // Set allowlist to something that definitely doesn't contain $target
                config([$configKey => ['__definitely_not_' . $target]]);

                $policy = new ToolPolicy();

                $this->assertFalse(
                    $policy->targetAllowed($targetType, $target),
                    "Target '{$target}' should be denied for type '{$targetType}'"
                );
            });
    }

    /**
     * Property 5c: Empty allowlist denies all targets.
     */
    #[Test]
    public function empty_allowlist_denies_all_targets(): void
    {
        $this->forAll(
            Generators::elements(array_keys(self::CONFIG_MAP)),
            Generators::suchThat(
                fn ($s) => is_string($s) && strlen($s) > 0,
                Generators::string()
            ),
        )
            ->withMaxSize(50)
            ->__invoke(function (string $targetType, string $target): void {
                $configKey = self::CONFIG_MAP[$targetType];
                config([$configKey => []]);

                $policy = new ToolPolicy();

                $this->assertFalse(
                    $policy->targetAllowed($targetType, $target),
                    "Target '{$target}' should be denied when allowlist is empty"
                );
            });
    }
}
