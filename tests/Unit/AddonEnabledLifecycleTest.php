<?php

namespace Stokoe\AiGateway\Tests\Unit;

use Stokoe\AiGateway\Tests\TestCase;

class AddonEnabledLifecycleTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('ai_gateway.enabled', true);
        $app['config']->set('ai_gateway.token', 'test-token-lifecycle');
    }

    public function test_addon_enabled_registers_execute_route(): void
    {
        // Without a valid token we should get 401 (not 404), proving the route exists
        $response = $this->postJson('/ai-gateway/execute', [
            'tool' => 'entry.create',
            'arguments' => ['collection' => 'pages', 'slug' => 'test', 'data' => []],
        ]);

        $response->assertStatus(401);
        $response->assertJson(['ok' => false, 'error' => ['code' => 'unauthorized']]);
    }

    public function test_addon_enabled_registers_capabilities_route(): void
    {
        // Without a valid token we should get 401 (not 404), proving the route exists
        $response = $this->getJson('/ai-gateway/capabilities');

        $response->assertStatus(401);
        $response->assertJson(['ok' => false, 'error' => ['code' => 'unauthorized']]);
    }
}
