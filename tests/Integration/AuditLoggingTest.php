<?php

namespace Stokoe\AiGateway\Tests\Integration;

use Illuminate\Support\Facades\Log;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;
use Stokoe\AiGateway\Tests\TestCase;

class AuditLoggingTest extends TestCase
{
    use PreventsSavingStacheItemsToDisk;

    protected function defineEnvironment($app): void
    {
        $app['config']->set('ai_gateway.enabled', true);
        $app['config']->set('ai_gateway.token', 'test-integration-token');
        $app['config']->set('ai_gateway.tools.entry.create', true);
        $app['config']->set('ai_gateway.tools.entry.update', true);
        $app['config']->set('ai_gateway.allowed_collections', ['pages']);
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => 'Bearer test-integration-token'];
    }

    public function test_successful_request_writes_audit_log(): void
    {
        Collection::make('pages')->save();

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return $message === 'ai_gateway.audit'
                    && $context['tool'] === 'entry.create'
                    && $context['status'] === 'succeeded'
                    && $context['http_status'] === 200;
            });

        $this->postJson('/ai-gateway/execute', [
            'tool' => 'entry.create',
            'arguments' => [
                'collection' => 'pages',
                'slug' => 'audit-test',
                'data' => ['title' => 'Audit Test'],
            ],
        ], $this->authHeaders());
    }

    public function test_failed_request_writes_audit_log(): void
    {
        Collection::make('pages')->save();

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return $message === 'ai_gateway.audit'
                    && $context['tool'] === 'entry.update'
                    && $context['status'] === 'failed'
                    && $context['http_status'] === 404;
            });

        $this->postJson('/ai-gateway/execute', [
            'tool' => 'entry.update',
            'arguments' => [
                'collection' => 'pages',
                'slug' => 'nonexistent',
                'data' => ['title' => 'Nope'],
            ],
        ], $this->authHeaders());
    }

    public function test_rejected_request_writes_audit_log(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return $message === 'ai_gateway.audit'
                    && $context['tool'] === 'entry.create'
                    && $context['status'] === 'rejected'
                    && $context['error_code'] === 'forbidden';
            });

        $this->postJson('/ai-gateway/execute', [
            'tool' => 'entry.create',
            'arguments' => [
                'collection' => 'secrets',
                'slug' => 'test',
                'data' => ['title' => 'Test'],
            ],
        ], $this->authHeaders());
    }
}
