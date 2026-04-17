# AI Gateway — API Reference

Complete endpoint documentation for the AI Gateway tool execution interface.

## Site Credentials

All examples assume you've loaded credentials for a specific site from `sites.json`:

```bash
SITE_URL=$(jq -r '.sites.SITE_NAME.base_url' ~/.config/ai-gateway/sites.json)
SITE_TOKEN=$(jq -r '.sites.SITE_NAME.token' ~/.config/ai-gateway/sites.json)
```

Replace `SITE_NAME` with the site key (e.g. `marketing`, `docs`).

## Endpoints

| Method | Path                       | Purpose                |
|--------|----------------------------|------------------------|
| POST   | `/ai-gateway/execute`      | Execute a tool         |
| GET    | `/ai-gateway/capabilities` | Discover available tools |

All requests require:
- `Authorization: Bearer {token}` (from `sites.json`)
- `Content-Type: application/json` (POST only)

---

## Request Envelope

Every POST to `/ai-gateway/execute` uses this structure:

```json
{
    "tool": "string (required)",
    "arguments": { "(required, tool-specific)" },
    "request_id": "string (optional — echoed in response meta)",
    "idempotency_key": "string (optional — included in audit log)",
    "confirmation_token": "string (optional — required when confirming a gated operation)"
}
```

---

## Response Envelopes

### Success

```json
{
    "ok": true,
    "tool": "entry.upsert",
    "result": {
        "status": "created",
        "target_type": "entry",
        "target": {
            "collection": "pages",
            "slug": "hello-world",
            "site": "default"
        }
    },
    "meta": {
        "request_id": "marketing:upsert-hello"
    }
}
```

### Error

```json
{
    "ok": false,
    "tool": "entry.create",
    "error": {
        "code": "validation_failed",
        "message": "The title field is required.",
        "details": {
            "data.title": ["The title field is required."]
        }
    },
    "meta": {
        "request_id": "marketing:create-about"
    }
}
```

`error.details` is present only for `validation_failed` responses.

### Confirmation Required

```json
{
    "ok": false,
    "tool": "cache.clear",
    "error": {
        "code": "confirmation_required",
        "message": "This operation requires explicit confirmation in production."
    },
    "confirmation": {
        "token": "base64-encoded-hmac-token",
        "expires_at": "2026-04-14T12:05:00+00:00",
        "operation_summary": {
            "tool": "cache.clear",
            "target": "static",
            "environment": "production"
        }
    },
    "meta": {}
}
```

---

## Error Codes

| Code                   | HTTP | Meaning                                        | Action                                    |
|------------------------|------|------------------------------------------------|-------------------------------------------|
| `unauthorized`         | 401  | Missing or invalid bearer token                | Check token in `sites.json`               |
| `forbidden`            | 403  | Target not in allowlist                        | Target is off-limits on this site         |
| `tool_not_found`       | 404  | Tool name not registered                       | Check name against capabilities           |
| `tool_disabled`        | 403  | Tool registered but not enabled                | Tool is turned off on this site           |
| `validation_failed`    | 422  | Bad envelope or tool arguments                 | Read `error.message` and `error.details`  |
| `resource_not_found`   | 404  | Target resource doesn't exist in Statamic      | Collection/entry/global/nav/taxonomy missing |
| `conflict`             | 409  | Entry already exists                           | Use `entry.upsert` instead                |
| `rate_limited`         | 429  | Too many requests                              | Back off and retry                        |
| `confirmation_required`| 200  | Confirmation token issued                      | Resend with `confirmation_token`          |
| `execution_failed`     | 500  | Tool threw an unexpected error                 | Retry or report                           |
| `internal_error`       | 500  | Unhandled server error                         | Retry or report                           |

---

## Capabilities Endpoint

Discover what's available on a specific site:

```bash
curl -s -H "Authorization: Bearer $SITE_TOKEN" \
  $SITE_URL/ai-gateway/capabilities | jq .
```

Response:

