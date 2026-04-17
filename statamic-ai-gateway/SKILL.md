---
name: statamic-ai-gateway
description: Manage Statamic content through a tool execution gateway (composer require stokoe/ai-gateway).
version: 0.0.7
metadata:
  openclaw:
    requires:
      env:
        - AI_GATEWAY_SITES_CONFIG
      bins:
        - curl
        - jq
      config:
        - ~/.config/ai-gateway/sites.json
    primaryEnv: AI_GATEWAY_SITES_CONFIG
    emoji: "🛡️"
    homepage: https://github.com/stokoe/ai-gateway
---

# AI Gateway — Agent Skill

Manage Statamic content through a safe, authenticated tool execution gateway. Create and update entries, update globals, replace navigation trees, manage taxonomy terms, and clear caches — all through a single HTTP endpoint. Supports managing multiple Statamic sites from a single agent installation.

Before first use, follow the setup in [INSTALL.md](./INSTALL.md). For full endpoint schemas and examples, see [references/api.md](./references/api.md).

Requires stokoe/ai-gateway to be installed (Statamic addon).

## Site Registry

This skill manages one or more Statamic sites. Credentials are stored in a `sites.json` file (default: `~/.config/ai-gateway/sites.json`). Override the path with `AI_GATEWAY_SITES_CONFIG`.

```json
{
  "sites": {
    "marketing": {
      "base_url": "https://marketing.example.com",
      "token": "token-aaa..."
    },
    "docs": {
      "base_url": "https://docs.example.com",
      "token": "token-bbb..."
    }
  }
}
```

When operating on a site, look up its `base_url` and `token` from this registry by name. Every request targets a specific site.

## Endpoints

| Method | Path                              | Purpose                              |
|--------|-----------------------------------|--------------------------------------|
| POST   | `/ai-gateway/execute`             | Execute a tool                       |
| GET    | `/ai-gateway/capabilities`        | Discover all available tools         |
| GET    | `/ai-gateway/capabilities/{tool}` | Get detailed usage for a specific tool |

All requests require `Authorization: Bearer {token}` and `Content-Type: application/json`.

## Discovery Workflow

Before using a tool, always discover how to use it:

1. Call `GET /ai-gateway/capabilities` to see which tools are enabled
2. Call `GET /ai-gateway/capabilities/{tool.name}` to get full usage instructions for a specific tool — this returns the argument schema, example payloads, expected responses, error codes, allowed targets, denied fields, and behavioral notes
3. Use the returned information to construct your request

Example — get usage for `entry.upsert`:
```bash
curl -s -H "Authorization: Bearer $SITE_TOKEN" \
  $SITE_URL/ai-gateway/capabilities/entry.upsert | jq .
```

This returns everything you need: argument types, required/optional flags, defaults, a complete example request and response, which targets are allowed, and any important notes about the tool's behavior.

## Quick Start

Discover capabilities for a site:

```bash
SITE_URL=$(jq -r '.sites.marketing.base_url' ~/.config/ai-gateway/sites.json)
SITE_TOKEN=$(jq -r '.sites.marketing.token' ~/.config/ai-gateway/sites.json)

curl -s -H "Authorization: Bearer $SITE_TOKEN" \
  $SITE_URL/ai-gateway/capabilities | jq .
```

Execute a tool:

```bash
curl -s -X POST \
  -H "Authorization: Bearer $SITE_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"tool":"entry.upsert","arguments":{"collection":"pages","slug":"hello","data":{"title":"Hello World"}}}' \
  $SITE_URL/ai-gateway/execute | jq .
```

## Available Tools

