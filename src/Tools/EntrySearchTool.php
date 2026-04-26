<?php

namespace Stokoe\AiGateway\Tools;

use Stokoe\AiGateway\Exceptions\ToolValidationException;
use Stokoe\AiGateway\Support\ToolResponse;
use Stokoe\AiGateway\Tools\Contracts\GatewayTool;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;

class EntrySearchTool implements GatewayTool
{
    public function name(): string
    {
        return 'entry.search';
    }

    public function targetType(): string
    {
        return 'entry';
    }

    public function validationRules(): array
    {
        return [
            'collection' => ['required', 'string'],
            'filter'     => ['sometimes', 'array'],
            'query'      => ['sometimes', 'string'],
            'limit'      => ['sometimes', 'integer', 'min:1', 'max:100'],
            'offset'     => ['sometimes', 'integer', 'min:0'],
            'site'       => ['sometimes', 'string'],
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
        $filter = $arguments['filter'] ?? [];
        $queryString = $arguments['query'] ?? null;
        $limit = $arguments['limit'] ?? 25;
        $offset = $arguments['offset'] ?? 0;
        $site = $arguments['site'] ?? 'default';

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

        // Apply field-value filters
        foreach ($filter as $field => $value) {
            $query->where($field, $value);
        }

        // Apply case-insensitive title substring match
        if ($queryString !== null && $queryString !== '') {
            $query->where('title', 'like', '%' . $queryString . '%');
        }

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

    public function requiresConfirmation(string $environment): bool
    {
        return false;
    }

    public function describe(): array
    {
        return [
            'name' => $this->name(),
            'description' => 'Searches entries in a collection by field values or title.',
            'target_type' => $this->targetType(),
            'arguments' => [
                'collection' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'The handle of the collection to search.',
                    'default' => null,
                ],
                'filter' => [
                    'type' => 'object',
                    'required' => false,
                    'description' => 'Field-value pairs to filter entries by.',
                    'default' => null,
                ],
                'query' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'Case-insensitive title substring to search for.',
                    'default' => null,
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
                    'collection' => 'articles',
                    'filter' => ['author' => 'John'],
                    'query' => 'search term',
                    'limit' => 10,
                    'offset' => 0,
                    'site' => 'default',
                ],
            ],
            'response_example' => [
                'ok' => true,
                'tool' => $this->name(),
                'result' => [
                    'target_type' => 'entry',
                    'target' => [
                        'collection' => 'articles',
                        'site' => 'default',
                    ],
                    'entries' => [
                        [
                            'id' => 'abc-123',
                            'slug' => 'my-article',
                            'published' => true,
                            'data' => ['title' => 'My Article', 'author' => 'John'],
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
                'Filter applies exact field-value matching on all provided pairs.',
                'Query performs case-insensitive partial title matching.',
                'Returns pagination info with total count, limit, and offset.',
            ],
        ];
    }

    private function rejectUnknownKeys(array $arguments): void
    {
        $allowed = ['collection', 'filter', 'query', 'limit', 'offset', 'site'];
        $unknown = array_diff(array_keys($arguments), $allowed);

        if (! empty($unknown)) {
            throw new ToolValidationException(
                'Unknown argument keys: ' . implode(', ', $unknown),
                ['unknown_keys' => array_values($unknown)],
            );
        }
    }
}
