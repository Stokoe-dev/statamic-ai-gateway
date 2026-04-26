<?php

namespace Stokoe\AiGateway\Http\Controllers;

use Illuminate\Http\Request;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Collection;
use Statamic\Facades\Form;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Nav;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\CpController;
use Stokoe\AiGateway\Support\SettingsRepository;

class SettingsController extends CpController
{
    public function index()
    {
        abort_unless(User::current()?->isSuper(), 403);

        $repo = app(SettingsRepository::class);
        $settings = $repo->resolve();
        $maskedToken = SettingsRepository::maskToken($settings['token'] ?? null);
        $logChannels = array_keys(config('logging.channels', []));

        return view('ai-gateway::settings.index', compact('settings', 'maskedToken', 'logChannels'));
    }

    public function update(Request $request)
    {
        abort_unless(User::current()?->isSuper(), 403);

        $validated = $request->validate([
            'enabled' => 'boolean',
            'token' => 'nullable|string',
            'rate_limits.execute' => 'integer|min:1',
            'rate_limits.capabilities' => 'integer|min:1',
            'max_request_size' => 'integer|min:1024',
            'tools.*.*' => 'boolean',
            'allowed_collections.*' => 'string|filled',
            'allowed_globals.*' => 'string|filled',
            'allowed_navigations.*' => 'string|filled',
            'allowed_taxonomies.*' => 'string|filled',
            'allowed_asset_containers' => 'sometimes|array',
            'allowed_asset_containers.*' => 'string|filled',
            'allowed_forms' => 'sometimes|array',
            'allowed_forms.*' => 'string|filled',
            'allowed_custom_commands' => 'sometimes|array',
            'allowed_custom_commands.*' => 'string|filled',
            'allowed_user_operations' => 'boolean',
            'max_asset_size' => 'integer|min:1',
            'allowed_asset_extensions' => 'sometimes|array',
            'allowed_asset_extensions.*' => 'string|filled',
            'allowed_cache_targets.*' => 'in:application,static,stache,glide',
            'denied_fields.entry.*' => 'string|filled',
            'denied_fields.global.*' => 'string|filled',
            'denied_fields.term.*' => 'string|filled',
            'confirmation.ttl' => 'integer|min:1',
            'audit.channel' => 'nullable|string',
            'custom_commands' => 'sometimes|array',
            'custom_commands.*.alias' => 'required_with:custom_commands|string|filled|regex:/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/',
            'custom_commands.*.command' => 'required_with:custom_commands|string|filled',
            'custom_commands.*.description' => 'nullable|string',
            'custom_commands.*.confirmation_environments' => 'nullable|array',
            'custom_commands.*.confirmation_environments.*' => 'string|filled',
        ]);

        // Validate unique aliases in custom_commands
        if (isset($validated['custom_commands']) && is_array($validated['custom_commands'])) {
            $aliases = array_column($validated['custom_commands'], 'alias');
            $duplicates = array_diff_assoc($aliases, array_unique($aliases));
            if (! empty($duplicates)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'custom_commands' => ['Custom command aliases must be unique. Duplicates: ' . implode(', ', array_unique($duplicates))],
                ]);
            }
        }

        // Convert empty audit channel to null (means "use default")
        if (isset($validated['audit']['channel']) && $validated['audit']['channel'] === '') {
            $validated['audit']['channel'] = null;
        }

        // Convert confirmation tool environment strings to arrays
        if (isset($validated['confirmation']['tools'])) {
            foreach ($validated['confirmation']['tools'] as $group => $actions) {
                foreach ($actions as $action => $envString) {
                    if (is_string($envString)) {
                        $envs = array_filter(array_map('trim', explode(',', $envString)));
                        $validated['confirmation']['tools'][$group][$action] = array_values($envs);
                    }
                }
            }
        }

        $repo = app(SettingsRepository::class);

        // If token was not sent, preserve the existing one
        if (! array_key_exists('token', $validated)) {
            $existing = $repo->read();
            if (isset($existing['token'])) {
                $validated['token'] = $existing['token'];
            }
        }

        try {
            $repo->write($validated);
            $repo->applyToConfig();
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Failed to save settings: '.$e->getMessage()], 500);
            }
            return redirect()->back()->with('error', 'Failed to save settings: '.$e->getMessage());
        }

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Settings saved successfully.']);
        }

        return redirect()->back()->with('success', 'Settings saved successfully.');
    }

    public function resources()
    {
        abort_unless(User::current()?->isSuper(), 403);

        return response()->json([
            'collections' => Collection::all()->map(fn ($c) => [
                'handle' => $c->handle(),
                'title'  => $c->title(),
            ])->values(),
            'globals' => GlobalSet::all()->map(fn ($g) => [
                'handle' => $g->handle(),
                'title'  => $g->title(),
            ])->values(),
            'navigations' => Nav::all()->map(fn ($n) => [
                'handle' => $n->handle(),
                'title'  => $n->title(),
            ])->values(),
            'taxonomies' => Taxonomy::all()->map(fn ($t) => [
                'handle' => $t->handle(),
                'title'  => $t->title(),
            ])->values(),
            'asset_containers' => AssetContainer::all()->map(fn ($ac) => [
                'handle' => $ac->handle(),
                'title'  => $ac->title(),
            ])->values(),
            'forms' => Form::all()->map(fn ($f) => [
                'handle' => $f->handle(),
                'title'  => $f->title(),
            ])->values(),
        ]);
    }
}
