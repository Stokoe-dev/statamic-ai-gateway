<?php

namespace Stokoe\AiGateway\Tools\Contracts;

use Stokoe\AiGateway\Support\ToolResponse;

interface GatewayTool
{
    /** Tool name as registered in the registry, e.g. 'entry.create' */
    public function name(): string;

    /** Target type for authorization, e.g. 'entry', 'global', 'cache' */
    public function targetType(): string;

    /** Laravel validation rules for the arguments object */
    public function validationRules(): array;

    /** Extract the target identifier from arguments for authorization */
    public function resolveTarget(array $arguments): ?string;

    /** Execute the tool and return a ToolResponse */
    public function execute(array $arguments): ToolResponse;

    /** Whether this tool requires confirmation in the given environment */
    public function requiresConfirmation(string $environment): bool;

    /** Return comprehensive usage documentation for this tool */
    public function describe(): array;
}
