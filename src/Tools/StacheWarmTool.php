<?php

namespace Stokoe\AiGateway\Tools;

use Illuminate\Support\Facades\Artisan;
use Stokoe\AiGateway\Exceptions\ToolValidationException;
use Stokoe\AiGateway\Support\ToolResponse;
use Stokoe\AiGateway\Tools\Contracts\GatewayTool;

class StacheWarmTool implements GatewayTool
{
    public function name(): string
    {
        return 'stache.warm';
    }

    public function targetType(): string
    {
        return 'cache';
    }

    public function validationRules(): array
    {
        return [];
    }

    public function resolveTarget(array $arguments): ?string
    {
        return 'stache';
    }

    public function execute(array $arguments): ToolResponse
    {
        $this->rejectUnknownKeys($arguments);

        Artisan::call('statamic:stache:warm');

        return ToolResponse::success($this->name(), [
            'status'      => 'warmed',
            'target_type' => $this->targetType(),
            'target'      => [
                'cache_target' => 'stache',
            ],
        ]);
    }

    public function describe(): array
    {
        return [
            'name' => $this->name(),
            'description' => 'Warms the Stache cache by rebuilding all content indexes.',
            'target_type' => $this->targetType(),
            'arguments' => [],
            'example' => [
                'tool' => $this->name(),
                'arguments' => (object) [],
            ],
            'response_example' => [
                'ok' => true,
                'tool' => $this->name(),
                'result' => [
                    'status' => 'warmed',
                    'target_type' => 'cache',
                    'target' => [
                        'cache_target' => 'stache',
                    ],
                ],
            ],
            'errors' => [],
            'notes' => [
                'This rebuilds all Stache indexes from content files.',
                'Useful after clearing the Stache or deploying new content.',
            ],
        ];
    }

    public function requiresConfirmation(string $environment): bool
    {
        $environments = config('ai_gateway.confirmation.tools.stache.warm', []);

        return in_array($environment, $environments, true);
    }

    private function rejectUnknownKeys(array $arguments): void
    {
        if (! empty($arguments)) {
            throw new ToolValidationException(
                'Unknown argument keys: ' . implode(', ', array_keys($arguments)),
                ['unknown_keys' => array_keys($arguments)],
            );
        }
    }
}
