<?php

namespace Stokoe\AiGateway\Tests\Integration;

use Statamic\Facades\Nav;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;
use Stokoe\AiGateway\Tests\TestCase;

class NavigationToolsTest extends TestCase
{
    use PreventsSavingStacheItemsToDisk;

    protected function defineEnvironment($app): void
    {
        $app['config']->set('ai_gateway.enabled', true);
        $app['config']->set('ai_gateway.token', 'test-integration-token');
        $app['config']->set('ai_gateway.tools.navigation.update', true);
        $app['config']->set('ai_gateway.allowed_navigations', ['main']);
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => 'Bearer test-integration-token'];
    }

    public function test_update_existing_navigation(): void
    {
        $nav = Nav::make('main');
        $nav->save();
        $nav->makeTree('default', [['url' => '/old']])->save();

        $response = $this->postJson('/ai-gateway/execute', [
            'tool' => 'navigation.update',
            'arguments' => [
                'handle' => 'main',
                'tree' => [['url' => '/home'], ['url' => '/about']],
            ],
        ], $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJson([
            'ok' => true,
            'tool' => 'navigation.update',
            'result' => [
                'status' => 'updated',
                'target_type' => 'navigation',
            ],
        ]);
    }

    public function test_update_nonexistent_navigation_returns_404(): void
    {
        $response = $this->postJson('/ai-gateway/execute', [
            'tool' => 'navigation.update',
            'arguments' => [
                'handle' => 'main',
                'tree' => [['url' => '/home']],
            ],
        ], $this->authHeaders());

        $response->assertStatus(404);
        $response->assertJson([
            'ok' => false,
            'error' => ['code' => 'resource_not_found'],
        ]);
    }
}
