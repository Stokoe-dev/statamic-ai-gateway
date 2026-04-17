<?php

namespace Stokoe\AiGateway\Tests\Integration;

use Stokoe\AiGateway\Tests\TestCase;

class CapabilitiesEndpointTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('ai_gateway.enabled', true);
        $app['config']->set('ai_gateway.token', 'test-integration-token');
        $app['config']->set('ai_gateway.tools.entry.create', true);
        $app['config']->set('ai_gateway.tools.cache.clear', true);
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => 'Bearer test-integration-token'];
    }

    public function test_capabilities_returns_correct_envelope_structure(): void
    {
        $response = $this->getJson('/ai-gateway/capabilities', $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJson(['ok' => true, 'tool' => 'capabilities']);
        $response->assertJsonStructure([
            'ok',
            'tool',
            'result' => [
                'capabilities',
            ],
            'meta',
        ]);
    }

    public function test_capabilities_lists_all_registered_tools(): void
    {
        $response = $this->getJson('/ai-gateway/capabilities', $this->authHeaders());

        $capabilities = $response->json('result.capabilities');

        $expectedTools = [
            'entry.create', 'entry.update', 'entry.upsert',
            'global.update', 'navigation.update', 'term.upsert', 'cache.clear',
        ];

        foreach ($expectedTools as $tool) {
            $this->assertArrayHasKey($tool, $capabilities, "Missing tool: {$tool}");
        }
    }

    public function test_capabilities_reflects_enabled_status(): void
    {
        $response = $this->getJson('/ai-gateway/capabilities', $this->authHeaders());

        $capabilities = $response->json('result.capabilities');

        $this->assertTrue($capabilities['entry.create']['enabled']);
        $this->assertTrue($capabilities['cache.clear']['enabled']);
        $this->assertFalse($capabilities['entry.update']['enabled']);
        $this->assertFalse($capabilities['global.update']['enabled']);
    }

    public function test_capabilities_includes_target_type_and_confirmation(): void
    {
        $response = $this->getJson('/ai-gateway/capabilities', $this->authHeaders());

        $capabilities = $response->json('result.capabilities');

        $this->assertEquals('entry', $capabilities['entry.create']['target_type']);
        $this->assertEquals('cache', $capabilities['cache.clear']['target_type']);
        $this->assertArrayHasKey('requires_confirmation', $capabilities['cache.clear']);
    }
}
