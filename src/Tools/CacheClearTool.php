<?php

namespace Stokoe\AiGateway\Tools;

use Illuminate\Support\Facades\Artisan;
use Stokoe\AiGateway\Exceptions\ToolValidationException;
use Stokoe\AiGateway\Support\ToolResponse;
use Stokoe\AiGateway\Tools\Contracts\GatewayTool;

class CacheClearTool implements GatewayTool
{
    private const ALLOWED_TARGETS = ['application', 'static', 'stache', 'glide'];

    private const ARTISAN_COMMANDS = [
        'application' => 'cache:clear',
        'static'      => 'statamic:static:clear',
        'stache'      => 'statamic:stache:clear',
        'glide'       => 'statamic:glide:clear',
    ];

    public function name(): string
    {
        return 'cache.clear';
    }

    public function targetType(): string
    {
        return 'cache';
    }

    public function validationRules(): array
    {
        return [
            'target' => ['required', 'string', 'in:application,static,stache,glide'],
        ];
    }

    public function resolveTarget(array $arguments): ?string
    {
        return $arguments['target'] ?? null;
    }

    public function execute(array $arguments): ToolResponse
    {
        $this->rejectUnknownKeys($arguments);

        $target = $arguments['target'];

        if (! in_array($target, self::ALLOWED_TARGETS, true)) {
            throw new ToolValidationException(
                "Invalid cache target '{$target}'. Must be one of: " . implode(', ', self::ALLOWED_TARGETS),
                ['target' => ["The target must be one of: " . implode(', ', self::ALLOWED_TARGETS)]],
            );
        }

        Artisan::call(self::ARTISAN_COMMANDS[$target]);

        return ToolResponse::success($this->name(), [
            'status'      => 'cleared',
            'target_type' => $this->targetType(),
            'target'      => [
                'cache_target' => $target,
            ],
        ]);
    }

    public function describe(): array
    {
        return [
            'name' => $this->name(),
            'description' => 'Clears a specific cache target (application, static, stache, or glide).',
            'target_type' => $this->targetType(),
            'arguments' => [
                'target' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'The cache target to clear. Must be one of: application, static, stache, glide.',
                    'default' => null,
                ],
            ],
            'example' => [
                'tool' => $this->name(),
                'arguments' => [
                    'target' => 'stache',
                ],
            ],
            'response_example' => [
                'ok' => true,
                'tool' => $this->name(),
                'result' => [
                    'status' => 'cleared',
                    'target_type' => 'cache',
                    'target' => [
                        'cache_target' => 'stache',
                    ],
                ],
            ],
            'errors' => [
                'validation_failed' => 'When the target is not one of the allowed values.',
            ],
            'notes' => [
                'Confirmation-gated in production by default. Requires user approval before confirming.',
                'Allowed targets: application, static, stache, glide.',
            ],
        ];
    }

    public function requiresConfirmation(string $environment): bool
    {
        $environments = config('ai_gateway.confirmation.tools.cache.clear', []);

        return in_array($environment, $environments, true);
    }

    private function rejectUnknownKeys(array $arguments): void
    {
        $allowed = ['target'];
        $unknown = array_diff(array_keys($arguments), $allowed);

        if (! empty($unknown)) {
            throw new ToolValidationException(
                'Unknown argument keys: ' . implode(', ', $unknown),
                ['unknown_keys' => array_values($unknown)],
            );
        }
    }
}
