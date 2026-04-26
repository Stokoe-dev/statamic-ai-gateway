<?php

namespace Stokoe\AiGateway\Tests\Property;

use PHPUnit\Framework\Attributes\Test;
use Stokoe\AiGateway\Support\SettingsRepository;
use Stokoe\AiGateway\Tests\TestCase;

/**
 * Feature: gateway-content-expansion, Property 8: Custom command settings round-trip
 *
 * For any valid set of custom command definitions, writing them to the SettingsRepository
 * and then reading them back SHALL produce an equivalent set of definitions, and calling
 * applyToConfig() SHALL make them available via config('ai_gateway.custom_commands').
 *
 * **Validates: Requirements 11.2, 11.4**
 */
class CustomCommandSettingsRoundTripPropertyTest extends TestCase
{
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
    ];

    private const ENVIRONMENTS = [
        'production', 'staging', 'local', 'testing', 'development',
        'qa', 'uat', 'demo',
    ];

    private const ITERATIONS = 100;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempFile = sys_get_temp_dir() . '/ai-gw-cmd-roundtrip-' . uniqid() . '.yaml';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
        parent::tearDown();
    }

    /**
     * Property 8a: Writing custom commands to SettingsRepository and reading
     * them back produces an equivalent set of definitions.
     */
    #[Test]
    public function write_then_read_preserves_custom_commands(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $commands = $this->randomValidCommands(random_int(0, 6));

            $settings = ['custom_commands' => $commands];

            $repo = new SettingsRepository($this->tempFile);
            $repo->write($settings);
            $result = $repo->read();

            $this->assertArrayHasKey('custom_commands', $result,
                "Iteration {$i}: Read-back should contain custom_commands key"
            );

            $this->assertSame(
                $commands,
                $result['custom_commands'],
                "Iteration {$i}: Custom commands should round-trip exactly"
            );

            // Clean up for next iteration
            if (file_exists($this->tempFile)) {
                unlink($this->tempFile);
            }
        }
    }

    /**
     * Property 8b: After applyToConfig(), custom commands are available
     * via config('ai_gateway.custom_commands').
     */
    #[Test]
    public function apply_to_config_makes_commands_available(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $commands = $this->randomValidCommands(random_int(0, 6));

            $settings = ['custom_commands' => $commands];

            // Reset config to defaults before each iteration
            config(['ai_gateway.custom_commands' => []]);

            $repo = new SettingsRepository($this->tempFile);
            $repo->write($settings);
            $repo->applyToConfig();

            $configCommands = config('ai_gateway.custom_commands');

            $this->assertSame(
                $commands,
                $configCommands,
                "Iteration {$i}: config('ai_gateway.custom_commands') should match written commands"
            );

            // Clean up for next iteration
            if (file_exists($this->tempFile)) {
                unlink($this->tempFile);
            }
        }
    }

    /**
     * Property 8c: Round-trip preserves confirmation_environments arrays.
     */
    #[Test]
    public function round_trip_preserves_confirmation_environments(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $commands = $this->randomValidCommandsWithConfirmation(random_int(1, 5));

            $settings = ['custom_commands' => $commands];

            $repo = new SettingsRepository($this->tempFile);
            $repo->write($settings);
            $result = $repo->read();

            foreach ($commands as $idx => $command) {
                $this->assertSame(
                    $command['confirmation_environments'],
                    $result['custom_commands'][$idx]['confirmation_environments'],
                    "Iteration {$i}, command {$idx}: confirmation_environments should round-trip exactly"
                );
            }

            // Clean up for next iteration
            if (file_exists($this->tempFile)) {
                unlink($this->tempFile);
            }
        }
    }

    /**
     * Property 8d: Empty custom_commands array round-trips correctly.
     */
    #[Test]
    public function empty_commands_round_trip(): void
    {
        $repo = new SettingsRepository($this->tempFile);
        $repo->write(['custom_commands' => []]);
        $result = $repo->read();

        $this->assertSame([], $result['custom_commands']);

        $repo->applyToConfig();
        $this->assertSame([], config('ai_gateway.custom_commands'));
    }

    /**
     * @return array[]
     */
    private function randomValidCommands(int $count): array
    {
        if ($count === 0) {
            return [];
        }

        $aliases = $this->randomUniqueAliases($count);

        return array_map(fn (string $alias) => [
            'alias' => $alias,
            'description' => 'Test command for ' . $alias,
            'command' => self::VALID_COMMANDS[array_rand(self::VALID_COMMANDS)],
            'confirmation_environments' => [],
        ], $aliases);
    }

    /**
     * @return array[]
     */
    private function randomValidCommandsWithConfirmation(int $count): array
    {
        if ($count === 0) {
            return [];
        }

        $aliases = $this->randomUniqueAliases($count);

        return array_map(fn (string $alias) => [
            'alias' => $alias,
            'description' => 'Test command for ' . $alias,
            'command' => self::VALID_COMMANDS[array_rand(self::VALID_COMMANDS)],
            'confirmation_environments' => $this->randomSubset(
                self::ENVIRONMENTS,
                random_int(0, 4)
            ),
        ], $aliases);
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

    /**
     * @return string[]
     */
    private function randomSubset(array $items, int $count): array
    {
        if ($count === 0 || empty($items)) {
            return [];
        }

        $count = min($count, count($items));
        $keys = array_rand($items, $count);
        if (! is_array($keys)) {
            $keys = [$keys];
        }

        return array_values(array_map(fn ($k) => $items[$k], $keys));
    }
}
