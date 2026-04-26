<?php

namespace Stokoe\AiGateway\Tests\Property;

use PHPUnit\Framework\Attributes\Test;
use Stokoe\AiGateway\Policies\ToolPolicy;
use Stokoe\AiGateway\Tests\TestCase;

/**
 * Feature: gateway-content-expansion, Property 15: User tools gated by operations toggle
 *
 * For any user tool invocation (user.list, user.get, user.create, user.update, user.delete),
 * when the allowed_user_operations config toggle is false, the ToolPolicy SHALL reject the
 * request with a ToolAuthorizationException.
 *
 * **Validates: Requirements 19.12**
 */
class UserToolsTogglePropertyTest extends TestCase
{
    private const USER_TOOL_NAMES = [
        'user.list',
        'user.get',
        'user.create',
        'user.update',
        'user.delete',
    ];

    private const ITERATIONS = 100;

    /**
     * Property 15a: When allowed_user_operations is false,
     * ToolPolicy::targetAllowed('user', 'enabled') returns false.
     */
    #[Test]
    public function returns_false_when_toggle_is_disabled(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $toolName = self::USER_TOOL_NAMES[array_rand(self::USER_TOOL_NAMES)];

            config(['ai_gateway.allowed_user_operations' => false]);

            $policy = new ToolPolicy();

            $this->assertFalse(
                $policy->targetAllowed('user', 'enabled'),
                "Iteration {$i}: Tool '{$toolName}' — ToolPolicy::targetAllowed('user', 'enabled') "
                . "should return false when allowed_user_operations is false"
            );
        }
    }

    /**
     * Property 15b: When allowed_user_operations is true,
     * ToolPolicy::targetAllowed('user', 'enabled') returns true.
     */
    #[Test]
    public function returns_true_when_toggle_is_enabled(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $toolName = self::USER_TOOL_NAMES[array_rand(self::USER_TOOL_NAMES)];

            config(['ai_gateway.allowed_user_operations' => true]);

            $policy = new ToolPolicy();

            $this->assertTrue(
                $policy->targetAllowed('user', 'enabled'),
                "Iteration {$i}: Tool '{$toolName}' — ToolPolicy::targetAllowed('user', 'enabled') "
                . "should return true when allowed_user_operations is true"
            );
        }
    }

    /**
     * Property 15c: Test across all five user tool names with randomized toggle values.
     * The biconditional: targetAllowed returns true iff toggle is true.
     */
    #[Test]
    public function biconditional_toggle_value_matches_target_allowed(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $toolName = self::USER_TOOL_NAMES[array_rand(self::USER_TOOL_NAMES)];
            $toggleValue = (bool) random_int(0, 1);

            config(['ai_gateway.allowed_user_operations' => $toggleValue]);

            $policy = new ToolPolicy();

            $result = $policy->targetAllowed('user', 'enabled');

            $this->assertSame(
                $toggleValue,
                $result,
                "Iteration {$i}: Tool '{$toolName}' — toggle is "
                . ($toggleValue ? 'true' : 'false')
                . " but targetAllowed returned "
                . ($result ? 'true' : 'false')
            );
        }
    }

    /**
     * Property 15d: Each of the five user tools uses target type 'user'
     * and resolveTarget returns 'enabled', so the toggle gates all of them uniformly.
     */
    #[Test]
    public function all_five_user_tools_gated_by_same_toggle(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $toggleValue = (bool) random_int(0, 1);

            config(['ai_gateway.allowed_user_operations' => $toggleValue]);

            $policy = new ToolPolicy();

            foreach (self::USER_TOOL_NAMES as $toolName) {
                $result = $policy->targetAllowed('user', 'enabled');

                $this->assertSame(
                    $toggleValue,
                    $result,
                    "Iteration {$i}: Tool '{$toolName}' — toggle is "
                    . ($toggleValue ? 'true' : 'false')
                    . " but targetAllowed returned "
                    . ($result ? 'true' : 'false')
                );
            }
        }
    }
}
