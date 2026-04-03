# GrayFox Plugin — Consolidated Code Review Issues

**Last updated:** 2026-04-01
**Status:** All BLOCKERs and MAJORs resolved. Open items are MINORs only.

---

## Summary

| Phase / Component | BLOCKERs | MAJORs | MINORs | Result |
|---|---|---|---|---|
| Phase 1 — Core (3 passes) | 0 | 0 | 0 | APPROVED |
| Phase 2A — Google OAuth2 | 0 | 0 | 2 open | APPROVED WITH NOTES |
| Phase 2B — Calendar Booking | 0 | 0 | 0 | APPROVED |
| Phase 2C — Drive Sync | 0 | 0 | 0 | APPROVED |
| Phase 3A — Sheets Analytics | 0 | 0 | 0 | APPROVED |
| Ed25519 License Hardening | 0 | 0 | 1 open | APPROVED WITH NOTES |
| Post-deployment (2026-03-25 – 2026-04-01) | 0 | 0 | 0 | RESOLVED |
| **RAG/KB Bug Fixes + Conversation Limits + Site Builder (2026-04-01)** | **1** | **5** | **6** | **REQUEST_CHANGES** |

**Overall: REQUEST_CHANGES — 1 BLOCKER, 5 MAJORs, 9 open MINORs**

---

## Phase 1 — Core Plugin (Reviewed 2026-03-22, 3 passes)

All findings resolved before final approval. Issues included:
- SSE architecture mismatch between POST handler and stream endpoint (BLOCKER × 2) — FIXED
- `handle_stream()` missing nonce verification (BLOCKER) — FIXED (`wp_verify_nonce` at line 209)
- `$grayfox_position` double-escaped in `chat-widget.php` (MINOR) — FIXED
- `esc_sql()` missing on table name interpolations in `class-grayfox-rag.php` and `class-grayfox-db.php` (BLOCKER) — FIXED
- Action Scheduler callback not registered on `init` hook (MAJOR) — FIXED
- SSE client not guarding `'0'`/`'-1'` sentinels before JSON parse (MAJOR) — FIXED

**Final verdict: PASS (Pass 3, 2026-03-22)**

---

## Phase 2A — Google OAuth2 (Reviewed 2026-03-23)

### Resolved

| ID | Severity | Finding | Resolution |
|---|---|---|---|
| arch | MAJOR | `new GrayFox_Google()` in template — second instance, latent double-hook risk | FIXED — template uses `GrayFox_Google::get_instance()` |
| sec-003 | MAJOR | Client ID stored without `grayfox_encrypt()` | FIXED — line 431 wraps in `grayfox_encrypt()` |
| js-003 | MINOR | Duplicate JS: inline `<script>` in template + compiled `.min.js` | FIXED — no inline `<script>` block in template |
| oauth-001–008 | INFO | State nonce, redirect safety, token encryption, expiry refresh | All PASS |

### Open

| ID | Severity | Finding | Action Required |
|---|---|---|---|
| db-001 | MINOR | Bare `DELETE FROM \`{$table}\`` in `class-grayfox-google.php` (disconnect) — no `$wpdb->prepare()` wrapper. Table name is internally controlled via `GrayFox_DB::get_table()`, no injection risk in practice, but violates the categorical db-001 rule. | Accept as documented deviation with inline justification comment, or restructure. |
| esc-002 | MINOR | English fallback strings in `src/styles/google-connect.js` (e.g., `'Saving…'`, `'Disconnecting…'`). `GrayFoxGoogleL10n` is always populated server-side; fallbacks are development/error-state only. | Remove fallback strings or accept as dev-only strings with a comment. |

---

## Phase 2B — Google Calendar Booking (Reviewed 2026-03-23, 2 passes)

### Resolved

