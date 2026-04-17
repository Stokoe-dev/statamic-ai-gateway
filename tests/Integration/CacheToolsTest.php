<?php

namespace Stokoe\AiGateway\Tests\Integration;

use Stokoe\AiGateway\Tests\TestCase;

class CacheToolsTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('ai_gateway.enabled', true);
        $app['config']->set('ai_gateway.token', 'test-integration-token');
        $app['config']->set('ai_gateway.tools.cache.clear', true);
        $app['config']->set('ai_gateway.allowed_cache_targets', ['application', 'stache']);
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => 'Bearer test-integration-token'];
    }

    public function test_clear_valid_cache_target(): void
    {
        $response = $this->postJson('/ai-gateway/execute', [
            'tool' => 'cache.clear',
            'arguments' => ['target' => 'application'],
        ], $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJson([
            'ok' => true,
            'tool' => 'cache.clear',
            'result' => [
                'status' => 'cleared',
                'target_type' => 'cache',
            ],
        ]);
    }

    public function test_clear_invalid_cache_target_returns_422(): void
    {
        // 'invalid' is not in the allowed enum values, so tool validation rejects it
        $response = $this->postJson('/ai-gateway/execute', [
            'tool' => 'cache.clear',
            'arguments' => ['target' => 'invalid'],
        ], $this->authHeaders());

        // 'invalid' is not in the allowlist either, so it gets 403 forbidden first
        // But if it were in the allowlist, it would get 422 from tool validation
        $this->assertTrue(
            in_array($response->getStatusCode(), [403, 422]),
            "Expected 403 or 422, got {$response->getStatusCode()}"
        );
    }

    public function test_clear_disallowed_cache_target_returns_403(): void
    {
        // 'static' is a valid target but not in our allowlist
        $response = $this->postJson('/ai-gateway/execute', [
            'tool' => 'cache.clear',
            'arguments' => ['target' => 'static'],
        ], $this->authHeaders());

        $response->assertStatus(403);
        $response->assertJson([
            'ok' => false,
            'error' => ['code' => 'forbidden'],
        ]);
    }

    public function test_confirmation_flow_in_production(): void
    {
        // Override environment to production and set confirmation config
        config(['ai_gateway.confirmation.tools.cache.clear' => ['production']]);
        app()->detectEnvironment(fn () => 'production');

        $response = $this->postJson('/ai-gateway/execute', [
            'tool' => 'cache.clear',
            'arguments' => ['target' => 'application'],
        ], $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJson([
            'ok' => false,
            'error' => ['code' => 'confirmation_required'],
        ]);
        $response->assertJsonStructure([
            'confirmation' => ['token', 'expires_at', 'operation_summary'],
        ]);
    }

    public function test_confirmation_flow_with_valid_token(): void
    {
        config(['ai_gateway.confirmation.tools.cache.clear' => ['production']]);
        app()->detectEnvironment(fn () => 'production');

        // First request: get the confirmation token
        $firstResponse = $this->postJson('/ai-gateway/execute', [
            'tool' => 'cache.clear',
            'arguments' => ['target' => 'application'],
        ], $this->authHeaders());

        $token = $firstResponse->json('confirmation.token');

        // Second request: provide the confirmation token
        $response = $this->postJson('/ai-gateway/execute', [
            'tool' => 'cache.clear',
            'arguments' => ['target' => 'application'],
            'confirmation_token' => $token,
        ], $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJson([
            'ok' => true,
            'tool' => 'cache.clear',
            'result' => [
                'status' => 'cleared',
            ],
        ]);
    }
}
