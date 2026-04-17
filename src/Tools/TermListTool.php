<?php

namespace Stokoe\AiGateway\Tools;

use Stokoe\AiGateway\Exceptions\ToolValidationException;
use Stokoe\AiGateway\Support\ToolResponse;
use Stokoe\AiGateway\Tools\Contracts\GatewayTool;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;

class TermListTool implements GatewayTool
{
    public function name(): string
    {
        return 'term.list';
    }

    public function targetType(): string
    {
        return 'taxonomy';
    }

    public function validationRules(): array
    {
        return [
            'taxonomy' => ['required', 'string'],
            'site'     => ['sometimes', 'string'],
            'limit'    => ['sometimes', 'integer', 'min:1', 'max:100'],
            'offset'   => ['sometimes', 'integer', 'min:0'],
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
        $site = $arguments['site'] ?? 'default';
        $limit = $arguments['limit'] ?? 25;
        $offset = $arguments['offset'] ?? 0;

        $taxonomyInstance = Taxonomy::findByHandle($taxonomy);
        if (! $taxonomyInstance) {
            return ToolResponse::error(
                $this->name(),
                'resource_not_found',
                "Taxonomy '{$taxonomy}' does not exist.",
                404,
            );
        }

        $query = Term::query()
            ->where('taxonomy', $taxonomy);

        $total = $query->count();

        $terms = $query
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(fn ($term) => [
                'slug' => $term->slug(),
                'data' => $term->data()->toArray(),
            ])
            ->values()
            ->toArray();

        return ToolResponse::success($this->name(), [
            'target_type' => $this->targetType(),
            'target'      => [
                'taxonomy' => $taxonomy,
                'site'     => $site,
            ],
            'terms'      => $terms,
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
            'description' => 'Lists terms in a taxonomy with pagination support.',
            'target_type' => $this->targetType(),
            'arguments' => [
                'taxonomy' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'The handle of the taxonomy to list terms from.',
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
                    'description' => 'Maximum number of terms to return (1-100).',
                    'default' => 25,
                ],
                'offset' => [
                    'type' => 'integer',
                    'required' => false,
                    'description' => 'Number of terms to skip for pagination.',
                    'default' => 0,
                ],
            ],
            'example' => [
                'tool' => $this->name(),
                'arguments' => [
                    'taxonomy' => 'tags',
                    'site' => 'default',
                    'limit' => 10,
                    'offset' => 0,
                ],
            ],
            'response_example' => [
                'ok' => true,
                'tool' => $this->name(),
                'result' => [
                    'target_type' => 'taxonomy',
                    'target' => [
                        'taxonomy' => 'tags',
                        'site' => 'default',
                    ],
                    'terms' => [
                        [
                            'slug' => 'featured',
                            'data' => ['title' => 'Featured'],
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
                'resource_not_found' => 'When the taxonomy does not exist.',
            ],
            'notes' => [
                'Denied fields are stripped from each term in the response.',
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
        $allowed = ['taxonomy', 'site', 'limit', 'offset'];
        $unknown = array_diff(array_keys($arguments), $allowed);

        if (! empty($unknown)) {
            throw new ToolValidationException(
                'Unknown argument keys: ' . implode(', ', $unknown),
                ['unknown_keys' => array_values($unknown)],
            );
        }
    }
}
