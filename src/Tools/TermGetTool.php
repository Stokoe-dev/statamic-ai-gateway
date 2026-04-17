<?php

namespace Stokoe\AiGateway\Tools;

use Stokoe\AiGateway\Exceptions\ToolValidationException;
use Stokoe\AiGateway\Support\ToolResponse;
use Stokoe\AiGateway\Tools\Contracts\GatewayTool;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;

class TermGetTool implements GatewayTool
{
    public function name(): string
    {
        return 'term.get';
    }

    public function targetType(): string
    {
        return 'taxonomy';
    }

    public function validationRules(): array
    {
        return [
            'taxonomy' => ['required', 'string'],
            'slug'     => ['required', 'string'],
            'site'     => ['sometimes', 'string'],
        ];
    }

    public function resolveTarget(array $arguments): ?string
    {
        return $arguments['taxonomy'] ?? null;
    }

    public function execute(array $arguments): ToolResponse
    {
        $this->rejectUnknownKeys($arguments);

        $taxonomy = $arguments['taxonomy'];
        $slug = $arguments['slug'];
        $site = $arguments['site'] ?? 'default';

        $taxonomyInstance = Taxonomy::findByHandle($taxonomy);
        if (! $taxonomyInstance) {
            return ToolResponse::error(
                $this->name(),
                'resource_not_found',
                "Taxonomy '{$taxonomy}' does not exist.",
                404,
            );
        }

        $term = Term::query()
            ->where('taxonomy', $taxonomy)
            ->where('slug', $slug)
            ->first();

        if (! $term) {
            return ToolResponse::error(
                $this->name(),
                'resource_not_found',
                "Term '{$slug}' not found in taxonomy '{$taxonomy}'.",
                404,
            );
        }

        return ToolResponse::success($this->name(), [
            'target_type' => $this->targetType(),
            'target'      => [
                'taxonomy' => $taxonomy,
                'slug'     => $slug,
                'site'     => $site,
            ],
            'term' => [
                'slug' => $term->slug(),
                'data' => $term->data()->toArray(),
            ],
        ]);
    }

    public function describe(): array
    {
        return [
            'name' => $this->name(),
            'description' => 'Retrieves a single taxonomy term by taxonomy handle and slug.',
            'target_type' => $this->targetType(),
            'arguments' => [
                'taxonomy' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'The handle of the taxonomy containing the term.',
                    'default' => null,
                ],
                'slug' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'The slug of the term to retrieve.',
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
                    'taxonomy' => 'tags',
                    'slug' => 'featured',
                    'site' => 'default',
                ],
            ],
            'response_example' => [
                'ok' => true,
                'tool' => $this->name(),
                'result' => [
                    'target_type' => 'taxonomy',
                    'target' => [
                        'taxonomy' => 'tags',
                        'slug' => 'featured',
                        'site' => 'default',
                    ],
                    'term' => [
                        'slug' => 'featured',
                        'data' => [
                            'title' => 'Featured',
                        ],
                    ],
                ],
            ],
            'errors' => [
                'resource_not_found' => 'When the taxonomy or term does not exist.',
            ],
            'notes' => [
                'Denied fields are stripped from the response data.',
            ],
        ];
    }

    public function requiresConfirmation(string $environment): bool
    {
        return false;
    }

    private function rejectUnknownKeys(array $arguments): void
    {
        $allowed = ['taxonomy', 'slug', 'site'];
        $unknown = array_diff(array_keys($arguments), $allowed);

        if (! empty($unknown)) {
            throw new ToolValidationException(
                'Unknown argument keys: ' . implode(', ', $unknown),
                ['unknown_keys' => array_values($unknown)],
            );
        }
    }
}
