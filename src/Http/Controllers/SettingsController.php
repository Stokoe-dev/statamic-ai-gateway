<?php

namespace Stokoe\AiGateway\Http\Controllers;

use Illuminate\Http\Request;
use Statamic\Facades\User;
use Statamic\Facades\Collection;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Nav;
use Statamic\Facades\Taxonomy;
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
            'allowed_cache_targets.*' => 'in:application,static,stache,glide',
            'denied_fields.entry.*' => 'string|filled',
            'denied_fields.global.*' => 'string|filled',
            'denied_fields.term.*' => 'string|filled',
            'confirmation.ttl' => 'integer|min:1',
            'audit.channel' => 'nullable|string',
        ]);

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
        ]);
    }
}
