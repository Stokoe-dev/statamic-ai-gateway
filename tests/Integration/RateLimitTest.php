<?php

namespace Stokoe\AiGateway\Tests\Integration;

use Illuminate\Support\Facades\RateLimiter;
use Stokoe\AiGateway\Tests\TestCase;

class RateLimitTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('ai_gateway.enabled', true);
        $app['config']->set('ai_gateway.token', 'test-integration-token');
        $app['config']->set('ai_gateway.rate_limits.execute', 3);
        $app['config']->set('ai_gateway.rate_limits.capabilities', 3);
        $app['config']->set('ai_gateway.tools.entry.create', true);
        $app['config']->set('ai_gateway.allowed_collections', ['pages']);
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => 'Bearer test-integration-token'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('ai_gateway:execute:' . hash('sha256', 'test-integration-token'));
        RateLimiter::clear('ai_gateway:capabilities:' . hash('sha256', 'test-integration-token'));
    }

    public function test_execute_requests_within_limit_succeed(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $response = $this->postJson('/ai-gateway/execute', [
                'tool' => 'entry.create',
                'arguments' => ['collection' => 'pages', 'slug' => "test-{$i}", 'data' => ['title' => 'Test']],
            ], $this->authHeaders());

            $this->assertNotEquals(429, $response->getStatusCode(), "Request {$i} should not be rate limited");
        }
    }

    public function test_execute_request_exceeding_limit_returns_429(): void
    {
        // Use up the limit
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/ai-gateway/execute', [
                'tool' => 'entry.create',
                'arguments' => ['collection' => 'pages', 'slug' => "test-{$i}", 'data' => ['title' => 'Test']],
            ], $this->authHeaders());
        }

        // This one should be rate limited
        $response = $this->postJson('/ai-gateway/execute', [
            'tool' => 'entry.create',
            'arguments' => ['collection' => 'pages', 'slug' => 'test-extra', 'data' => ['title' => 'Test']],
        ], $this->authHeaders());

        $response->assertStatus(429);
        $response->assertJson([
            'ok' => false,
            'error' => ['code' => 'rate_limited'],
        ]);
    }

    public function test_capabilities_requests_within_limit_succeed(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $response = $this->getJson('/ai-gateway/capabilities', $this->authHeaders());
            $this->assertNotEquals(429, $response->getStatusCode(), "Request {$i} should not be rate limited");
        }
    }

    public function test_capabilities_request_exceeding_limit_returns_429(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->getJson('/ai-gateway/capabilities', $this->authHeaders());
        }

        $response = $this->getJson('/ai-gateway/capabilities', $this->authHeaders());

        $response->assertStatus(429);
        $response->assertJson([
            'ok' => false,
            'error' => ['code' => 'rate_limited'],
        ]);
    }
}
