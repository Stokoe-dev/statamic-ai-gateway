<?php

namespace Stokoe\AiGateway\Policies;

class ToolPolicy
{
    /**
     * Mapping from target type to the config key holding the allowlist.
     */
    private const ALLOWLIST_MAP = [
        'entry'      => 'ai_gateway.allowed_collections',
        'global'     => 'ai_gateway.allowed_globals',
        'navigation' => 'ai_gateway.allowed_navigations',
        'taxonomy'   => 'ai_gateway.allowed_taxonomies',
        'cache'      => 'ai_gateway.allowed_cache_targets',
    ];

    /**
     * Check if a tool is enabled in configuration.
     */
    public function toolEnabled(string $toolName): bool
    {
        return (bool) config("ai_gateway.tools.{$toolName}", false);
    }

    /**
     * Check if a target identifier is in the allowlist for the given target type.
     */
    public function targetAllowed(string $targetType, string $target): bool
    {
        $configKey = self::ALLOWLIST_MAP[$targetType] ?? null;

        if ($configKey === null) {
            return false;
        }

        $allowlist = config($configKey, []);

        return in_array($target, $allowlist, true);
    }

    /**
     * Return the list of denied fields for a target type (and optionally a specific target).
     */
    public function deniedFields(string $targetType, ?string $target): array
    {
        return config("ai_gateway.denied_fields.{$targetType}", []);
    }
}
