<?php

namespace Stokoe\AiGateway\Support;

use Illuminate\Contracts\Container\Container;
use Stokoe\AiGateway\Exceptions\ToolDisabledException;
use Stokoe\AiGateway\Exceptions\ToolNotFoundException;
use Stokoe\AiGateway\Tools\AssetDeleteTool;
use Stokoe\AiGateway\Tools\AssetGetTool;
use Stokoe\AiGateway\Tools\AssetListTool;
use Stokoe\AiGateway\Tools\AssetMoveTool;
use Stokoe\AiGateway\Tools\AssetUploadTool;
use Stokoe\AiGateway\Tools\BlueprintCreateTool;
use Stokoe\AiGateway\Tools\BlueprintDeleteTool;
use Stokoe\AiGateway\Tools\BlueprintGetTool;
use Stokoe\AiGateway\Tools\BlueprintUpdateTool;
use Stokoe\AiGateway\Tools\CacheClearTool;
use Stokoe\AiGateway\Tools\CollectionListTool;
use Stokoe\AiGateway\Tools\Contracts\GatewayTool;
use Stokoe\AiGateway\Tools\CustomCommandTool;
use Stokoe\AiGateway\Tools\EntryCreateTool;
use Stokoe\AiGateway\Tools\EntryDeleteTool;
use Stokoe\AiGateway\Tools\EntryGetTool;
use Stokoe\AiGateway\Tools\EntryListTool;
use Stokoe\AiGateway\Tools\EntryPublishTool;
use Stokoe\AiGateway\Tools\EntrySearchTool;
use Stokoe\AiGateway\Tools\EntryUnpublishTool;
use Stokoe\AiGateway\Tools\EntryUpdateTool;
use Stokoe\AiGateway\Tools\EntryUpsertTool;
use Stokoe\AiGateway\Tools\FormGetTool;
use Stokoe\AiGateway\Tools\FormListTool;
use Stokoe\AiGateway\Tools\FormSubmissionsTool;
use Stokoe\AiGateway\Tools\GlobalGetTool;
use Stokoe\AiGateway\Tools\GlobalUpdateTool;
use Stokoe\AiGateway\Tools\NavigationGetTool;
use Stokoe\AiGateway\Tools\NavigationListTool;
use Stokoe\AiGateway\Tools\NavigationUpdateTool;
use Stokoe\AiGateway\Tools\SiteListTool;
use Stokoe\AiGateway\Tools\StacheWarmTool;
use Stokoe\AiGateway\Tools\StaticWarmTool;
use Stokoe\AiGateway\Tools\SystemInfoTool;
use Stokoe\AiGateway\Tools\TaxonomyGetTool;
use Stokoe\AiGateway\Tools\TaxonomyListTool;
use Stokoe\AiGateway\Tools\TermDeleteTool;
use Stokoe\AiGateway\Tools\TermGetTool;
use Stokoe\AiGateway\Tools\TermListTool;
use Stokoe\AiGateway\Tools\TermUpsertTool;
use Stokoe\AiGateway\Tools\UserCreateTool;
use Stokoe\AiGateway\Tools\UserDeleteTool;
use Stokoe\AiGateway\Tools\UserGetTool;
use Stokoe\AiGateway\Tools\UserListTool;
use Stokoe\AiGateway\Tools\UserUpdateTool;

class ToolRegistry
{
    /** @var array<string, class-string<GatewayTool>> */
    protected array $tools = [
        'entry.get'              => EntryGetTool::class,
        'entry.list'             => EntryListTool::class,
        'entry.create'           => EntryCreateTool::class,
        'entry.update'           => EntryUpdateTool::class,
        'entry.upsert'           => EntryUpsertTool::class,
        'entry.delete'           => EntryDeleteTool::class,
        'entry.search'           => EntrySearchTool::class,
        'entry.publish'          => EntryPublishTool::class,
        'entry.unpublish'        => EntryUnpublishTool::class,
        'global.get'             => GlobalGetTool::class,
        'global.update'          => GlobalUpdateTool::class,
        'navigation.get'         => NavigationGetTool::class,
        'navigation.update'      => NavigationUpdateTool::class,
        'navigation.list'        => NavigationListTool::class,
        'term.get'               => TermGetTool::class,
        'term.list'              => TermListTool::class,
        'term.upsert'            => TermUpsertTool::class,
        'term.delete'            => TermDeleteTool::class,
        'cache.clear'            => CacheClearTool::class,
        'stache.warm'            => StacheWarmTool::class,
        'static.warm'            => StaticWarmTool::class,
        'asset.upload'           => AssetUploadTool::class,
        'asset.list'             => AssetListTool::class,
        'asset.get'              => AssetGetTool::class,
        'asset.delete'           => AssetDeleteTool::class,
        'asset.move'             => AssetMoveTool::class,
        'blueprint.get'          => BlueprintGetTool::class,
        'blueprint.create'       => BlueprintCreateTool::class,
        'blueprint.update'       => BlueprintUpdateTool::class,
        'blueprint.delete'       => BlueprintDeleteTool::class,
        'collection.list'        => CollectionListTool::class,
        'form.get'               => FormGetTool::class,
        'form.list'              => FormListTool::class,
        'form.submissions'       => FormSubmissionsTool::class,
        'site.list'              => SiteListTool::class,
        'custom_command.execute'  => CustomCommandTool::class,
        'taxonomy.list'          => TaxonomyListTool::class,
        'taxonomy.get'           => TaxonomyGetTool::class,
        'user.list'              => UserListTool::class,
        'user.get'               => UserGetTool::class,
        'user.create'            => UserCreateTool::class,
        'user.update'            => UserUpdateTool::class,
        'user.delete'            => UserDeleteTool::class,
        'system.info'            => SystemInfoTool::class,
    ];

    protected Container $app;

    public function __construct(Container $app)
    {
        $this->app = $app;
    }

    /**
     * Resolve a tool by name.
     *
     * @throws ToolNotFoundException  If the tool name is not registered.
     * @throws ToolDisabledException If the tool is registered but disabled.
     */
    public function resolve(string $name): GatewayTool
    {
        if (! isset($this->tools[$name])) {
            throw new ToolNotFoundException($name);
        }

        if (! $this->isEnabled($name)) {
            throw new ToolDisabledException($name);
        }

        return $this->app->make($this->tools[$name]);
    }

    /**
     * Check if a tool is enabled in configuration.
     */
    public function isEnabled(string $name): bool
    {
        return (bool) config("ai_gateway.tools.{$name}", false);
    }

    /**
     * Resolve a tool by name without checking if it is enabled.
     *
     * @throws ToolNotFoundException If the tool name is not registered.
     */
    public function resolveWithoutEnabledCheck(string $name): GatewayTool
    {
        if (! isset($this->tools[$name])) {
            throw new ToolNotFoundException($name);
        }

        return $this->app->make($this->tools[$name]);
    }

    /**
     * Return metadata for all registered tools (for capabilities endpoint).
     */
    public function all(): array
    {
        $result = [];

        foreach ($this->tools as $name => $class) {
            $result[$name] = [
                'enabled' => $this->isEnabled($name),
                'handler' => $class,
            ];
        }

        return $result;
    }

    /**
     * Get the list of registered tool names.
     */
    public function registeredNames(): array
    {
        return array_keys($this->tools);
    }
}
