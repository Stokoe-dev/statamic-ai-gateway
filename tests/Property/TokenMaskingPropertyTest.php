<?php

namespace Stokoe\AiGateway\Tests\Property;

use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Stokoe\AiGateway\Support\SettingsRepository;

/**
 * Property 4: Token Masking (Req 6.1)
 *
 * For any token string of length >= 4, the masked output has the same
 * character length as the input, the last 4 characters match the original,
 * and all preceding characters are mask characters. For tokens shorter
 * than 4 characters, the entire output is masked.
 *
 * **Validates: Requirements 6.1**
 */
class TokenMaskingPropertyTest extends TestCase
{
    use TestTrait;

    #[Test]
    public function masking_preserves_length_and_reveals_only_last_4_chars(): void
    {
        // Generate ASCII-only strings of length 1–200 to avoid multi-byte issues
        $this->forAll(
            Generators::suchThat(
                fn ($s) => is_string($s) && strlen($s) >= 1 && strlen($s) <= 200 && mb_check_encoding($s, 'ASCII'),
                Generators::string()
            )
        )
            ->withMaxSize(50)
            ->__invoke(function (string $token): void {
                $masked = SettingsRepository::maskToken($token);
                $len = strlen($token);

                // Character-length preservation (using mb_strlen since mask char is multi-byte)
                $this->assertSame($len, mb_strlen($masked), 'Masked output must have same character count as input');

                if ($len <= 4) {
                    // Everything masked
                    $this->assertSame(
                        str_repeat('•', $len),
                        $masked,
                        'Tokens <= 4 chars must be fully masked'
                    );
                } else {
                    // Last 4 chars revealed
                    $this->assertSame(
                        substr($token, -4),
                        mb_substr($masked, -4),
                        'Last 4 characters must match original'
                    );

                    // Prefix is all mask characters
                    $prefix = mb_substr($masked, 0, $len - 4);
                    $this->assertSame(
                        str_repeat('•', $len - 4),
                        $prefix,
                        'All characters except last 4 must be mask characters'
                    );
                }
            });
    }
}
