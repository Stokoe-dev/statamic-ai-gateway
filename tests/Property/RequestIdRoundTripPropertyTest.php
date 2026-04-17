<?php

namespace Stokoe\AiGateway\Tests\Property;

use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\Attributes\Test;
use Stokoe\AiGateway\Tests\TestCase;
use Stokoe\AiGateway\Http\Controllers\ToolExecutionController;
use Stokoe\AiGateway\Policies\ToolPolicy;
use Stokoe\AiGateway\Support\AuditLogger;
use Stokoe\AiGateway\Support\ConfirmationTokenManager;
use Stokoe\AiGateway\Support\FieldFilter;
use Stokoe\AiGateway\Support\ToolRegistry;
use Stokoe\AiGateway\Support\ToolResponse;
use Stokoe\AiGateway\Tools\Contracts\GatewayTool;
use Illuminate\Http\Request;

/**
 * Feature: ai-gateway, Property 3: Request ID round-trip
 *
 * For any string provided as `request_id` in the request envelope, the response
 * envelope's `meta.request_id` field contains the exact same string.
 *
 * **Validates: Requirements 3.6**
 */
class RequestIdRoundTripPropertyTest extends TestCase
{
    use TestTrait;

    private function makeController(): ToolExecutionController
    {
        return app(ToolExecutionController::class);
    }

    private function buildRequest(array $payload): Request
    {
        $json = json_encode($payload);

        $request = Request::create(
            '/ai-gateway/execute',
            'POST',
            [],
            [],
            [],
            [
                'CONTENT_TYPE'   => 'application/json',
                'HTTP_ACCEPT'    => 'application/json',
                'CONTENT_LENGTH' => strlen($json),
            ],
            $json,
        );

        return $request;
    }

    /**
     * Property 3a: request_id in the request appears unchanged in response meta
     * for successful tool executions.
     *
     * We use a stub tool that always succeeds to isolate the round-trip behavior.
     */
    #[Test]
    public function request_id_round_trips_through_successful_execution(): void
    {
        $this->forAll(
            Generators::string(),
        )
            ->withMaxSize(100)
            ->__invoke(function (string $requestId): void {
                // Register a stub tool that always succeeds
                $stubTool = new class implements GatewayTool {
                    public function name(): string { return 'test.stub'; }
                    public function targetType(): string { return 'entry'; }
                    public function validationRules(): array { return []; }
                    public function resolveTarget(array $arguments): ?string { return null; }
                    public function execute(array $arguments): ToolResponse {
                        return ToolResponse::success('test.stub', ['status' => 'ok']);
                    }
                    public function requiresConfirmation(string $environment): bool { return false; }
                    public function describe(): array { return ['name' => 'test.stub']; }
                };

                // Set up config so the stub tool is enabled and no target auth needed
                config([
                    'ai_gateway.enabled' => true,
                    'ai_gateway.tools.test.stub' => true,
                ]);

                // Create a custom registry with the stub tool
                $registry = $this->createStub(ToolRegistry::class);
                $registry->method('resolve')->willReturn($stubTool);

                $policy = $this->createStub(ToolPolicy::class);
                $policy->method('toolEnabled')->willReturn(true);
                $policy->method('targetAllowed')->willReturn(true);
                $policy->method('deniedFields')->willReturn([]);

                $controller = new ToolExecutionController(
                    $registry,
                    $policy,
                    new FieldFilter(),
                    new ConfirmationTokenManager(),
                    new AuditLogger(),
                );

                $payload = [
                    'tool'       => 'test.stub',
                    'arguments'  => [],
                    'request_id' => $requestId,
                ];

                $request = $this->buildRequest($payload);
                $response = $controller->execute($request);
                $data = json_decode($response->getContent(), true);

                $this->assertArrayHasKey('meta', $data);
                $this->assertArrayHasKey('request_id', $data['meta']);
                $this->assertSame(
                    $requestId,
                    $data['meta']['request_id'],
                    'request_id must round-trip exactly through the response meta',
                );
            });
    }

    /**
     * Property 3b: request_id in the request appears unchanged in response meta
     * even for error responses (e.g. validation failures).
     */
    #[Test]
    public function request_id_round_trips_through_error_responses(): void
    {
        $this->forAll(
            Generators::string(),
        )
            ->withMaxSize(100)
            ->__invoke(function (string $requestId): void {
                config([
                    'ai_gateway.enabled' => true,
                ]);

                $controller = $this->makeController();

                // Send a request with a non-existent tool to trigger tool_not_found
                $payload = [
                    'tool'       => 'nonexistent.tool.xyz',
                    'arguments'  => [],
                    'request_id' => $requestId,
                ];

                $request = $this->buildRequest($payload);
                $response = $controller->execute($request);
                $data = json_decode($response->getContent(), true);

                $this->assertArrayHasKey('meta', $data);
                $this->assertArrayHasKey('request_id', $data['meta']);
                $this->assertSame(
                    $requestId,
                    $data['meta']['request_id'],
                    'request_id must round-trip exactly even in error responses',
                );
            });
    }
}
