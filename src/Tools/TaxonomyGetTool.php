<?php

namespace Stokoe\AiGateway\Tools;

use Stokoe\AiGateway\Exceptions\ToolValidationException;
use Stokoe\AiGateway\Support\ToolResponse;
use Stokoe\AiGateway\Tools\Contracts\GatewayTool;
use Statamic\Facades\Taxonomy;

class TaxonomyGetTool implements GatewayTool
{
    public function name(): string
    {
        return 'taxonomy.get';
    }

    public function targetType(): string
    {
        return 'taxonomy';
    }

    public function validationRules(): array
    {
        return [
            'handle' => ['required', 'string'],
        ];
    }

    public function resolveTarget(array $arguments): ?string
    {
        return $arguments['handle'] ?? null;
    }

    public function execute(array $arguments): ToolResponse
    {
        $this->rejectUnknownKeys($arguments);

        $handle = $arguments['handle'];

        $taxonomy = Taxonomy::findByHandle($handle);

        if (! $taxonomy) {
            return ToolResponse::error(
                $this->name(),
                'resource_not_found',
                "Taxonomy '{$handle}' does not exist.",
                404,
            );
        }

        $collections = $taxonomy->collections()
            ->map(fn ($collection) => $collection->handle())
            ->values()
            ->toArray();

        $blueprints = $taxonomy->blueprints()
            ->map(fn ($blueprint) => [
                'handle' => $blueprint->handle(),
                'title' => $blueprint->title(),
            ])
            ->values()
            ->toArray();

        return ToolResponse::success($this->name(), [
            'target_type' => $this->targetType(),
            'target' => $handle,
            'taxonomy' => [
                'handle' => $taxonomy->handle(),
                'title' => $taxonomy->title(),
                'route' => $taxonomy->route('default') ?? null,
                'collections' => $collections,
                'blueprints' => $blueprints,
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
            'description' => 'Retrieves a taxonomy by handle with route pattern, attached collections, and blueprint info.',
            'target_type' => $this->targetType(),
            'arguments' => [
                'handle' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'The handle of the taxonomy to retrieve.',
                    'default' => null,
                ],
            ],
            'example' => [
                'tool' => $this->name(),
                'arguments' => [
                    'handle' => 'tags',
                ],
            ],
            'response_example' => [
                'ok' => true,
                'tool' => $this->name(),
                'result' => [
                    'target_type' => 'taxonomy',
                    'target' => 'tags',
                    'taxonomy' => [
                        'handle' => 'tags',
                        'title' => 'Tags',
                        'route' => '/{slug}',
                        'collections' => ['articles'],
                        'blueprints' => [
                            ['handle' => 'tag', 'title' => 'Tag'],
                        ],
                    ],
                ],
            ],
            'errors' => [
                'resource_not_found' => 'When the specified taxonomy does not exist.',
            ],
            'notes' => [
                'This is a read-only tool that does not require confirmation.',
                'The taxonomy must be in the allowed_taxonomies allowlist.',
            ],
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
