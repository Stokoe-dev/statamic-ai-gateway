<?php

namespace Stokoe\AiGateway\Tests\Property;

use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\User;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;
use Stokoe\AiGateway\Support\SettingsRepository;
use Stokoe\AiGateway\Tests\TestCase;

/**
 * Feature: gateway-content-expansion, Property 9: Custom command alias validation
 *
 * For any set of custom command definitions, the SettingsController validation SHALL
 * reject the set if any alias is empty, any alias is not kebab-case, any artisan
 * command string is empty, or any two commands share the same alias; and SHALL accept
 * the set if all aliases are non-empty kebab-case strings, all command strings are
 * non-empty, and all aliases are unique.
 *
 * **Validates: Requirements 11.3**
 */
class CustomCommandAliasValidationPropertyTest extends TestCase
{
    use PreventsSavingStacheItemsToDisk;

    private string $tempFile;

    private const VALID_ALIASES = [
        'rebuild-search', 'clear-cache', 'warm-stache', 'sync-assets',
        'flush-queue', 'run-migrations', 'seed-data', 'deploy-static',
        'prune-revisions', 'reindex', 'backup-db', 'restore-db',
        'generate-sitemap', 'optimize-images', 'compile-assets',
        'refresh-tokens', 'purge-logs', 'update-index', 'check-health',
        'run-scheduler', 'queue-work', 'cache-routes', 'view-clear',
    ];

    private const VALID_COMMANDS = [
        'inspire', 'cache:clear', 'config:cache', 'route:cache',
        'statamic:search:update --all', 'statamic:stache:warm',
        'queue:restart', 'migrate --force', 'db:seed',
        'statamic:static:warm', 'statamic:forms:clear',
    ];

    private const INVALID_ALIASES = [
        '',              // empty
        'UPPER-CASE',   // uppercase
        'camelCase',    // camelCase
        'under_score',  // underscore
        '-leading',     // leading hyphen
        'trailing-',    // trailing hyphen
        'double--dash', // double dash
        'has space',    // space
        'has.dot',      // dot
        '123-start',    // starts with number
        'ALLCAPS',      // all caps
    ];

    private const ITERATIONS = 100;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempFile = sys_get_temp_dir() . '/ai-gw-alias-validation-' . uniqid() . '.yaml';
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
            ->id('super-alias-' . uniqid())
            ->email('super-alias-' . uniqid() . '@example.com')
            ->makeSuper();

        $user->save();

