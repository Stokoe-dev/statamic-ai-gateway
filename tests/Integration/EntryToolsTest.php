<?php

namespace Stokoe\AiGateway\Tests\Integration;

use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;
use Stokoe\AiGateway\Tests\TestCase;

class EntryToolsTest extends TestCase
{
    use PreventsSavingStacheItemsToDisk;

    protected function defineEnvironment($app): void
    {
        $app['config']->set('ai_gateway.enabled', true);
        $app['config']->set('ai_gateway.token', 'test-integration-token');
        $app['config']->set('ai_gateway.tools.entry.create', true);
        $app['config']->set('ai_gateway.tools.entry.update', true);
        $app['config']->set('ai_gateway.tools.entry.upsert', true);
        $app['config']->set('ai_gateway.allowed_collections', ['pages']);
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => 'Bearer test-integration-token'];
    }

    // --- entry.create ---

    public function test_create_entry_in_allowed_collection(): void
    {
        Collection::make('pages')->save();

        $response = $this->postJson('/ai-gateway/execute', [
            'tool' => 'entry.create',
            'arguments' => [
                'collection' => 'pages',
                'slug' => 'hello-world',
                'data' => ['title' => 'Hello World'],
            ],
        ], $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJson([
            'ok' => true,
            'tool' => 'entry.create',
            'result' => [
                'status' => 'created',
                'target_type' => 'entry',
                'target' => [
                    'collection' => 'pages',
                    'slug' => 'hello-world',
                    'site' => 'default',
                ],
            ],
        ]);
    }

    public function test_create_duplicate_entry_returns_409_conflict(): void
    {
        Collection::make('pages')->save();
        Entry::make()->collection('pages')->slug('existing')->data(['title' => 'Existing'])->save();

        $response = $this->postJson('/ai-gateway/execute', [
            'tool' => 'entry.create',
            'arguments' => [
                'collection' => 'pages',
                'slug' => 'existing',
                'data' => ['title' => 'Duplicate'],
            ],
        ], $this->authHeaders());

        $response->assertStatus(409);
        $response->assertJson([
            'ok' => false,
            'error' => ['code' => 'conflict'],
        ]);
    }

    public function test_create_entry_in_nonexistent_collection_returns_404(): void
    {
        $response = $this->postJson('/ai-gateway/execute', [
            'tool' => 'entry.create',
            'arguments' => [
                'collection' => 'pages',
                'slug' => 'test',
                'data' => ['title' => 'Test'],
            ],
        ], $this->authHeaders());

        $response->assertStatus(404);
        $response->assertJson([
            'ok' => false,
            'error' => ['code' => 'resource_not_found'],
        ]);
    }

    public function test_create_entry_in_disallowed_collection_returns_403(): void
    {
        $response = $this->postJson('/ai-gateway/execute', [
            'tool' => 'entry.create',
            'arguments' => [
                'collection' => 'secrets',
                'slug' => 'test',
                'data' => ['title' => 'Test'],
            ],
        ], $this->authHeaders());

        $response->assertStatus(403);
        $response->assertJson([
            'ok' => false,
            'error' => ['code' => 'forbidden'],
        ]);
    }

    // --- entry.update ---

    public function test_update_existing_entry(): void
    {
        Collection::make('pages')->save();
        Entry::make()->collection('pages')->slug('about')->data(['title' => 'About'])->save();

        $response = $this->postJson('/ai-gateway/execute', [
            'tool' => 'entry.update',
            'arguments' => [
                'collection' => 'pages',
                'slug' => 'about',
                'data' => ['title' => 'About Us'],
            ],
        ], $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJson([
            'ok' => true,
            'tool' => 'entry.update',
            'result' => [
                'status' => 'updated',
                'target_type' => 'entry',
            ],
        ]);
    }

    public function test_update_nonexistent_entry_returns_404(): void
    {
        Collection::make('pages')->save();

        $response = $this->postJson('/ai-gateway/execute', [
            'tool' => 'entry.update',
            'arguments' => [
                'collection' => 'pages',
                'slug' => 'nonexistent',
                'data' => ['title' => 'Nope'],
            ],
        ], $this->authHeaders());

        $response->assertStatus(404);
        $response->assertJson([
            'ok' => false,
            'error' => ['code' => 'resource_not_found'],
        ]);
    }

    // --- entry.upsert ---

    public function test_upsert_creates_new_entry(): void
    {
        Collection::make('pages')->save();

        $response = $this->postJson('/ai-gateway/execute', [
            'tool' => 'entry.upsert',
            'arguments' => [
                'collection' => 'pages',
                'slug' => 'new-page',
                'data' => ['title' => 'New Page'],
            ],
        ], $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJson([
            'ok' => true,
            'tool' => 'entry.upsert',
            'result' => [
                'status' => 'created',
                'target_type' => 'entry',
            ],
        ]);
    }

    public function test_upsert_updates_existing_entry(): void
    {
        Collection::make('pages')->save();
        Entry::make()->collection('pages')->slug('existing')->data(['title' => 'Old'])->save();

        $response = $this->postJson('/ai-gateway/execute', [
            'tool' => 'entry.upsert',
            'arguments' => [
                'collection' => 'pages',
                'slug' => 'existing',
                'data' => ['title' => 'Updated'],
            ],
        ], $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJson([
            'ok' => true,
            'tool' => 'entry.upsert',
            'result' => [
                'status' => 'updated',
                'target_type' => 'entry',
            ],
        ]);
    }

    // --- Full pipeline: request_id round-trip ---

    public function test_request_id_is_echoed_in_response(): void
    {
        Collection::make('pages')->save();

        $response = $this->postJson('/ai-gateway/execute', [
            'tool' => 'entry.create',
            'arguments' => [
                'collection' => 'pages',
                'slug' => 'with-request-id',
                'data' => ['title' => 'Test'],
            ],
            'request_id' => 'req_test_123',
        ], $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('meta.request_id', 'req_test_123');
    }
}
