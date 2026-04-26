<?php

namespace Stokoe\AiGateway\Tools;

use Stokoe\AiGateway\Exceptions\ToolValidationException;
use Stokoe\AiGateway\Support\ToolResponse;
use Stokoe\AiGateway\Tools\Contracts\GatewayTool;
use Statamic\Facades\User;

class UserUpdateTool implements GatewayTool
{
    private bool $hasPasswordChange = false;

    public function name(): string
    {
        return 'user.update';
    }

    public function targetType(): string
    {
        return 'user';
    }

    public function validationRules(): array
    {
        return [
            'id'       => ['sometimes', 'string'],
            'email'    => ['sometimes', 'string'],
            'name'     => ['sometimes', 'string'],
            'password' => ['sometimes', 'string'],
            'roles'    => ['sometimes', 'array'],
            'roles.*'  => ['string'],
        ];
    }

    public function resolveTarget(array $arguments): ?string
    {
        $this->hasPasswordChange = isset($arguments['password']);

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

        if (isset($arguments['name'])) {
            $user->set('name', $arguments['name']);
        }

        if (isset($arguments['password'])) {
            $user->password($arguments['password']);
        }

        if (isset($arguments['roles'])) {
            $user->roles($arguments['roles']);
        }

        $user->save();

        return ToolResponse::success($this->name(), [
            'status'      => 'updated',
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
        if (! $this->hasPasswordChange) {
            return false;
        }

        $environments = config('ai_gateway.confirmation.tools.user.update', []);

        return in_array($environment, $environments, true);
    }

    public function describe(): array
    {
        return [
            'name' => $this->name(),
            'description' => 'Updates an existing user. Requires confirmation when changing password.',
            'target_type' => $this->targetType(),
            'arguments' => [
                'id' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'The ID of the user to update.',
                    'default' => null,
                ],
                'email' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'The email of the user to update.',
                    'default' => null,
                ],
                'name' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'The new name for the user.',
                    'default' => null,
                ],
                'password' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'The new password for the user. Triggers confirmation requirement.',
                    'default' => null,
                ],
                'roles' => [
                    'type' => 'array',
                    'required' => false,
                    'description' => 'An array of role handles to assign to the user.',
                    'default' => null,
                ],
            ],
            'example' => [
                'tool' => $this->name(),
                'arguments' => [
                    'email' => 'john@example.com',
                    'name' => 'John Updated',
                    'roles' => ['editor'],
                ],
            ],
            'response_example' => [
                'ok' => true,
                'tool' => $this->name(),
                'result' => [
                    'status' => 'updated',
                    'target_type' => 'user',
                    'user' => [
                        'id' => 'abc-123',
                        'name' => 'John Updated',
                        'email' => 'john@example.com',
                        'roles' => ['editor'],
                        'super_admin' => false,
                    ],
                ],
            ],
            'errors' => [
                'resource_not_found' => 'When the user does not exist.',
            ],
            'notes' => [
                'At least one of id or email must be provided to identify the user.',
                'Requires confirmation when password field is present.',
                'Requires the allowed_user_operations toggle to be enabled.',
            ],
        ];
    }

    private function rejectUnknownKeys(array $arguments): void
    {
        $allowed = ['id', 'email', 'name', 'password', 'roles'];
        $unknown = array_diff(array_keys($arguments), $allowed);

        if (! empty($unknown)) {
            throw new ToolValidationException(
                'Unknown argument keys: ' . implode(', ', $unknown),
                ['unknown_keys' => array_values($unknown)],
            );
        }
    }
}