| Tool                | Action                           | Target type  |
|---------------------|----------------------------------|--------------|
| `entry.get`         | Retrieve a single entry          | `entry`      |
| `entry.list`        | List entries in a collection     | `entry`      |
| `entry.create`      | Create a new entry               | `entry`      |
| `entry.update`      | Update an existing entry         | `entry`      |
| `entry.upsert`      | Create or update an entry        | `entry`      |
| `global.get`        | Retrieve a global variable set   | `global`     |
| `global.update`     | Update a global variable set     | `global`     |
| `navigation.get`    | Retrieve a navigation tree       | `navigation` |
| `navigation.update` | Replace a navigation tree        | `navigation` |
| `term.get`          | Retrieve a single taxonomy term  | `taxonomy`   |
| `term.list`         | List terms in a taxonomy         | `taxonomy`   |
| `term.upsert`       | Create or update a taxonomy term | `taxonomy`   |
| `cache.clear`       | Clear a specific cache           | `cache`      |

Only tools where `enabled: true` in the capabilities response will work. Capabilities vary per site. See [references/api.md](./references/api.md) for full argument tables and examples.

## Request Envelope

```json
{
    "tool": "tool.name",
    "arguments": { },
    "request_id": "optional-tracking-id",
    "idempotency_key": "optional-dedup-key",
    "confirmation_token": "optional-if-confirming"
}
```

## Response Envelope

Check `ok` first. If `false`, read `error.code`.

Success: `{ "ok": true, "tool": "...", "result": { ... }, "meta": { ... } }`

Error: `{ "ok": false, "tool": "...", "error": { "code": "...", "message": "..." }, "meta": { ... } }`

## Rules

> **⛔ CRITICAL — Structured field values are READ-ONLY structures.**
> Some Statamic fields (Bard, Replicator, Grid, etc.) store their values as deeply nested JSON that mirrors a TipTap/ProseMirror document tree. When you read these values back from `entry.get` or `global.get`, you will see arrays of node objects with `type`, `attrs`, `content`, and `marks` keys.
>
> **You MUST NOT alter the structure of these values.** Never add, remove, reorder, or rename nodes, attributes, or marks. Never flatten, wrap, or restructure the tree. You may **only** change the literal text strings inside `text` leaf nodes — nothing else.
>
> If you need to update a Bard/rich-text field:
> 1. Fetch the current value with `entry.get` (or `global.get`).
> 2. Locate the `text` values you need to change.
> 3. Replace **only** the `text` strings, leaving every other key, nesting level, and node intact.
> 4. Send the modified value back exactly as it was structurally.
>
> Violating this rule will corrupt content and break the site. When in doubt, do not touch the field.

1. Always look up the site's `base_url` and `token` from `sites.json` before making requests.
2. Discover capabilities per site before operating — each site has different enabled tools and allowlists.
3. Only call tools that are `enabled` in that site's capabilities.
4. Only target collections/globals/navigations/taxonomies/caches that the site operator has allowlisted. `forbidden` means it's off-limits.
5. `data` must be a JSON object (key-value pairs), never an array or string.
6. Don't send unknown argument keys — they cause `validation_failed`.
7. Prefer `entry.upsert` over `entry.create` — it's safer and idempotent.
8. `navigation.update` is a full replacement. Always send the complete tree.
9. **CRITICAL — Confirmation-gated tools require user approval.** If a tool has `requires_confirmation: true` in capabilities, you MUST ask the user for explicit permission before sending the confirmation token. Never auto-confirm. The flow is: (1) send the request, (2) receive `confirmation_required` with a token, (3) show the user the `operation_summary` and ask "Do you want to proceed?", (4) only if the user says yes, resend with the `confirmation_token`. If the user says no, stop.
10. When handling `confirmation_required`: extract the token from `confirmation.token` in the response body, then resend the **exact same request** with `confirmation_token` added to the envelope. See the confirmation flow section below.
11. If you get `rate_limited`, back off and retry. Rate limits are per site.
12. Include the site name in `request_id` (e.g. `marketing:upsert-about`) for traceability.

## Tool Argument Quick Reference

Every tool's arguments go inside the `arguments` object. Here are the exact shapes:

