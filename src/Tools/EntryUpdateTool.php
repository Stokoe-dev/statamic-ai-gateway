<?php

namespace Stokoe\AiGateway\Tools;

use Stokoe\AiGateway\Exceptions\ToolValidationException;
use Stokoe\AiGateway\Support\ToolResponse;
use Stokoe\AiGateway\Tools\Contracts\GatewayTool;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;

class EntryUpdateTool implements GatewayTool
{
    public function name(): string
    {
        return 'entry.update';
    }

    public function targetType(): string
    {
        return 'entry';
    }

    public function validationRules(): array
    {
        return [
            'collection' => ['required', 'string'],
            'slug'       => ['required', 'string'],
            'site'       => ['sometimes', 'string'],
            'published'  => ['sometimes', 'boolean'],
            'data'       => ['required', 'array'],
        ];
    }

    public function resolveTarget(array $arguments): ?string
    {
        return $arguments['collection'] ?? null;
    }

    public function execute(array $arguments): ToolResponse
    {
        $this->rejectUnknownKeys($arguments);
        $this->validateDataIsAssociative($arguments);

        $collection = $arguments['collection'];
        $slug = $arguments['slug'];
        $site = $arguments['site'] ?? 'default';
        $published = $arguments['published'] ?? null;
        $data = $arguments['data'];

        // Check collection exists
        $collectionInstance = Collection::findByHandle($collection);
        if (! $collectionInstance) {
            return ToolResponse::error(
                $this->name(),
                'resource_not_found',
                "Collection '{$collection}' does not exist.",
                404,
            );
        }

        // Find existing entry
        $entry = Entry::query()
            ->where('collection', $collection)
            ->where('slug', $slug)
            ->where('locale', $site)
            ->first();

        if (! $entry) {
            return ToolResponse::error(
                $this->name(),
                'resource_not_found',
                "Entry '{$slug}' not found in collection '{$collection}' for site '{$site}'.",
                404,
            );
        }

        // Merge data onto existing entry
        foreach ($data as $key => $value) {
            $entry->set($key, $value);
        }

        if ($published !== null) {
            $entry->published($published);
        }

        $entry->save();

        return ToolResponse::success($this->name(), [
            'status'      => 'updated',
            'target_type' => $this->targetType(),
            'target'      => [
                'collection' => $collection,
                'slug'       => $slug,
                'site'       => $site,
            ],
        ]);
    }

    public function describe(): array
    {
        return [
            'name' => $this->name(),
            'description' => 'Updates an existing entry by merging the provided data fields.',
            'target_type' => $this->targetType(),
            'arguments' => [
                'collection' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'The handle of the collection containing the entry.',
                    'default' => null,
                ],
                'slug' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'The slug of the entry to update.',
                    'default' => null,
                ],
                'site' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'The site handle for multi-site installations.',
                    'default' => 'default',
                ],
                'published' => [
                    'type' => 'boolean',
                    'required' => false,
                    'description' => 'Whether the entry should be published.',
                    'default' => null,
                ],
                'data' => [
                    'type' => 'object',
                    'required' => true,
                    'description' => 'An associative array of field values to merge onto the entry.',
                    'default' => null,
                ],
            ],
            'example' => [
                'tool' => $this->name(),
                'arguments' => [
                    'collection' => 'pages',
                    'slug' => 'home',
                    'data' => [
                        'title' => 'Updated Home Title',
                    ],
                ],
            ],
            'response_example' => [
                'ok' => true,
                'tool' => $this->name(),
                'result' => [
                    'status' => 'updated',
                    'target_type' => 'entry',
                    'target' => [
                        'collection' => 'pages',
                        'slug' => 'home',
                        'site' => 'default',
                    ],
                ],
            ],
            'errors' => [
                'resource_not_found' => 'When the collection or entry does not exist.',
            ],
            'notes' => [
                'Only the fields provided in data are changed; all other fields are preserved.',
            ],
        ];
    }

    public function requiresConfirmation(string $environment): bool
    {
        return false;
    }

    private function rejectUnknownKeys(array $arguments): void
    {
        $allowed = ['collection', 'slug', 'site', 'published', 'data'];
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
