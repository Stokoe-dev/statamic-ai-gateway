<?php

namespace Stokoe\AiGateway\Tools;

use Stokoe\AiGateway\Exceptions\ToolValidationException;
use Stokoe\AiGateway\Support\ToolResponse;
use Stokoe\AiGateway\Tools\Contracts\GatewayTool;
use Statamic\Facades\Form;

class FormSubmissionsTool implements GatewayTool
{
    public function name(): string
    {
        return 'form.submissions';
    }

    public function targetType(): string
    {
        return 'form';
    }

    public function validationRules(): array
    {
        return [
            'handle' => ['required', 'string'],
            'limit'  => ['sometimes', 'integer', 'min:1', 'max:100'],
            'offset' => ['sometimes', 'integer', 'min:0'],
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
        $limit = $arguments['limit'] ?? 25;
        $offset = $arguments['offset'] ?? 0;

        $form = Form::find($handle);

        if (! $form) {
            return ToolResponse::error(
                $this->name(),
                'resource_not_found',
                "Form '{$handle}' does not exist.",
                404,
            );
        }

        $submissions = $form->submissions();
        $total = $submissions->count();

        $items = $submissions
            ->values()
            ->slice($offset, $limit)
            ->map(fn ($submission) => [
                'id' => $submission->id(),
                'date' => $submission->date()->toIso8601String(),
                'data' => $submission->data()->toArray(),
            ])
            ->values()
            ->toArray();

        return ToolResponse::success($this->name(), [
            'target_type' => $this->targetType(),
            'target' => $handle,
            'submissions' => $items,
            'pagination' => [
                'total' => $total,
                'limit' => $limit,
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
            'description' => 'Lists paginated form submissions with ID, date, and field data.',
            'target_type' => $this->targetType(),
            'arguments' => [
                'handle' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'The handle of the form to retrieve submissions for.',
                    'default' => null,
                ],
                'limit' => [
                    'type' => 'integer',
                    'required' => false,
                    'description' => 'Maximum number of submissions to return (1-100).',
                    'default' => 25,
                ],
                'offset' => [
                    'type' => 'integer',
                    'required' => false,
                    'description' => 'Number of submissions to skip for pagination.',
                    'default' => 0,
                ],
            ],
            'example' => [
                'tool' => $this->name(),
                'arguments' => [
                    'handle' => 'contact',
                    'limit' => 10,
                    'offset' => 0,
                ],
            ],
            'response_example' => [
                'ok' => true,
                'tool' => $this->name(),
                'result' => [
                    'target_type' => 'form',
                    'target' => 'contact',
                    'submissions' => [
                        [
                            'id' => '1234567890',
                            'date' => '2024-01-15T10:30:00+00:00',
                            'data' => [
                                'name' => 'John Doe',
                                'email' => 'john@example.com',
                                'message' => 'Hello!',
                            ],
                        ],
                    ],
                    'pagination' => [
                        'total' => 42,
                        'limit' => 10,
                        'offset' => 0,
                    ],
                ],
            ],
            'errors' => [
                'resource_not_found' => 'When the specified form does not exist.',
            ],
            'notes' => [
                'Returns pagination info with total count, limit, and offset.',
                'This is a read-only tool that does not require confirmation.',
                'The form must be in the allowed_forms allowlist.',
            ],
        ];
    }

    private function rejectUnknownKeys(array $arguments): void
    {
        $allowed = ['handle', 'limit', 'offset'];
        $unknown = array_diff(array_keys($arguments), $allowed);

        if (! empty($unknown)) {
            throw new ToolValidationException(
                'Unknown argument keys: ' . implode(', ', $unknown),
                ['unknown_keys' => array_values($unknown)],
            );
        }
    }
}
