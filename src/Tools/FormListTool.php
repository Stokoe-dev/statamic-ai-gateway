<?php

namespace Stokoe\AiGateway\Tools;

use Stokoe\AiGateway\Exceptions\ToolValidationException;
use Stokoe\AiGateway\Support\ToolResponse;
use Stokoe\AiGateway\Tools\Contracts\GatewayTool;
use Statamic\Facades\Form;

class FormListTool implements GatewayTool
{
    public function name(): string
    {
        return 'form.list';
    }

    public function targetType(): string
    {
        return 'form';
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

        $allowlist = config('ai_gateway.allowed_forms', []);

        $forms = Form::all()
            ->filter(fn ($form) => in_array($form->handle(), $allowlist, true))
            ->map(fn ($form) => [
                'handle' => $form->handle(),
                'title' => $form->title(),
                'submission_count' => $form->submissions()->count(),
            ])
            ->values()
            ->toArray();

        return ToolResponse::success($this->name(), [
            'target_type' => $this->targetType(),
            'forms' => $forms,
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
            'description' => 'Lists all forms in the allowed_forms allowlist with handle, title, and submission count.',
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
                    'target_type' => 'form',
                    'forms' => [
                        [
                            'handle' => 'contact',
                            'title' => 'Contact Form',
                            'submission_count' => 42,
                        ],
                    ],
                ],
            ],
            'errors' => [],
            'notes' => [
                'Returns only forms that appear in the allowed_forms allowlist.',
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
