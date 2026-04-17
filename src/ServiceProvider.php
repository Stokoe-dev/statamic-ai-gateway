<?php

namespace Stokoe\AiGateway;

use Statamic\Facades\CP\Nav;
use Statamic\Providers\AddonServiceProvider;
use Stokoe\AiGateway\Support\AuditLogger;
use Stokoe\AiGateway\Support\ConfirmationTokenManager;
use Stokoe\AiGateway\Support\SettingsRepository;
use Stokoe\AiGateway\Support\ToolRegistry;

class ServiceProvider extends AddonServiceProvider
{
    protected $viewNamespace = 'ai-gateway';

    protected $vite = [
        'input' => [
            'resources/js/cp.js',
        ],
        'publicDirectory' => 'resources/dist',
        'hotFile' => __DIR__.'/../resources/dist/hot',
    ];

    public function register(): void
    {
        parent::register();

        $this->mergeConfigFrom(__DIR__.'/../config/ai_gateway.php', 'ai_gateway');

        $this->app->singleton(SettingsRepository::class);

        $this->app->make(SettingsRepository::class)->applyToConfig();
    }

    public function bootAddon(): void
    {
        // Always register publishable config — must work even when addon is disabled
        $this->publishes([
            __DIR__.'/../config/ai_gateway.php' => config_path('ai_gateway.php'),
        ], 'ai-gateway-config');

        $this->bootCp(); // Always runs — admins must access settings even when gateway is disabled

        if (! config('ai_gateway.enabled')) {
            return; // No routes, no bindings — addon is invisible
        }

        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        $this->app->singleton(ToolRegistry::class, function ($app) {
            return new ToolRegistry($app);
        });
        $this->app->singleton(AuditLogger::class);
        $this->app->singleton(ConfirmationTokenManager::class);
    }

    protected function bootCp(): void
    {
        Nav::extend(function ($nav) {
            $nav->tools('AI Gateway')
                ->route('ai-gateway.settings.index')
                ->icon('ai-chat-spark');
        });
    }
}