```json
{
    "ok": true,
    "tool": "capabilities",
    "result": {
        "capabilities": {
            "entry.create":      { "enabled": true,  "target_type": "entry",      "requires_confirmation": false },
            "entry.update":      { "enabled": true,  "target_type": "entry",      "requires_confirmation": false },
            "entry.upsert":      { "enabled": true,  "target_type": "entry",      "requires_confirmation": false },
            "global.update":     { "enabled": false, "target_type": "global",     "requires_confirmation": false },
            "navigation.update": { "enabled": false, "target_type": "navigation", "requires_confirmation": false },
            "term.upsert":       { "enabled": false, "target_type": "taxonomy",   "requires_confirmation": false },
            "cache.clear":       { "enabled": true,  "target_type": "cache",      "requires_confirmation": true  }
        }
    },
    "meta": {}
}
```

Only call tools where `enabled` is `true`. Capabilities vary per site — always check before operating on a new site.

---

## Tool Reference

### `entry.create`

Creates a new entry. Fails with `409 conflict` if the entry already exists.

| Argument     | Required | Type    | Default     | Notes                                    |
|-------------|----------|---------|-------------|------------------------------------------|
| `collection` | yes      | string  |             | Must be in the site's allowlist          |
| `slug`       | yes      | string  |             | Unique within collection + site          |
| `data`       | yes      | object  |             | Field values; validated against blueprint|
| `published`  | no       | boolean |             | Publish state                            |
| `site`       | no       | string  | `"default"` | For multi-site Statamic setups           |

```bash
curl -s -X POST \
  -H "Authorization: Bearer $SITE_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "tool": "entry.create",
    "arguments": {
      "collection": "pages",
      "slug": "about-us",
      "published": true,
      "data": { "title": "About Us", "content": "We build things." }
    },
    "request_id": "marketing:create-about"
  }' \
  $SITE_URL/ai-gateway/execute | jq .
```

---

### `entry.update`

Updates an existing entry. Merges `data` onto the existing entry — only the fields you send are changed.

Same arguments as `entry.create`. Returns `404 resource_not_found` if the entry doesn't exist.

```bash
curl -s -X POST \
  -H "Authorization: Bearer $SITE_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "tool": "entry.update",
    "arguments": {
      "collection": "pages",
      "slug": "about-us",
      "data": { "title": "About Our Team" }
    },
    "request_id": "marketing:update-about"
  }' \
  $SITE_URL/ai-gateway/execute | jq .
```

---

### `entry.upsert`

Creates the entry if it doesn't exist, updates it if it does. Returns `status: "created"` or `status: "updated"`. Same arguments as `entry.create`.

Recommended for most content operations — idempotent, no existence check needed, no risk of `conflict`.

```bash
curl -s -X POST \
  -H "Authorization: Bearer $SITE_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "tool": "entry.upsert",
    "arguments": {
      "collection": "pages",
      "slug": "services",
      "published": true,
      "data": {
        "title": "Our Services",
        "content": "We offer web development, infrastructure, and consulting."
      }
    },
    "request_id": "marketing:upsert-services"
  }' \
  $SITE_URL/ai-gateway/execute | jq .
```

---

### `global.update`

Updates a global variable set's values for a given site.

| Argument | Required | Type   | Default     | Notes                            |
|----------|----------|--------|-------------|----------------------------------|
| `handle` | yes      | string |             | Must be in the site's allowlist  |
| `data`   | yes      | object |             | Field values as key-value pairs  |
| `site`   | no       | string | `"default"` | For multi-site Statamic setups   |

```bash
curl -s -X POST \
  -H "Authorization: Bearer $SITE_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "tool": "global.update",
    "arguments": {
      "handle": "contact_information",
      "data": {
        "phone": "+64 21 555 0100",
        "email": "hello@example.com"
      }
    },
    "request_id": "marketing:update-contact"
  }' \
  $SITE_URL/ai-gateway/execute | jq .
```

---

### `navigation.update`

Replaces an entire navigation tree. This is a **full replacement** — the existing tree is completely discarded.

