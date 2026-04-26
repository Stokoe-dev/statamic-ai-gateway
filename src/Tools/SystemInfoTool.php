<?php

namespace Stokoe\AiGateway\Tools;

use Stokoe\AiGateway\Exceptions\ToolValidationException;
use Stokoe\AiGateway\Support\ToolResponse;
use Stokoe\AiGateway\Tools\Contracts\GatewayTool;
use Statamic\Statamic;

class SystemInfoTool implements GatewayTool
{
    public function name(): string
    {
        return 'system.info';
    }

    public function targetType(): string
    {
        return 'system';
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

        $addonVersion = $this->resolveAddonVersion();

        return ToolResponse::success($this->name(), [
            'target_type' => $this->targetType(),
            'system' => [
                'statamic_version' => Statamic::version(),
                'laravel_version' => app()->version(),
                'php_version' => phpversion(),
                'environment' => app()->environment(),
                'addon_version' => $addonVersion,
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
            'description' => 'Returns system version and environment information including Statamic, Laravel, PHP versions, environment name, and addon version.',
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
                    'target_type' => 'system',
                    'system' => [
                        'statamic_version' => '6.0.0',
                        'laravel_version' => '12.0.0',
                        'php_version' => '8.3.0',
                        'environment' => 'production',
                        'addon_version' => '1.0.0',
                    ],
                ],
            ],
            'errors' => [],
            'notes' => [
                'This is a read-only tool that does not require confirmation.',
                'No allowlist authorization is needed — only tool-level enablement.',
            ],
        ];
    }

    private function resolveAddonVersion(): string
    {
        $composerPath = base_path('vendor/stokoe/ai-gateway/composer.json');

        if (file_exists($composerPath)) {
            $composer = json_decode(file_get_contents($composerPath), true);

            if (isset($composer['version'])) {
                return $composer['version'];
            }
        }

        // Fallback: try to read from installed packages
        $installedPath = base_path('vendor/composer/installed.json');

        if (file_exists($installedPath)) {
            $installed = json_decode(file_get_contents($installedPath), true);
            $packages = $installed['packages'] ?? $installed;

            foreach ($packages as $package) {
                if (($package['name'] ?? '') === 'stokoe/ai-gateway') {
                    return $package['version'] ?? 'unknown';
                }
            }
        }

        return 'dev';
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
