<?php

namespace Stokoe\AiGateway\Tests\Property;

use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Stokoe\AiGateway\Support\SettingsRepository;

/**
 * Property 3: Token Generation Format (Req 6.3)
 *
 * Every generated token is exactly 64 characters long and contains
 * only hexadecimal characters (0-9, a-f).
 *
 * **Validates: Requirements 6.3**
 */
class TokenGenerationPropertyTest extends TestCase
{
    use TestTrait;

    #[Test]
    public function generated_tokens_are_always_64_hex_chars(): void
    {
        // Use a dummy generator to drive 100+ iterations
        $this->forAll(
            Generators::choose(0, 999999)
        )
            ->withMaxSize(50)
            ->__invoke(function (int $_seed): void {
                $token = SettingsRepository::generateToken();

                $this->assertSame(64, strlen($token), 'Token must be exactly 64 characters');
                $this->assertMatchesRegularExpression(
                    '/^[0-9a-f]{64}$/',
                    $token,
                    'Token must contain only hex characters'
                );
            });
    }
}