**entry.get** (retrieve a single entry):
```json
{ "tool": "entry.get", "arguments": { "collection": "pages", "slug": "about" } }
```

**entry.list** (list entries in a collection, paginated):
```json
{ "tool": "entry.list", "arguments": { "collection": "pages", "limit": 25, "offset": 0 } }
```

**entry.create / entry.update / entry.upsert:**
```json
{ "tool": "entry.upsert", "arguments": { "collection": "pages", "slug": "about", "data": { "title": "About" } } }
```

**global.get** (retrieve a global set's current values):
```json
{ "tool": "global.get", "arguments": { "handle": "contact_information" } }
```

**global.update:**
```json
{ "tool": "global.update", "arguments": { "handle": "contact_information", "data": { "phone": "555-0100" } } }
```

**navigation.get** (retrieve the current navigation tree):
```json
{ "tool": "navigation.get", "arguments": { "handle": "main_navigation" } }
```

**navigation.update:**
```json
{ "tool": "navigation.update", "arguments": { "handle": "main_navigation", "tree": [{ "url": "/", "title": "Home" }] } }
```

**term.get** (retrieve a single term):
```json
{ "tool": "term.get", "arguments": { "taxonomy": "tags", "slug": "laravel" } }
```

**term.list** (list terms in a taxonomy, paginated):
```json
{ "tool": "term.list", "arguments": { "taxonomy": "tags", "limit": 25, "offset": 0 } }
```

**term.upsert:**
```json
{ "tool": "term.upsert", "arguments": { "taxonomy": "tags", "slug": "laravel", "data": { "title": "Laravel" } } }
```

**cache.clear:**
```json
{ "tool": "cache.clear", "arguments": { "target": "stache" } }
```

Valid `target` values: `application`, `static`, `stache`, `glide`. No other arguments.

**Recommended workflow:** Use `entry.get` or `entry.list` to read current content before updating. Use `global.get` to see current values before calling `global.update`. Use `navigation.get` to retrieve the full tree before calling `navigation.update` (which is a full replacement).

## Working with Rich Content (Bard Fields)

Statamic uses Bard fields for rich text content. Bard stores content as **ProseMirror JSON** — an array of node objects, NOT plain text or HTML. If you send a plain string for a Bard field, it will be stored incorrectly.

**ALWAYS read the existing entry first** with `entry.get` to see the current Bard structure before updating. Match the format exactly.

### Bard field structure

A Bard field value is an array of nodes:

```json
{
    "data": {
        "content": [
            {
                "type": "paragraph",
                "content": [
                    { "type": "text", "text": "This is a paragraph of text." }
                ]
            },
            {
                "type": "heading",
                "attrs": { "level": 2 },
                "content": [
                    { "type": "text", "text": "A Heading" }
                ]
            },
            {
                "type": "paragraph",
                "content": [
                    { "type": "text", "text": "Another paragraph after the heading." }
                ]
            }
        ]
    }
}
```

### Common node types

| Node type   | Structure                                                                 |
|-------------|---------------------------------------------------------------------------|
| `paragraph` | `{ "type": "paragraph", "content": [{ "type": "text", "text": "..." }] }` |
| `heading`   | `{ "type": "heading", "attrs": { "level": 2 }, "content": [{ "type": "text", "text": "..." }] }` |
| `bulletList` | `{ "type": "bulletList", "content": [{ "type": "listItem", "content": [...] }] }` |
| `orderedList` | `{ "type": "orderedList", "content": [{ "type": "listItem", "content": [...] }] }` |

### Text formatting

Bold, italic, and links are applied as `marks` on text nodes:

```json
{
    "type": "text",
    "marks": [{ "type": "bold" }],
    "text": "Bold text"
}
```

```json
{
    "type": "text",
    "marks": [{ "type": "link", "attrs": { "href": "https://example.com" } }],
    "text": "A link"
}
```

### Rules for Bard content

1. **Always read first.** Call `entry.get` before updating to see the existing Bard structure.
2. **Never send plain strings** for Bard fields. Always use the ProseMirror node array format.
3. **Preserve the structure** when updating. If the existing content has specific node types, attrs, or marks, keep them unless intentionally changing them.
4. **Each paragraph is a separate node.** Don't put multiple paragraphs in one text node — split them into separate paragraph nodes.

## Working with Large Content

The gateway has a maximum request size (default 64KB). For entries with large content:

1. **Read first, update selectively.** Use `entry.get` to read the full entry, then use `entry.update` with only the fields you're changing. Don't resend unchanged fields.
2. **Keep payloads focused.** Only include the fields in `data` that you're actually modifying. Unchanged fields are preserved automatically by `entry.update` and `entry.upsert`.
3. **If you hit the size limit** (422 `validation_failed` with "Request body exceeds maximum allowed size"), break the update into smaller pieces — update a few fields at a time across multiple requests.
4. **Don't regenerate entire Bard fields** unless necessary. If you only need to change one paragraph in a long Bard field, read the current value, modify the specific node, and send the full updated array back.

## Confirmation Flow

Some tools require two-step confirmation in production (e.g. `cache.clear`). When `requires_confirmation: true` in capabilities:

**YOU MUST ASK THE USER FOR PERMISSION BEFORE CONFIRMING.** Never auto-confirm a confirmation-gated operation. Always show the user what will happen and wait for their explicit approval.

**Step 1 — Send the request normally:**
```json
{ "tool": "cache.clear", "arguments": { "target": "stache" }, "request_id": "marketing:clear-stache" }
```

**Response — token is at `confirmation.token`:**
```json
{
    "ok": false,
    "tool": "cache.clear",
    "error": { "code": "confirmation_required", "message": "This operation requires explicit confirmation in production." },
    "confirmation": {
        "token": "THE_TOKEN_VALUE",
        "expires_at": "2026-04-14T12:05:00+00:00",
        "operation_summary": { "tool": "cache.clear", "target": "stache", "environment": "production" }
    },
    "meta": { "request_id": "marketing:clear-stache" }
}
```

**Step 2 — Ask the user.** Present the `operation_summary` to the user and ask: "This will clear the stache cache on the production environment. Do you want to proceed?" Only continue if the user explicitly confirms.

**Step 3 — If the user approves, resend the EXACT same request with `confirmation_token` added to the envelope:**
```json
{
    "tool": "cache.clear",
    "arguments": { "target": "stache" },
    "confirmation_token": "THE_TOKEN_VALUE",
    "request_id": "marketing:clear-stache"
}
```

Key points:
- Extract the token from `confirmation.token` in the response (NOT `meta.confirmation_token`)
- Put it as `confirmation_token` at the top level of the request envelope (sibling of `tool` and `arguments`)
- The `arguments` must be identical to the first request
- Token expires after 60 seconds

## Error Codes

| Code                   | HTTP | Action                                            |
|------------------------|------|---------------------------------------------------|
| `unauthorized`         | 401  | Check the token for this site in `sites.json`     |
| `forbidden`            | 403  | Target not in allowlist on this site              |
| `tool_not_found`       | 404  | Check tool name against this site's capabilities  |
| `tool_disabled`        | 403  | Tool is turned off on this site                   |
| `validation_failed`    | 422  | Read `error.message` and `error.details`          |
| `resource_not_found`   | 404  | Collection/entry/global/nav/taxonomy doesn't exist|
| `conflict`             | 409  | Entry already exists — use `entry.upsert` instead |
| `rate_limited`         | 429  | Wait and retry                                    |
| `confirmation_required`| 200  | Resend with the provided confirmation token       |
| `execution_failed`     | 500  | Server-side error — retry or report               |
| `internal_error`       | 500  | Server error — retry or report                    |
