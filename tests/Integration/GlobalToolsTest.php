<?php

namespace Stokoe\AiGateway\Tests\Integration;

use Statamic\Facades\GlobalSet;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;
use Stokoe\AiGateway\Tests\TestCase;

class GlobalToolsTest extends TestCase
{
    use PreventsSavingStacheItemsToDisk;

    protected function defineEnvironment($app): void
    {
        $app['config']->set('ai_gateway.enabled', true);
        $app['config']->set('ai_gateway.token', 'test-integration-token');
        $app['config']->set('ai_gateway.tools.global.update', true);
        $app['config']->set('ai_gateway.allowed_globals', ['contact']);
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => 'Bearer test-integration-token'];
    }

    public function test_update_existing_global(): void
    {
        $global = GlobalSet::make('contact');
        $global->save();
        $global->makeLocalization('default')->data(['phone' => '555-0100'])->save();

        $response = $this->postJson('/ai-gateway/execute', [
            'tool' => 'global.update',
            'arguments' => [
                'handle' => 'contact',
                'data' => ['phone' => '555-0200'],
            ],
        ], $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJson([
            'ok' => true,
            'tool' => 'global.update',
            'result' => [
                'status' => 'updated',
                'target_type' => 'global',
            ],
        ]);
    }

    public function test_update_nonexistent_global_returns_404(): void
    {
        $response = $this->postJson('/ai-gateway/execute', [
            'tool' => 'global.update',
            'arguments' => [
                'handle' => 'contact',
                'data' => ['phone' => '555-0200'],
            ],
        ], $this->authHeaders());

        $response->assertStatus(404);
        $response->assertJson([
            'ok' => false,
            'error' => ['code' => 'resource_not_found'],
        ]);
    }
}
