<?php

namespace Stokoe\AiGateway\Tools;

use Stokoe\AiGateway\Exceptions\ToolValidationException;
use Stokoe\AiGateway\Support\ToolResponse;
use Stokoe\AiGateway\Tools\Contracts\GatewayTool;
use Statamic\Facades\Nav;

class NavigationGetTool implements GatewayTool
{
    public function name(): string
    {
        return 'navigation.get';
    }

    public function targetType(): string
    {
        return 'navigation';
    }

    public function validationRules(): array
    {
        return [
            'handle' => ['required', 'string'],
            'site'   => ['sometimes', 'string'],
        ];
    }

    public function resolveTarget(array $arguments): ?string
    {
        return $arguments['handle'] ?? null;
    }

    public function execute(array $arguments): ToolResponse
    {
        $this->rejectUnknownKeys($arguments);

        $handle = $arguments['handle'];
        $site = $arguments['site'] ?? 'default';

        $nav = Nav::findByHandle($handle);
        if (! $nav) {
            return ToolResponse::error(
                $this->name(),
                'resource_not_found',
                "Navigation '{$handle}' does not exist.",
                404,
            );
        }

        $navTree = $nav->in($site);
        $tree = $navTree ? $navTree->tree() : [];

        return ToolResponse::success($this->name(), [
            'target_type' => $this->targetType(),
            'target'      => [
                'handle' => $handle,
                'site'   => $site,
            ],
            'tree' => $tree,
        ]);
    }

    public function describe(): array
    {
        return [
            'name' => $this->name(),
            'description' => 'Retrieves a navigation tree by its handle.',
            'target_type' => $this->targetType(),
            'arguments' => [
                'handle' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'The handle of the navigation to retrieve.',
                    'default' => null,
                ],
                'site' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'The site handle for multi-site installations.',
                    'default' => 'default',
                ],
            ],
            'example' => [
                'tool' => $this->name(),
                'arguments' => [
                    'handle' => 'main_nav',
                    'site' => 'default',
                ],
            ],
            'response_example' => [
                'ok' => true,
                'tool' => $this->name(),
                'result' => [
                    'target_type' => 'navigation',
                    'target' => [
                        'handle' => 'main_nav',
                        'site' => 'default',
                    ],
                    'tree' => [
                        ['page' => 'home', 'title' => 'Home', 'children' => []],
                    ],
                ],
            ],
            'errors' => [
                'resource_not_found' => 'When the navigation does not exist.',
            ],
            'notes' => [],
        ];
    }

    public function requiresConfirmation(string $environment): bool
    {
        return false;
    }

    private function rejectUnknownKeys(array $arguments): void
    {
        $allowed = ['handle', 'site'];
        $unknown = array_diff(array_keys($arguments), $allowed);

        if (! empty($unknown)) {
            throw new ToolValidationException(
                'Unknown argument keys: ' . implode(', ', $unknown),
                ['unknown_keys' => array_values($unknown)],
            );
        }
    }
}
