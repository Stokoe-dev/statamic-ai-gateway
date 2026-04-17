<?php

namespace Stokoe\AiGateway\Tools;

use Stokoe\AiGateway\Exceptions\ToolValidationException;
use Stokoe\AiGateway\Support\ToolResponse;
use Stokoe\AiGateway\Tools\Contracts\GatewayTool;
use Statamic\Facades\Nav;

class NavigationUpdateTool implements GatewayTool
{
    public function name(): string
    {
        return 'navigation.update';
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
            'tree'   => ['required', 'array'],
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
        $tree = $arguments['tree'];

        // Find navigation
        $nav = Nav::findByHandle($handle);
        if (! $nav) {
            return ToolResponse::error(
                $this->name(),
                'resource_not_found',
                "Navigation '{$handle}' does not exist.",
                404,
            );
        }

        // Full replacement of the navigation tree
        $navTree = $nav->in($site);

        if (! $navTree) {
            $navTree = $nav->makeTree($site);
        }

        $navTree->tree($tree)->save();

        return ToolResponse::success($this->name(), [
            'status'      => 'updated',
            'target_type' => $this->targetType(),
            'target'      => [
                'handle' => $handle,
                'site'   => $site,
            ],
        ]);
    }

    public function describe(): array
    {
        return [
            'name' => $this->name(),
            'description' => 'Replaces an entire navigation tree. This is a full replacement, not a partial patch.',
            'target_type' => $this->targetType(),
            'arguments' => [
                'handle' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'The handle of the navigation to update.',
                    'default' => null,
                ],
                'site' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'The site handle for multi-site installations.',
                    'default' => 'default',
                ],
                'tree' => [
                    'type' => 'array',
                    'required' => true,
                    'description' => 'The complete navigation tree to replace the existing one.',
                    'default' => null,
                ],
            ],
            'example' => [
                'tool' => $this->name(),
                'arguments' => [
                    'handle' => 'main_nav',
                    'tree' => [
                        ['page' => 'home', 'title' => 'Home', 'children' => []],
                        ['page' => 'about', 'title' => 'About', 'children' => []],
                    ],
                ],
            ],
            'response_example' => [
                'ok' => true,
                'tool' => $this->name(),
                'result' => [
                    'status' => 'updated',
                    'target_type' => 'navigation',
                    'target' => [
                        'handle' => 'main_nav',
                        'site' => 'default',
                    ],
                ],
            ],
            'errors' => [
                'resource_not_found' => 'When the navigation does not exist.',
            ],
            'notes' => [
                'This performs a FULL REPLACEMENT of the navigation tree, not a partial patch.',
                'Always send the complete tree structure. Any items not included will be removed.',
            ],
        ];
    }

    public function requiresConfirmation(string $environment): bool
    {
        return false;
    }

    private function rejectUnknownKeys(array $arguments): void
    {
        $allowed = ['handle', 'site', 'tree'];
        $unknown = array_diff(array_keys($arguments), $allowed);

        if (! empty($unknown)) {
            throw new ToolValidationException(
                'Unknown argument keys: ' . implode(', ', $unknown),
                ['unknown_keys' => array_values($unknown)],
            );
        }
    }
}
