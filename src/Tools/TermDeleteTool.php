<?php

namespace Stokoe\AiGateway\Tools;

use Stokoe\AiGateway\Exceptions\ToolValidationException;
use Stokoe\AiGateway\Support\ToolResponse;
use Stokoe\AiGateway\Tools\Contracts\GatewayTool;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;

class TermDeleteTool implements GatewayTool
{
    public function name(): string
    {
        return 'term.delete';
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

        $term->delete();

        return ToolResponse::success($this->name(), [
            'status'      => 'deleted',
            'target_type' => $this->targetType(),
            'target'      => [
                'taxonomy' => $taxonomy,
                'slug'     => $slug,
            ],
        ]);
    }

    public function requiresConfirmation(string $environment): bool
    {
        $environments = config('ai_gateway.confirmation.tools.term.delete', []);

        return in_array($environment, $environments, true);
    }

    public function describe(): array
    {
        return [
            'name' => $this->name(),
            'description' => 'Deletes a taxonomy term.',
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
                    'description' => 'The slug of the term to delete.',
                    'default' => null,
                ],
            ],
            'example' => [
                'tool' => $this->name(),
                'arguments' => [
                    'taxonomy' => 'tags',
                    'slug' => 'old-tag',
                ],
            ],
            'response_example' => [
                'ok' => true,
                'tool' => $this->name(),
                'result' => [
                    'status' => 'deleted',
                    'target_type' => 'taxonomy',
                    'target' => [
                        'taxonomy' => 'tags',
                        'slug' => 'old-tag',
                    ],
                ],
            ],
            'errors' => [
                'resource_not_found' => 'When the taxonomy or term does not exist.',
            ],
            'notes' => [
                'This is a destructive operation that requires confirmation in configured environments.',
                'The term will be permanently removed from the taxonomy.',
            ],
        ];
    }

    private function rejectUnknownKeys(array $arguments): void
    {
        $allowed = ['taxonomy', 'slug'];
        $unknown = array_diff(array_keys($arguments), $allowed);

        if (! empty($unknown)) {
            throw new ToolValidationException(
                'Unknown argument keys: ' . implode(', ', $unknown),
                ['unknown_keys' => array_values($unknown)],
            );
        }
    }
}