| ID | Severity | Finding | Resolution |
|---|---|---|---|
| db-001 × 2 | BLOCKER | `esc_sql()` missing on `$appt_table` in `get_appointments()` and `cancel_appointment()` | FIXED — `$appt_table = esc_sql( GrayFox_DB::get_table('appointments') )` applied before all interpolations |
| SEC-REDOS | MAJOR | Catastrophic backtracking regex in `extract_booking_intent()` — `\{.*?.*?.*?\}` with DOTALL | FIXED — rewritten with bounded negated character classes: `/\{[^{}]{0,2000}\}/s` |
| INTEGRITY | MAJOR | `create_appointment()` did not check `$wpdb->insert()` return value — split-brain state on failure | FIXED — checks return value, returns `WP_Error` on failure, logs partial state |
| XSS | MAJOR | `showNotice()` in `booking-settings.php` used `innerHTML` with server-supplied message | FIXED — uses `document.createElement('p')` + `textContent` + `replaceChildren()` |
| job-003 | MINOR | `grayfox_process_booking` AS callback registered at plugin load, not on `init` priority 5 | FIXED — dedicated `register_as_callback()` hooked to `init` at priority 5 |
| QA-P2-003 | MAJOR | `timeMin`/`timeMax` used UTC `Z` suffix — wrong date for non-UTC timezones | FIXED — uses `new DateTime( ..., $tz )` + `DateTime::ATOM` with local offset |
| QA-P2-007 | MAJOR | `bookingEnabled` was string `'true'`/`'false'` — `'false'` is truthy in JS | FIXED — emits integer `1` or `0` via `? 1 : 0` |

**Final verdict: APPROVED (Pass 2, 2026-03-23)**

---

## Phase 2C — Google Drive Sync (Reviewed 2026-03-23, 2 passes)

### Resolved

| ID | Severity | Finding | Resolution |
|---|---|---|---|
| esc-001 | BLOCKER | `templates/admin/drive-sync.php` missing — fatal PHP error on page load | FIXED — template created with full escaping |
| db-001 | BLOCKER | `get_consolidated_knowledge()` called `$wpdb->prepare()` with zero placeholders (WP 6.2+ returns null) | FIXED — uses `$wpdb->get_results()` directly with `esc_sql()` on table name |
| job-002 | MAJOR | `as_schedule_recurring_action()` called without `function_exists()` guard | FIXED — guarded with `function_exists('as_schedule_recurring_action')` |
| js-003 | MAJOR | `assets/dist/grayfox-drive-sync.min.js` missing — zero JS functionality | FIXED — asset created and enqueued |
| timestamp | MAJOR | `process_document()` used `current_time('mysql')` (local) vs `current_time('mysql', true)` (UTC) | FIXED — all calls use UTC flag |
| check-1 | MINOR | `get_file_metadata()` skipped `is_connected()` guard | FIXED — guard added to match `list_folder_files()` pattern |
| check-4 | MINOR | No content size cap before LLM in `summarize_to_knowledge_base()` | FIXED — `mb_substr($text, 0, 60000)` applied before LLM call |

**Final verdict: APPROVED (Pass 2, 2026-03-23)**

---

## Phase 3A — Google Sheets Analytics (Reviewed 2026-03-23, 2 passes)

### Resolved

| ID | Severity | Finding | Resolution |
|---|---|---|---|
| ajax-003 | MAJOR | All 5 AJAX handlers missing `return` after `wp_send_json_error()` | FIXED — `return;` added after every call |
| job-003 | MAJOR | Deactivation hook did not unschedule `grayfox_generate_sheets_report` AS jobs | FIXED — `as_unschedule_all_actions( GrayFox_Sheets::AS_HOOK_REPORT )` added |
| prompt-injection | MAJOR | Raw Sheets cell values passed to LLM with no structural delimiter | FIXED — system message + `SPREADSHEET DATA:` label + backtick fences + 500-char per-cell cap |
| esc-002 | MINOR | `value="GrayFox Report"` hardcoded in `sheets.php` input attribute | FIXED — `esc_attr( __( 'GrayFox Report', 'grayfox' ) )` |
| i18n-js | MINOR | Button reset labels hardcoded in JS, not from `GrayFoxSheetsL10n` | FIXED — 5 keys added to `GrayFoxSheetsL10n`; JS uses `L10n.key \|\| 'fallback'` |
| tier-sync | MINOR | `is_pro_or_above()` array single-element with no extensibility note | FIXED — inline comment `// Extend this array when higher tiers are added` + sync note pointing to template |
| QA-3A-001 | MAJOR | `generate_report_job()` did not re-schedule next recurring run | FIXED — re-schedules itself on both success and error paths using frequency config |
| QA-3A-002 | MAJOR | `handle_delete_report()` did not unschedule pending AS job | FIXED — `as_unschedule_all_actions()` with matching args structure |

