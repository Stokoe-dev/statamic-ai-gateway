<?php

namespace Stokoe\AiGateway\Tests\Property;

use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\Attributes\Test;
use Stokoe\AiGateway\Support\ConfirmationTokenManager;
use Stokoe\AiGateway\Tests\TestCase;

/**
 * Feature: ai-gateway, Property 8: Confirmation token round-trip
 *
 * For any tool name and arguments, generating a confirmation token and immediately
 * validating it with the same tool name and arguments succeeds (token is valid and unexpired).
 *
 * **Validates: Requirements 16.3, 16.6**
 */
class ConfirmationTokenRoundTripPropertyTest extends TestCase
{
    use TestTrait;

    /**
     * Property 8: Generate then validate with same inputs always succeeds.
     */
    #[Test]
    public function generate_then_validate_succeeds_immediately(): void
    {
        $this->forAll(
            Generators::suchThat(
                fn ($s) => is_string($s) && strlen($s) > 0 && strlen($s) <= 50,
                Generators::string()
            ),
            Generators::associative([
                'collection' => Generators::string(),
                'slug'       => Generators::string(),
                'site'       => Generators::elements(['default', 'en', 'fr']),
            ]),
        )
            ->withMaxSize(50)
            ->__invoke(function (string $tool, array $arguments): void {
                config(['ai_gateway.confirmation.ttl' => 60]);

                $manager = new ConfirmationTokenManager();

                $result = $manager->generate($tool, $arguments);

                $this->assertArrayHasKey('token', $result);
                $this->assertArrayHasKey('expires_at', $result);
                $this->assertIsString($result['token']);
                $this->assertIsString($result['expires_at']);

                // Immediate validation should succeed
                $valid = $manager->validate($result['token'], $tool, $arguments);

                $this->assertTrue($valid, 'Token should validate immediately after generation');
            });
    }
}
