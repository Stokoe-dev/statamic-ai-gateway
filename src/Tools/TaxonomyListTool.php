<?php

namespace Stokoe\AiGateway\Tools;

use Stokoe\AiGateway\Exceptions\ToolValidationException;
use Stokoe\AiGateway\Support\ToolResponse;
use Stokoe\AiGateway\Tools\Contracts\GatewayTool;
use Statamic\Facades\Taxonomy;

class TaxonomyListTool implements GatewayTool
{
    public function name(): string
    {
        return 'taxonomy.list';
    }

    public function targetType(): string
    {
        return 'taxonomy';
    }

    public function validationRules(): array
    {
        return [];
    }

    public function resolveTarget(array $arguments): ?string
    {
        return null;
    }

    public function execute(array $arguments): ToolResponse
    {
        $this->rejectUnknownKeys($arguments);

        $allowlist = config('ai_gateway.allowed_taxonomies', []);

        $taxonomies = Taxonomy::all()
            ->filter(fn ($taxonomy) => in_array($taxonomy->handle(), $allowlist, true))
            ->map(fn ($taxonomy) => [
                'handle' => $taxonomy->handle(),
                'title' => $taxonomy->title(),
                'term_count' => $taxonomy->queryTerms()->count(),
            ])
            ->values()
            ->toArray();

        return ToolResponse::success($this->name(), [
            'target_type' => $this->targetType(),
            'taxonomies' => $taxonomies,
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
            'description' => 'Lists all taxonomies in the allowed_taxonomies allowlist with handle, title, and term count.',
            'target_type' => $this->targetType(),
            'arguments' => [],
            'example' => [
                'tool' => $this->name(),
                'arguments' => [],
            ],
            'response_example' => [
                'ok' => true,
                'tool' => $this->name(),
                'result' => [
                    'target_type' => 'taxonomy',
                    'taxonomies' => [
                        [
                            'handle' => 'tags',
                            'title' => 'Tags',
                            'term_count' => 12,
                        ],
                    ],
                ],
            ],
            'errors' => [],
            'notes' => [
                'Returns only taxonomies that appear in the allowed_taxonomies allowlist.',
                'This is a read-only tool that does not require confirmation.',
            ],
        ];
    }

    private function rejectUnknownKeys(array $arguments): void
    {
        $allowed = [];
        $unknown = array_diff(array_keys($arguments), $allowed);

        if (! empty($unknown)) {
            throw new ToolValidationException(
                'Unknown argument keys: ' . implode(', ', $unknown),
                ['unknown_keys' => array_values($unknown)],
            );
        }
    }
}
