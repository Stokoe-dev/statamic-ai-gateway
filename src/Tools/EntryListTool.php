<?php

namespace Stokoe\AiGateway\Tools;

use Stokoe\AiGateway\Exceptions\ToolValidationException;
use Stokoe\AiGateway\Support\ToolResponse;
use Stokoe\AiGateway\Tools\Contracts\GatewayTool;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;

class EntryListTool implements GatewayTool
{
    public function name(): string
    {
        return 'entry.list';
    }

    public function targetType(): string
    {
        return 'entry';
    }

    public function validationRules(): array
    {
        return [
            'collection' => ['required', 'string'],
            'site'       => ['sometimes', 'string'],
            'limit'      => ['sometimes', 'integer', 'min:1', 'max:100'],
            'offset'     => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function resolveTarget(array $arguments): ?string
    {
        return $arguments['collection'] ?? null;
    }

    public function execute(array $arguments): ToolResponse
    {
        $this->rejectUnknownKeys($arguments);

        $collection = $arguments['collection'];
        $site = $arguments['site'] ?? 'default';
        $limit = $arguments['limit'] ?? 25;
        $offset = $arguments['offset'] ?? 0;

        $collectionInstance = Collection::findByHandle($collection);
        if (! $collectionInstance) {
            return ToolResponse::error(
                $this->name(),
                'resource_not_found',
                "Collection '{$collection}' does not exist.",
                404,
            );
        }

        $query = Entry::query()
            ->where('collection', $collection)
            ->where('locale', $site);

        $total = $query->count();

        $entries = $query
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(fn ($entry) => [
                'id'        => $entry->id(),
                'slug'      => $entry->slug(),
                'published' => $entry->published(),
                'data'      => $entry->data()->toArray(),
            ])
            ->values()
            ->toArray();

        return ToolResponse::success($this->name(), [
            'target_type' => $this->targetType(),
            'target'      => [
                'collection' => $collection,
                'site'       => $site,
            ],
            'entries'    => $entries,
            'pagination' => [
                'total'  => $total,
                'limit'  => $limit,
                'offset' => $offset,
            ],
        ]);
    }

    public function describe(): array
    {
        return [
            'name' => $this->name(),
            'description' => 'Lists entries in a collection with pagination support.',
            'target_type' => $this->targetType(),
            'arguments' => [
                'collection' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'The handle of the collection to list entries from.',
                    'default' => null,
                ],
                'site' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'The site handle for multi-site installations.',
                    'default' => 'default',
                ],
                'limit' => [
                    'type' => 'integer',
                    'required' => false,
                    'description' => 'Maximum number of entries to return (1-100).',
                    'default' => 25,
                ],
                'offset' => [
                    'type' => 'integer',
                    'required' => false,
                    'description' => 'Number of entries to skip for pagination.',
                    'default' => 0,
                ],
            ],
            'example' => [
                'tool' => $this->name(),
                'arguments' => [
                    'collection' => 'pages',
                    'site' => 'default',
                    'limit' => 10,
                    'offset' => 0,
                ],
            ],
            'response_example' => [
                'ok' => true,
                'tool' => $this->name(),
                'result' => [
                    'target_type' => 'entry',
                    'target' => [
                        'collection' => 'pages',
                        'site' => 'default',
                    ],
                    'entries' => [
                        [
                            'id' => 'abc-123',
                            'slug' => 'home',
                            'published' => true,
                            'data' => ['title' => 'Home'],
                        ],
                    ],
                    'pagination' => [
                        'total' => 1,
                        'limit' => 10,
                        'offset' => 0,
                    ],
                ],
            ],
            'errors' => [
                'resource_not_found' => 'When the collection does not exist.',
            ],
            'notes' => [
                'Denied fields are stripped from each entry in the response.',
                'Returns pagination info with total count, limit, and offset.',
            ],
        ];
    }

    public function requiresConfirmation(string $environment): bool
    {
        return false;
    }

    private function rejectUnknownKeys(array $arguments): void
    {
        $allowed = ['collection', 'site', 'limit', 'offset'];
        $unknown = array_diff(array_keys($arguments), $allowed);

        if (! empty($unknown)) {
            throw new ToolValidationException(
                'Unknown argument keys: ' . implode(', ', $unknown),
                ['unknown_keys' => array_values($unknown)],
            );
        }
    }
}