        return $user;
    }

    private function baseSettings(): array
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
     * Property 9a: Valid custom command definitions (unique kebab-case aliases,
     * non-empty commands) are accepted by validation.
     */
    #[Test]
    public function valid_command_definitions_are_accepted(): void
    {
        $admin = $this->superAdmin();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $count = random_int(1, 5);
            $aliases = $this->randomUniqueAliases($count);
            $commands = $this->buildValidCommands($aliases);

            $settings = $this->baseSettings();
            $settings['custom_commands'] = $commands;

            $response = $this->actingAs($admin, 'web')
                ->post(cp_route('ai-gateway.settings.update'), $settings);

            $response->assertSessionDoesntHaveErrors('custom_commands');
            $response->assertSessionDoesntHaveErrors('custom_commands.*.alias');
            $response->assertSessionDoesntHaveErrors('custom_commands.*.command');
        }
    }

    /**
     * Property 9b: Command definitions with empty aliases are rejected.
     */
    #[Test]
    public function empty_alias_is_rejected(): void
    {
        $admin = $this->superAdmin();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $commands = [
                [
                    'alias' => '',
                    'command' => $this->randomCommand(),
                    'description' => 'Test',
                    'confirmation_environments' => [],
                ],
            ];

            $settings = $this->baseSettings();
            $settings['custom_commands'] = $commands;

            $response = $this->actingAs($admin, 'web')
                ->post(cp_route('ai-gateway.settings.update'), $settings);

            $response->assertSessionHasErrors('custom_commands.0.alias');
        }
    }

    /**
     * Property 9c: Command definitions with non-kebab-case aliases are rejected.
     */
    #[Test]
    public function non_kebab_case_alias_is_rejected(): void
    {
        $admin = $this->superAdmin();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $invalidAlias = self::INVALID_ALIASES[array_rand(self::INVALID_ALIASES)];

            // Skip empty string — tested separately in 9b
            if ($invalidAlias === '') {
                continue;
            }

            $commands = [
                [
                    'alias' => $invalidAlias,
                    'command' => $this->randomCommand(),
                    'description' => 'Test',
                    'confirmation_environments' => [],
                ],
            ];

            $settings = $this->baseSettings();
            $settings['custom_commands'] = $commands;

            $response = $this->actingAs($admin, 'web')
                ->post(cp_route('ai-gateway.settings.update'), $settings);

            $this->assertTrue(
                $response->getSession()->has('errors'),
                "Iteration {$i}: Invalid alias '{$invalidAlias}' should be rejected"
            );
            $errors = $response->getSession()->get('errors');
            $this->assertTrue(
                $errors->has('custom_commands.0.alias'),
                "Iteration {$i}: Invalid alias '{$invalidAlias}' should produce alias validation error"
            );
        }
    }

    /**
     * Property 9d: Command definitions with empty command strings are rejected.
     */
    #[Test]
    public function empty_command_string_is_rejected(): void
    {
        $admin = $this->superAdmin();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $commands = [
                [
                    'alias' => $this->randomValidAlias(),
                    'command' => '',
                    'description' => 'Test',
                    'confirmation_environments' => [],
                ],
            ];

            $settings = $this->baseSettings();
            $settings['custom_commands'] = $commands;

            $response = $this->actingAs($admin, 'web')
                ->post(cp_route('ai-gateway.settings.update'), $settings);

            $response->assertSessionHasErrors('custom_commands.0.command');
        }
    }

    /**
     * Property 9e: Command definitions with duplicate aliases are rejected.
     */
    #[Test]
    public function duplicate_aliases_are_rejected(): void
    {
        $admin = $this->superAdmin();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $alias = $this->randomValidAlias();

            $commands = [
                [
                    'alias' => $alias,
                    'command' => $this->randomCommand(),
                    'description' => 'First',
                    'confirmation_environments' => [],
                ],
                [
                    'alias' => $alias,
                    'command' => $this->randomCommand(),
                    'description' => 'Duplicate',
                    'confirmation_environments' => [],
                ],
            ];

            $settings = $this->baseSettings();
            $settings['custom_commands'] = $commands;

            $response = $this->actingAs($admin, 'web')
                ->post(cp_route('ai-gateway.settings.update'), $settings);

            $this->assertTrue(
                $response->getSession()->has('errors'),
                "Iteration {$i}: Duplicate alias '{$alias}' should be rejected"
            );
            $errors = $response->getSession()->get('errors');
            $this->assertTrue(
                $errors->has('custom_commands'),
                "Iteration {$i}: Duplicate alias '{$alias}' should produce custom_commands validation error"
            );
        }
    }

    /**
     * Property 9f: An empty custom_commands array is accepted.
     */
    #[Test]
    public function empty_commands_array_is_accepted(): void
    {
        $admin = $this->superAdmin();

        $settings = $this->baseSettings();
        $settings['custom_commands'] = [];

        $response = $this->actingAs($admin, 'web')
            ->post(cp_route('ai-gateway.settings.update'), $settings);

        $response->assertSessionDoesntHaveErrors('custom_commands');
    }

    private function randomValidAlias(): string
    {
        return self::VALID_ALIASES[array_rand(self::VALID_ALIASES)];
    }

    private function randomCommand(): string
    {
        return self::VALID_COMMANDS[array_rand(self::VALID_COMMANDS)];
    }

    /**
     * @return string[]
     */
    private function randomUniqueAliases(int $count): array
    {
        $count = min($count, count(self::VALID_ALIASES));
        $keys = array_rand(self::VALID_ALIASES, $count);
        if (! is_array($keys)) {
            $keys = [$keys];
        }

        return array_map(fn ($k) => self::VALID_ALIASES[$k], $keys);
    }

    private function buildValidCommands(array $aliases): array
    {
        return array_map(fn (string $alias) => [
            'alias' => $alias,
            'command' => $this->randomCommand(),
            'description' => 'Test command for ' . $alias,
            'confirmation_environments' => [],
        ], $aliases);
    }
}
