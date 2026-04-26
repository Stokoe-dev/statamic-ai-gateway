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
            'get'       => env('AI_GATEWAY_TOOL_ENTRY_GET', false),
            'list'      => env('AI_GATEWAY_TOOL_ENTRY_LIST', false),
            'create'    => env('AI_GATEWAY_TOOL_ENTRY_CREATE', false),
            'update'    => env('AI_GATEWAY_TOOL_ENTRY_UPDATE', false),
            'upsert'    => env('AI_GATEWAY_TOOL_ENTRY_UPSERT', false),
            'delete'    => env('AI_GATEWAY_TOOL_ENTRY_DELETE', false),
            'search'    => env('AI_GATEWAY_TOOL_ENTRY_SEARCH', false),
            'publish'   => env('AI_GATEWAY_TOOL_ENTRY_PUBLISH', false),
            'unpublish' => env('AI_GATEWAY_TOOL_ENTRY_UNPUBLISH', false),
        ],
        'global' => [
            'get'    => env('AI_GATEWAY_TOOL_GLOBAL_GET', false),
            'update' => env('AI_GATEWAY_TOOL_GLOBAL_UPDATE', false),
        ],
        'navigation' => [
            'get'    => env('AI_GATEWAY_TOOL_NAVIGATION_GET', false),
            'update' => env('AI_GATEWAY_TOOL_NAVIGATION_UPDATE', false),
            'list'   => env('AI_GATEWAY_TOOL_NAVIGATION_LIST', false),
        ],
        'term' => [
            'get'    => env('AI_GATEWAY_TOOL_TERM_GET', false),
            'list'   => env('AI_GATEWAY_TOOL_TERM_LIST', false),
            'upsert' => env('AI_GATEWAY_TOOL_TERM_UPSERT', false),
            'delete' => env('AI_GATEWAY_TOOL_TERM_DELETE', false),
        ],
        'cache' => [
            'clear' => env('AI_GATEWAY_TOOL_CACHE_CLEAR', false),
        ],
        'stache' => [
            'warm' => env('AI_GATEWAY_TOOL_STACHE_WARM', false),
        ],
        'static' => [
            'warm' => env('AI_GATEWAY_TOOL_STATIC_WARM', false),
        ],
        'asset' => [
            'upload' => env('AI_GATEWAY_TOOL_ASSET_UPLOAD', false),
            'list'   => env('AI_GATEWAY_TOOL_ASSET_LIST', false),
            'get'    => env('AI_GATEWAY_TOOL_ASSET_GET', false),
            'delete' => env('AI_GATEWAY_TOOL_ASSET_DELETE', false),
            'move'   => env('AI_GATEWAY_TOOL_ASSET_MOVE', false),
        ],
        'blueprint' => [
            'get'    => env('AI_GATEWAY_TOOL_BLUEPRINT_GET', false),
            'create' => env('AI_GATEWAY_TOOL_BLUEPRINT_CREATE', false),
            'update' => env('AI_GATEWAY_TOOL_BLUEPRINT_UPDATE', false),
            'delete' => env('AI_GATEWAY_TOOL_BLUEPRINT_DELETE', false),
        ],
        'collection' => [
            'list' => env('AI_GATEWAY_TOOL_COLLECTION_LIST', false),
        ],
        'form' => [
            'get'         => env('AI_GATEWAY_TOOL_FORM_GET', false),
            'list'        => env('AI_GATEWAY_TOOL_FORM_LIST', false),
            'submissions' => env('AI_GATEWAY_TOOL_FORM_SUBMISSIONS', false),
        ],
        'site' => [
            'list' => env('AI_GATEWAY_TOOL_SITE_LIST', false),
        ],
        'custom_command' => [
            'execute' => env('AI_GATEWAY_TOOL_CUSTOM_COMMAND_EXECUTE', false),
        ],
        'taxonomy' => [
            'list' => env('AI_GATEWAY_TOOL_TAXONOMY_LIST', false),
            'get'  => env('AI_GATEWAY_TOOL_TAXONOMY_GET', false),
        ],
        'user' => [
            'list'   => env('AI_GATEWAY_TOOL_USER_LIST', false),
            'get'    => env('AI_GATEWAY_TOOL_USER_GET', false),
            'create' => env('AI_GATEWAY_TOOL_USER_CREATE', false),
            'update' => env('AI_GATEWAY_TOOL_USER_UPDATE', false),
            'delete' => env('AI_GATEWAY_TOOL_USER_DELETE', false),
        ],
        'system' => [
            'info' => env('AI_GATEWAY_TOOL_SYSTEM_INFO', false),
        ],
    ],

    // Target allowlists — empty by default (nothing permitted)
    'allowed_collections' => [],
    'allowed_globals' => [],
    'allowed_navigations' => [],
    'allowed_taxonomies' => [],
    'allowed_cache_targets' => [],
    'allowed_asset_containers' => [],
    'allowed_forms' => [],
    'allowed_custom_commands' => [],

    // User management — toggle-based authorization (not per-resource allowlist)
    'allowed_user_operations' => false,

    // Asset upload constraints
    'max_asset_size' => env('AI_GATEWAY_MAX_ASSET_SIZE', 10485760), // 10MB
    'allowed_asset_extensions' => [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv',
        'txt', 'md', 'mp4', 'webm', 'mp3',
    ],

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
            'stache' => [
                'warm' => [],
            ],
            'static' => [
                'warm' => [],
            ],
            'entry' => [
                'delete'    => ['production'],
                'unpublish' => ['production'],
            ],
            'term' => [
                'delete' => ['production'],
            ],
            'asset' => [
                'upload' => ['production'],
                'delete' => ['production'],
                'move'   => ['production'],
            ],
            'blueprint' => [
                'delete' => ['production'],
            ],
            'user' => [
                'create' => ['production'],
                'delete' => ['production'],
            ],
        ],
    ],

    // Audit logging
    'audit' => [
        'channel' => env('AI_GATEWAY_LOG_CHANNEL', null),
    ],

    // Custom commands (populated via CP settings)
    'custom_commands' => [],
];
