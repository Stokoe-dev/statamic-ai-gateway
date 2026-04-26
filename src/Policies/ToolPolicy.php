<?php

namespace Stokoe\AiGateway\Policies;

class ToolPolicy
{
    /**
     * Mapping from target type to the config key holding the allowlist.
     * A null value means no allowlist restriction (always allowed).
     */
    private const ALLOWLIST_MAP = [
        'entry'          => 'ai_gateway.allowed_collections',
        'global'         => 'ai_gateway.allowed_globals',
        'navigation'     => 'ai_gateway.allowed_navigations',
        'taxonomy'       => 'ai_gateway.allowed_taxonomies',
        'cache'          => 'ai_gateway.allowed_cache_targets',
        'asset'          => 'ai_gateway.allowed_asset_containers',
        'form'           => 'ai_gateway.allowed_forms',
        'custom_command' => 'ai_gateway.allowed_custom_commands',
        'user'           => 'ai_gateway.allowed_user_operations',
        'site'           => null,
        'system'         => null,
        'blueprint'      => null,
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
     *
     * Returns true when:
     * - The target type has no allowlist (config key is null) — e.g. site, system, blueprint
     * - The config value is a boolean — returns that boolean directly (e.g. user toggle)
     * - The target appears in the allowlist array
     */
    public function targetAllowed(string $targetType, string $target): bool
    {
        if (! array_key_exists($targetType, self::ALLOWLIST_MAP)) {
            return false;
        }

        $configKey = self::ALLOWLIST_MAP[$targetType];

        if ($configKey === null) {
            return true;
        }

        $allowlist = config($configKey, []);

        if (is_bool($allowlist)) {
            return $allowlist;
        }

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
