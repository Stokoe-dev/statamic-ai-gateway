<?php

namespace Stokoe\AiGateway\Tests\Property;

use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\Attributes\Test;
use Stokoe\AiGateway\Exceptions\ToolDisabledException;
use Stokoe\AiGateway\Exceptions\ToolNotFoundException;
use Stokoe\AiGateway\Support\ToolRegistry;
use Stokoe\AiGateway\Tests\TestCase;
use Stokoe\AiGateway\Tools\Contracts\GatewayTool;

/**
 * Feature: ai-gateway, Property 4: Tool registry resolution
 *
 * For any tool name string, the ToolRegistry resolves it to the correct handler class
 * if and only if the name is registered and the tool is enabled in configuration.
 * Unregistered names produce tool_not_found, and registered-but-disabled names produce tool_disabled.
 *
 * **Validates: Requirements 5.1, 5.2, 6.2**
 */
class ToolRegistryPropertyTest extends TestCase
{
    use TestTrait;

    private const REGISTERED_TOOLS = [
        'entry.get',
        'entry.list',
        'entry.create',
        'entry.update',
        'entry.upsert',
        'global.get',
        'global.update',
        'navigation.get',
        'navigation.update',
        'term.get',
        'term.list',
        'term.upsert',
        'cache.clear',
    ];

    /**
     * Property 4a: Unregistered tool names always throw ToolNotFoundException.
     */
    #[Test]
    public function unregistered_tool_names_throw_not_found(): void
    {
        $this->forAll(
            Generators::suchThat(
                fn ($s) => is_string($s) && ! in_array($s, self::REGISTERED_TOOLS, true),
                Generators::string()
            )
        )
            ->withMaxSize(50)
            ->__invoke(function (string $name): void {
                $registry = new ToolRegistry($this->app);

                try {
                    $registry->resolve($name);
                    $this->fail("Expected ToolNotFoundException for unregistered tool '{$name}'");
                } catch (ToolNotFoundException $e) {
                    $this->assertStringContainsString($name, $e->getMessage());
                }
            });
    }

    /**
     * Property 4b: Registered but disabled tools throw ToolDisabledException.
     */
    #[Test]
    public function disabled_registered_tools_throw_disabled(): void
    {
        $this->forAll(
            Generators::elements(self::REGISTERED_TOOLS)
        )
            ->withMaxSize(50)
            ->__invoke(function (string $name): void {
                // Ensure tool is disabled
                config(["ai_gateway.tools.{$name}" => false]);

                $registry = new ToolRegistry($this->app);

                try {
                    $registry->resolve($name);
                    $this->fail("Expected ToolDisabledException for disabled tool '{$name}'");
                } catch (ToolDisabledException $e) {
                    $this->assertStringContainsString($name, $e->getMessage());
                }
            });
    }

    /**
     * Property 4c: Enabled registered tools resolve to an GatewayTool instance.
     */
    #[Test]
    public function enabled_registered_tools_resolve_successfully(): void
    {
        $this->forAll(
            Generators::elements(self::REGISTERED_TOOLS)
        )
            ->withMaxSize(50)
            ->__invoke(function (string $name): void {
                // Enable the tool
                config(["ai_gateway.tools.{$name}" => true]);

                // Bind a mock for the handler class so the container can resolve it
                $registry = new ToolRegistry($this->app);
                $handlerClass = $registry->all()[$name]['handler'];

                $mock = $this->createStub(GatewayTool::class);
                $this->app->instance($handlerClass, $mock);

                $resolved = $registry->resolve($name);

                $this->assertInstanceOf(GatewayTool::class, $resolved);
            });
    }
}
