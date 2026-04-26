<?php

namespace Stokoe\AiGateway\Tools;

use Stokoe\AiGateway\Exceptions\ToolValidationException;
use Stokoe\AiGateway\Support\ToolResponse;
use Stokoe\AiGateway\Tools\Contracts\GatewayTool;
use Statamic\Facades\Nav;

class NavigationListTool implements GatewayTool
{
    public function name(): string
    {
        return 'navigation.list';
    }

    public function targetType(): string
    {
        return 'navigation';
    }

    public function validationRules(): array
    {
        return [];
    }

    public function resolveTarget(array $arguments): ?string
    {
        return null;
    }

    public function execute(array $arguments): ToolResponse
    {
        $this->rejectUnknownKeys($arguments);

        $allowlist = config('ai_gateway.allowed_navigations', []);

        $navigations = Nav::all()
            ->filter(fn ($nav) => in_array($nav->handle(), $allowlist, true))
            ->map(fn ($nav) => [
                'handle' => $nav->handle(),
                'title' => $nav->title(),
                'max_depth' => $nav->maxDepth(),
            ])
            ->values()
            ->toArray();

        return ToolResponse::success($this->name(), [
            'target_type' => $this->targetType(),
            'navigations' => $navigations,
        ]);
    }

    public function requiresConfirmation(string $environment): bool
    {
        return false;
    }

    public function describe(): array
    {
        return [
            'name' => $this->name(),
            'description' => 'Lists all navigations in the allowed_navigations allowlist with handle, title, and max depth.',
            'target_type' => $this->targetType(),
            'arguments' => [],
            'example' => [
                'tool' => $this->name(),
                'arguments' => [],
            ],
            'response_example' => [
                'ok' => true,
                'tool' => $this->name(),
                'result' => [
                    'target_type' => 'navigation',
                    'navigations' => [
                        [
                            'handle' => 'main',
                            'title' => 'Main Navigation',
                            'max_depth' => 3,
                        ],
                    ],
                ],
            ],
            'errors' => [],
            'notes' => [
                'Returns only navigations that appear in the allowed_navigations allowlist.',
                'This is a read-only tool that does not require confirmation.',
            ],
        ];
    }

    private function rejectUnknownKeys(array $arguments): void
    {
        $allowed = [];
        $unknown = array_diff(array_keys($arguments), $allowed);

        if (! empty($unknown)) {
            throw new ToolValidationException(
                'Unknown argument keys: ' . implode(', ', $unknown),
                ['unknown_keys' => array_values($unknown)],
            );
        }
    }
}
