<?php

return [
    // Master kill switch — addon is invisible when false
    'enabled' => env('AI_GATEWAY_ENABLED', false),

    // Bearer token for authentication
    'token' => env('AI_GATEWAY_TOKEN'),

    // Request constraints
    'max_request_size' => env('AI_GATEWAY_MAX_REQUEST_SIZE', 65536), // 64KB

    // Rate limits (requests per minute)
    'rate_limits' => [
        'execute' => env('AI_GATEWAY_RATE_LIMIT_EXECUTE', 30),
        'capabilities' => env('AI_GATEWAY_RATE_LIMIT_CAPABILITIES', 60),
    ],

    // Tool enablement — all disabled by default
    'tools' => [
        'entry' => [
            'get'    => env('AI_GATEWAY_TOOL_ENTRY_GET', false),
            'list'   => env('AI_GATEWAY_TOOL_ENTRY_LIST', false),
            'create' => env('AI_GATEWAY_TOOL_ENTRY_CREATE', false),
            'update' => env('AI_GATEWAY_TOOL_ENTRY_UPDATE', false),
            'upsert' => env('AI_GATEWAY_TOOL_ENTRY_UPSERT', false),
        ],
        'global' => [
            'get'    => env('AI_GATEWAY_TOOL_GLOBAL_GET', false),
            'update' => env('AI_GATEWAY_TOOL_GLOBAL_UPDATE', false),
        ],
        'navigation' => [
            'get'    => env('AI_GATEWAY_TOOL_NAVIGATION_GET', false),
            'update' => env('AI_GATEWAY_TOOL_NAVIGATION_UPDATE', false),
        ],
        'term' => [
            'get'    => env('AI_GATEWAY_TOOL_TERM_GET', false),
            'list'   => env('AI_GATEWAY_TOOL_TERM_LIST', false),
            'upsert' => env('AI_GATEWAY_TOOL_TERM_UPSERT', false),
        ],
        'cache' => [
            'clear' => env('AI_GATEWAY_TOOL_CACHE_CLEAR', false),
        ],
    ],

    // Target allowlists — empty by default (nothing permitted)
    'allowed_collections' => [],
    'allowed_globals' => [],
    'allowed_navigations' => [],
    'allowed_taxonomies' => [],
    'allowed_cache_targets' => [],

    // Field-level deny lists per target type
    'denied_fields' => [
        'entry' => [],
        'global' => [],
        'term' => [],
    ],

    // Confirmation flow
    'confirmation' => [
        'ttl' => env('AI_GATEWAY_CONFIRMATION_TTL', 60),
        'tools' => [
            'cache' => [
                'clear' => ['production'],
            ],
        ],
    ],

    // Audit logging
    'audit' => [
        'channel' => env('AI_GATEWAY_LOG_CHANNEL', null),
    ],
];