**Final verdict: APPROVED (Pass 2 + QA fix verification, 2026-03-23)**

---

## Ed25519 License Hardening (Reviewed 2026-03-24)

### Resolved

| ID | Severity | Finding | Resolution |
|---|---|---|---|
| lic-001 | MAJOR | `get_tier()` was a public unverified bypass path around Ed25519 — no deprecation notice | FIXED — full `@deprecated` PHPDoc + `_doing_it_wrong()` call directing callers to `get_verified_tier()` |

### Open

| ID | Severity | Finding | Action Required |
|---|---|---|---|
| lic-002 | MINOR | `get_verified_status()` returns `'unknown'` on token failure but does not re-enqueue a validation job, unlike `get_verified_tier()` which does | Add `as_enqueue_async_action('grayfox_validate_license')` inside `function_exists` guard on the `$result === false` branch of `get_verified_status()`, matching `get_verified_tier()`. |

---

## Post-Deployment Fixes (2026-03-25 – 2026-04-01)

All issues identified during live deployment testing. All resolved.

| Finding | Severity | Resolution |
|---|---|---|
| `max_tokens` rejected by newer OpenAI models (gpt-5.4+) | MAJOR | Changed to `max_completion_tokens` in `stream_openai()` and probe. Groq keeps `max_tokens`. |
| Model field free-text allowed invalid model names | MAJOR | Replaced with provider-aware `<select>` dropdown; `get_models_by_provider()` is single source of truth; server-side sanitizer validates against same list |
| `grayfox_llm_max_tokens` setting missing — Anthropic requires explicit `max_tokens` | MAJOR | Added setting (default 1024, range 64–32000), consumed by all four streaming methods |
| KB upload button permanently disabled (`'disabled' => null` renders attribute) | MAJOR | Fixed with conditional attributes array — omits the attribute entirely when not at limit |
| Documents not visible after upload until AS job completes | MAJOR | Pending row inserted immediately on upload before AS job queues |
| `class-grayfox-google.php`: missing `return` after `wp_send_json_error()` in 2 handlers | MAJOR | `return;` added — fixed 2026-04-01 |
| `class-grayfox-booking.php`: missing `return` after `wp_send_json_error()` in 6 locations | MAJOR | `return;` added — fixed 2026-04-01 |
| `class-grayfox-drive.php`: missing `return` after `wp_send_json_error()` in 8 locations | MAJOR | `return;` added — fixed 2026-04-01 |
| License overview showing "Not configured" despite valid Ed25519 token | MAJOR | `overview.php` now uses `GrayFox_License::get_verified_tier()` / `get_verified_status()` instead of raw `get_option()` |
| LLM classifier flagging greetings as off-topic | MAJOR | KB context injected into classifier system prompt; explicit rule: greetings/ambiguous = `'safe'`; fail-open on API errors |

---

## RAG/KB Bug Fixes + Conversation Limits + Site Builder (Reviewed 2026-04-01)

### New Issues Found

---