| Argument | Required | Type   | Default     | Notes                              |
|----------|----------|--------|-------------|------------------------------------|
| `handle` | yes      | string |             | Must be in the site's allowlist    |
| `tree`   | yes      | array  |             | Complete navigation structure       |
| `site`   | no       | string | `"default"` | For multi-site Statamic setups     |

Always send the complete tree. Partial patches are not supported.

```bash
curl -s -X POST \
  -H "Authorization: Bearer $SITE_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "tool": "navigation.update",
    "arguments": {
      "handle": "main_navigation",
      "tree": [
        { "url": "/", "title": "Home" },
        { "url": "/about", "title": "About" },
        {
          "url": "/services", "title": "Services",
          "children": [
            { "url": "/services/web", "title": "Web Development" },
            { "url": "/services/infra", "title": "Infrastructure" }
          ]
        },
        { "url": "/contact", "title": "Contact" }
      ]
    },
    "request_id": "marketing:replace-nav"
  }' \
  $SITE_URL/ai-gateway/execute | jq .
```

---

### `term.upsert`

Creates or updates a taxonomy term.

| Argument   | Required | Type   | Default     | Notes                              |
|-----------|----------|--------|-------------|------------------------------------|
| `taxonomy` | yes      | string |             | Must be in the site's allowlist    |
| `slug`     | yes      | string |             | Term identifier                    |
| `data`     | yes      | object |             | Field values as key-value pairs    |
| `site`     | no       | string | `"default"` | For multi-site Statamic setups     |

```bash
curl -s -X POST \
  -H "Authorization: Bearer $SITE_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "tool": "term.upsert",
    "arguments": {
      "taxonomy": "tags",
      "slug": "laravel",
      "data": { "title": "Laravel" }
    },
    "request_id": "docs:upsert-tag"
  }' \
  $SITE_URL/ai-gateway/execute | jq .
```

---

### `cache.clear`

Clears a specific cache target. May require confirmation in production.

| Argument | Required | Type   | Allowed values                              |
|----------|----------|--------|---------------------------------------------|
| `target` | yes      | string | `application`, `static`, `stache`, `glide`  |

- `application` — Laravel application cache
- `static` — Statamic static page cache
- `stache` — Statamic content cache
- `glide` — Image manipulation cache

```bash
curl -s -X POST \
  -H "Authorization: Bearer $SITE_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "tool": "cache.clear",
    "arguments": { "target": "stache" },
    "request_id": "marketing:clear-stache"
  }' \
  $SITE_URL/ai-gateway/execute | jq .
```

---

## Confirmation Flow

Some tools require a two-step confirmation in production. `cache.clear` is confirmation-gated by default.

### Step 1: Initial request

Send the request normally. The response includes a signed confirmation token:

```json
{
    "ok": false,
    "error": { "code": "confirmation_required", "message": "This operation requires explicit confirmation in production." },
    "confirmation": {
        "token": "base64-encoded-hmac-token",
        "expires_at": "2026-04-14T12:05:00+00:00",
        "operation_summary": { "tool": "cache.clear", "target": "stache", "environment": "production" }
    }
}
```

### Step 2: Confirm

Resend the exact same request with `confirmation_token` added:

```json
{
    "tool": "cache.clear",
    "arguments": { "target": "stache" },
    "confirmation_token": "base64-encoded-hmac-token"
}
```

Token rules:
- Expires after 60 seconds (configurable by site operator)
- Bound to the exact tool + arguments — a token for `target: "stache"` won't work for `target: "static"`
- Stateless (HMAC-signed, no database)
- If expired or invalid, a new token is issued automatically

---

## Constraints

1. `data` must be a JSON object (key-value pairs), never an array or primitive.
2. Unknown argument keys are rejected with `validation_failed`.
3. Rate limit is 30 requests/min on the execute endpoint by default. Rate limits are per site.
4. Maximum request body size is 64KB by default.
5. Each site has independent configuration — capabilities, allowlists, rate limits, and tokens are all per-site.
6. Always look up credentials from `sites.json` by site name before making requests.
