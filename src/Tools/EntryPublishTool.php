<?php

namespace Stokoe\AiGateway\Tools;

use Stokoe\AiGateway\Exceptions\ToolValidationException;
use Stokoe\AiGateway\Support\ToolResponse;
use Stokoe\AiGateway\Tools\Contracts\GatewayTool;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;

class EntryPublishTool implements GatewayTool
{
    public function name(): string
    {
        return 'entry.publish';
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
        $slug = $arguments['slug'];
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

        $entry->published(true);
        $entry->save();

        return ToolResponse::success($this->name(), [
            'status'      => 'published',
            'target_type' => $this->targetType(),
            'target'      => [
                'collection' => $collection,
                'slug'       => $slug,
                'site'       => $site,
            ],
            'entry' => [
                'id'        => $entry->id(),
                'slug'      => $entry->slug(),
                'published' => $entry->published(),
                'data'      => $entry->data()->toArray(),
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
            'description' => 'Publishes an entry in a collection by setting its published state to true.',
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
                    'description' => 'The slug of the entry to publish.',
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
                    'collection' => 'articles',
                    'slug' => 'my-draft-post',
                ],
            ],
            'response_example' => [
                'ok' => true,
                'tool' => $this->name(),
                'result' => [
                    'status' => 'published',
                    'target_type' => 'entry',
                    'target' => [
                        'collection' => 'articles',
                        'slug' => 'my-draft-post',
                        'site' => 'default',
                    ],
                    'entry' => [
                        'id' => 'abc-123',
                        'slug' => 'my-draft-post',
                        'published' => true,
                        'data' => [
                            'title' => 'My Draft Post',
                        ],
                    ],
                ],
            ],
            'errors' => [
                'resource_not_found' => 'When the collection or entry does not exist.',
            ],
            'notes' => [
                'Publishing sets the entry\'s published state to true.',
                'This is a distinct editorial workflow action, separate from entry data updates.',
            ],
        ];
    }

    private function rejectUnknownKeys(array $arguments): void
    {
        $allowed = ['collection', 'slug', 'site'];
        $unknown = array_diff(array_keys($arguments), $allowed);

        if (! empty($unknown)) {
            throw new ToolValidationException(
                'Unknown argument keys: ' . implode(', ', $unknown),
                ['unknown_keys' => array_values($unknown)],
            );
        }
    }
}
