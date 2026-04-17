<?php

namespace Stokoe\AiGateway\Tests\Property;

use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\Attributes\Test;
use Stokoe\AiGateway\Support\ConfirmationTokenManager;
use Stokoe\AiGateway\Tests\TestCase;

/**
 * Feature: ai-gateway, Property 9: Confirmation token binding
 *
 * For any two distinct (tool, arguments) pairs, a confirmation token generated
 * for one pair does not validate for the other pair.
 *
 * **Validates: Requirements 16.5**
 */
class ConfirmationTokenBindingPropertyTest extends TestCase
{
    use TestTrait;

    /**
     * Property 9: Token for one (tool, arguments) pair does not validate for a different pair.
     */
    #[Test]
    public function token_does_not_validate_for_different_tool_or_arguments(): void
    {
        $toolGen = Generators::suchThat(
            fn ($s) => is_string($s) && strlen($s) > 0 && strlen($s) <= 30,
            Generators::string()
        );

        $this->forAll(
            $toolGen,
            $toolGen,
            Generators::associative([
                'slug' => Generators::string(),
                'site' => Generators::elements(['default', 'en']),
            ]),
            Generators::associative([
                'slug' => Generators::string(),
                'site' => Generators::elements(['fr', 'de']),
            ]),
        )
            ->withMaxSize(50)
            ->__invoke(function (string $tool1, string $tool2, array $args1, array $args2): void {
                // Ensure the two pairs are actually distinct
                if ($tool1 === $tool2 && $args1 === $args2) {
                    return; // Skip identical pairs
                }

                config(['ai_gateway.confirmation.ttl' => 60]);

                $manager = new ConfirmationTokenManager();

                $result = $manager->generate($tool1, $args1);

                // Token generated for (tool1, args1) should NOT validate for (tool2, args2)
                $valid = $manager->validate($result['token'], $tool2, $args2);

                $this->assertFalse(
                    $valid,
                    "Token for ({$tool1}) should not validate for ({$tool2})"
                );
            });
    }
}
