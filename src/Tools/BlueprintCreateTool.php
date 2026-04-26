<?php

namespace Stokoe\AiGateway\Tools;

use Facades\Statamic\Fields\FieldtypeRepository;
use Stokoe\AiGateway\Exceptions\ToolAuthorizationException;
use Stokoe\AiGateway\Exceptions\ToolValidationException;
use Stokoe\AiGateway\Policies\ToolPolicy;
use Stokoe\AiGateway\Support\ToolResponse;
use Stokoe\AiGateway\Tools\Contracts\GatewayTool;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Taxonomy;

class BlueprintCreateTool implements GatewayTool
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
        return 'blueprint.create';
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
            'fields'        => ['required', 'array'],
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
        $fields = $arguments['fields'];

        // Internal authorization: check handle against the correct allowlist for the resource type.
        $policyTargetType = self::RESOURCE_TYPE_MAP[$resourceType];
        $policy = app(ToolPolicy::class);

        if (! $policy->targetAllowed($policyTargetType, $handle)) {
            throw new ToolAuthorizationException(
                "The {$resourceType} '{$handle}' is not in the allowed list.",
            );
        }

        // Validate that the fields array contains associative field definitions.
        $this->validateFieldsArray($fields);

        // Validate field types against Statamic's known fieldtypes.
        $invalidTypes = $this->validateFieldTypes($fields);

        if (! empty($invalidTypes)) {
            return ToolResponse::error(
                $this->name(),
                'validation_failed',
                'One or more field definitions contain unrecognized fieldtypes: ' . implode(', ', $invalidTypes),
                422,
                [],
                ['invalid_fieldtypes' => $invalidTypes],
            );
        }

        // Resolve the resource to ensure it exists.
        $resource = $this->resolveResource($resourceType, $handle);

        if ($resource === null) {
            return ToolResponse::error(
                $this->name(),
                'resource_not_found',
                $this->notFoundMessage($resourceType, $handle),
                404,
            );
        }

        // Build the blueprint contents from the field definitions.
        $blueprintFields = $this->buildBlueprintFields($fields);

        // Determine the namespace for the blueprint.
        $namespace = $this->resolveNamespace($resourceType, $handle);

        // Create and save the blueprint.
        $blueprint = Blueprint::make($handle)
            ->setNamespace($namespace)
            ->setContents([
                'tabs' => [
                    'main' => [
                        'sections' => [
                            [
                                'fields' => $blueprintFields,
                            ],
                        ],
                    ],
                ],
            ]);

        $blueprint->save();

        // Return the created blueprint schema.
        $createdFields = $blueprint->fields()->all()->map(function ($field) {
            return [
                'handle'   => $field->handle(),
                'display'  => $field->display(),
                'type'     => $field->type(),
                'rules'    => $field->rules(),
                'required' => in_array('required', $field->rules()),
            ];
        })->values()->toArray();

        return ToolResponse::success($this->name(), [
            'status'      => 'created',
            'target_type' => $this->targetType(),
            'target'      => [
                'resource_type' => $resourceType,
                'handle'        => $handle,
            ],
            'fields' => $createdFields,
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
            'description' => 'Creates a new blueprint for a collection, global set, or taxonomy.',
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
                    'description' => 'The handle of the resource to create the blueprint for.',
                    'default' => null,
                ],
                'fields' => [
                    'type' => 'array',
                    'required' => true,
                    'description' => 'Array of field definitions. Each field must have a handle and type, with optional display, required, and validate properties.',
                    'default' => null,
                ],
            ],
            'example' => [
                'tool' => $this->name(),
                'arguments' => [
                    'resource_type' => 'collection',
                    'handle' => 'pages',
                    'fields' => [
                        [
                            'handle' => 'title',
                            'type' => 'text',
                            'display' => 'Title',
                            'required' => true,
                            'validate' => ['required', 'string', 'max:200'],
                        ],
                        [
                            'handle' => 'content',
                            'type' => 'bard',
                            'display' => 'Content',
                        ],
                    ],
                ],
            ],
            'response_example' => [
                'ok' => true,
                'tool' => $this->name(),
                'result' => [
                    'status' => 'created',
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
                'validation_failed' => 'When one or more field definitions contain unrecognized fieldtypes.',
                'forbidden' => 'When the resource handle is not in the corresponding allowlist.',
            ],
            'notes' => [
                'Field types are validated against Statamic\'s known fieldtypes before creation.',
                'Authorization is checked against the resource-type allowlist (e.g., collection handle against allowed_collections).',
                'Field definitions are accepted from agent input — no hardcoded templates.',
            ],
        ];
    }

    private function rejectUnknownKeys(array $arguments): void
    {
        $allowed = ['resource_type', 'handle', 'fields'];
        $unknown = array_diff(array_keys($arguments), $allowed);

        if (! empty($unknown)) {
            throw new ToolValidationException(
                'Unknown argument keys: ' . implode(', ', $unknown),
                ['unknown_keys' => array_values($unknown)],
            );
        }
    }

    /**
     * Validate that the fields array contains valid field definition objects.
     */
    private function validateFieldsArray(array $fields): void
    {
        foreach ($fields as $index => $field) {
            if (! is_array($field)) {
                throw new ToolValidationException(
                    "Field at index {$index} must be an object.",
                    ['fields' => ["Field at index {$index} must be an object."]],
                );
            }

            if (! isset($field['handle']) || ! is_string($field['handle'])) {
                throw new ToolValidationException(
                    "Field at index {$index} must have a string 'handle' property.",
                    ['fields' => ["Field at index {$index} must have a string 'handle' property."]],
                );
            }

            if (! isset($field['type']) || ! is_string($field['type'])) {
                throw new ToolValidationException(
                    "Field at index {$index} must have a string 'type' property.",
                    ['fields' => ["Field at index {$index} must have a string 'type' property."]],
                );
            }
        }
    }

    /**
     * Validate field types against Statamic's known fieldtypes.
     *
     * @return array List of invalid fieldtype names.
     */
    private function validateFieldTypes(array $fields): array
    {
        $knownHandles = FieldtypeRepository::handles()->values()->all();
        $invalidTypes = [];

        foreach ($fields as $field) {
            $type = $field['type'];

            if (! in_array($type, $knownHandles, true)) {
                $invalidTypes[] = $type;
            }
        }

        return array_unique($invalidTypes);
    }

    /**
     * Build the blueprint fields array from agent-provided field definitions.
     */
    private function buildBlueprintFields(array $fields): array
    {
        return array_map(function ($field) {
            $config = [
                'type' => $field['type'],
            ];

            if (isset($field['display'])) {
                $config['display'] = $field['display'];
            }

            if (isset($field['validate'])) {
                $config['validate'] = $field['validate'];
            }

            if (isset($field['required']) && $field['required'] === true) {
                // Add 'required' to validate rules if not already present.
                $validate = $config['validate'] ?? [];
                if (! in_array('required', $validate)) {
                    array_unshift($validate, 'required');
                    $config['validate'] = $validate;
                }
            }

            return [
                'handle' => $field['handle'],
                'field'  => $config,
            ];
        }, $fields);
    }

    /**
     * Resolve the resource for the given resource type and handle.
     *
     * @return mixed|null
     */
    private function resolveResource(string $resourceType, string $handle)
    {
        switch ($resourceType) {
            case 'collection':
                return Collection::findByHandle($handle);

            case 'global':
                return GlobalSet::findByHandle($handle);

            case 'taxonomy':
                return Taxonomy::findByHandle($handle);

            default:
                return null;
        }
    }

    /**
     * Resolve the blueprint namespace for the given resource type and handle.
     */
    private function resolveNamespace(string $resourceType, string $handle): string
    {
        switch ($resourceType) {
            case 'collection':
                return "collections.{$handle}";

            case 'global':
                return "globals.{$handle}";

            case 'taxonomy':
                return "taxonomies.{$handle}";

            default:
                return $handle;
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
