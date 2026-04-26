<?php

namespace Stokoe\AiGateway\Tools;

use Stokoe\AiGateway\Exceptions\ToolValidationException;
use Stokoe\AiGateway\Support\ToolResponse;
use Stokoe\AiGateway\Tools\Contracts\GatewayTool;
use Statamic\Facades\User;

class UserGetTool implements GatewayTool
{
    public function name(): string
    {
        return 'user.get';
    }

    public function targetType(): string
    {
        return 'user';
    }

    public function validationRules(): array
    {
        return [
            'id'    => ['sometimes', 'string'],
            'email' => ['sometimes', 'string'],
        ];
    }

    public function resolveTarget(array $arguments): ?string
    {
        return 'enabled';
    }

    public function execute(array $arguments): ToolResponse
    {
        $this->rejectUnknownKeys($arguments);

        $id = $arguments['id'] ?? null;
        $email = $arguments['email'] ?? null;

        if (! $id && ! $email) {
            throw new ToolValidationException(
                'At least one of id or email is required.',
                ['id' => ['At least one of id or email is required.']],
            );
        }

        $user = null;

        if ($id) {
            $user = User::find($id);
        }

        if (! $user && $email) {
            $user = User::findByEmail($email);
        }

        if (! $user) {
            $identifier = $id ?? $email;

            return ToolResponse::error(
                $this->name(),
                'resource_not_found',
                "User '{$identifier}' does not exist.",
                404,
            );
        }

        return ToolResponse::success($this->name(), [
            'target_type' => $this->targetType(),
            'user' => [
                'id'          => $user->id(),
                'name'        => $user->name(),
                'email'       => $user->email(),
                'roles'       => $user->roles()->map->handle()->values()->toArray(),
                'super_admin' => $user->isSuper(),
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
            'description' => 'Retrieves a user by ID or email.',
            'target_type' => $this->targetType(),
            'arguments' => [
                'id' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'The ID of the user to retrieve.',
                    'default' => null,
                ],
                'email' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'The email of the user to retrieve.',
                    'default' => null,
                ],
            ],
            'example' => [
                'tool' => $this->name(),
                'arguments' => [
                    'email' => 'john@example.com',
                ],
            ],
            'response_example' => [
                'ok' => true,
                'tool' => $this->name(),
                'result' => [
                    'target_type' => 'user',
                    'user' => [
                        'id' => 'abc-123',
                        'name' => 'John Doe',
                        'email' => 'john@example.com',
                        'roles' => ['admin'],
                        'super_admin' => false,
                    ],
                ],
            ],
            'errors' => [
                'resource_not_found' => 'When the user does not exist.',
            ],
            'notes' => [
                'At least one of id or email must be provided.',
                'Requires the allowed_user_operations toggle to be enabled.',
            ],
        ];
    }

    private function rejectUnknownKeys(array $arguments): void
    {
        $allowed = ['id', 'email'];
        $unknown = array_diff(array_keys($arguments), $allowed);

        if (! empty($unknown)) {
            throw new ToolValidationException(
                'Unknown argument keys: ' . implode(', ', $unknown),
                ['unknown_keys' => array_values($unknown)],
            );
        }
    }
}
