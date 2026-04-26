<?php

namespace Stokoe\AiGateway\Tests\Property;

use PHPUnit\Framework\Attributes\Test;
use Stokoe\AiGateway\Tests\TestCase;
use Stokoe\AiGateway\Tools\CustomCommandTool;

/**
 * Feature: gateway-content-expansion, Property 10: Custom command dynamic confirmation
 *
 * For any custom command definition with a confirmation environments list and any
 * current application environment, the CustomCommandTool::requiresConfirmation()
 * SHALL return true if and only if the current environment appears in the command's
 * confirmation environments list.
 *
 * **Validates: Requirements 13.3**
 */
class CustomCommandConfirmationPropertyTest extends TestCase
{
    private const ENVIRONMENTS = [
        'production', 'staging', 'local', 'testing', 'development',
        'qa', 'uat', 'demo', 'sandbox', 'preview', 'ci', 'cd',
    ];

    private const ALIASES = [
        'rebuild-search', 'clear-cache', 'warm-stache', 'sync-assets',
        'flush-queue', 'run-migrations', 'seed-data', 'deploy-static',
        'prune-revisions', 'reindex-content', 'backup-db', 'restore-db',
    ];

    private const ITERATIONS = 100;

    /**
     * Property 10a: When the current environment IS in the command's
     * confirmation_environments list, requiresConfirmation() returns true.
     */
    #[Test]
    public function returns_true_when_environment_is_in_confirmation_list(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $alias = $this->randomAlias();
            $environment = $this->randomEnvironment();

            // Build a confirmation list that includes the current environment
            $confirmationEnvs = $this->randomSubset(self::ENVIRONMENTS, random_int(1, 5));
            if (! in_array($environment, $confirmationEnvs, true)) {
                $confirmationEnvs[] = $environment;
            }

            $this->setCustomCommands([
                [
                    'alias' => $alias,
                    'description' => 'Test command',
                    'command' => 'inspire',
                    'confirmation_environments' => $confirmationEnvs,
                ],
            ]);

            $tool = new CustomCommandTool();
            $tool->resolveTarget(['alias' => $alias]);

            $this->assertTrue(
                $tool->requiresConfirmation($environment),
                "Iteration {$i}: Environment '{$environment}' is in confirmation list ["
                . implode(', ', $confirmationEnvs) . "] but requiresConfirmation() returned false"
            );
        }
    }

    /**
     * Property 10b: When the current environment is NOT in the command's
     * confirmation_environments list, requiresConfirmation() returns false.
     */
    #[Test]
    public function returns_false_when_environment_is_not_in_confirmation_list(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $alias = $this->randomAlias();
            $environment = $this->randomEnvironment();

            // Build a confirmation list that excludes the current environment
            $confirmationEnvs = $this->randomSubsetExcluding(
                self::ENVIRONMENTS,
                $environment,
                random_int(0, 4)
            );

            $this->setCustomCommands([
                [
                    'alias' => $alias,
                    'description' => 'Test command',
                    'command' => 'inspire',
                    'confirmation_environments' => $confirmationEnvs,
                ],
            ]);

            $tool = new CustomCommandTool();
            $tool->resolveTarget(['alias' => $alias]);

            $this->assertFalse(
                $tool->requiresConfirmation($environment),
                "Iteration {$i}: Environment '{$environment}' is NOT in confirmation list ["
                . implode(', ', $confirmationEnvs) . "] but requiresConfirmation() returned true"
            );
        }
    }

    /**
     * Property 10c: When the command has an empty confirmation_environments list,
     * requiresConfirmation() returns false for any environment.
     */
    #[Test]
    public function returns_false_when_confirmation_list_is_empty(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $alias = $this->randomAlias();
            $environment = $this->randomEnvironment();

            $this->setCustomCommands([
                [
                    'alias' => $alias,
                    'description' => 'Test command',
                    'command' => 'inspire',
                    'confirmation_environments' => [],
                ],
            ]);

            $tool = new CustomCommandTool();
            $tool->resolveTarget(['alias' => $alias]);

            $this->assertFalse(
                $tool->requiresConfirmation($environment),
                "Iteration {$i}: Empty confirmation list should always return false, "
                . "but returned true for environment '{$environment}'"
            );
        }
    }

    /**
     * Property 10d: When the alias does not match any command,
     * requiresConfirmation() returns false.
     */
    #[Test]
    public function returns_false_when_alias_not_found(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $environment = $this->randomEnvironment();
            $unknownAlias = 'unknown-' . bin2hex(random_bytes(4));

            $this->setCustomCommands([
                [
                    'alias' => 'some-other-command',
                    'description' => 'Test command',
                    'command' => 'inspire',
                    'confirmation_environments' => self::ENVIRONMENTS,
                ],
            ]);

            $tool = new CustomCommandTool();
            $tool->resolveTarget(['alias' => $unknownAlias]);

            $this->assertFalse(
                $tool->requiresConfirmation($environment),
                "Iteration {$i}: Unknown alias '{$unknownAlias}' should return false "
                . "for environment '{$environment}'"
            );
        }
    }

    /**
     * Property 10e: Biconditional — requiresConfirmation() returns true
     * if and only if the environment is in the list.
     */
    #[Test]
    public function biconditional_environment_in_list_iff_requires_confirmation(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $alias = $this->randomAlias();
            $environment = $this->randomEnvironment();
            $confirmationEnvs = $this->randomSubset(self::ENVIRONMENTS, random_int(0, count(self::ENVIRONMENTS)));

            $this->setCustomCommands([
                [
                    'alias' => $alias,
                    'description' => 'Test command',
                    'command' => 'inspire',
                    'confirmation_environments' => $confirmationEnvs,
                ],
            ]);

            $tool = new CustomCommandTool();
            $tool->resolveTarget(['alias' => $alias]);

            $expected = in_array($environment, $confirmationEnvs, true);
            $actual = $tool->requiresConfirmation($environment);

            $this->assertSame(
                $expected,
                $actual,
                "Iteration {$i}: For environment '{$environment}' and confirmation list ["
                . implode(', ', $confirmationEnvs) . "], expected "
                . ($expected ? 'true' : 'false') . " but got "
                . ($actual ? 'true' : 'false')
            );
        }
    }

    private function setCustomCommands(array $commands): void
    {
        config(['ai_gateway.custom_commands' => $commands]);
    }

    private function randomAlias(): string
    {
        return self::ALIASES[array_rand(self::ALIASES)];
    }

    private function randomEnvironment(): string
    {
        return self::ENVIRONMENTS[array_rand(self::ENVIRONMENTS)];
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

        return array_map(fn ($k) => $items[$k], $keys);
    }

    /**
     * @return string[]
     */
    private function randomSubsetExcluding(array $items, string $excluded, int $count): array
    {
        $candidates = array_values(array_filter(
            $items,
            fn (string $item) => $item !== $excluded
        ));

        return $this->randomSubset($candidates, $count);
    }
}
