<?php

namespace Stokoe\AiGateway\Tools;

use Stokoe\AiGateway\Exceptions\ToolValidationException;
use Stokoe\AiGateway\Support\ToolResponse;
use Stokoe\AiGateway\Tools\Contracts\GatewayTool;
use Statamic\Facades\AssetContainer;

class AssetDeleteTool implements GatewayTool
{
    public function name(): string
    {
        return 'asset.delete';
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

        $asset->delete();

        return ToolResponse::success($this->name(), [
            'status'      => 'deleted',
            'target_type' => $this->targetType(),
            'target'      => [
                'container' => $containerHandle,
                'path'      => $path,
            ],
        ]);
    }

    public function requiresConfirmation(string $environment): bool
    {
        $environments = config('ai_gateway.confirmation.tools.asset.delete', []);

        return in_array($environment, $environments, true);
    }

    public function describe(): array
    {
        return [
            'name' => $this->name(),
            'description' => 'Deletes an asset from a container.',
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
                    'path' => 'images/old-hero.jpg',
                ],
            ],
            'response_example' => [
                'ok' => true,
                'tool' => $this->name(),
                'result' => [
                    'status' => 'deleted',
                    'target_type' => 'asset',
                    'target' => [
                        'container' => 'assets',
                        'path' => 'images/old-hero.jpg',
                    ],
                ],
            ],
            'errors' => [
                'resource_not_found' => 'When the asset container or asset does not exist.',
            ],
            'notes' => [
                'This is a destructive operation that requires confirmation in configured environments.',
                'The asset file will be permanently removed from the container.',
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
