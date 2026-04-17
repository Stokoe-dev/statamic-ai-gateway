<?php

namespace Stokoe\AiGateway\Tests\Unit;

use Stokoe\AiGateway\Tests\TestCase;

class AddonLifecycleTest extends TestCase
{
    /**
     * By default, ai_gateway.enabled is false (from config defaults).
     * The ServiceProvider should NOT load routes when disabled.
     */
    public function test_addon_disabled_returns_404_on_execute_endpoint(): void
    {
        // Config defaults to enabled=false, so routes should not be registered
        $response = $this->postJson('/ai-gateway/execute', [
            'tool' => 'entry.create',
            'arguments' => ['collection' => 'pages', 'slug' => 'test', 'data' => []],
        ]);

        $response->assertStatus(404);
    }

    public function test_addon_disabled_returns_404_on_capabilities_endpoint(): void
    {
        $response = $this->getJson('/ai-gateway/capabilities');

        $response->assertStatus(404);
    }
}
