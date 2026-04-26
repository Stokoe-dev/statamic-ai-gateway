<?php

namespace Stokoe\AiGateway\Tools;

use Stokoe\AiGateway\Exceptions\ToolValidationException;
use Stokoe\AiGateway\Support\ToolResponse;
use Stokoe\AiGateway\Tools\Contracts\GatewayTool;
use Statamic\Facades\AssetContainer;

class AssetGetTool implements GatewayTool
{
    public function name(): string
    {
        return 'asset.get';
    }

    public function targetType(): string
    {
        return 'asset';
    }

    public function validationRules(): array
    {
        return [
            'container' => ['required', 'string'],
            'path'      => ['required', 'string'],
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
        $path = $arguments['path'];

        $container = AssetContainer::findByHandle($containerHandle);

        if (! $container) {
            return ToolResponse::error(
                $this->name(),
                'resource_not_found',
                "Asset container '{$containerHandle}' does not exist.",
                404,
            );
        }

        $asset = $container->asset($path);

        if (! $asset) {
            return ToolResponse::error(
                $this->name(),
                'resource_not_found',
                "Asset '{$path}' does not exist in container '{$containerHandle}'.",
                404,
            );
        }

        $data = [
            'id'            => $asset->id(),
            'path'          => $asset->path(),
            'url'           => $asset->url(),
            'size'          => $asset->size(),
            'last_modified' => $asset->lastModified()->toIso8601String(),
            'alt'           => $asset->get('alt'),
            'mime_type'     => $asset->mimeType(),
        ];

        if ($asset->isImage()) {
            $data['width'] = $asset->width();
            $data['height'] = $asset->height();
        }

        return ToolResponse::success($this->name(), [
            'target_type' => $this->targetType(),
            'target'      => [
                'container' => $containerHandle,
                'path'      => $path,
            ],
            'asset' => $data,
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
            'description' => 'Retrieves a single asset\'s metadata by container and path.',
            'target_type' => $this->targetType(),
            'arguments' => [
                'container' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'The handle of the asset container.',
                    'default' => null,
                ],
                'path' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'The path of the asset within the container.',
                    'default' => null,
                ],
            ],
            'example' => [
                'tool' => $this->name(),
                'arguments' => [
                    'container' => 'assets',
                    'path' => 'images/hero.jpg',
                ],
            ],
            'response_example' => [
                'ok' => true,
                'tool' => $this->name(),
                'result' => [
                    'target_type' => 'asset',
                    'target' => [
                        'container' => 'assets',
                        'path' => 'images/hero.jpg',
                    ],
                    'asset' => [
                        'id' => 'assets::images/hero.jpg',
                        'path' => 'images/hero.jpg',
                        'url' => '/assets/images/hero.jpg',
                        'size' => 102400,
                        'last_modified' => '2024-01-15T10:30:00+00:00',
                        'alt' => 'Hero image',
                        'mime_type' => 'image/jpeg',
                        'width' => 1920,
                        'height' => 1080,
                    ],
                ],
            ],
            'errors' => [
                'resource_not_found' => 'When the asset container or asset does not exist.',
            ],
            'notes' => [
                'Image dimensions (width, height) are only included when the asset is an image.',
                'This is a read-only tool that does not require confirmation.',
            ],
        ];
    }

    private function rejectUnknownKeys(array $arguments): void
    {
        $allowed = ['container', 'path'];
        $unknown = array_diff(array_keys($arguments), $allowed);

        if (! empty($unknown)) {
            throw new ToolValidationException(
                'Unknown argument keys: ' . implode(', ', $unknown),
                ['unknown_keys' => array_values($unknown)],
            );
        }
    }
}
