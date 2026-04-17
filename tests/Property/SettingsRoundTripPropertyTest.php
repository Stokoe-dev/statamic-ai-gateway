<?php

namespace Stokoe\AiGateway\Tests\Property;

use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Stokoe\AiGateway\Support\SettingsRepository;

/**
 * Property 1: Settings Round-Trip (Req 3.1)
 *
 * For any valid settings array, writing to the YAML file and reading back
 * produces an equivalent array.
 *
 * **Validates: Requirements 3.1**
 */
class SettingsRoundTripPropertyTest extends TestCase
{
    use TestTrait;

    private string $tempFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempFile = sys_get_temp_dir() . '/ai-gw-roundtrip-' . uniqid() . '.yaml';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
        parent::tearDown();
    }

    #[Test]
    public function write_then_read_produces_same_array(): void
    {
        $this->forAll(
            Generators::associative([
                'enabled' => Generators::bool(),
                'token' => Generators::suchThat(
                    fn ($s) => is_string($s) && strlen($s) >= 1 && strlen($s) <= 100,
                    Generators::string()
                ),
                'max_request_size' => Generators::choose(1024, 1000000),
                'rate_limits' => Generators::associative([
                    'execute' => Generators::choose(1, 1000),
                    'capabilities' => Generators::choose(1, 1000),
                ]),
            ])
        )
            ->withMaxSize(50)
            ->__invoke(function (array $settings): void {
                $repo = new SettingsRepository($this->tempFile);

                $repo->write($settings);
                $result = $repo->read();

                $this->assertSame($settings, $result);
            });
    }
}
