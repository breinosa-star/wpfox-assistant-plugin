# Public KB API — TODO

Goal: expose the knowledge base publicly so AI agents (ChatGPT, Claude, etc.) can discover and query it organically, without requiring user configuration.

---

## ~~1. Settings~~ ✓

- ~~Add a "Public KB API" section to GrayFox > Settings.~~
- ~~Toggle: **Enable public KB API** (default: off).~~
- ~~When enabled, display the endpoint URL and the llms.txt URL so the site owner can reference or share them.~~
- ~~Add a rate limit field: max requests per IP per hour (default: 60).~~

---

## ~~2. REST API endpoint~~ ✓

- ~~Register a WP REST API route: `GET /wp-json/grayfox/v1/kb`~~
- ~~Accept an optional `query` parameter to filter results.~~
- ~~Call `GrayFox_RAG::get_consolidated_knowledge( $query )` and return the result as JSON.~~
- ~~Return `403` with a clear message if the public API is disabled in settings.~~
- ~~Return `429` with a `Retry-After` header when rate limit is exceeded.~~
- ~~No authentication required when enabled — this is intentionally public.~~

---

## ~~3. Rate limiting~~ ✓

- ~~IP-based, using WP transients (consistent with existing chat rate limiting).~~
- ~~Configurable limit from settings (step 1).~~
- ~~Log blocked requests to `wp_grayfox_security_log` with reason `api_rate_limit`.~~

---

## ~~4. llms.txt~~ ✓

- ~~Serve `llms.txt` at `yoursite.com/llms.txt` via a WP rewrite rule.~~
- ~~Only serve if the public API is enabled; return `404` otherwise.~~
- ~~Content should be generated dynamically and include site name, description, endpoint URL, and usage examples.~~

---

## ~~5. Admin UI — settings page update~~ ✓

- ~~New "Public KB API" card/section in the settings page.~~
- ~~Toggle with a clear description of what enabling it does.~~
- ~~When enabled: show endpoint URL and llms.txt URL with copy buttons.~~

---

## ~~6. Readme update~~ ✓

- ~~Add a "Public KB API" section to `readme.txt` explaining the feature for end users.~~
- ~~Make clear that this feature is intended for customer-facing knowledge bases. Sites with internal or private KB content should not enable it.~~

---

## Implementation order

1. Settings toggle + rate limit field (needed by everything else)
2. REST endpoint (core feature)
3. Rate limiting (security before going live)
4. llms.txt (discoverability)
5. Admin UI polish (copy buttons, URLs)
6. Docs
