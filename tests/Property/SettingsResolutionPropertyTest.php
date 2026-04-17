<?php

namespace Stokoe\AiGateway\Tests\Property;

use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\Attributes\Test;
use Stokoe\AiGateway\Support\SettingsRepository;
use Stokoe\AiGateway\Tests\TestCase;

/**
 * Property 2: Config Resolution Precedence (Req 4.1, 4.2)
 *
 * For any settings key, if the YAML file contains a value for that key,
 * the resolved config uses the YAML value. If the YAML file does not
 * contain a value, the resolved config uses the config/env default.
 *
 * **Validates: Requirements 4.1, 4.2**
 */
class SettingsResolutionPropertyTest extends TestCase
{
    use TestTrait;

    private string $tempFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempFile = sys_get_temp_dir() . '/ai-gw-resolve-' . uniqid() . '.yaml';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
        parent::tearDown();
    }

    #[Test]
    public function yaml_values_override_defaults_and_absent_keys_fall_back(): void
    {
        $this->forAll(
            Generators::choose(1, 1000),   // yaml execute rate
            Generators::choose(1, 1000),   // default execute rate
            Generators::choose(1, 1000),   // default capabilities rate
            Generators::bool(),            // yaml enabled
            Generators::choose(1024, 999999) // default max_request_size
        )
            ->withMaxSize(50)
            ->__invoke(function (
                int $yamlExecute,
                int $defaultExecute,
                int $defaultCapabilities,
                bool $yamlEnabled,
                int $defaultMaxSize,
            ): void {
                // Set config defaults
                config()->set('ai_gateway', [
                    'enabled' => ! $yamlEnabled,
                    'max_request_size' => $defaultMaxSize,
                    'rate_limits' => [
                        'execute' => $defaultExecute,
                        'capabilities' => $defaultCapabilities,
                    ],
                ]);

                $repo = new SettingsRepository($this->tempFile);

                // Write partial overrides — only enabled and rate_limits.execute
                $repo->write([
                    'enabled' => $yamlEnabled,
                    'rate_limits' => [
                        'execute' => $yamlExecute,
                    ],
                ]);

                $resolved = $repo->resolve();

                // YAML values win
                $this->assertSame($yamlEnabled, $resolved['enabled'], 'YAML enabled should override default');
                $this->assertSame($yamlExecute, $resolved['rate_limits']['execute'], 'YAML execute rate should override default');

                // Absent keys fall back to config defaults
                $this->assertSame($defaultMaxSize, $resolved['max_request_size'], 'max_request_size should fall back to default');
                $this->assertSame($defaultCapabilities, $resolved['rate_limits']['capabilities'], 'capabilities rate should fall back to default');

                // Cleanup for next iteration
                if (file_exists($this->tempFile)) {
                    unlink($this->tempFile);
                }
            });
    }
}
