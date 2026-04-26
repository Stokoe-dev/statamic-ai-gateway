<?php

namespace Stokoe\AiGateway\Tools;

use Stokoe\AiGateway\Exceptions\ToolAuthorizationException;
use Stokoe\AiGateway\Exceptions\ToolValidationException;
use Stokoe\AiGateway\Policies\ToolPolicy;
use Stokoe\AiGateway\Support\ToolResponse;
use Stokoe\AiGateway\Tools\Contracts\GatewayTool;
use Statamic\Facades\Collection;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Taxonomy;

class BlueprintDeleteTool implements GatewayTool
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
        return 'blueprint.delete';
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

        // Resolve the existing blueprint.
        $blueprint = $this->resolveBlueprint($resourceType, $handle);

        if ($blueprint === null) {
            return ToolResponse::error(
                $this->name(),
                'resource_not_found',
                $this->notFoundMessage($resourceType, $handle),
                404,
            );
        }

        $blueprint->delete();

        return ToolResponse::success($this->name(), [
            'status'      => 'deleted',
            'target_type' => $this->targetType(),
            'target'      => [
                'resource_type' => $resourceType,
                'handle'        => $handle,
            ],
        ]);
    }

    public function requiresConfirmation(string $environment): bool
    {
        $environments = config('ai_gateway.confirmation.tools.blueprint.delete', []);

        return in_array($environment, $environments, true);
    }

    public function describe(): array
    {
        return [
            'name' => $this->name(),
            'description' => 'Deletes a blueprint for a collection, global set, or taxonomy.',
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
                    'description' => 'The handle of the resource whose blueprint to delete.',
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
                    'status' => 'deleted',
                    'target_type' => 'blueprint',
                    'target' => [
                        'resource_type' => 'collection',
                        'handle' => 'pages',
                    ],
                ],
            ],
            'errors' => [
                'resource_not_found' => 'When the specified resource or its blueprint does not exist.',
                'forbidden' => 'When the resource handle is not in the corresponding allowlist.',
            ],
            'notes' => [
                'This is a destructive operation that requires confirmation in configured environments.',
                'Authorization is checked against the resource-type allowlist (e.g., collection handle against allowed_collections).',
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
            'collection' => 'Collection or its blueprint',
            'global'     => 'Global set or its blueprint',
            'taxonomy'   => 'Taxonomy or its blueprint',
        ];

        $label = $labels[$resourceType] ?? ucfirst($resourceType);

        return "{$label} '{$handle}' does not exist.";
    }
}
