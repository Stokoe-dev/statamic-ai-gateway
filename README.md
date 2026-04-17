# AI Gateway

A safe, structured interface between AI agents and your Statamic application.

---

## Table of Contents

- [Overview](#overview)
- [Why This Addon Exists](#why-this-addon-exists)
- [Core Philosophy](#core-philosophy)
- [Architecture](#architecture)
  - [Key Design Decisions](#key-design-decisions)
  - [High-Level Architecture](#high-level-architecture)
  - [Request Pipeline](#request-pipeline)
  - [Module Structure](#module-structure)
  - [How the Pipeline Works](#how-the-pipeline-works)
- [Installation](#installation)
- [Enabling the Addon](#enabling-the-addon)
  - [How the Kill Switch Works](#how-the-kill-switch-works)
- [Control Panel Settings](#control-panel-settings)
  - [Accessing the Settings Panel](#accessing-the-settings-panel)
  - [What You Can Configure](#what-you-can-configure)
  - [How Settings Persistence Works](#how-settings-persistence-works)
  - [Configuration Precedence](#configuration-precedence)
- [Endpoints](#endpoints)
- [Configuration Reference](#configuration-reference)
  - [Full Configuration File](#full-configuration-file)
  - [Environment Variables](#environment-variables)
- [Security Model](#security-model)
  - [Three Layers of Authorization](#three-layers-of-authorization)
  - [Authentication](#authentication)
  - [Rate Limiting](#rate-limiting)
  - [Production Safety](#production-safety)
  - [Request Size Limit](#request-size-limit)
- [Confirmation Flow](#confirmation-flow)
  - [How It Works](#how-it-works)
  - [Token Implementation](#token-implementation)
- [Audit Logging](#audit-logging)
  - [What Gets Logged](#what-gets-logged)
  - [What Never Gets Logged](#what-never-gets-logged)
- [Tool Reference](#tool-reference)
  - [`entry.create`](#entrycreate)
  - [`entry.update`](#entryupdate)
  - [`entry.upsert`](#entryupsert)
  - [`global.update`](#globalupdate)
  - [`navigation.update`](#navigationupdate)
  - [`term.upsert`](#termupsert)
  - [`cache.clear`](#cacheclear)
- [Request / Response Contract](#request--response-contract)
  - [Request Envelope](#request-envelope)
  - [Success Response](#success-response)
  - [Error Response](#error-response)
  - [Confirmation Required Response](#confirmation-required-response)
  - [Error Codes](#error-codes)
  - [Exception Mapping](#exception-mapping)
- [Capability Discovery](#capability-discovery)
- [Internal Components](#internal-components)
  - [GatewayTool Interface](#gatewaytool-interface)
  - [ToolRegistry](#toolregistry)
  - [ToolPolicy](#toolpolicy)
  - [FieldFilter](#fieldfilter)
  - [ToolResponse](#toolresponse)
- [Testing](#testing)
  - [Property-Based Tests (Eris)](#property-based-tests-eris)
  - [Unit Tests](#unit-tests)
  - [Integration Tests](#integration-tests)
  - [Running Tests](#running-tests)
- [Extending with Custom Tools](#extending-with-custom-tools)
- [When Should You Use This?](#when-should-you-use-this)
- [When This Might Not Be For You](#when-this-might-not-be-for-you)
- [Minimal Setup Example](#minimal-setup-example)
- [Agent Setup Guide](#agent-setup-guide)
  - [1. Install the Skill](#1-install-the-skill)
  - [2. Prepare Each Site](#2-prepare-each-site)
  - [3. Configure the Agent](#3-configure-the-agent)
  - [4. Discover Capabilities Per Site](#4-discover-capabilities-per-site)
  - [5. Multi-Site Operation Tips](#5-multi-site-operation-tips)
  - [6. Verifying the Connection](#6-verifying-the-connection)
- [Future Direction](#future-direction)

---

## Overview

AI Gateway is a Statamic v6 addon (`stokoe/ai-gateway`) that provides a controlled, authenticated HTTP tool execution interface for AI agents. The addon acts as a gateway layer between external AI systems and your Statamic/Laravel application, mediating all AI-initiated content mutations and operational actions through a strict pipeline of authentication, validation, authorization, tool resolution, execution, and audit logging.

Rather than exposing your application directly, this addon introduces a **tool-based execution layer**. AI agents can request actions such as creating content, updating globals, modifying navigation, or clearing caches — and the addon decides whether, how, and when those actions are allowed to happen.

This makes it possible to bring AI-assisted workflows into Statamic without compromising stability, security, or developer control.

---

## Why This Addon Exists

AI integrations are powerful — but they can also be dangerous if given unrestricted access.

Most approaches fall into one of two categories:

- **Direct access** to application internals (risky, hard to control)
- **Large tool surfaces** with many granular actions (complex, error-prone)

This addon takes a different approach:

> **A small, well-defined set of safe actions, enforced by strict rules.**

Instead of asking "what *can* the AI do?", this addon answers:

> **"What is the AI allowed to do safely?"**

---

## Core Philosophy

### 1. Controlled access, not full access

Your agent does not get direct access to your application. Instead, it interacts through **named tools**, each with explicit inputs, strict validation rules, and clear boundaries.

### 2. Safe by default

Everything is locked down unless you explicitly allow it. Tools must be enabled. Targets must be allowlisted. Fields can be restricted. Risky operations can require confirmation. If something isn't explicitly allowed, it doesn't happen.

### 3. Designed for production

This is not just a development tool — it's built to run safely in real environments. That means structured request/response contracts, predictable error handling, rate limiting, audit logging via Laravel's logging system, and confirmation flows for sensitive operations.

### 4. Fewer, smarter tools

Instead of exposing dozens of granular operations, this addon provides a small set of high-level actions. For example, `entry.upsert` instead of separate create/update flows, and `cache.clear` instead of multiple low-level commands. This makes it easier for agents to choose the right action, avoid mistakes, and behave predictably.

### 5. Framework-native execution

All actions are executed using Statamic and Laravel APIs, not direct file or system manipulation. This ensures compatibility with your existing setup, respect for blueprints and content structure, and consistency with how your application normally behaves.

---

## Architecture

### Key Design Decisions

1. **In-process addon over separate service** — Direct access to Statamic APIs, simpler deployment, no cross-service auth sync needed.
2. **Tool gateway pattern** — Named tools with structured arguments rather than generic remote access. Each capability is intentionally added.
3. **Configuration-driven allowlisting** — Default-deny posture where every tool, target, and field must be explicitly permitted.
4. **Synchronous execution only (v1)** — Simpler mental model, immediate feedback, appropriate for expected operation volume.
5. **Stateless confirmation tokens** — HMAC-signed tokens for production confirmation flow, avoiding database persistence.
6. **Single execution endpoint** — All tools invoked through one route, keeping the API contract minimal and consistent.

### High-Level Architecture

```mermaid
flowchart LR
    A[AI Agent] -->|HTTP POST/GET| B[Laravel Router]
    B --> C[AuthenticateGateway Middleware]
    C --> D[EnforceRateLimit Middleware]
    D --> E[ToolExecutionController]
    E --> F[Request Envelope Validation]
    F --> G[ToolRegistry]
    G --> H[Policy Layer - Authorization]
    H --> I[Tool Handler]
    I --> J[Statamic/Laravel APIs]
    I --> K[AuditLogger]
    K --> L[Laravel Log Channel]
    J --> M[Content Store / Cache]
```

### Request Pipeline

```mermaid
sequenceDiagram
    participant AG as AI Agent
    participant MW as Middleware Stack
    participant CTRL as ToolExecutionController
    participant REG as ToolRegistry
    participant POL as Policy Layer
    participant TOOL as Tool Handler
    participant STA as Statamic/Laravel
    participant LOG as AuditLogger

    AG->>MW: POST /ai-gateway/execute
    MW->>MW: AuthenticateGateway (bearer token)
    MW->>MW: EnforceRateLimit (per-token bucket)
    MW->>CTRL: Authenticated request
    CTRL->>CTRL: Validate request envelope
    CTRL->>REG: Resolve tool by name
    REG-->>CTRL: Tool handler instance
    CTRL->>POL: Authorize (tool-level, target-level, field-level)
    POL-->>CTRL: Authorized / Denied
    CTRL->>CTRL: Check confirmation flow (if production + gated)
    CTRL->>TOOL: Execute with validated arguments
    TOOL->>STA: Perform operation
    STA-->>TOOL: Result
    TOOL-->>CTRL: ToolResponse
    CTRL->>LOG: Record audit event
    CTRL-->>AG: JSON Response Envelope
```

### Module Structure

```
addons/stokoe/ai-gateway/
├── src/
│   ├── ServiceProvider.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── ToolExecutionController.php
│   │   └── Middleware/
│   │       ├── AuthenticateGateway.php
│   │       └── EnforceRateLimit.php
│   ├── Tools/
│   │   ├── Contracts/
│   │   │   └── GatewayTool.php
│   │   ├── EntryCreateTool.php
│   │   ├── EntryUpdateTool.php
│   │   ├── EntryUpsertTool.php
│   │   ├── GlobalUpdateTool.php
│   │   ├── NavigationUpdateTool.php
│   │   ├── TermUpsertTool.php
│   │   └── CacheClearTool.php
│   ├── Support/
│   │   ├── ToolRegistry.php
│   │   ├── ToolResponse.php
│   │   ├── ConfirmationTokenManager.php
│   │   ├── AuditLogger.php
│   │   └── FieldFilter.php
│   ├── Policies/
│   │   └── ToolPolicy.php
│   └── Exceptions/
│       ├── ToolNotFoundException.php
│       ├── ToolDisabledException.php
│       ├── ToolAuthorizationException.php
│       └── ToolValidationException.php
├── config/
│   └── ai_gateway.php
├── routes/
│   └── api.php
└── tests/
    ├── TestCase.php
    ├── Property/          (11 property-based tests)
    ├── Unit/              (addon lifecycle tests)
    └── Integration/       (full pipeline tests)
```

### How the Pipeline Works

At a high level:

1. Your agent sends a JSON request describing what it wants to do
2. The `AuthenticateGateway` middleware validates the bearer token using `hash_equals()` for timing-safe comparison
3. The `EnforceRateLimit` middleware checks per-token rate limits
4. The `ToolExecutionController` validates the request envelope (content type, size, schema)
5. The `ToolRegistry` resolves the tool name to a handler class
6. The `ToolPolicy` checks tool-level authorization (is the tool enabled?) and target-level authorization (is the target in the allowlist?)
7. The `FieldFilter` strips any denied fields from the payload
8. The `ConfirmationTokenManager` checks whether confirmation is required (production safety)
9. Tool-specific validation runs against the handler's declared rules
10. The tool executes through Statamic/Laravel APIs
11. The `AuditLogger` records the operation
12. A structured JSON response is returned

All of this happens through a single, consistent interface.

---

## Installation

The addon is installed as a local package at `addons/stokoe/ai-gateway/`. To publish the configuration file:

```bash
php artisan vendor:publish --tag=ai-gateway-config
```

This copies `ai_gateway.php` into your application's `config/` directory, where you can customise it.

---

## Enabling the Addon

The addon is **disabled by default**. When disabled, it registers no routes, no bindings — it is completely invisible. Requests to `/ai-gateway/*` return Laravel's standard 404, leaking no information about the addon's existence.

Add to your `.env`:

```env
AI_GATEWAY_ENABLED=true
AI_GATEWAY_TOKEN=your-secret-token-here
```

`AI_GATEWAY_TOKEN` is the bearer token that all requests must include. Generate a strong, random string — this is your only authentication layer. The token is compared using `hash_equals()` to prevent timing attacks.

### How the Kill Switch Works

The `ServiceProvider` merges config in `register()`, then checks `ai_gateway.enabled` in `bootAddon()`. When disabled, it returns immediately — no routes are loaded, no singletons are bound. The addon is architecturally invisible.

```php
public function bootAddon(): void
{
    if (! config('ai_gateway.enabled')) {
        return;
    }
    // ... routes, singletons, publishable config
}
```

---

## Control Panel Settings

The addon includes a full settings panel in the Statamic Control Panel, so you can configure everything from the browser without touching `.env` files or config files.

### Accessing the Settings Panel

Once the addon is installed, an "AI Gateway" item appears in the CP sidebar under Tools. Click it to open the settings page. The panel is available even when the gateway API is disabled — you need to be able to turn it on from somewhere.

Only super admins can access the settings panel. Regular CP users won't see the nav item, and direct URL access returns a 403.

### What You Can Configure

The settings panel covers every configuration option:

- **General** — Master enable/disable toggle and bearer token management (masked display, reveal, and one-click generation of cryptographically random 64-character hex tokens)
- **Rate Limits** — Requests per minute for the execute and capabilities endpoints
- **Request Limits** — Maximum request body size in bytes
- **Tools** — Individual enable/disable toggles for all 13 tools, grouped by type (entry, global, navigation, term, cache)
- **Allowlists** — Tag-input editors for collections, globals, navigations, and taxonomies, plus checkboxes for cache targets
- **Field Deny Lists** — Tag-input editors for entry, global, and term denied fields
- **Confirmation Flow** — Token TTL and per-tool environment rules (e.g. require confirmation for `cache.clear` in production)
- **Audit** — Log channel selection from your configured Laravel log channels

### How Settings Persistence Works

Settings are saved to a YAML file at `storage/statamic/addons/ai-gateway/settings.yaml`. This keeps them:

- Version-controllable (if you include storage in your repo)
- Consistent with Statamic's flat-file philosophy
- Independent of the database
- Portable across environments

The file is created automatically on first save. The `SettingsRepository` handles reading, writing, and directory creation.

### Configuration Precedence

The addon uses a layered configuration model:

1. **CP settings** (YAML file) — highest priority, wins when present
2. **Published config** (`config/ai_gateway.php`) — mid-level, used when no CP value exists
3. **Package defaults** (addon's built-in config with `.env` fallbacks) — lowest priority

This means:

- You can start with just `.env` variables and no CP configuration
- Once you save something in the CP, that value takes over
- `.env` values still work as fallbacks for anything you haven't configured in the CP
- Deleting the YAML file resets everything back to `.env`/config defaults

The merge happens at boot time via `SettingsRepository::applyToConfig()`, which runs `array_replace_recursive()` to merge YAML values over config defaults. All existing `config('ai_gateway.*')` calls throughout the codebase continue to work without modification.

---

## Endpoints

Once enabled, two routes are available:

| Method | Path                       | Purpose                              |
|--------|----------------------------|--------------------------------------|
| POST   | `/ai-gateway/execute`      | Execute a tool                       |
| GET    | `/ai-gateway/capabilities` | Discover available tools and config  |

Both require the `Authorization: Bearer <token>` header. Both pass through `AuthenticateGateway` and `EnforceRateLimit` middleware.

---

## Configuration Reference

### Full Configuration File

The complete `config/ai_gateway.php`:

```php
return [
    // Master kill switch — addon is invisible when false
    'enabled' => env('AI_GATEWAY_ENABLED', false),

    // Bearer token for authentication
    'token' => env('AI_GATEWAY_TOKEN'),

    // Maximum request body size in bytes (default 64KB)
    'max_request_size' => env('AI_GATEWAY_MAX_REQUEST_SIZE', 65536),

    // Rate limits (requests per minute, per token)
    'rate_limits' => [
        'execute'      => env('AI_GATEWAY_RATE_LIMIT_EXECUTE', 30),
        'capabilities' => env('AI_GATEWAY_RATE_LIMIT_CAPABILITIES', 60),
    ],

    // Tool enablement — all disabled by default
    'tools' => [
        'entry.create'       => env('AI_GATEWAY_TOOL_ENTRY_CREATE', false),
        'entry.update'       => env('AI_GATEWAY_TOOL_ENTRY_UPDATE', false),
        'entry.upsert'       => env('AI_GATEWAY_TOOL_ENTRY_UPSERT', false),
        'global.update'      => env('AI_GATEWAY_TOOL_GLOBAL_UPDATE', false),
        'navigation.update'  => env('AI_GATEWAY_TOOL_NAVIGATION_UPDATE', false),
        'term.upsert'        => env('AI_GATEWAY_TOOL_TERM_UPSERT', false),
        'cache.clear'        => env('AI_GATEWAY_TOOL_CACHE_CLEAR', false),
    ],

    // Target allowlists — empty by default (nothing permitted)
    'allowed_collections'   => [],
    'allowed_globals'       => [],
    'allowed_navigations'   => [],
    'allowed_taxonomies'    => [],
    'allowed_cache_targets' => [],

    // Field-level deny lists per target type
    'denied_fields' => [
        'entry'  => [],
        'global' => [],
        'term'   => [],
    ],

    // Confirmation flow
    'confirmation' => [
        'ttl'   => env('AI_GATEWAY_CONFIRMATION_TTL', 60), // seconds
        'tools' => [
            'cache.clear' => ['production'],
        ],
    ],

    // Audit logging
    'audit' => [
        'channel' => env('AI_GATEWAY_LOG_CHANNEL', null), // null = default channel
    ],
];
```

### Environment Variables

| Variable                            | Default  | Description                                    |
|-------------------------------------|----------|------------------------------------------------|
| `AI_GATEWAY_ENABLED`               | `false`  | Master kill switch                             |
| `AI_GATEWAY_TOKEN`                 | `null`   | Bearer token for authentication                |
| `AI_GATEWAY_MAX_REQUEST_SIZE`      | `65536`  | Max request body in bytes                      |
| `AI_GATEWAY_RATE_LIMIT_EXECUTE`    | `30`     | Requests/min for execute endpoint              |
| `AI_GATEWAY_RATE_LIMIT_CAPABILITIES`| `60`    | Requests/min for capabilities endpoint         |
| `AI_GATEWAY_TOOL_ENTRY_CREATE`     | `false`  | Enable entry.create tool                       |
| `AI_GATEWAY_TOOL_ENTRY_UPDATE`     | `false`  | Enable entry.update tool                       |
| `AI_GATEWAY_TOOL_ENTRY_UPSERT`    | `false`  | Enable entry.upsert tool                       |
| `AI_GATEWAY_TOOL_GLOBAL_UPDATE`    | `false`  | Enable global.update tool                      |
| `AI_GATEWAY_TOOL_NAVIGATION_UPDATE`| `false`  | Enable navigation.update tool                  |
| `AI_GATEWAY_TOOL_TERM_UPSERT`     | `false`  | Enable term.upsert tool                        |
| `AI_GATEWAY_TOOL_CACHE_CLEAR`     | `false`  | Enable cache.clear tool                        |
| `AI_GATEWAY_CONFIRMATION_TTL`     | `60`     | Confirmation token lifetime in seconds         |
| `AI_GATEWAY_LOG_CHANNEL`          | `null`   | Laravel log channel for audit (null = default) |

---

## Security Model

### Three Layers of Authorization

The addon enforces a **default-deny** security model with three layers:

**Layer 1 — Tool-level:** Each tool must be explicitly enabled in config. A disabled tool returns `403 tool_disabled`.

**Layer 2 — Target-level:** Even with a tool enabled, it can only operate on explicitly allowed targets. The `ToolPolicy` maps each tool's target type to its corresponding allowlist:

| Tool target type | Config key                      |
|------------------|---------------------------------|
| `entry`          | `ai_gateway.allowed_collections`|
| `global`         | `ai_gateway.allowed_globals`    |
| `navigation`     | `ai_gateway.allowed_navigations`|
| `taxonomy`       | `ai_gateway.allowed_taxonomies` |
| `cache`          | `ai_gateway.allowed_cache_targets`|

A request targeting something not in the allowlist returns `403 forbidden`.

**Layer 3 — Field-level:** Even within allowed targets, specific fields can be denied:

```php
'denied_fields' => [
    'entry'  => ['slug', 'date', 'author', 'blueprint'],
    'global' => ['site_secret_key'],
    'term'   => [],
],
```

Denied fields are silently stripped from the `data` payload before the tool executes. The caller is not notified — the fields simply don't get written. This happens after target-level authorization and before tool execution.

### Authentication

All requests require an `Authorization: Bearer <token>` header. The `AuthenticateGateway` middleware:

- Rejects missing headers → `401 unauthorized`
- Rejects malformed headers (not `Bearer <token>`) → `401 unauthorized`
- Compares tokens using `hash_equals()` for timing-safe comparison → `401 unauthorized` on mismatch
- Loads the expected token from `config('ai_gateway.token')`, never from hardcoded values

### Rate Limiting

The `EnforceRateLimit` middleware uses Laravel's `RateLimiter` facade with per-token buckets:

- Execute endpoint: `ai_gateway.rate_limits.execute` (default 30/min)
- Capabilities endpoint: `ai_gateway.rate_limits.capabilities` (default 60/min)
- Rate limit key is derived from a SHA-256 hash of the bearer token
- Exceeding the limit returns `429 rate_limited`

### Production Safety

In production (`APP_ENV=production`):

- Stack traces and filesystem paths are never included in error responses
- Exception class names are not exposed
- The `error.message` for `internal_error` is generic: "An internal error occurred"
- Full exception details are written to the audit log for operator diagnosis
- The `cache.clear` tool requires a two-step confirmation flow by default

### Request Size Limit

The maximum request body size defaults to 64KB:

```env
AI_GATEWAY_MAX_REQUEST_SIZE=65536
```

Oversized requests are rejected with `422 validation_failed` before any processing occurs.

---

## Confirmation Flow

Sensitive operations can require a two-step confirmation in specific environments. By default, `cache.clear` requires confirmation in production:

```php
'confirmation' => [
    'ttl'   => env('AI_GATEWAY_CONFIRMATION_TTL', 60),
    'tools' => [
        'cache.clear' => ['production'],
    ],
],
```

### How It Works

1. Agent calls a confirmation-gated tool without a token
2. The addon returns `confirmation_required` with a signed, short-lived token
3. Agent resends the exact same request with the `confirmation_token` field
4. The addon validates the token and executes the tool

First request → receives token:
```json
{
    "ok": false,
    "error": { "code": "confirmation_required", "message": "This operation requires explicit confirmation in production." },
    "confirmation": {
        "token": "base64-encoded-hmac-token",
        "expires_at": "2026-04-14T12:05:00+00:00",
        "operation_summary": { "tool": "cache.clear", "target": "static", "environment": "production" }
    },
    "meta": {}
}
```

Second request → include the token:
```json
{
    "tool": "cache.clear",
    "arguments": { "target": "static" },
    "confirmation_token": "base64-encoded-hmac-token"
}
```

### Token Implementation

Tokens are generated by the `ConfirmationTokenManager` using HMAC-SHA256:

- **Signing input:** `tool_name | canonical_arguments_json | timestamp`
- **Signing key:** Laravel's `APP_KEY`
- **Token format:** `base64(timestamp.signature)`
- **Expiry:** Checked by extracting the embedded timestamp — no database required
- **Binding:** Tokens are bound to the exact tool + arguments they were issued for. A token for `cache.clear` with `target: "static"` will not validate for `target: "stache"`

This prevents:
- Accidental destructive actions
- One-step execution of sensitive operations
- Token reuse across different operations
- Unintended automation loops

---

## Audit Logging

Every request to the execute endpoint is logged — including rejected requests. Logs go through Laravel's logging system.

```env
AI_GATEWAY_LOG_CHANNEL=stack
```

Set to `null` (default) to use your application's default log channel.

### What Gets Logged

Each log entry (written as `ai_gateway.audit` via `Log::info()`) includes:

| Field               | Description                                    |
|---------------------|------------------------------------------------|
| `request_id`        | Client-provided tracking ID (if any)           |
| `idempotency_key`   | Client-provided dedup key (if any)             |
| `tool`              | Tool name that was invoked                     |
| `status`            | `succeeded`, `failed`, or `rejected`           |
| `http_status`       | HTTP status code of the response               |
| `target_type`       | `entry`, `global`, `navigation`, `taxonomy`, `cache` |
| `target_identifier` | The specific target (e.g. collection handle)   |
| `environment`       | Application environment                        |
| `duration_ms`       | Request processing time in milliseconds        |
| `error_code`        | Error code (when applicable)                   |

### What Never Gets Logged

The `AuditLogger` explicitly strips these sensitive keys:

- Bearer tokens / authorization headers
- Confirmation tokens
- Raw request payloads
- Passwords and secrets

---

## Tool Reference

### `entry.create`

Creates a new entry in a collection. Returns `409 conflict` if the entry already exists.

```json
{
    "tool": "entry.create",
    "arguments": {
        "collection": "pages",
        "slug": "hello-world",
        "site": "default",
        "published": true,
        "data": { "title": "Hello World", "content": "Welcome to our site." }
    }
}
```

| Argument     | Required | Type    | Default     | Notes                                  |
|-------------|----------|---------|-------------|----------------------------------------|
| `collection` | yes      | string  |             | Must be in `allowed_collections`       |
| `slug`       | yes      | string  |             | Unique within collection + site        |
| `data`       | yes      | object  |             | Field values; validated against blueprint |
| `published`  | no       | boolean |             | Publish state of the entry             |
| `site`       | no       | string  | `"default"` | For multi-site setups                  |

- Checks collection exists via `Collection::findByHandle()` → `404 resource_not_found`
- Checks entry doesn't already exist → `409 conflict`
- Validates `data` against the collection's blueprint when available
- Creates via `Entry::make()`

### `entry.update`

Updates an existing entry. Merges the provided `data` onto the existing entry — only the fields you send are changed, everything else is preserved.

Same arguments as `entry.create`. Returns `404 resource_not_found` if the entry doesn't exist.

### `entry.upsert`

Creates the entry if it doesn't exist, updates it if it does. Returns `status: "created"` or `status: "updated"`. Same arguments as `entry.create`.

This is the safest choice for most content operations — no need to check existence first, no risk of `conflict` errors.

### `global.update`

Updates a global variable set's localized values for a given site.

```json
{
    "tool": "global.update",
    "arguments": {
        "handle": "contact_information",
        "site": "default",
        "data": { "phone": "555-0200", "email": "hello@example.com" }
    }
}
```

| Argument | Required | Type   | Default     | Notes                            |
|----------|----------|--------|-------------|----------------------------------|
| `handle` | yes      | string |             | Must be in `allowed_globals`     |
| `data`   | yes      | object |             | Field values as key-value pairs  |
| `site`   | no       | string | `"default"` | For multi-site setups            |

- Finds global set via `GlobalSet::findByHandle()` → `404 resource_not_found`
- Gets or creates localized variables for the site

### `navigation.update`

Replaces an entire navigation tree. This is a **full replacement** — the existing tree is discarded entirely.

```json
{
    "tool": "navigation.update",
    "arguments": {
        "handle": "main_navigation",
        "site": "default",
        "tree": [
            { "url": "/", "title": "Home" },
            { "url": "/about", "title": "About" },
            { "url": "/contact", "title": "Contact" }
        ]
    }
}
```

| Argument | Required | Type   | Default     | Notes                              |
|----------|----------|--------|-------------|------------------------------------|
| `handle` | yes      | string |             | Must be in `allowed_navigations`   |
| `tree`   | yes      | array  |             | Complete navigation structure       |
| `site`   | no       | string | `"default"` | For multi-site setups              |

- Finds navigation via `Nav::findByHandle()` → `404 resource_not_found`
- Always send the complete tree — partial patches are not supported

### `term.upsert`

Creates or updates a taxonomy term.

```json
{
    "tool": "term.upsert",
    "arguments": {
        "taxonomy": "tags",
        "slug": "laravel",
        "site": "default",
        "data": { "title": "Laravel" }
    }
}
```

| Argument   | Required | Type   | Default     | Notes                              |
|-----------|----------|--------|-------------|------------------------------------|
| `taxonomy` | yes      | string |             | Must be in `allowed_taxonomies`    |
| `slug`     | yes      | string |             | Term identifier                    |
| `data`     | yes      | object |             | Field values as key-value pairs    |
| `site`     | no       | string | `"default"` | For multi-site setups              |

- Finds taxonomy via `Taxonomy::findByHandle()` → `404 resource_not_found`
- Returns `status: "created"` or `status: "updated"`

### `cache.clear`

Clears a specific cache target. Confirmation-gated in production by default.

```json
{
    "tool": "cache.clear",
    "arguments": {
        "target": "stache"
    }
}
```

| Argument | Required | Type   | Allowed values                              |
|----------|----------|--------|---------------------------------------------|
| `target` | yes      | string | `application`, `static`, `stache`, `glide`  |

Each target maps to an Artisan command:

| Target        | Artisan command                |
|---------------|--------------------------------|
| `application` | `cache:clear`                  |
| `static`      | `statamic:static:clear`        |
| `stache`      | `statamic:stache:clear`        |
| `glide`       | `statamic:glide:clear`         |

- `requiresConfirmation()` checks `config('ai_gateway.confirmation.tools.cache.clear')` for the current environment
- Must be in `allowed_cache_targets`

---

## Request / Response Contract

### Request Envelope

Every request to `POST /ai-gateway/execute` uses the same envelope:

```json
{
    "tool": "entry.create",
    "arguments": { "collection": "pages", "slug": "test", "data": { "title": "Test" } },
    "request_id": "optional-tracking-id",
    "idempotency_key": "optional-dedup-key",
    "confirmation_token": "optional-if-confirming"
}
```

| Field                | Required | Type   | Description                                |
|----------------------|----------|--------|--------------------------------------------|
| `tool`               | yes      | string | Tool name to invoke                        |
| `arguments`          | yes      | object | Tool-specific arguments                    |
| `request_id`         | no       | string | Echoed back in `meta.request_id`           |
| `idempotency_key`    | no       | string | Included in audit log for dedup tracking   |
| `confirmation_token` | no       | string | Required when confirming a gated operation |

### Success Response

```json
{
    "ok": true,
    "tool": "entry.create",
    "result": {
        "status": "created",
        "target_type": "entry",
        "target": { "collection": "pages", "slug": "hello-world", "site": "default" }
    },
    "meta": { "request_id": "your-tracking-id" }
}
```

### Error Response

```json
{
    "ok": false,
    "tool": "entry.create",
    "error": {
        "code": "validation_failed",
        "message": "The title field is required.",
        "details": { "data.title": ["The title field is required."] }
    },
    "meta": { "request_id": "your-tracking-id" }
}
```

### Confirmation Required Response

```json
{
    "ok": false,
    "tool": "cache.clear",
    "error": { "code": "confirmation_required", "message": "This operation requires explicit confirmation in production." },
    "confirmation": {
        "token": "base64-encoded-token",
        "expires_at": "2026-04-14T12:05:00+00:00",
        "operation_summary": { "tool": "cache.clear", "target": "static", "environment": "production" }
    },
    "meta": {}
}
```

### Error Codes

| Code                   | HTTP | Meaning                                        |
|------------------------|------|------------------------------------------------|
| `unauthorized`         | 401  | Missing or invalid bearer token                |
| `forbidden`            | 403  | Target not in allowlist                        |
| `tool_not_found`       | 404  | Tool name not registered                       |
| `tool_disabled`        | 403  | Tool exists but is not enabled                 |
| `validation_failed`    | 422  | Bad request envelope or tool arguments         |
| `resource_not_found`   | 404  | Collection, entry, global, etc. doesn't exist  |
| `conflict`             | 409  | Entry already exists (`entry.create` only)     |
| `rate_limited`         | 429  | Too many requests                              |
| `confirmation_required`| 200  | Confirmation token issued, re-send to confirm  |
| `execution_failed`     | 500  | Tool threw an unexpected error                 |
| `internal_error`       | 500  | Unhandled server error (production only)       |

### Exception Mapping

The `ToolExecutionController` catches all known exception types and maps them to response envelopes:

| Exception                    | HTTP | Error Code         |
|------------------------------|------|--------------------|
| `ToolNotFoundException`      | 404  | `tool_not_found`   |
| `ToolDisabledException`      | 403  | `tool_disabled`    |
| `ToolAuthorizationException` | 403  | `forbidden`        |
| `ToolValidationException`    | 422  | `validation_failed`|
| `\Throwable` (catch-all)    | 500  | `execution_failed` / `internal_error` |

No exceptions bubble up to Laravel's default exception handler for addon routes.

---

## Capability Discovery

Call `GET /ai-gateway/capabilities` to see what's available:

```json
{
    "ok": true,
    "tool": "capabilities",
    "result": {
        "capabilities": {
            "entry.create":      { "enabled": true,  "target_type": "entry",      "requires_confirmation": false },
            "entry.update":      { "enabled": true,  "target_type": "entry",      "requires_confirmation": false },
            "entry.upsert":      { "enabled": false, "target_type": "entry",      "requires_confirmation": false },
            "global.update":     { "enabled": false, "target_type": "global",     "requires_confirmation": false },
            "navigation.update": { "enabled": false, "target_type": "navigation", "requires_confirmation": false },
            "term.upsert":       { "enabled": false, "target_type": "taxonomy",   "requires_confirmation": false },
            "cache.clear":       { "enabled": true,  "target_type": "cache",      "requires_confirmation": true  }
        }
    },
    "meta": {}
}
```

This endpoint reads all registered tools from the `ToolRegistry`, instantiates each handler, and returns its enabled status, target type, and whether it requires confirmation in the current environment.

---

## Internal Components

### GatewayTool Interface

Every tool implements `Stokoe\AiGateway\Tools\Contracts\GatewayTool`:

```php
interface GatewayTool
{
    public function name(): string;                              // e.g. 'entry.create'
    public function targetType(): string;                        // e.g. 'entry', 'global', 'cache'
    public function validationRules(): array;                    // Laravel validation rules
    public function resolveTarget(array $arguments): ?string;    // Extract target for authorization
    public function execute(array $arguments): ToolResponse;     // Perform the operation
    public function requiresConfirmation(string $environment): bool;
}
```

### ToolRegistry

Maps tool names to handler classes. Resolves tools via the Laravel container and checks enabled status from config:

- `resolve(string $name)` → returns `GatewayTool` instance, throws `ToolNotFoundException` or `ToolDisabledException`
- `isEnabled(string $name)` → checks `config("ai_gateway.tools.{$name}")`
- `all()` → returns metadata for all registered tools (used by capabilities endpoint)

### ToolPolicy

Centralized authorization:

- `toolEnabled(string $toolName)` → checks config
- `targetAllowed(string $targetType, string $target)` → checks the corresponding allowlist
- `deniedFields(string $targetType, ?string $target)` → returns the deny list for field filtering

### FieldFilter

Strips denied fields from data payloads:

```php
$filter->filter(['title' => 'Hello', 'author' => 'AI'], ['author']);
// Returns: ['title' => 'Hello']
```

Uses `array_diff_key()` for efficient filtering.

### ToolResponse

Value object for consistent response building with three static factories:

- `ToolResponse::success(string $tool, array $result, array $meta = [])`
- `ToolResponse::error(string $tool, string $code, string $message, int $httpStatus, array $meta = [], ?array $details = null)`
- `ToolResponse::confirmationRequired(string $tool, string $token, string $expiresAt, array $operationSummary, array $meta = [])`

All produce a `JsonResponse` via `toJsonResponse()` with the correct envelope structure.

---

## Testing

The addon has 81 tests with 11,500+ assertions across three categories:

### Property-Based Tests (Eris)

11 correctness properties validated with 100+ random iterations each:

1. **Token comparison correctness** — `hash_equals` returns true iff tokens match
2. **Request envelope validation** — accepts valid envelopes, rejects malformed ones
3. **Request ID round-trip** — `request_id` appears unchanged in response meta
4. **Tool registry resolution** — correct handler for registered+enabled, exceptions for others
5. **Allowlist enforcement** — targets in allowlist pass, all others denied
6. **Field filtering** — preserves allowed fields, removes denied fields
7. **Tool argument validation** — required fields, correct types, no unknown keys
8. **Confirmation token round-trip** — generate then validate succeeds immediately
9. **Confirmation token binding** — token for one (tool, args) pair fails for another
10. **Response envelope consistency** — success/error/confirmation all have correct structure
11. **Audit log completeness and safety** — required fields present, sensitive data absent

### Unit Tests

- Addon disabled → 404 on both endpoints
- Addon enabled → routes registered (401 without token, not 404)

### Integration Tests

Full HTTP pipeline tests covering:

- Authentication (missing/invalid/valid tokens, capabilities auth)
- Rate limiting (within limit, exceeding limit, separate buckets)
- Entry tools (create, duplicate conflict, update, upsert create/update paths, disallowed collection, nonexistent collection, request_id round-trip)
- Global tools (update existing, nonexistent global)
- Navigation tools (update existing, nonexistent navigation)
- Term tools (upsert create, upsert update, nonexistent taxonomy)
- Cache tools (valid target, invalid target, disallowed target, confirmation flow, confirmation with valid token)
- Capabilities endpoint (structure, tool listing, enabled status, target types)
- Audit logging (succeeded, failed, rejected events)

### Running Tests

```bash
cd addons/stokoe/ai-gateway
vendor/bin/phpunit
```

---

## Extending with Custom Tools

To add a new tool:

1. Create a class implementing `GatewayTool` in `src/Tools/`
2. Define `name()`, `targetType()`, `validationRules()`, `resolveTarget()`, `execute()`, `requiresConfirmation()`
3. Register it in `ToolRegistry::$tools`
4. Add a config entry in `ai_gateway.tools`
5. Add the corresponding allowlist config if needed

The tool automatically gets the full pipeline: authentication, rate limiting, envelope validation, authorization, field filtering, confirmation flow, audit logging.

---

## When Should You Use This?

This addon is a great fit if you want to:

- Integrate agentic content management into a Statamic project
- Automate content workflows safely
- Allow AI-assisted updates without giving away full control
- Build custom AI-powered tooling on top of Statamic
- Maintain strong operational and security boundaries

## When This Might Not Be For You

This addon is intentionally restrictive. It may not be the right fit if you want:

- Unrestricted programmatic control over your app
- Direct execution of arbitrary commands
- A large, exploratory tool surface for development experimentation
- AI acting with full system-level access

---

## Minimal Setup Example

For a site that needs AI-managed content in `pages` and `projects`, with cache clearing:

**.env:**
```env
AI_GATEWAY_ENABLED=true
AI_GATEWAY_TOKEN=sk_live_a1b2c3d4e5f6g7h8i9j0

AI_GATEWAY_TOOL_ENTRY_UPSERT=true
AI_GATEWAY_TOOL_CACHE_CLEAR=true
```

**config/ai_gateway.php** (published):
```php
'allowed_collections' => ['pages', 'projects'],
'allowed_cache_targets' => ['stache', 'static'],

'denied_fields' => [
    'entry' => ['author', 'blueprint'],
],
```

Two tools, two collections, two cache targets, two denied fields. Everything else stays locked down.

---

## Agent Setup Guide

This section walks through connecting an AI agent (such as OpenClaw) to one or more Statamic sites running the AI Gateway addon. The assumption is that you're managing multiple websites and want a single agent to operate across all of them.

### 1. Install the Skill

Install the AI Gateway skill from ClawHub so your agent knows how to interact with the endpoint:

```
clawhub install statamic-ai-gateway
```

The skill teaches your agent the request format, available tools, error handling, and confirmation flow. Without it, the agent won't know how to structure requests.

### 2. Prepare Each Site

On every Statamic site you want the agent to manage, you need three things: the addon enabled, a unique token, and the tools/targets configured.

Generate a unique, strong token for each site. Don't reuse tokens across sites — if one is compromised, you only need to rotate that one.

```bash
# Generate a random token (run once per site)
openssl rand -hex 32
```

Add to each site's `.env`:

```env
AI_GATEWAY_ENABLED=true
AI_GATEWAY_TOKEN=<the-token-you-generated>
```

Then publish and configure the allowlists for that site. Each site will likely have different collections, globals, and navigations — configure only what that specific site needs:

```bash
php artisan vendor:publish --tag=ai-gateway-config
```

### 3. Configure the Agent

Your agent needs to know about each site it manages. Set up a site registry — the exact format depends on your agent platform, but the information needed per site is:

| Field       | Example                              | Notes                                |
|-------------|--------------------------------------|--------------------------------------|
| Name        | `marketing-site`                     | Human-readable label                 |
| Base URL    | `https://marketing.example.com`      | The site's public URL                |
| Endpoint    | `https://marketing.example.com/ai-gateway/execute` | Always `{base_url}/ai-gateway/execute` |
| Token       | `a1b2c3d4e5f6...`                   | The `AI_GATEWAY_TOKEN` for this site |

For example, if you manage three sites:

```
┌─────────────────────┬──────────────────────────────────────────┬──────────────┐
│ Site                │ Endpoint                                 │ Token        │
├─────────────────────┼──────────────────────────────────────────┼──────────────┤
│ Marketing site      │ https://marketing.example.com/ai-gateway │ token-aaa... │
│ Documentation site  │ https://docs.example.com/ai-gateway      │ token-bbb... │
│ Client portal       │ https://portal.example.com/ai-gateway    │ token-ccc... │
└─────────────────────┴──────────────────────────────────────────┴──────────────┘
```

Each site is independent — its own token, its own allowlists, its own rate limits. The agent selects the right endpoint and token based on which site it's operating on.

### 4. Discover Capabilities Per Site

Before performing operations on a site, the agent should call the capabilities endpoint to learn what's available:

```
GET https://marketing.example.com/ai-gateway/capabilities
Authorization: Bearer token-aaa...
```

This returns which tools are enabled, what target types they operate on, and whether any require confirmation. The agent should cache this per site and refresh periodically — capabilities can change when a site operator updates their config.

Different sites will have different capabilities. The marketing site might allow `entry.upsert` on `pages` and `projects`, while the docs site only allows `entry.upsert` on `articles`. The agent should respect these boundaries and not assume one site's config applies to another.

### 5. Multi-Site Operation Tips

**Use unique `request_id` values.** Include the site name in your request IDs (e.g. `marketing-site:req_01abc`) so audit logs are easy to trace across sites.

**Handle errors per site.** A `403 forbidden` on one site doesn't mean the same target is forbidden on another. Always check the specific error and site context.

**Rate limits are per site.** Each site enforces its own rate limits independently. Hitting the limit on the marketing site doesn't affect your quota on the docs site.

**Prefer `entry.upsert` over `entry.create`.** When operating across multiple sites, upsert is more resilient — you don't need to track whether an entry already exists on each site.

**Rotate tokens independently.** If you need to rotate a token, update the `.env` on that specific site and the corresponding entry in your agent's site registry. Other sites are unaffected.

**Test in staging first.** Each site can have its own staging environment with the gateway enabled. Use separate tokens for staging vs production. The confirmation flow only activates in production by default, so staging operations execute immediately.

### 6. Verifying the Connection

Once a site is configured and the agent has its credentials, verify the connection:

```bash
# Check capabilities (should return 200 with tool list)
curl -s -H "Authorization: Bearer <token>" \
  https://your-site.com/ai-gateway/capabilities | jq .

# Test a simple operation (if entry.upsert is enabled)
curl -s -X POST \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"tool":"entry.upsert","arguments":{"collection":"pages","slug":"test","data":{"title":"Connection Test"}},"request_id":"setup-test"}' \
  https://your-site.com/ai-gateway/execute | jq .
```

If you get `401`, check the token. If you get `404`, the addon isn't enabled. If you get `403`, the tool or target isn't in the allowlist. The error codes are designed to tell you exactly what's wrong.

---

## Future Direction

The architecture is designed to grow safely over time. Potential future capabilities include:

- Controlled execution of whitelisted Artisan commands
- Read/query tools for content inspection
- Dry-run and "explain" modes for safer planning
- More advanced audit and observability features
- Deeper integration with custom application logic

All while maintaining the same core principle:

> **Controlled, explicit, and safe interaction between AI and your application.**
