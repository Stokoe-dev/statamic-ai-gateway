<?php

namespace Stokoe\AiGateway\Tests\Property;

use PHPUnit\Framework\Attributes\Test;
use Stokoe\AiGateway\Tests\TestCase;
use Stokoe\AiGateway\Tools\UserUpdateTool;

/**
 * Feature: gateway-content-expansion, Property 14: User update password triggers confirmation
 *
 * For any UserUpdateTool invocation where the arguments contain a password field and the
 * current environment is in the user.update confirmation environments list, the tool SHALL
 * require confirmation before execution.
 *
 * **Validates: Requirements 19.8**
 */
class UserUpdatePasswordConfirmationPropertyTest extends TestCase
{
    private const ENVIRONMENTS = [
        'production', 'staging', 'local', 'testing', 'development',
        'qa', 'uat', 'demo', 'sandbox', 'preview', 'ci', 'cd',
    ];

    private const ITERATIONS = 100;

    /**
     * Property 14a: When password IS present and environment IS in the
     * confirmation list, requiresConfirmation() returns true.
     */
    #[Test]
    public function returns_true_when_password_present_and_environment_in_list(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $environment = $this->randomEnvironment();

            // Build a confirmation list that includes the current environment
            $confirmationEnvs = $this->randomSubset(self::ENVIRONMENTS, random_int(1, 5));
            if (! in_array($environment, $confirmationEnvs, true)) {
                $confirmationEnvs[] = $environment;
            }

            config(['ai_gateway.confirmation.tools.user.update' => $confirmationEnvs]);

            $tool = new UserUpdateTool();
            $tool->resolveTarget([
                'email' => 'test@example.com',
                'password' => 'new-password-' . bin2hex(random_bytes(4)),
            ]);

            $this->assertTrue(
                $tool->requiresConfirmation($environment),
                "Iteration {$i}: Password present, environment '{$environment}' is in confirmation list ["
                . implode(', ', $confirmationEnvs) . "] but requiresConfirmation() returned false"
            );
        }
    }

    /**
     * Property 14b: When password IS present but environment is NOT in the
     * confirmation list, requiresConfirmation() returns false.
     */
    #[Test]
    public function returns_false_when_password_present_but_environment_not_in_list(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $environment = $this->randomEnvironment();

            // Build a confirmation list that excludes the current environment
            $confirmationEnvs = $this->randomSubsetExcluding(
                self::ENVIRONMENTS,
                $environment,
                random_int(0, 4)
            );

            config(['ai_gateway.confirmation.tools.user.update' => $confirmationEnvs]);

            $tool = new UserUpdateTool();
            $tool->resolveTarget([
                'email' => 'test@example.com',
                'password' => 'new-password-' . bin2hex(random_bytes(4)),
            ]);

            $this->assertFalse(
                $tool->requiresConfirmation($environment),
                "Iteration {$i}: Password present, environment '{$environment}' is NOT in confirmation list ["
                . implode(', ', $confirmationEnvs) . "] but requiresConfirmation() returned true"
            );
        }
    }

    /**
     * Property 14c: When password is NOT present, requiresConfirmation()
     * returns false regardless of environment or confirmation list.
     */
    #[Test]
    public function returns_false_when_password_not_present(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $environment = $this->randomEnvironment();

            // Use a confirmation list that includes the environment — should still be false
            $confirmationEnvs = $this->randomSubset(self::ENVIRONMENTS, random_int(0, count(self::ENVIRONMENTS)));

            config(['ai_gateway.confirmation.tools.user.update' => $confirmationEnvs]);

            $tool = new UserUpdateTool();
            // No password in arguments
            $tool->resolveTarget([
                'email' => 'test@example.com',
                'name' => 'Updated Name',
            ]);

            $this->assertFalse(
                $tool->requiresConfirmation($environment),
                "Iteration {$i}: No password in arguments, environment '{$environment}', "
                . "confirmation list [" . implode(', ', $confirmationEnvs)
                . "] — requiresConfirmation() should return false"
            );
        }
    }

    /**
     * Property 14d: Biconditional — requiresConfirmation() returns true
     * if and only if password is present AND environment is in the list.
     */
    #[Test]
    public function biconditional_password_and_environment_iff_requires_confirmation(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $environment = $this->randomEnvironment();
            $hasPassword = (bool) random_int(0, 1);
            $confirmationEnvs = $this->randomSubset(self::ENVIRONMENTS, random_int(0, count(self::ENVIRONMENTS)));

            config(['ai_gateway.confirmation.tools.user.update' => $confirmationEnvs]);

            $tool = new UserUpdateTool();

            $arguments = ['email' => 'test@example.com'];
            if ($hasPassword) {
                $arguments['password'] = 'pw-' . bin2hex(random_bytes(4));
            }

            $tool->resolveTarget($arguments);

            $expected = $hasPassword && in_array($environment, $confirmationEnvs, true);
            $actual = $tool->requiresConfirmation($environment);

            $this->assertSame(
                $expected,
                $actual,
                "Iteration {$i}: password=" . ($hasPassword ? 'yes' : 'no')
                . ", environment='{$environment}', confirmation list=["
                . implode(', ', $confirmationEnvs) . "], expected "
                . ($expected ? 'true' : 'false') . " but got "
                . ($actual ? 'true' : 'false')
            );
        }
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
