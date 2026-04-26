<?php

namespace Stokoe\AiGateway\Tools;

use Stokoe\AiGateway\Exceptions\ToolValidationException;
use Stokoe\AiGateway\Support\ToolResponse;
use Stokoe\AiGateway\Tools\Contracts\GatewayTool;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Asset;

class AssetUploadTool implements GatewayTool
{
    public function name(): string
    {
        return 'asset.upload';
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
            'file'      => ['required', 'string'],
            'alt'       => ['sometimes', 'string'],
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
        $base64 = $arguments['file'];
        $alt = $arguments['alt'] ?? null;

        // Decode base64 and validate size
        $decoded = base64_decode($base64, true);

        if ($decoded === false) {
            return ToolResponse::error(
                $this->name(),
                'validation_failed',
                'The file field is not valid base64.',
                422,
            );
        }

        $maxSize = config('ai_gateway.max_asset_size', 10485760);

        if (strlen($decoded) > $maxSize) {
            return ToolResponse::error(
                $this->name(),
                'validation_failed',
                "The decoded file size exceeds the maximum allowed size of {$maxSize} bytes.",
                422,
            );
        }

        // Validate file extension
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $allowedExtensions = config('ai_gateway.allowed_asset_extensions', []);

        if (! in_array($extension, $allowedExtensions, true)) {
            return ToolResponse::error(
                $this->name(),
                'validation_failed',
                "The file extension '{$extension}' is not allowed. Allowed extensions: " . implode(', ', $allowedExtensions),
                422,
            );
        }

        // Check container exists
        $container = AssetContainer::findByHandle($containerHandle);

        if (! $container) {
            return ToolResponse::error(
                $this->name(),
                'resource_not_found',
                "Asset container '{$containerHandle}' does not exist.",
                404,
            );
        }

        // Store the file in the container
        $container->disk()->put($path, $decoded);

        // Create or find the asset in Statamic
        $asset = Asset::findById("{$containerHandle}::{$path}");

        if (! $asset) {
            $asset = Asset::make()
                ->container($container)
                ->path($path);
        }

        if ($alt !== null) {
            $asset->set('alt', $alt);
        }

        $asset->save();

        return ToolResponse::success($this->name(), [
            'status'      => 'uploaded',
            'target_type' => $this->targetType(),
            'target'      => [
                'container' => $containerHandle,
                'path'      => $path,
            ],
            'url' => $asset->url(),
            'id'  => $asset->id(),
        ]);
    }

    public function requiresConfirmation(string $environment): bool
    {
        $environments = config('ai_gateway.confirmation.tools.asset.upload', []);

        return in_array($environment, $environments, true);
    }

    public function describe(): array
    {
        return [
            'name' => $this->name(),
            'description' => 'Uploads a base64-encoded file to a Statamic asset container.',
            'target_type' => $this->targetType(),
            'arguments' => [
                'container' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'The handle of the asset container to upload to.',
                    'default' => null,
                ],
                'path' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'The path (including filename) where the asset should be stored.',
                    'default' => null,
                ],
                'file' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'The base64-encoded file content.',
                    'default' => null,
                ],
                'alt' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'Optional alt text for the asset.',
                    'default' => null,
                ],
            ],
            'example' => [
                'tool' => $this->name(),
                'arguments' => [
                    'container' => 'assets',
                    'path' => 'images/hero.jpg',
                    'file' => '<base64-encoded-content>',
                    'alt' => 'Hero image',
                ],
            ],
            'response_example' => [
                'ok' => true,
                'tool' => $this->name(),
                'result' => [
                    'status' => 'uploaded',
                    'target_type' => 'asset',
                    'target' => [
                        'container' => 'assets',
                        'path' => 'images/hero.jpg',
                    ],
                    'url' => '/assets/images/hero.jpg',
                    'id' => 'assets::images/hero.jpg',
                ],
            ],
            'errors' => [
                'resource_not_found' => 'When the asset container does not exist.',
                'validation_failed' => 'When the file exceeds max size or has a disallowed extension.',
            ],
            'notes' => [
                'The file must be base64-encoded.',
                'The decoded file size is validated against the max_asset_size config.',
                'The file extension is validated against the allowed_asset_extensions config.',
            ],
        ];
    }

    private function rejectUnknownKeys(array $arguments): void
    {
        $allowed = ['container', 'path', 'file', 'alt'];
        $unknown = array_diff(array_keys($arguments), $allowed);

        if (! empty($unknown)) {
            throw new ToolValidationException(
                'Unknown argument keys: ' . implode(', ', $unknown),
                ['unknown_keys' => array_values($unknown)],
            );
        }
    }
}
