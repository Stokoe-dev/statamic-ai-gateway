<?php

namespace Stokoe\AiGateway\Tests\Property;

use PHPUnit\Framework\Attributes\Test;
use Stokoe\AiGateway\Support\ToolRegistry;
use Stokoe\AiGateway\Tests\TestCase;
use Stokoe\AiGateway\Tools\Contracts\GatewayTool;

/**
 * Feature: gateway-content-expansion, Property 11: All registered tools resolve to GatewayTool instances
 *
 * For any tool name registered in the ToolRegistry, resolving that tool
 * (bypassing enablement check via resolveWithoutEnabledCheck) SHALL return
 * an object implementing the GatewayTool interface with a name() matching
 * the registered key.
 *
 * **Validates: Requirements 14.5**
 */
class ToolRegistryExpansionPropertyTest extends TestCase
{
    #[Test]
    public function all_registered_tools_resolve_to_gateway_tool_instances(): void
    {
        $registry = new ToolRegistry($this->app);
        $toolNames = $registry->registeredNames();

        $this->assertNotEmpty($toolNames, 'ToolRegistry should have registered tools');

        foreach ($toolNames as $name) {
            $tool = $registry->resolveWithoutEnabledCheck($name);

            $this->assertInstanceOf(
                GatewayTool::class,
                $tool,
                "Tool '{$name}' should resolve to a GatewayTool instance"
            );

            $this->assertSame(
                $name,
                $tool->name(),
                "Tool '{$name}' should return its registered name from name()"
            );
        }
    }
}
