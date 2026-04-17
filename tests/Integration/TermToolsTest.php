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
}
