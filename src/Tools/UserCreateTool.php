<?php

namespace Stokoe\AiGateway\Tools;

use Stokoe\AiGateway\Exceptions\ToolValidationException;
use Stokoe\AiGateway\Support\ToolResponse;
use Stokoe\AiGateway\Tools\Contracts\GatewayTool;
use Statamic\Facades\User;

class UserCreateTool implements GatewayTool
{
    public function name(): string
    {
        return 'user.create';
    }

    public function targetType(): string
    {
        return 'user';
    }

    public function validationRules(): array
    {
        return [
            'email'    => ['required', 'string'],
            'name'     => ['required', 'string'],
            'password' => ['required', 'string'],
            'roles'    => ['sometimes', 'array'],
            'roles.*'  => ['string'],
        ];
    }

    public function resolveTarget(array $arguments): ?string
    {
        return 'enabled';
    }

    public function execute(array $arguments): ToolResponse
    {
        $this->rejectUnknownKeys($arguments);

        $email = $arguments['email'];
        $name = $arguments['name'];
        $password = $arguments['password'];
        $roles = $arguments['roles'] ?? [];

        // Check if user already exists
        $existing = User::findByEmail($email);
        if ($existing) {
            return ToolResponse::error(
                $this->name(),
                'conflict',
                "A user with email '{$email}' already exists.",
                409,
            );
        }

        $user = User::make()
            ->email($email)
            ->data(['name' => $name]);

        $user->password($password);

        if (! empty($roles)) {
            $user->roles($roles);
        }

        $user->save();

        return ToolResponse::success($this->name(), [
            'status'      => 'created',
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
        $environments = config('ai_gateway.confirmation.tools.user.create', []);

        return in_array($environment, $environments, true);
    }

    public function describe(): array
    {
        return [
            'name' => $this->name(),
            'description' => 'Creates a new user.',
            'target_type' => $this->targetType(),
            'arguments' => [
                'email' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'The email address for the new user.',
                    'default' => null,
                ],
                'name' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'The name of the new user.',
                    'default' => null,
                ],
                'password' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'The password for the new user.',
                    'default' => null,
                ],
                'roles' => [
                    'type' => 'array',
                    'required' => false,
                    'description' => 'An array of role handles to assign to the user.',
                    'default' => [],
                ],
            ],
            'example' => [
                'tool' => $this->name(),
                'arguments' => [
                    'email' => 'jane@example.com',
                    'name' => 'Jane Doe',
                    'password' => 'secure-password',
                    'roles' => ['editor'],
                ],
            ],
            'response_example' => [
                'ok' => true,
                'tool' => $this->name(),
                'result' => [
                    'status' => 'created',
                    'target_type' => 'user',
                    'user' => [
                        'id' => 'abc-123',
                        'name' => 'Jane Doe',
                        'email' => 'jane@example.com',
                        'roles' => ['editor'],
                        'super_admin' => false,
                    ],
                ],
            ],
            'errors' => [
                'conflict' => 'When a user with the same email already exists.',
            ],
            'notes' => [
                'Requires confirmation in configured environments.',
                'Requires the allowed_user_operations toggle to be enabled.',
            ],
        ];
    }

    private function rejectUnknownKeys(array $arguments): void
    {
        $allowed = ['email', 'name', 'password', 'roles'];
        $unknown = array_diff(array_keys($arguments), $allowed);

        if (! empty($unknown)) {
            throw new ToolValidationException(
                'Unknown argument keys: ' . implode(', ', $unknown),
                ['unknown_keys' => array_values($unknown)],
            );
        }
    }
}
