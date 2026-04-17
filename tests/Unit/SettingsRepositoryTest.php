<?php

namespace Stokoe\AiGateway\Tests\Unit;

use Stokoe\AiGateway\Support\SettingsRepository;
use Stokoe\AiGateway\Tests\TestCase;

class SettingsRepositoryTest extends TestCase
{
    private string $tempFile;

    private SettingsRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempFile = sys_get_temp_dir() . '/ai-gateway-test-' . uniqid() . '.yaml';
        $this->repo = new SettingsRepository($this->tempFile);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }

        parent::tearDown();
    }

    public function test_path_returns_configured_path(): void
    {
        $this->assertSame($this->tempFile, $this->repo->path());
    }

    public function test_path_defaults_to_storage_path(): void
    {
        $repo = new SettingsRepository();
        $this->assertSame(storage_path('statamic/addons/ai-gateway/settings.yaml'), $repo->path());
    }

    public function test_read_returns_empty_array_when_file_missing(): void
    {
        $this->assertSame([], $this->repo->read());
    }

    public function test_write_creates_file_and_directories(): void
    {
        $nested = sys_get_temp_dir() . '/ai-gw-test-' . uniqid() . '/sub/settings.yaml';
        $repo = new SettingsRepository($nested);

        $repo->write(['enabled' => true]);

        $this->assertFileExists($nested);

        // Cleanup
        unlink($nested);
        rmdir(dirname($nested));
        rmdir(dirname($nested, 2));
    }

    public function test_write_then_read_round_trips(): void
    {
        $settings = [
            'enabled' => true,
            'token' => 'abc123',
            'rate_limits' => ['execute' => 50, 'capabilities' => 100],
        ];

        $this->repo->write($settings);
        $result = $this->repo->read();

        $this->assertSame($settings, $result);
    }

    public function test_read_returns_empty_array_for_empty_file(): void
    {
        file_put_contents($this->tempFile, '');
        $this->assertSame([], $this->repo->read());
    }

    public function test_resolve_uses_config_defaults_when_no_file(): void
    {
        config()->set('ai_gateway', [
            'enabled' => false,
            'token' => 'env-token',
        ]);

        $resolved = $this->repo->resolve();

        $this->assertFalse($resolved['enabled']);
        $this->assertSame('env-token', $resolved['token']);
    }

    public function test_resolve_yaml_overrides_config_defaults(): void
    {
        config()->set('ai_gateway', [
            'enabled' => false,
            'token' => 'env-token',
            'max_request_size' => 65536,
        ]);

        $this->repo->write([
            'enabled' => true,
            'token' => 'yaml-token',
        ]);

        $resolved = $this->repo->resolve();

        $this->assertTrue($resolved['enabled']);
        $this->assertSame('yaml-token', $resolved['token']);
        $this->assertSame(65536, $resolved['max_request_size']);
    }

    public function test_resolve_merges_nested_arrays_recursively(): void
    {
        config()->set('ai_gateway', [
            'rate_limits' => ['execute' => 30, 'capabilities' => 60],
        ]);

        $this->repo->write([
            'rate_limits' => ['execute' => 100],
        ]);

        $resolved = $this->repo->resolve();

        $this->assertSame(100, $resolved['rate_limits']['execute']);
        $this->assertSame(60, $resolved['rate_limits']['capabilities']);
    }

    public function test_apply_to_config_sets_resolved_values(): void
    {
        config()->set('ai_gateway', [
            'enabled' => false,
            'token' => 'old',
        ]);

        $this->repo->write(['enabled' => true, 'token' => 'new']);
        $this->repo->applyToConfig();

        $this->assertTrue(config('ai_gateway.enabled'));
        $this->assertSame('new', config('ai_gateway.token'));
    }

    public function test_mask_token_null_returns_empty_string(): void
    {
        $this->assertSame('', SettingsRepository::maskToken(null));
    }

    public function test_mask_token_empty_returns_empty_string(): void
    {
        $this->assertSame('', SettingsRepository::maskToken(''));
    }

    public function test_mask_token_short_masks_everything(): void
    {
        $this->assertSame('•••', SettingsRepository::maskToken('abc'));
        $this->assertSame('••••', SettingsRepository::maskToken('abcd'));
    }

    public function test_mask_token_reveals_last_four(): void
    {
        $this->assertSame('••••5678', SettingsRepository::maskToken('12345678'));
    }

    public function test_mask_token_preserves_length(): void
    {
        $token = str_repeat('a', 64);
        $masked = SettingsRepository::maskToken($token);

        $this->assertSame(64, mb_strlen($masked));
        $this->assertSame('aaaa', mb_substr($masked, -4));
    }

    public function test_generate_token_is_64_hex_chars(): void
    {
        $token = SettingsRepository::generateToken();

        $this->assertSame(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    public function test_generate_token_produces_unique_values(): void
    {
        $a = SettingsRepository::generateToken();
        $b = SettingsRepository::generateToken();

        $this->assertNotSame($a, $b);
    }
}
