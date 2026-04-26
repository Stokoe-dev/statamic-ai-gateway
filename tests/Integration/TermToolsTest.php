<?php

namespace Stokoe\AiGateway\Tests\Integration;

use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;
use Stokoe\AiGateway\Tests\TestCase;

class TermToolsTest extends TestCase
{
    use PreventsSavingStacheItemsToDisk;

    protected function defineEnvironment($app): void
    {
        $app['config']->set('ai_gateway.enabled', true);
        $app['config']->set('ai_gateway.token', 'test-integration-token');
        $app['config']->set('ai_gateway.tools.term.upsert', true);
        $app['config']->set('ai_gateway.tools.term.delete', true);
        $app['config']->set('ai_gateway.allowed_taxonomies', ['tags']);
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => 'Bearer test-integration-token'];
    }

    public function test_upsert_creates_new_term(): void
    {
        Taxonomy::make('tags')->save();

        $response = $this->postJson('/ai-gateway/execute', [
            'tool' => 'term.upsert',
            'arguments' => [
                'taxonomy' => 'tags',
                'slug' => 'php',
                'data' => ['title' => 'PHP'],
            ],
        ], $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJson([
            'ok' => true,
            'tool' => 'term.upsert',
            'result' => [
                'status' => 'created',
                'target_type' => 'taxonomy',
            ],
        ]);
    }

    public function test_upsert_updates_existing_term(): void
    {
        Taxonomy::make('tags')->save();
        Term::make()->taxonomy('tags')->slug('php')->data(['title' => 'PHP'])->save();

        $response = $this->postJson('/ai-gateway/execute', [
            'tool' => 'term.upsert',
            'arguments' => [
                'taxonomy' => 'tags',
                'slug' => 'php',
                'data' => ['title' => 'PHP 8'],
            ],
        ], $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJson([
            'ok' => true,
            'tool' => 'term.upsert',
            'result' => [
                'status' => 'updated',
                'target_type' => 'taxonomy',
            ],
        ]);
    }

    public function test_upsert_in_nonexistent_taxonomy_returns_404(): void
    {
        $response = $this->postJson('/ai-gateway/execute', [
            'tool' => 'term.upsert',
            'arguments' => [
                'taxonomy' => 'tags',
                'slug' => 'php',
                'data' => ['title' => 'PHP'],
            ],
        ], $this->authHeaders());

        $response->assertStatus(404);
        $response->assertJson([
            'ok' => false,
            'error' => ['code' => 'resource_not_found'],
        ]);
    }

    public function test_delete_removes_existing_term(): void
    {
        Taxonomy::make('tags')->save();
        Term::make()->taxonomy('tags')->slug('php')->data(['title' => 'PHP'])->save();

        $response = $this->postJson('/ai-gateway/execute', [
            'tool' => 'term.delete',
            'arguments' => [
                'taxonomy' => 'tags',
                'slug' => 'php',
            ],
        ], $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJson([
            'ok' => true,
            'tool' => 'term.delete',
            'result' => [
                'status' => 'deleted',
                'target_type' => 'taxonomy',
                'target' => [
                    'taxonomy' => 'tags',
                    'slug' => 'php',
                ],
            ],
        ]);
    }

    public function test_delete_nonexistent_term_returns_404(): void
    {
        Taxonomy::make('tags')->save();

        $response = $this->postJson('/ai-gateway/execute', [
            'tool' => 'term.delete',
            'arguments' => [
                'taxonomy' => 'tags',
                'slug' => 'nonexistent',
            ],
        ], $this->authHeaders());

        $response->assertStatus(404);
        $response->assertJson([
            'ok' => false,
            'error' => ['code' => 'resource_not_found'],
        ]);
    }

    public function test_delete_in_nonallowlisted_taxonomy_returns_403(): void
    {
        $response = $this->postJson('/ai-gateway/execute', [
            'tool' => 'term.delete',
            'arguments' => [
                'taxonomy' => 'nonexistent',
                'slug' => 'php',
            ],
        ], $this->authHeaders());

        $response->assertStatus(403);
        $response->assertJson([
            'ok' => false,
            'error' => ['code' => 'forbidden'],
        ]);
    }

    public function test_delete_in_nonexistent_but_allowlisted_taxonomy_returns_404(): void
    {
        // 'tags' is in the allowlist but doesn't exist as a taxonomy
        $response = $this->postJson('/ai-gateway/execute', [
            'tool' => 'term.delete',
            'arguments' => [
                'taxonomy' => 'tags',
                'slug' => 'php',
            ],
        ], $this->authHeaders());

        $response->assertStatus(404);
        $response->assertJson([
            'ok' => false,
            'error' => ['code' => 'resource_not_found'],
        ]);
    }
}
