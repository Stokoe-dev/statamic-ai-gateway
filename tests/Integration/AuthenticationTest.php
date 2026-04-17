<?php

namespace Stokoe\AiGateway\Tests\Integration;

use Stokoe\AiGateway\Tests\TestCase;

class AuthenticationTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('ai_gateway.enabled', true);
        $app['config']->set('ai_gateway.token', 'test-integration-token');
        $app['config']->set('ai_gateway.tools.entry.create', true);
        $app['config']->set('ai_gateway.allowed_collections', ['pages']);
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => 'Bearer test-integration-token'];
    }

    public function test_missing_authorization_header_returns_401(): void
    {
        $response = $this->postJson('/ai-gateway/execute', [
            'tool' => 'entry.create',
            'arguments' => ['collection' => 'pages', 'slug' => 'test', 'data' => ['title' => 'Test']],
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'ok' => false,
            'error' => ['code' => 'unauthorized'],
        ]);
    }

    public function test_invalid_bearer_token_returns_401(): void
    {
        $response = $this->postJson('/ai-gateway/execute', [
            'tool' => 'entry.create',
            'arguments' => ['collection' => 'pages', 'slug' => 'test', 'data' => ['title' => 'Test']],
        ], ['Authorization' => 'Bearer wrong-token']);

        $response->assertStatus(401);
        $response->assertJson([
            'ok' => false,
            'error' => ['code' => 'unauthorized'],
        ]);
    }

    public function test_malformed_authorization_header_returns_401(): void
    {
        $response = $this->postJson('/ai-gateway/execute', [
            'tool' => 'entry.create',
            'arguments' => ['collection' => 'pages', 'slug' => 'test', 'data' => ['title' => 'Test']],
        ], ['Authorization' => 'Basic dXNlcjpwYXNz']);

        $response->assertStatus(401);
        $response->assertJson([
            'ok' => false,
            'error' => ['code' => 'unauthorized'],
        ]);
    }

    public function test_valid_token_passes_authentication(): void
    {
        // With a valid token, the request should pass auth and reach the controller.
        // It may fail for other reasons (e.g. collection not found) but NOT 401.
        $response = $this->postJson('/ai-gateway/execute', [
            'tool' => 'entry.create',
            'arguments' => ['collection' => 'pages', 'slug' => 'test', 'data' => ['title' => 'Test']],
        ], $this->authHeaders());

        $response->assertStatus(404); // resource_not_found because collection doesn't exist in test env
        $this->assertNotEquals(401, $response->getStatusCode());
    }

    public function test_capabilities_endpoint_requires_auth(): void
    {
        $response = $this->getJson('/ai-gateway/capabilities');

        $response->assertStatus(401);
        $response->assertJson([
            'ok' => false,
            'error' => ['code' => 'unauthorized'],
        ]);
    }

    public function test_capabilities_endpoint_with_valid_token(): void
    {
        $response = $this->getJson('/ai-gateway/capabilities', $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJson(['ok' => true]);
    }
}
