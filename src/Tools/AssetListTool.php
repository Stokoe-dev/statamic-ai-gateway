<?php

namespace Stokoe\AiGateway\Tools;

use Stokoe\AiGateway\Exceptions\ToolValidationException;
use Stokoe\AiGateway\Support\ToolResponse;
use Stokoe\AiGateway\Tools\Contracts\GatewayTool;
use Statamic\Facades\AssetContainer;

class AssetListTool implements GatewayTool
{
    public function name(): string
    {
        return 'asset.list';
    }

    public function targetType(): string
    {
        return 'asset';
    }

    public function validationRules(): array
    {
        return [
            'container' => ['required', 'string'],
            'path'      => ['sometimes', 'string'],
            'limit'     => ['sometimes', 'integer', 'min:1', 'max:100'],
            'offset'    => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function resolveTarget(array $arguments): ?string
    {
        return $arguments['container'] ?? null;
    }

    public function execute(array $arguments): ToolResponse
    {
        $this->rejectUnknownKeys($arguments);

        $containerHandle = $arguments['container'];
        $pathPrefix = $arguments['path'] ?? null;
        $limit = $arguments['limit'] ?? 25;
        $offset = $arguments['offset'] ?? 0;

        $container = AssetContainer::findByHandle($containerHandle);

        if (! $container) {
            return ToolResponse::error(
                $this->name(),
                'resource_not_found',
                "Asset container '{$containerHandle}' does not exist.",
                404,
            );
        }

        $assets = $container->assets();

        if ($pathPrefix !== null) {
            $assets = $assets->filter(fn ($asset) => str_starts_with($asset->path(), $pathPrefix));
        }

        $total = $assets->count();

        $items = $assets
            ->values()
            ->slice($offset, $limit)
            ->map(fn ($asset) => [
                'id'            => $asset->id(),
                'path'          => $asset->path(),
                'url'           => $asset->url(),
                'size'          => $asset->size(),
                'last_modified' => $asset->lastModified()->toIso8601String(),
            ])
            ->values()
            ->toArray();

        return ToolResponse::success($this->name(), [
            'target_type' => $this->targetType(),
            'target'      => [
                'container' => $containerHandle,
            ],
            'assets'     => $items,
            'pagination' => [
                'total'  => $total,
                'limit'  => $limit,
                'offset' => $offset,
            ],
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
            'description' => 'Lists assets in a container with optional path prefix filtering and pagination.',
            'target_type' => $this->targetType(),
            'arguments' => [
                'container' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'The handle of the asset container to list assets from.',
                    'default' => null,
                ],
                'path' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'Optional path prefix to filter assets by.',
                    'default' => null,
                ],
                'limit' => [
                    'type' => 'integer',
                    'required' => false,
                    'description' => 'Maximum number of assets to return (1-100).',
                    'default' => 25,
                ],
                'offset' => [
                    'type' => 'integer',
                    'required' => false,
                    'description' => 'Number of assets to skip for pagination.',
                    'default' => 0,
                ],
            ],
            'example' => [
                'tool' => $this->name(),
                'arguments' => [
                    'container' => 'assets',
                    'path' => 'images/',
                    'limit' => 10,
                    'offset' => 0,
                ],
            ],
            'response_example' => [
                'ok' => true,
                'tool' => $this->name(),
                'result' => [
                    'target_type' => 'asset',
                    'target' => [
                        'container' => 'assets',
                    ],
                    'assets' => [
                        [
                            'id' => 'assets::images/hero.jpg',
                            'path' => 'images/hero.jpg',
                            'url' => '/assets/images/hero.jpg',
                            'size' => 102400,
                            'last_modified' => '2024-01-15T10:30:00+00:00',
                        ],
                    ],
                    'pagination' => [
                        'total' => 1,
                        'limit' => 10,
                        'offset' => 0,
                    ],
                ],
            ],
            'errors' => [
                'resource_not_found' => 'When the asset container does not exist.',
            ],
            'notes' => [
                'Returns pagination info with total count, limit, and offset.',
                'Use the path argument to filter assets by path prefix.',
            ],
        ];
    }

    private function rejectUnknownKeys(array $arguments): void
    {
        $allowed = ['container', 'path', 'limit', 'offset'];
        $unknown = array_diff(array_keys($arguments), $allowed);

        if (! empty($unknown)) {
            throw new ToolValidationException(
                'Unknown argument keys: ' . implode(', ', $unknown),
                ['unknown_keys' => array_values($unknown)],
            );
        }
    }
}
