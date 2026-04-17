<?php

namespace Stokoe\AiGateway\Tools;

use Stokoe\AiGateway\Exceptions\ToolValidationException;
use Stokoe\AiGateway\Support\ToolResponse;
use Stokoe\AiGateway\Tools\Contracts\GatewayTool;
use Statamic\Facades\GlobalSet;

class GlobalGetTool implements GatewayTool
{
    public function name(): string
    {
        return 'global.get';
    }

    public function targetType(): string
    {
        return 'global';
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

        $globalSet = GlobalSet::findByHandle($handle);
        if (! $globalSet) {
            return ToolResponse::error(
                $this->name(),
                'resource_not_found',
                "Global set '{$handle}' does not exist.",
                404,
            );
        }

        $variables = $globalSet->inSelectedSite();
        $data = $variables ? $variables->data()->toArray() : [];

        return ToolResponse::success($this->name(), [
            'target_type' => $this->targetType(),
            'target'      => [
                'handle' => $handle,
                'site'   => $site,
            ],
            'data' => $data,
        ]);
    }

    public function describe(): array
    {
        return [
            'name' => $this->name(),
            'description' => 'Retrieves the values of a global set by its handle.',
            'target_type' => $this->targetType(),
            'arguments' => [
                'handle' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'The handle of the global set to retrieve.',
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
                    'handle' => 'contact_information',
                    'site' => 'default',
                ],
            ],
            'response_example' => [
                'ok' => true,
                'tool' => $this->name(),
                'result' => [
                    'target_type' => 'global',
                    'target' => [
                        'handle' => 'contact_information',
                        'site' => 'default',
                    ],
                    'data' => [
                        'email' => 'hello@example.com',
                        'phone' => '555-0100',
                    ],
                ],
            ],
            'errors' => [
                'resource_not_found' => 'When the global set does not exist.',
            ],
            'notes' => [
                'Denied fields are stripped from the response data.',
            ],
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
