<?php

namespace Stokoe\AiGateway\Tools;

use Stokoe\AiGateway\Exceptions\ToolValidationException;
use Stokoe\AiGateway\Support\ToolResponse;
use Stokoe\AiGateway\Tools\Contracts\GatewayTool;
use Statamic\Facades\User;

class UserListTool implements GatewayTool
{
    public function name(): string
    {
        return 'user.list';
    }

    public function targetType(): string
    {
        return 'user';
    }

    public function validationRules(): array
    {
        return [
            'limit'  => ['sometimes', 'integer', 'min:1', 'max:100'],
            'offset' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function resolveTarget(array $arguments): ?string
    {
        return 'enabled';
    }

    public function execute(array $arguments): ToolResponse
    {
        $this->rejectUnknownKeys($arguments);

        $limit = $arguments['limit'] ?? 25;
        $offset = $arguments['offset'] ?? 0;

        $allUsers = User::all();
        $total = $allUsers->count();

        $users = $allUsers
            ->slice($offset, $limit)
            ->map(fn ($user) => [
                'id'          => $user->id(),
                'name'        => $user->name(),
                'email'       => $user->email(),
                'roles'       => $user->roles()->map->handle()->values()->toArray(),
                'super_admin' => $user->isSuper(),
            ])
            ->values()
            ->toArray();

        return ToolResponse::success($this->name(), [
            'target_type' => $this->targetType(),
            'users'       => $users,
            'pagination'  => [
                'total'  => $total,
                'limit'  => $limit,
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
            'description' => 'Lists users with pagination support.',
            'target_type' => $this->targetType(),
            'arguments' => [
                'limit' => [
                    'type' => 'integer',
                    'required' => false,
                    'description' => 'Maximum number of users to return (1-100).',
                    'default' => 25,
                ],
                'offset' => [
                    'type' => 'integer',
                    'required' => false,
                    'description' => 'Number of users to skip for pagination.',
                    'default' => 0,
                ],
            ],
            'example' => [
                'tool' => $this->name(),
                'arguments' => [
                    'limit' => 10,
                    'offset' => 0,
                ],
            ],
            'response_example' => [
                'ok' => true,
                'tool' => $this->name(),
                'result' => [
                    'target_type' => 'user',
                    'users' => [
                        [
                            'id' => 'abc-123',
                            'name' => 'John Doe',
                            'email' => 'john@example.com',
                            'roles' => ['admin'],
                            'super_admin' => false,
                        ],
                    ],
                    'pagination' => [
                        'total' => 1,
                        'limit' => 10,
                        'offset' => 0,
                    ],
                ],
            ],
            'errors' => [],
            'notes' => [
                'Requires the allowed_user_operations toggle to be enabled.',
                'Returns pagination info with total count, limit, and offset.',
            ],
        ];
    }

    private function rejectUnknownKeys(array $arguments): void
    {
        $allowed = ['limit', 'offset'];
        $unknown = array_diff(array_keys($arguments), $allowed);

        if (! empty($unknown)) {
            throw new ToolValidationException(
                'Unknown argument keys: ' . implode(', ', $unknown),
                ['unknown_keys' => array_values($unknown)],
            );
        }
    }
}
