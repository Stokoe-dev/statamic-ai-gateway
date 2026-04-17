<?php

namespace Stokoe\AiGateway\Tests\Property;

use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\User;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;
use Stokoe\AiGateway\Support\SettingsRepository;
use Stokoe\AiGateway\Tests\TestCase;

/**
 * Property 5: Validation Rejects Invalid Numeric Inputs (Req 14.3, 14.5)
 * Property 6: Allowlist Entries Must Be Non-Empty Strings (Req 14.6)
 *
 * **Validates: Requirements 14.3, 14.4, 14.5, 14.6**
 */
class SettingsValidationPropertyTest extends TestCase
{
    use TestTrait;
    use PreventsSavingStacheItemsToDisk;

    private string $tempFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempFile = sys_get_temp_dir() . '/ai-gw-validation-' . uniqid() . '.yaml';
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
            ->id('super-prop-' . uniqid())
            ->email('super-prop-' . uniqid() . '@example.com')
            ->makeSuper();

        $user->save();

        return $user;
    }

    private function validSettings(): array
    {
        return [
            'enabled' => true,
            'token' => 'test-token',
            'rate_limits' => ['execute' => 30, 'capabilities' => 60],
            'max_request_size' => 65536,
            'tools' => [
                'entry' => ['create' => false, 'update' => false, 'upsert' => false, 'get' => false, 'list' => false],
                'global' => ['get' => false, 'update' => false],
                'navigation' => ['get' => false, 'update' => false],
                'term' => ['get' => false, 'list' => false, 'upsert' => false],
                'cache' => ['clear' => false],
            ],
            'allowed_collections' => [],
            'allowed_globals' => [],
            'allowed_navigations' => [],
            'allowed_taxonomies' => [],
            'allowed_cache_targets' => [],
            'denied_fields' => ['entry' => [], 'global' => [], 'term' => []],
            'confirmation' => ['ttl' => 60],
            'audit' => ['channel' => ''],
        ];
    }

    /**
     * Property 5: For any non-positive-integer rate limit or TTL value,
     * validation rejects the submission.
     *
     * **Validates: Requirements 14.3, 14.5**
     */
    #[Test]
    public function non_positive_rate_limit_or_ttl_is_rejected(): void
    {
        $admin = $this->superAdmin();

        // Generate non-positive integers (0 and negatives)
        $this->forAll(
            Generators::choose(-1000, 0),
            Generators::elements('rate_limits.execute', 'rate_limits.capabilities', 'confirmation.ttl')
        )
            ->withMaxSize(50)
            ->__invoke(function (int $invalidValue, string $field) use ($admin): void {
                $settings = $this->validSettings();

                // Set the invalid value at the correct nested path
                $parts = explode('.', $field);
                if (count($parts) === 2) {
                    $settings[$parts[0]][$parts[1]] = $invalidValue;
                }

                $response = $this->actingAs($admin, 'web')
                    ->post(cp_route('ai-gateway.settings.update'), $settings);

                $response->assertSessionHasErrors($field);
            });
    }

    /**
     * Property 6: For any allowlist containing empty strings,
     * validation rejects the submission.
     *
     * **Validates: Requirements 14.6**
     */
    #[Test]
    public function allowlist_with_empty_strings_is_rejected(): void
    {
        $admin = $this->superAdmin();

        $this->forAll(
            Generators::elements(
                'allowed_collections',
                'allowed_globals',
                'allowed_navigations',
                'allowed_taxonomies'
            )
        )
            ->withMaxSize(50)
            ->__invoke(function (string $field) use ($admin): void {
                $settings = $this->validSettings();

                // Insert an empty string into the allowlist
                $settings[$field] = ['valid-item', ''];

                $response = $this->actingAs($admin, 'web')
                    ->post(cp_route('ai-gateway.settings.update'), $settings);

                $response->assertSessionHasErrors("{$field}.1");
            });
    }
}
