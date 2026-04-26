<?php

namespace Stokoe\AiGateway\Tools;

use Stokoe\AiGateway\Exceptions\ToolValidationException;
use Stokoe\AiGateway\Support\ToolResponse;
use Stokoe\AiGateway\Tools\Contracts\GatewayTool;
use Statamic\Facades\Collection;

class CollectionListTool implements GatewayTool
{
    public function name(): string
    {
        return 'collection.list';
    }

    public function targetType(): string
    {
        return 'entry';
    }

    public function validationRules(): array
    {
        return [
            'handle' => ['sometimes', 'string'],
        ];
    }

    public function resolveTarget(array $arguments): ?string
    {
        return $arguments['handle'] ?? null;
    }

    public function execute(array $arguments): ToolResponse
    {
        $this->rejectUnknownKeys($arguments);

        $handle = $arguments['handle'] ?? null;

        if ($handle !== null) {
            return $this->getSingleCollection($handle);
        }

        return $this->listAllCollections();
    }

    public function requiresConfirmation(string $environment): bool
    {
        return false;
    }

    public function describe(): array
    {
        return [
            'name' => $this->name(),
            'description' => 'Lists collections with their configuration, or returns metadata for a single collection by handle.',
            'target_type' => $this->targetType(),
            'arguments' => [
                'handle' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'Optional collection handle to retrieve metadata for a single collection.',
                    'default' => null,
                ],
            ],
            'example' => [
                'tool' => $this->name(),
                'arguments' => [],
            ],
            'response_example' => [
                'ok' => true,
                'tool' => $this->name(),
                'result' => [
                    'target_type' => 'entry',
                    'collections' => [
                        [
                            'handle' => 'pages',
                            'title' => 'Pages',
                            'route' => '/{slug}',
                            'structure' => ['max_depth' => 3],
                            'taxonomies' => ['tags'],
                        ],
                    ],
                ],
            ],
            'errors' => [
                'resource_not_found' => 'When the specified collection handle does not exist.',
            ],
            'notes' => [
                'When no handle is provided, returns all collections in the allowed_collections allowlist.',
                'When a handle is provided, returns metadata for that single collection.',
            ],
        ];
    }

    private function getSingleCollection(string $handle): ToolResponse
    {
        $collection = Collection::findByHandle($handle);

        if (! $collection) {
            return ToolResponse::error(
                $this->name(),
                'resource_not_found',
                "Collection '{$handle}' does not exist.",
                404,
            );
        }

        return ToolResponse::success($this->name(), [
            'target_type' => $this->targetType(),
            'collection' => $this->formatCollection($collection),
        ]);
    }

    private function listAllCollections(): ToolResponse
    {
        $allowlist = config('ai_gateway.allowed_collections', []);

        $collections = Collection::all()
            ->filter(fn ($collection) => in_array($collection->handle(), $allowlist, true))
            ->map(fn ($collection) => $this->formatCollection($collection))
            ->values()
            ->toArray();

        return ToolResponse::success($this->name(), [
            'target_type' => $this->targetType(),
            'collections' => $collections,
        ]);
    }

    private function formatCollection($collection): array
    {
        $structure = $collection->structure();

        return [
            'handle' => $collection->handle(),
            'title' => $collection->title(),
            'route' => $collection->route('default') ?? null,
            'structure' => $structure ? [
                'max_depth' => $structure->maxDepth(),
            ] : null,
            'taxonomies' => $collection->taxonomies()
                ->map(fn ($taxonomy) => $taxonomy->handle())
                ->values()
                ->toArray(),
        ];
    }

    private function rejectUnknownKeys(array $arguments): void
    {
        $allowed = ['handle'];
        $unknown = array_diff(array_keys($arguments), $allowed);

        if (! empty($unknown)) {
            throw new ToolValidationException(
                'Unknown argument keys: ' . implode(', ', $unknown),
                ['unknown_keys' => array_values($unknown)],
            );
        }
    }
}
