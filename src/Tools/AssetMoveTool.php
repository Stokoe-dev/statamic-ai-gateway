<?php

namespace Stokoe\AiGateway\Tools;

use Stokoe\AiGateway\Exceptions\ToolAuthorizationException;
use Stokoe\AiGateway\Exceptions\ToolValidationException;
use Stokoe\AiGateway\Policies\ToolPolicy;
use Stokoe\AiGateway\Support\ToolResponse;
use Stokoe\AiGateway\Tools\Contracts\GatewayTool;
use Statamic\Facades\Asset;
use Statamic\Facades\AssetContainer;

class AssetMoveTool implements GatewayTool
{
    public function name(): string
    {
        return 'asset.move';
    }

    public function targetType(): string
    {
        return 'asset';
    }

    public function validationRules(): array
    {
        return [
            'source_container'      => ['required', 'string'],
            'source_path'           => ['required', 'string'],
            'destination_path'      => ['required', 'string'],
            'destination_container' => ['sometimes', 'string'],
        ];
    }

    public function resolveTarget(array $arguments): ?string
    {
        return $arguments['source_container'] ?? null;
    }

    public function execute(array $arguments): ToolResponse
    {
        $this->rejectUnknownKeys($arguments);

        $sourceContainerHandle = $arguments['source_container'];
        $sourcePath = $arguments['source_path'];
        $destinationPath = $arguments['destination_path'];
        $destinationContainerHandle = $arguments['destination_container'] ?? $sourceContainerHandle;

        // Check destination container against allowlist internally
        if ($destinationContainerHandle !== $sourceContainerHandle) {
            $policy = app(ToolPolicy::class);

            if (! $policy->targetAllowed($this->targetType(), $destinationContainerHandle)) {
                throw new ToolAuthorizationException(
                    "Destination asset container '{$destinationContainerHandle}' is not in the allowed asset containers list."
                );
            }
        }

        // Resolve source container
        $sourceContainer = AssetContainer::findByHandle($sourceContainerHandle);

        if (! $sourceContainer) {
            return ToolResponse::error(
                $this->name(),
                'resource_not_found',
                "Asset container '{$sourceContainerHandle}' does not exist.",
                404,
            );
        }

        // Resolve destination container
        $destinationContainer = ($destinationContainerHandle === $sourceContainerHandle)
            ? $sourceContainer
            : AssetContainer::findByHandle($destinationContainerHandle);

        if (! $destinationContainer) {
            return ToolResponse::error(
                $this->name(),
                'resource_not_found',
                "Asset container '{$destinationContainerHandle}' does not exist.",
                404,
            );
        }

        // Check source asset exists
        $sourceAsset = $sourceContainer->asset($sourcePath);

        if (! $sourceAsset) {
            return ToolResponse::error(
                $this->name(),
                'resource_not_found',
                "Asset '{$sourcePath}' does not exist in container '{$sourceContainerHandle}'.",
                404,
            );
        }

        // Check destination does not already have an asset at that path
        $existingAsset = $destinationContainer->asset($destinationPath);

        if ($existingAsset) {
            return ToolResponse::error(
                $this->name(),
                'conflict',
                "An asset already exists at '{$destinationPath}' in container '{$destinationContainerHandle}'.",
                409,
            );
        }

        // Perform the move: copy content to destination, then delete source
        $contents = $sourceAsset->contents();
        $meta = $sourceAsset->data()->all();

        $destinationContainer->disk()->put($destinationPath, $contents);

        $newAsset = Asset::findById("{$destinationContainerHandle}::{$destinationPath}");

        if (! $newAsset) {
            $newAsset = Asset::make()
                ->container($destinationContainer)
                ->path($destinationPath);
        }

        foreach ($meta as $key => $value) {
            $newAsset->set($key, $value);
        }

        $newAsset->save();
        $sourceAsset->delete();

        // Resolve the final asset to return its URL and path
        $movedAsset = $destinationContainer->asset($destinationPath);

        return ToolResponse::success($this->name(), [
            'status'      => 'moved',
            'target_type' => $this->targetType(),
            'url'         => $movedAsset ? $movedAsset->url() : null,
            'path'        => $destinationPath,
            'container'   => $destinationContainerHandle,
        ]);
    }

    public function requiresConfirmation(string $environment): bool
    {
        $environments = config('ai_gateway.confirmation.tools.asset.move', []);

        return in_array($environment, $environments, true);
    }

    public function describe(): array
    {
        return [
            'name' => $this->name(),
            'description' => 'Moves an asset within or between containers.',
            'target_type' => $this->targetType(),
            'arguments' => [
                'source_container' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'The handle of the source asset container.',
                    'default' => null,
                ],
                'source_path' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'The path of the asset to move.',
                    'default' => null,
                ],
                'destination_path' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'The destination path for the asset.',
                    'default' => null,
                ],
                'destination_container' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'The handle of the destination asset container. Defaults to the source container.',
                    'default' => null,
                ],
            ],
            'example' => [
                'tool' => $this->name(),
                'arguments' => [
                    'source_container' => 'assets',
                    'source_path' => 'images/old-hero.jpg',
                    'destination_path' => 'images/archive/old-hero.jpg',
                ],
            ],
            'response_example' => [
                'ok' => true,
                'tool' => $this->name(),
                'result' => [
                    'status' => 'moved',
                    'target_type' => 'asset',
                    'url' => '/assets/images/archive/old-hero.jpg',
                    'path' => 'images/archive/old-hero.jpg',
                    'container' => 'assets',
                ],
            ],
            'errors' => [
                'resource_not_found' => 'When the source container, destination container, or source asset does not exist.',
                'conflict' => 'When an asset already exists at the destination path.',
            ],
            'notes' => [
                'This is a destructive operation that requires confirmation in configured environments.',
                'Both source and destination containers are checked against the asset container allowlist.',
                'If destination_container is omitted, the asset is moved within the source container.',
            ],
        ];
    }

    private function rejectUnknownKeys(array $arguments): void
    {
        $allowed = ['source_container', 'source_path', 'destination_path', 'destination_container'];
        $unknown = array_diff(array_keys($arguments), $allowed);

        if (! empty($unknown)) {
            throw new ToolValidationException(
                'Unknown argument keys: ' . implode(', ', $unknown),
                ['unknown_keys' => array_values($unknown)],
            );
        }
    }
}
