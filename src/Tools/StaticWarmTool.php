<?php

namespace Stokoe\AiGateway\Tools;

use Illuminate\Support\Facades\Artisan;
use Stokoe\AiGateway\Exceptions\ToolValidationException;
use Stokoe\AiGateway\Support\ToolResponse;
use Stokoe\AiGateway\Tools\Contracts\GatewayTool;

class StaticWarmTool implements GatewayTool
{
    public function name(): string
    {
        return 'static.warm';
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
        return 'static';
    }

    public function execute(array $arguments): ToolResponse
    {
        $this->rejectUnknownKeys($arguments);

        Artisan::call('statamic:static:warm');

        return ToolResponse::success($this->name(), [
            'status'      => 'warmed',
            'target_type' => $this->targetType(),
            'target'      => [
                'cache_target' => 'static',
            ],
        ]);
    }

    public function describe(): array
    {
        return [
            'name' => $this->name(),
            'description' => 'Warms the static page cache by crawling all URLs.',
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
                        'cache_target' => 'static',
                    ],
                ],
            ],
            'errors' => [],
            'notes' => [
                'This crawls all URLs and pre-generates static cache files.',
                'Useful after clearing the static cache or deploying content changes.',
                'May take some time depending on the number of pages.',
            ],
        ];
    }

    public function requiresConfirmation(string $environment): bool
    {
        $environments = config('ai_gateway.confirmation.tools.static.warm', []);

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
