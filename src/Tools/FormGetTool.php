<?php

namespace Stokoe\AiGateway\Tools;

use Stokoe\AiGateway\Exceptions\ToolValidationException;
use Stokoe\AiGateway\Support\ToolResponse;
use Stokoe\AiGateway\Tools\Contracts\GatewayTool;
use Statamic\Facades\Form;

class FormGetTool implements GatewayTool
{
    public function name(): string
    {
        return 'form.get';
    }

    public function targetType(): string
    {
        return 'form';
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

        $form = Form::find($handle);

        if (! $form) {
            return ToolResponse::error(
                $this->name(),
                'resource_not_found',
                "Form '{$handle}' does not exist.",
                404,
            );
        }

        $fields = collect($form->blueprint()->fields()->all())
            ->map(fn ($field) => [
                'handle' => $field->handle(),
                'type' => $field->type(),
                'display' => $field->display(),
                'rules' => $field->rules(),
            ])
            ->values()
            ->toArray();

        return ToolResponse::success($this->name(), [
            'target_type' => $this->targetType(),
            'target' => $handle,
            'form' => [
                'handle' => $form->handle(),
                'title' => $form->title(),
                'fields' => $fields,
                'submission_count' => $form->submissions()->count(),
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
            'description' => 'Retrieves a form\'s configuration including handle, title, fields, and submission count.',
            'target_type' => $this->targetType(),
            'arguments' => [
                'handle' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'The handle of the form to retrieve.',
                    'default' => null,
                ],
            ],
            'example' => [
                'tool' => $this->name(),
                'arguments' => [
                    'handle' => 'contact',
                ],
            ],
            'response_example' => [
                'ok' => true,
                'tool' => $this->name(),
                'result' => [
                    'target_type' => 'form',
                    'target' => 'contact',
                    'form' => [
                        'handle' => 'contact',
                        'title' => 'Contact Form',
                        'fields' => [
                            [
                                'handle' => 'name',
                                'type' => 'text',
                                'display' => 'Name',
                                'rules' => ['required'],
                            ],
                        ],
                        'submission_count' => 42,
                    ],
                ],
            ],
            'errors' => [
                'resource_not_found' => 'When the specified form does not exist.',
            ],
            'notes' => [
                'This is a read-only tool that does not require confirmation.',
                'The form must be in the allowed_forms allowlist.',
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
