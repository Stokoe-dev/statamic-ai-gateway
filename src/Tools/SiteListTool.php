<?php

namespace Stokoe\AiGateway\Tools;

use Stokoe\AiGateway\Exceptions\ToolValidationException;
use Stokoe\AiGateway\Support\ToolResponse;
use Stokoe\AiGateway\Tools\Contracts\GatewayTool;
use Statamic\Facades\Site;

class SiteListTool implements GatewayTool
{
    public function name(): string
    {
        return 'site.list';
    }

    public function targetType(): string
    {
        return 'site';
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

        $sites = Site::all()
            ->map(fn ($site) => [
                'handle' => $site->handle(),
                'name' => $site->name(),
                'locale' => $site->locale(),
                'url' => $site->url(),
            ])
            ->values()
            ->toArray();

        return ToolResponse::success($this->name(), [
            'target_type' => $this->targetType(),
            'sites' => $sites,
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
            'description' => 'Lists all configured Statamic sites with handle, name, locale, and URL.',
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
                    'target_type' => 'site',
                    'sites' => [
                        [
                            'handle' => 'default',
                            'name' => 'Default',
                            'locale' => 'en_US',
                            'url' => 'http://localhost',
                        ],
                    ],
                ],
            ],
            'errors' => [],
            'notes' => [
                'Returns all configured sites — no allowlist filtering is applied.',
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