[BLOCKER] job-003-SB: Site Builder AS callback not registered on `init` hook
File: includes/class-grayfox-site-builder.php line 71–74
Violation: `GrayFox_SiteBuilder::register()` adds `add_action( self::AS_HOOK_GENERATE, $this, 'generate_site_pages', 10, 2 )` through the loader, which fires on the `plugins_loaded`/boot path, not on the WordPress `init` hook. Rule job-003 requires all AS job callbacks to be registered with `add_action()` on the `init` hook (priority 5). All other components (RAG, Booking, Drive, Sheets) follow the correct pattern: they have a `register_as_callback()` method hooked to `init` at priority 5. The Site Builder lacks this entirely. When Action Scheduler fires `grayfox_generate_site_pages` in a background context, the callback resolution timing can differ from the web-request path. On some server configurations where AS runs in an isolated context, the hook may not be registered when AS looks it up, causing silent job failure with no retry.
Fix: Add an `init`-hooked `register_as_callback()` method to `GrayFox_SiteBuilder` following the exact pattern used in `GrayFox_RAG::register_as_callback()` and `GrayFox_Booking::register_as_callback()`. Register it via the loader at priority 5.

---

[MAJOR] xss-SB-JS-001: Server-controlled data injected into `innerHTML` without escaping in site-builder.js (environment detection block)
File: src/site-builder.js lines 174–179 (and compiled: assets/dist/grayfox-site-builder.min.js)
Violation: The environment detection AJAX response is rendered by concatenating server-returned strings directly into an HTML string that is assigned to `result.innerHTML`. Specifically, `envData.elementor_version`, `envData.other_builder_name`, and the surrounding `<table>` markup are all concatenated with `+` and written via `innerHTML`. If an attacker can influence the `other_builder_name` or `elementor_version` values returned by the server (e.g., by manipulating plugin metadata read by `ELEMENTOR_VERSION` constant or plugin file headers), they can inject arbitrary HTML and JavaScript. Even under normal conditions, `other_builder_name` originates from a static PHP array keyed by plugin slug, so the current risk is low — but the pattern violates esc-001 equivalents for JS: all server data inserted into the DOM must use `textContent` or `createElement`/`appendChild` rather than `innerHTML`. The values `envData.elementor_version` and `envData.other_builder_name` are not sanitized before DOM insertion.
Fix: Build the environment detection table using `document.createElement` and `textContent` for all server-supplied values. Do not use `innerHTML` with concatenated server data. The static labels ("Block Theme", "Elementor", "Other Builder") are safe as literals.

---

[MAJOR] xss-SB-JS-002: `d.estimated_cost` and `d.page_count` inserted via `innerHTML` without sanitization
File: src/site-builder.js line 259–261 (and compiled: assets/dist/grayfox-site-builder.min.js)
Violation: `result.innerHTML = '<strong>' + d.page_count + ' pages</strong> · ~' + d.total_tokens.toLocaleString() + ' tokens · est. cost: ' + d.estimated_cost;` — the `estimated_cost` string is returned from the server as `'$' . number_format( $cost, 4 )` (PHP), which in the normal path only contains digits, `$`, and `.`. However, the `'unknown'` fallback string is also set server-side, and both values are inserted via `innerHTML`. If the cost estimation path ever changes (or the model returns an unexpected pricing string), the surface is injectable. `d.page_count` and `d.total_tokens` are integers and are lower risk, but `d.estimated_cost` is a freeform string. Consistent use of `textContent` is the correct pattern here.
Fix: Build the estimate display using `createElement`/`textContent` for all server-sourced values, using `<strong>` created via `createElement('strong')` rather than inline HTML string concatenation.

---

[MAJOR] sec-platform-url-ssrf: `grayfox_platform_url` option used as a direct HTTP request target without protocol restriction
File: includes/class-grayfox-settings.php line 1005 (also class-grayfox-license.php line 199)
Violation: The `grayfox_platform_url` option value is passed directly to `trailingslashit( $platform_url ) . 'v1/validate'` inside `ajax_verify_key()` and the license validation method. The option is sanitized with `esc_url_raw` on save, which allows any valid URL including `http://`, `https://`, `ftp://`, and `file://` schemes. An admin who enters `http://169.254.169.254/latest/meta-data/` (AWS IMDSv1), a local network address, or a `file://` URI would cause the plugin to make a server-side request to that target — a classic SSRF vector. While this requires admin access, WordPress multisite environments and sites with compromised admin accounts are affected. The risk is elevated because the platform URL is user-configurable and the request is made with no scheme or host validation beyond `esc_url_raw`.
Fix: Before making the `wp_remote_post()` call, validate that the URL uses `https://` scheme only and that the host is not a loopback address, link-local address, or private IP range. Use `wp_http_validate_url()` as a minimum gate, then add explicit scheme enforcement.

