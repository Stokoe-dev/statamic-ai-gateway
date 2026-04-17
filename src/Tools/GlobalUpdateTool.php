<?php

namespace Stokoe\AiGateway\Tools;

use Stokoe\AiGateway\Exceptions\ToolValidationException;
use Stokoe\AiGateway\Support\ToolResponse;
use Stokoe\AiGateway\Tools\Contracts\GatewayTool;
use Statamic\Facades\GlobalSet;

class GlobalUpdateTool implements GatewayTool
{
    public function name(): string
    {
        return 'global.update';
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
            'data'   => ['required', 'array'],
        ];
    }

    public function resolveTarget(array $arguments): ?string
    {
        return $arguments['handle'] ?? null;
    }

    public function execute(array $arguments): ToolResponse
    {
        $this->rejectUnknownKeys($arguments);
        $this->validateDataIsAssociative($arguments);

        $handle = $arguments['handle'];
        $site = $arguments['site'] ?? 'default';
        $data = $arguments['data'];

        // Find global set
        $globalSet = GlobalSet::findByHandle($handle);
        if (! $globalSet) {
            return ToolResponse::error(
                $this->name(),
                'resource_not_found',
                "Global set '{$handle}' does not exist.",
                404,
            );
        }

        // Get or create localized variables for the site
        $variables = $globalSet->inSelectedSite();

        if (! $variables) {
            $variables = $globalSet->makeLocalization($site);
        }

        foreach ($data as $key => $value) {
            $variables->set($key, $value);
        }

        $variables->save();

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
            'description' => 'Updates a global set by merging the provided data fields.',
            'target_type' => $this->targetType(),
            'arguments' => [
                'handle' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'The handle of the global set to update.',
                    'default' => null,
                ],
                'site' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'The site handle for multi-site installations.',
                    'default' => 'default',
                ],
                'data' => [
                    'type' => 'object',
                    'required' => true,
                    'description' => 'An associative array of field values to merge onto the global set.',
                    'default' => null,
                ],
            ],
            'example' => [
                'tool' => $this->name(),
                'arguments' => [
                    'handle' => 'contact_information',
                    'data' => [
                        'email' => 'new@example.com',
                    ],
                ],
            ],
            'response_example' => [
                'ok' => true,
                'tool' => $this->name(),
                'result' => [
                    'status' => 'updated',
                    'target_type' => 'global',
                    'target' => [
                        'handle' => 'contact_information',
                        'site' => 'default',
                    ],
                ],
            ],
            'errors' => [
                'resource_not_found' => 'When the global set does not exist.',
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
        $allowed = ['handle', 'site', 'data'];
        $unknown = array_diff(array_keys($arguments), $allowed);

        if (! empty($unknown)) {
            throw new ToolValidationException(
                'Unknown argument keys: ' . implode(', ', $unknown),
                ['unknown_keys' => array_values($unknown)],
            );
        }
    }

    private function validateDataIsAssociative(array $arguments): void
    {
        if (! isset($arguments['data'])) {
            return;
        }

        $data = $arguments['data'];

        if (! is_array($data) || (! empty($data) && array_keys($data) === range(0, count($data) - 1))) {
            throw new ToolValidationException(
                'The data field must be an associative array (object).',
                ['data' => ['The data field must be an associative array (object).']],
            );
        }
    }
}
