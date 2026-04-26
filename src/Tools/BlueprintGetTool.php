<?php

namespace Stokoe\AiGateway\Tools;

use Stokoe\AiGateway\Exceptions\ToolAuthorizationException;
use Stokoe\AiGateway\Exceptions\ToolValidationException;
use Stokoe\AiGateway\Policies\ToolPolicy;
use Stokoe\AiGateway\Support\FieldFilter;
use Stokoe\AiGateway\Support\ToolResponse;
use Stokoe\AiGateway\Tools\Contracts\GatewayTool;
use Statamic\Facades\Collection;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Taxonomy;

class BlueprintGetTool implements GatewayTool
{
    /**
     * Map resource_type argument to the ToolPolicy target type used for allowlist checks.
     */
    private const RESOURCE_TYPE_MAP = [
        'collection' => 'entry',
        'global'     => 'global',
        'taxonomy'   => 'taxonomy',
    ];

    public function name(): string
    {
        return 'blueprint.get';
    }

    public function targetType(): string
    {
        return 'blueprint';
    }

    public function validationRules(): array
    {
        return [
            'resource_type' => ['required', 'string', 'in:collection,global,taxonomy'],
            'handle'        => ['required', 'string'],
        ];
    }

    public function resolveTarget(array $arguments): ?string
    {
        return null;
    }

    public function execute(array $arguments): ToolResponse
    {
        $this->rejectUnknownKeys($arguments);

        $resourceType = $arguments['resource_type'];
        $handle = $arguments['handle'];

        // Internal authorization: check handle against the correct allowlist for the resource type.
        $policyTargetType = self::RESOURCE_TYPE_MAP[$resourceType];
        $policy = app(ToolPolicy::class);

        if (! $policy->targetAllowed($policyTargetType, $handle)) {
            throw new ToolAuthorizationException(
                "The {$resourceType} '{$handle}' is not in the allowed list.",
            );
        }

        // Resolve the resource and get its blueprint.
        $blueprint = $this->resolveBlueprint($resourceType, $handle);

        if ($blueprint === null) {
            return ToolResponse::error(
                $this->name(),
                'resource_not_found',
                $this->notFoundMessage($resourceType, $handle),
                404,
            );
        }

        // Get denied fields for the resource type and strip them.
        $deniedFields = $policy->deniedFields($policyTargetType, $handle);
        $fieldFilter = app(FieldFilter::class);

        $fields = $blueprint->fields()->all()->map(function ($field) {
            return [
                'handle'     => $field->handle(),
                'display'    => $field->display(),
                'type'       => $field->type(),
                'rules'      => $field->rules(),
                'required'   => in_array('required', $field->rules()),
            ];
        })->values();

        // Strip denied fields from the returned schema.
        $fields = $fields->filter(function ($field) use ($deniedFields) {
            return ! in_array($field['handle'], $deniedFields);
        })->values()->toArray();

        return ToolResponse::success($this->name(), [
            'target_type'   => $this->targetType(),
            'target'        => [
                'resource_type' => $resourceType,
                'handle'        => $handle,
            ],
            'fields' => $fields,
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
            'description' => 'Retrieves the field schema of a collection, global set, or taxonomy blueprint.',
            'target_type' => $this->targetType(),
            'arguments' => [
                'resource_type' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'The type of resource: collection, global, or taxonomy.',
                    'default' => null,
                ],
                'handle' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'The handle of the resource whose blueprint to retrieve.',
                    'default' => null,
                ],
            ],
            'example' => [
                'tool' => $this->name(),
                'arguments' => [
                    'resource_type' => 'collection',
                    'handle' => 'pages',
                ],
            ],
            'response_example' => [
                'ok' => true,
                'tool' => $this->name(),
                'result' => [
                    'target_type' => 'blueprint',
                    'target' => [
                        'resource_type' => 'collection',
                        'handle' => 'pages',
                    ],
                    'fields' => [
                        [
                            'handle' => 'title',
                            'display' => 'Title',
                            'type' => 'text',
                            'rules' => ['required', 'string', 'max:200'],
                            'required' => true,
                        ],
                        [
                            'handle' => 'content',
                            'display' => 'Content',
                            'type' => 'bard',
                            'rules' => [],
                            'required' => false,
                        ],
                    ],
                ],
            ],
            'errors' => [
                'resource_not_found' => 'When the specified resource does not exist.',
                'forbidden' => 'When the resource handle is not in the corresponding allowlist.',
            ],
            'notes' => [
                'Denied fields are stripped from the returned schema.',
                'Authorization is checked against the resource-type allowlist (e.g., collection handle against allowed_collections).',
                'This is a read-only tool that does not require confirmation.',
            ],
        ];
    }

    private function rejectUnknownKeys(array $arguments): void
    {
        $allowed = ['resource_type', 'handle'];
        $unknown = array_diff(array_keys($arguments), $allowed);

        if (! empty($unknown)) {
            throw new ToolValidationException(
                'Unknown argument keys: ' . implode(', ', $unknown),
                ['unknown_keys' => array_values($unknown)],
            );
        }
    }

    /**
     * Resolve the blueprint for the given resource type and handle.
     *
     * @return \Statamic\Fields\Blueprint|null
     */
    private function resolveBlueprint(string $resourceType, string $handle)
    {
        switch ($resourceType) {
            case 'collection':
                $collection = Collection::findByHandle($handle);

                return $collection?->entryBlueprint();

            case 'global':
                $globalSet = GlobalSet::findByHandle($handle);

                return $globalSet?->blueprint();

            case 'taxonomy':
                $taxonomy = Taxonomy::findByHandle($handle);

                return $taxonomy?->termBlueprint();

            default:
                return null;
        }
    }

    private function notFoundMessage(string $resourceType, string $handle): string
    {
        $labels = [
            'collection' => 'Collection',
            'global'     => 'Global set',
            'taxonomy'   => 'Taxonomy',
        ];

        $label = $labels[$resourceType] ?? ucfirst($resourceType);

        return "{$label} '{$handle}' does not exist.";
    }
}