---

[MAJOR] job-002-SB: `as_enqueue_async_action()` called for site generation without `function_exists()` guard producing silent no-op
File: includes/class-grayfox-admin.php lines 909–910
Violation: `handle_start_site_generation()` calls `as_enqueue_async_action( GrayFox_SiteBuilder::AS_HOOK_GENERATE, ... )` inside `if ( function_exists( 'as_enqueue_async_action' ) )` — this part is correctly guarded. However, when Action Scheduler is not available, the function body exits the `if` block, sends `wp_send_json_success( array( 'started' => true ) )` with no job queued, no error message, and no fallback synchronous execution. The progress lock transient is set and the build option is set to `'running'`, but no work is ever done. The UI will show the generation as permanently "running" with 0% progress and no timeout recovery path. The `handle_install_pdf_support()` handler has the same pattern — no fallback when AS is absent — but that case is lower impact since the user gets a success toast for a no-op.
Fix: In `handle_start_site_generation()`, when `as_enqueue_async_action` is not available, either (a) return `wp_send_json_error` explaining that Action Scheduler is required, or (b) execute the generation synchronously with a set_time_limit increase. Do not return success and set the build state to `'running'` when no job was queued. The lock transient and build option must not be written before confirming the job was enqueued.

---

[MAJOR] shell-exec-path-trust: `run_install_dependencies()` trusts `shell_exec('which composer')` output as a command path
File: includes/class-grayfox-rag.php lines 93–101
Violation: The `which composer` output is trimmed and used directly as an `escapeshellarg`-wrapped command path. While `escapeshellarg` is correctly applied, `shell_exec('which composer 2>/dev/null')` itself is an unbounded shell execution that relies on the web server's `$PATH`. On shared hosting environments, a malicious process that modified `$PATH` or placed a `composer` binary earlier in the path could redirect this command. More critically, the function is triggered by an admin-initiated Action Scheduler job (`grayfox_install_dependencies`), which runs as the web server user. The AS job is enqueued without a `function_exists('as_enqueue_async_action')` guard in `handle_install_pdf_support()` (that guard exists but only gates the `as_enqueue_async_action` call — if AS is unavailable the handler still returns `wp_send_json_success` with `'queued' => true` which is misleading). The real concern is: `shell_exec` runs in an AS background context with no output validation, and any error is only logged via `error_log`. There is no integrity check on the `composer` binary found (checksum, known path, etc.). This is a MAJOR rather than BLOCKER because it requires admin initiation and is behind the `manage_options` capability check, but the pattern of running an externally-resolved binary path from user-triggered background jobs is a significant security posture concern.
Fix: Hard-code a known safe composer path (e.g., `/usr/local/bin/composer`) or use a whitelist of acceptable paths. Validate that the resolved path exists and is executable before passing to `shell_exec`. Add output validation: check that `composer install` exited cleanly (exit code 0 is not available via `shell_exec` alone — use `exec()` with the `$return_var` parameter instead).

---

### Open MINORs — New (2026-04-01)

---

[MINOR] sb-001: `handle_start_site_generation()` uses `publish_pages` capability instead of `manage_options`
File: includes/class-grayfox-admin.php line 883
Violation: All other site builder AJAX handlers use `current_user_can('manage_options')`. `handle_start_site_generation()` uses `current_user_can('publish_pages')`. This is inconsistent and allows Editor-role users (who have `publish_pages` but not `manage_options`) to trigger site generation, which creates pages in bulk and makes LLM API calls that consume the site owner's API credits. The sitemap data being used is admin-saved, but the generation job itself is an admin-level operation.
Fix: Change the capability check in `handle_start_site_generation()` to `manage_options` to match all other site builder handlers.

