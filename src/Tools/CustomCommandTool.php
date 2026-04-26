<?php

namespace Stokoe\AiGateway\Tools;

use Illuminate\Support\Facades\Artisan;
use Stokoe\AiGateway\Exceptions\ToolValidationException;
use Stokoe\AiGateway\Support\ToolResponse;
use Stokoe\AiGateway\Tools\Contracts\GatewayTool;

class CustomCommandTool implements GatewayTool
{
    /**
     * Stores the current alias from resolveTarget() so requiresConfirmation()
     * can look up the command definition. resolveTarget() is always called
     * before requiresConfirmation() in the controller pipeline.
     */
    private ?string $currentAlias = null;

    public function name(): string
    {
        return 'custom_command.execute';
    }

    public function targetType(): string
    {
        return 'custom_command';
    }

    public function validationRules(): array
    {
        return [
            'alias' => ['required', 'string'],
        ];
    }

    public function resolveTarget(array $arguments): ?string
    {
        $this->currentAlias = $arguments['alias'] ?? null;

        return $this->currentAlias;
    }

    public function execute(array $arguments): ToolResponse
    {
        $this->rejectUnknownKeys($arguments);

        $alias = $arguments['alias'];
        $command = $this->findCommand($alias);

        if ($command === null) {
            return ToolResponse::error(
                $this->name(),
                'resource_not_found',
                "Custom command '{$alias}' is not defined.",
                404,
            );
        }

        $exitCode = Artisan::call($command['command']);
        $output = trim(Artisan::output());

        if ($exitCode !== 0) {
            return ToolResponse::error(
                $this->name(),
                'execution_failed',
                "Command '{$alias}' failed with exit code {$exitCode}.",
                500,
                [],
                ['output' => $output],
            );
        }

        return ToolResponse::success($this->name(), [
            'status'      => 'executed',
            'target_type' => $this->targetType(),
            'target'      => [
                'alias' => $alias,
            ],
            'output' => $output,
        ]);
    }

    public function requiresConfirmation(string $environment): bool
    {
        if ($this->currentAlias === null) {
            return false;
        }

        $command = $this->findCommand($this->currentAlias);

        if ($command === null) {
            return false;
        }

        $environments = $command['confirmation_environments'] ?? [];

        return in_array($environment, $environments, true);
    }

    public function describe(): array
    {
        return [
            'name'        => $this->name(),
            'description' => 'Executes a custom artisan command by alias.',
            'target_type' => $this->targetType(),
            'arguments'   => [
                'alias' => [
                    'type'        => 'string',
                    'required'    => true,
                    'description' => 'The alias of the custom command to execute.',
                    'default'     => null,
                ],
            ],
            'example' => [
                'tool'      => $this->name(),
                'arguments' => [
                    'alias' => 'rebuild-search',
                ],
            ],
            'response_example' => [
                'ok'   => true,
                'tool' => $this->name(),
                'result' => [
                    'status'      => 'executed',
                    'target_type' => 'custom_command',
                    'target'      => [
                        'alias' => 'rebuild-search',
                    ],
                    'output' => 'Search index rebuilt successfully.',
                ],
            ],
            'errors' => [
                'resource_not_found' => 'When the alias does not match any defined custom command.',
                'execution_failed'   => 'When the artisan command exits with a non-zero code.',
            ],
            'notes' => [
                'Custom commands are defined by the site operator in the CP settings.',
                'Confirmation may be required depending on the command definition.',
            ],
        ];
    }

    private function findCommand(string $alias): ?array
    {
        $commands = config('ai_gateway.custom_commands', []);

        foreach ($commands as $command) {
            if (($command['alias'] ?? null) === $alias) {
                return $command;
            }
        }

        return null;
    }

    private function rejectUnknownKeys(array $arguments): void
    {
        $allowed = ['alias'];
        $unknown = array_diff(array_keys($arguments), $allowed);

        if (! empty($unknown)) {
            throw new ToolValidationException(
                'Unknown argument keys: ' . implode(', ', $unknown),
                ['unknown_keys' => array_values($unknown)],
            );
        }
    }
}
