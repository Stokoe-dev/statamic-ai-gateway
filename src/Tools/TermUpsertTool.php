<?php

namespace Stokoe\AiGateway\Tools;

use Stokoe\AiGateway\Exceptions\ToolValidationException;
use Stokoe\AiGateway\Support\ToolResponse;
use Stokoe\AiGateway\Tools\Contracts\GatewayTool;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;

class TermUpsertTool implements GatewayTool
{
    public function name(): string
    {
        return 'term.upsert';
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
            'data'     => ['required', 'array'],
        ];
    }

    public function resolveTarget(array $arguments): ?string
    {
        return $arguments['taxonomy'] ?? null;
    }

    public function execute(array $arguments): ToolResponse
    {
        $this->rejectUnknownKeys($arguments);
        $this->validateDataIsAssociative($arguments);

        $taxonomy = $arguments['taxonomy'];
        $slug = $arguments['slug'];
        $site = $arguments['site'] ?? 'default';
        $data = $arguments['data'];

        // Find taxonomy
        $taxonomyInstance = Taxonomy::findByHandle($taxonomy);
        if (! $taxonomyInstance) {
            return ToolResponse::error(
                $this->name(),
                'resource_not_found',
                "Taxonomy '{$taxonomy}' does not exist.",
                404,
            );
        }

        // Check if term already exists
        $existing = Term::query()
            ->where('taxonomy', $taxonomy)
            ->where('slug', $slug)
            ->first();

        if ($existing) {
            // Update existing term
            foreach ($data as $key => $value) {
                $existing->set($key, $value);
            }

            $existing->save();

            return ToolResponse::success($this->name(), [
                'status'      => 'updated',
                'target_type' => $this->targetType(),
                'target'      => [
                    'taxonomy' => $taxonomy,
                    'slug'     => $slug,
                    'site'     => $site,
                ],
            ]);
        }

        // Create new term
        $term = Term::make()
            ->taxonomy($taxonomyInstance)
            ->slug($slug)
            ->data($data);

        $term->save();

        return ToolResponse::success($this->name(), [
            'status'      => 'created',
            'target_type' => $this->targetType(),
            'target'      => [
                'taxonomy' => $taxonomy,
                'slug'     => $slug,
                'site'     => $site,
            ],
        ]);
    }

    public function describe(): array
    {
        return [
            'name' => $this->name(),
            'description' => 'Creates or updates a taxonomy term. If the term exists it is updated; otherwise a new term is created.',
            'target_type' => $this->targetType(),
            'arguments' => [
                'taxonomy' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'The handle of the taxonomy for the term.',
                    'default' => null,
                ],
                'slug' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'The slug of the term to create or update.',
                    'default' => null,
                ],
                'site' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'The site handle for multi-site installations.',
                    'default' => 'default',
                ],
                'data' => [
                    'type' => 'object',
                    'required' => true,
                    'description' => 'An associative array of field values for the term.',
                    'default' => null,
                ],
            ],
            'example' => [
                'tool' => $this->name(),
                'arguments' => [
                    'taxonomy' => 'tags',
                    'slug' => 'featured',
                    'data' => [
                        'title' => 'Featured',
                    ],
                ],
            ],
            'response_example' => [
                'ok' => true,
                'tool' => $this->name(),
                'result' => [
                    'status' => 'created',
                    'target_type' => 'taxonomy',
                    'target' => [
                        'taxonomy' => 'tags',
                        'slug' => 'featured',
                        'site' => 'default',
                    ],
                ],
            ],
            'errors' => [
                'resource_not_found' => 'When the taxonomy does not exist.',
            ],
            'notes' => [
                'Returns status "created" or "updated" to indicate what happened.',
            ],
        ];
    }

    public function requiresConfirmation(string $environment): bool
    {
        return false;
    }

    private function rejectUnknownKeys(array $arguments): void
    {
        $allowed = ['taxonomy', 'slug', 'site', 'data'];
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
