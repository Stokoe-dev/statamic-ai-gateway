<?php

namespace Stokoe\AiGateway\Tools;

use Stokoe\AiGateway\Exceptions\ToolValidationException;
use Stokoe\AiGateway\Support\ToolResponse;
use Stokoe\AiGateway\Tools\Contracts\GatewayTool;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;

class EntryCreateTool implements GatewayTool
{
    public function name(): string
    {
        return 'entry.create';
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

        // Check entry does not already exist
        $existing = Entry::query()
            ->where('collection', $collection)
            ->where('slug', $slug)
            ->where('locale', $site)
            ->first();

        if ($existing) {
            return ToolResponse::error(
                $this->name(),
                'conflict',
                "Entry '{$slug}' already exists in collection '{$collection}' for site '{$site}'.",
                409,
            );
        }

        // Validate data against blueprint when available
        $blueprint = $collectionInstance->entryBlueprint();
        if ($blueprint) {
            $this->validateAgainstBlueprint($blueprint, $data);
        }

        // Create entry
        $entry = Entry::make()
            ->collection($collectionInstance)
            ->slug($slug)
            ->locale($site)
            ->data($data);

        if ($published !== null) {
            $entry->published($published);
        }

        $entry->save();

        return ToolResponse::success($this->name(), [
            'status'      => 'created',
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
            'description' => 'Creates a new entry in a collection. Fails if the entry already exists.',
            'target_type' => $this->targetType(),
            'arguments' => [
                'collection' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'The handle of the collection to create the entry in.',
                    'default' => null,
                ],
                'slug' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'The slug for the new entry.',
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
                    'description' => 'An associative array of field values for the entry.',
                    'default' => null,
                ],
            ],
            'example' => [
                'tool' => $this->name(),
                'arguments' => [
                    'collection' => 'pages',
                    'slug' => 'about',
                    'published' => true,
                    'data' => [
                        'title' => 'About Us',
                        'content' => 'Learn more about our company.',
                    ],
                ],
            ],
            'response_example' => [
                'ok' => true,
                'tool' => $this->name(),
                'result' => [
                    'status' => 'created',
                    'target_type' => 'entry',
                    'target' => [
                        'collection' => 'pages',
                        'slug' => 'about',
                        'site' => 'default',
                    ],
                ],
            ],
            'errors' => [
                'resource_not_found' => 'When the collection does not exist.',
                'conflict' => 'When an entry with the same slug already exists in the collection.',
            ],
            'notes' => [
                'Validates data against the collection blueprint if one exists.',
                'Fails if an entry with the same slug already exists. Use entry.upsert for create-or-update behavior.',
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

    private function validateAgainstBlueprint($blueprint, array $data): void
    {
        $fields = $blueprint->fields()->addValues($data);

        try {
            $fields->validate();
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw new ToolValidationException(
                'Blueprint validation failed.',
                $e->errors(),
            );
        }
    }
}
