<?php

namespace Stokoe\AiGateway\Tests\Integration;

use Statamic\Facades\User;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;
use Stokoe\AiGateway\Support\SettingsRepository;
use Stokoe\AiGateway\Tests\TestCase;

class SettingsControllerTest extends TestCase
{
    use PreventsSavingStacheItemsToDisk;

    private string $tempFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempFile = sys_get_temp_dir() . '/ai-gw-test-' . uniqid() . '.yaml';
        $this->app->singleton(SettingsRepository::class, function () {
            return new SettingsRepository($this->tempFile);
        });
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }

        parent::tearDown();
    }

    private function superAdmin(): \Statamic\Contracts\Auth\User
    {
        $user = User::make()
            ->id('super-admin-ctrl')
            ->email('super-ctrl@example.com')
            ->makeSuper();

        $user->save();

        return $user;
    }

    private function validSettings(): array
    {
        return [
            'enabled' => true,
            'token' => 'test-token-value',
            'rate_limits' => ['execute' => 50, 'capabilities' => 100],
            'max_request_size' => 65536,
            'tools' => [
                'entry' => ['create' => true, 'update' => false, 'upsert' => false, 'get' => false, 'list' => false],
                'global' => ['get' => false, 'update' => false],
                'navigation' => ['get' => false, 'update' => false],
                'term' => ['get' => false, 'list' => false, 'upsert' => false],
                'cache' => ['clear' => false],
            ],
            'allowed_collections' => ['pages'],
            'allowed_globals' => [],
            'allowed_navigations' => [],
            'allowed_taxonomies' => [],
            'allowed_cache_targets' => ['application'],
            'denied_fields' => [
                'entry' => [],
                'global' => [],
                'term' => [],
            ],
            'confirmation' => ['ttl' => 60],
            'audit' => ['channel' => ''],
        ];
    }

    public function test_successful_save_persists_to_yaml(): void
    {
        $admin = $this->superAdmin();
        $settings = $this->validSettings();

        $this->actingAs($admin, 'web')
            ->post(cp_route('ai-gateway.settings.update'), $settings)
            ->assertRedirect();

        $repo = app(SettingsRepository::class);
        $saved = $repo->read();

        $this->assertTrue($saved['enabled']);
        $this->assertSame('test-token-value', $saved['token']);
        $this->assertSame(50, $saved['rate_limits']['execute']);
        $this->assertSame(100, $saved['rate_limits']['capabilities']);
    }

    public function test_save_with_valid_data_returns_success_flash(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin, 'web')
            ->post(cp_route('ai-gateway.settings.update'), $this->validSettings())
            ->assertSessionHas('success');
    }

    public function test_validation_rejects_non_positive_execute_rate_limit(): void
    {
        $admin = $this->superAdmin();
        $settings = $this->validSettings();
        $settings['rate_limits']['execute'] = 0;

        $this->actingAs($admin, 'web')
            ->post(cp_route('ai-gateway.settings.update'), $settings)
            ->assertSessionHasErrors('rate_limits.execute');
    }

    public function test_validation_rejects_negative_capabilities_rate_limit(): void
    {
        $admin = $this->superAdmin();
        $settings = $this->validSettings();
        $settings['rate_limits']['capabilities'] = -5;

        $this->actingAs($admin, 'web')
            ->post(cp_route('ai-gateway.settings.update'), $settings)
            ->assertSessionHasErrors('rate_limits.capabilities');
    }

    public function test_validation_rejects_max_request_size_below_1024(): void
    {
        $admin = $this->superAdmin();
        $settings = $this->validSettings();
        $settings['max_request_size'] = 512;

        $this->actingAs($admin, 'web')
            ->post(cp_route('ai-gateway.settings.update'), $settings)
            ->assertSessionHasErrors('max_request_size');
    }

    public function test_validation_rejects_non_positive_ttl(): void
    {
        $admin = $this->superAdmin();
        $settings = $this->validSettings();
        $settings['confirmation']['ttl'] = 0;

        $this->actingAs($admin, 'web')
            ->post(cp_route('ai-gateway.settings.update'), $settings)
            ->assertSessionHasErrors('confirmation.ttl');
    }

    public function test_validation_rejects_empty_allowlist_entries(): void
    {
        $admin = $this->superAdmin();
        $settings = $this->validSettings();
        $settings['allowed_collections'] = [''];

        $this->actingAs($admin, 'web')
            ->post(cp_route('ai-gateway.settings.update'), $settings)
            ->assertSessionHasErrors('allowed_collections.0');
    }

    public function test_saving_then_loading_shows_saved_values(): void
    {
        $admin = $this->superAdmin();
        $settings = $this->validSettings();
        $settings['rate_limits']['execute'] = 77;
        $settings['rate_limits']['capabilities'] = 88;
        $settings['max_request_size'] = 2048;
        $settings['allowed_collections'] = ['blog', 'pages'];

        // Save settings
        $this->actingAs($admin, 'web')
            ->post(cp_route('ai-gateway.settings.update'), $settings)
            ->assertSessionHas('success');

        // Load the settings page and check view data
        $response = $this->actingAs($admin, 'web')
            ->get(cp_route('ai-gateway.settings.index'));

        $response->assertStatus(200);

        $viewData = $response->viewData('settings');

        $this->assertSame(77, $viewData['rate_limits']['execute']);
        $this->assertSame(88, $viewData['rate_limits']['capabilities']);
        $this->assertSame(2048, $viewData['max_request_size']);
        $this->assertSame(['blog', 'pages'], $viewData['allowed_collections']);
    }

    public function test_cp_settings_override_env_config_defaults(): void
    {
        // Set config defaults
        config()->set('ai_gateway.rate_limits.execute', 30);
        config()->set('ai_gateway.rate_limits.capabilities', 60);
        config()->set('ai_gateway.max_request_size', 65536);

        $admin = $this->superAdmin();
        $settings = $this->validSettings();
        $settings['rate_limits']['execute'] = 99;
        $settings['max_request_size'] = 4096;

        // Save via CP
        $this->actingAs($admin, 'web')
            ->post(cp_route('ai-gateway.settings.update'), $settings)
            ->assertSessionHas('success');

        // Verify config was updated with CP values
        $this->assertSame(99, config('ai_gateway.rate_limits.execute'));
        $this->assertSame(4096, config('ai_gateway.max_request_size'));
    }
}