---

[MINOR] sb-002: `sanitize_sitemap_pages()` does not enforce a depth or page-count limit — unbounded recursion risk
File: includes/class-grayfox-admin.php lines 805–813
Violation: `sanitize_sitemap_pages()` recurses into `$page['children']` without any depth limit or total-page-count cap. A maliciously or accidentally deep JSON payload posted to `handle_save_sitemap()` could cause deep recursion leading to a stack overflow or memory exhaustion in PHP. PHP's default stack depth is typically sufficient for normal usage (3–4 levels), but there is no explicit guard. Related: `count_sitemap_pages()` and `process_pages_recursive()` in `class-grayfox-site-builder.php` also have no depth limit.
Fix: Add a `$depth` parameter to `sanitize_sitemap_pages()` and stop recursing beyond depth 5 (or a configurable constant). Add the same guard to `count_sitemap_pages()` and `process_pages_recursive()`.

---

[MINOR] sb-003: IP rate-limit transient TTL resets on every message — allows gradual abuse within hour window
File: includes/class-grayfox-chat.php lines 162–172
Violation: The hourly and daily IP session counters are incremented by calling `set_transient( $h_key, $h_count + 1, 3600 )`. WordPress transients do not support atomic increment, and each call resets the TTL to a full 3600 seconds or 86400 seconds from the moment of the call. This means an attacker who opens exactly 1 session every ~3599 seconds will always keep the hourly counter at 1, never triggering the limit. The effective window keeps sliding rather than being a fixed 1-hour tumbling window. This is a known WordPress transient limitation acknowledged in the inline comment, but the architectural implication (the rate limit is easily circumvented with timed requests) is not documented, and no alternative (e.g., using the security_log table's `created_at` timestamps to count sessions within a real rolling window) is considered.
Fix: Implement session counting using the `grayfox_security_log` table (which records sessions with `created_at`) via a `COUNT(*) WHERE created_at > NOW() - INTERVAL 1 HOUR AND ip_address = %s` query, giving a true rolling window. Alternatively, document the sliding-window limitation prominently in an inline comment so future developers are aware.

---

[MINOR] sb-004: `generate_page()` slug collision handling appends `-v2` unconditionally — no loop for further collisions
File: includes/class-grayfox-site-builder.php lines 264–267
Violation: `if ( get_page_by_path( $slug ) ) { $slug .= '-v2'; }` — if a page named `about-v2` already exists, the new page is also created with slug `about-v2`, and `wp_insert_post()` will silently append a numeric suffix to resolve the collision. The generated page will end up with a slug like `about-v2-2` which does not match the displayed title. For a 5–10 page site this is cosmetic, but for repeated builds it creates clutter.
Fix: Use a loop: `$i = 2; while ( get_page_by_path( $slug ) ) { $slug = $base_slug . '-' . $i++; }`.

---

[MINOR] sb-005: `usleep(1200000)` inside Action Scheduler job increases AS execution time significantly for multi-page builds
File: includes/class-grayfox-site-builder.php line 447
Violation: `fetch_unsplash_image()` calls `usleep(1200000)` (1.2 seconds) unconditionally before every Unsplash API request. For a 10-page sitemap, this adds at least 12 seconds of blocking sleep inside a single AS job execution. AS has a default max execution time, and large sitemaps could push the job past that limit, causing partial builds and lock transient orphans. The comment acknowledges "production should use a proper queue" but the current implementation is used in production.
Fix: Remove `usleep` from the AS callback path. If rate limiting is required, track the last Unsplash request time in a transient and skip the image fetch for the current page if the interval has not elapsed, rather than sleeping.

---

[MINOR] sb-006: Conflict notice in `knowledge-base.php` uses `$conflict['new_doc_id']` / `$conflict['old_doc_id']` keys that are never set by the PHP that creates conflicts
File: templates/admin/knowledge-base.php lines 95–98; cross-ref includes/class-grayfox-rag.php lines 561–569
Violation: `detect_and_flag_conflicts()` saves conflict data with keys `new_doc_name`, `conflicting_names`, `conflicting_doc_ids`, `overlapping_topics`, and `detected_at`. The `knowledge-base.php` template reads `$conflict['new_doc_id']` and `$conflict['old_doc_id']` from the same array — keys that are never written. Both will always be `0` after the `(int)` cast, making the dismissal notice banner show "Doc #0 overlaps with Doc #0", the anchor link point to `#grayfox-conflict-0-0`, and the conflict resolution panels loop (which correctly uses `new_doc_id` and `old_doc_id`) produce a panel with `$new_id = 0` and `$old_id = 0` on every conflict, causing the DB queries to match no rows and the panel to be silently skipped via `if ( ! $new_row || ! $old_row ) continue`.
Fix: In `detect_and_flag_conflicts()`, add `'new_doc_id' => 0` (filled in after insert returns the new row ID) and `'old_doc_id' => $conflicts[0]['id']` to the persisted array, matching what the template expects. Alternatively, rename the template's reads to match the existing keys (`conflicting_doc_ids[0]` for `old_doc_id`).

---

## Open MINORs — Full List (All Phases)

| ID | Component | Finding |
|---|---|---|
| db-001 | Phase 2A / Google | Bare `DELETE FROM \`{$table}\`` without `$wpdb->prepare()` in `disconnect()`. Table name is internally controlled; no injection risk. |
| esc-002 | Phase 2A / Google | English fallback strings in `google-connect.js` (e.g., `'Saving…'`) bypass i18n pipeline. Only active when `GrayFoxGoogleL10n` is absent (error/dev state). |
| lic-002 | License | `get_verified_status()` does not re-enqueue validation job on token failure (inconsistent with `get_verified_tier()`). |
| QA-004 | Phase 1 / LLM | Gemini streaming buffer: `preg_replace` could theoretically re-match already-yielded tokens if prior chunk left partial replacement artifacts. Low probability; buffer management is fragile for large streaming responses. |
| QA-005 | Phase 1 / Widget | `wp_localize_script` called twice with `GrayFoxConfig` if widget + shortcode both render on same page. Second call overwrites first (same data). Maintenance risk if they diverge. |
| QA-006 | Phase 1 / Chat | Uninstall script does not clean up `grayfox_stream_*` transients. Auto-expire in 60s; cosmetic only. |
| QA-P2-005 | Phase 2B / Booking | `appointments.php` and `booking-settings.php` use inline `<script>` blocks instead of enqueued `.min.js` files. CSP incompatibility risk; inconsistent with 2C pattern. |
| QA-P2-006 | Phase 2B / RAG | `token_estimate` calculated from uncapped text before `mb_substr()` — overstates token count for large documents. |
| QA-3A-003 | Phase 3A / Sheets | `is_pro_or_above()` tier check duplicated in PHP class and `sheets.php` template — drift risk when adding tiers. Sync comment added but duplication remains. |
| sb-001 | Site Builder | `handle_start_site_generation()` uses `publish_pages` capability instead of `manage_options` — Editors can trigger LLM API consumption. |
| sb-002 | Site Builder | `sanitize_sitemap_pages()` has no depth or page-count limit — deep JSON payloads could cause stack overflow. |
| sb-003 | Conversation Limits | IP rate-limit transient TTL resets on every increment — effective window slides rather than being a fixed tumbling window. |
| sb-004 | Site Builder | Slug collision fallback appends `-v2` once only — further collisions produce silently mangled slugs. |
| sb-005 | Site Builder | `usleep(1200000)` per page inside AS job — 10-page build adds 12s+ blocking sleep; may exceed AS execution limit. |
| sb-006 | KB Conflicts | Conflict array keys written by `detect_and_flag_conflicts()` do not match keys read by `knowledge-base.php` template — conflict resolution panels always silently fail. |
