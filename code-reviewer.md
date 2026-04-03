# GrayFox Plugin — Code Review Rules

## Scope
Invariant rules for the GrayFox WordPress plugin (PHP 8.1+ + vanilla JS + esbuild).
Every rule must be checked against every file. Findings are severity-tagged.

## Severity Levels
- **BLOCKER** — Must be fixed before any agent proceeds. Security or correctness issue.
- **MAJOR** — Should be fixed before QA. Functional or reliability issue.
- **MINOR** — Should be fixed before release. Code quality or convention issue.
- **INFO** — Observation, no action required.

## Output Format
Produce a `code-issues.md` file at `/Users/borisreinosa/Documents/grayfox-plugin/code-issues.md`.

Each finding:
```
[SEVERITY] RULE-ID: Description
File: path/to/file.php line N
Violation: exact quote or description of what was found
Fix: what should be done
```

---

## CAT_1: SECRETS & API KEY SECURITY

**sec-001 [BLOCKER]** — LLM API key must NEVER appear in any JavaScript file, inline script, or wp_localize_script output.
Violation: `grayfox_llm_api_key` or any LLM key value present in GrayFoxConfig or any JS object.

**sec-002 [BLOCKER]** — LLM API key must NEVER be sent to browser in AJAX responses.
Violation: any PHP AJAX handler that returns the key in its response JSON.

**sec-003 [BLOCKER]** — All sensitive values (LLM API key, license key, Google tokens) must be encrypted via `grayfox_encrypt()` before being written to wp_options or any DB table.
Violation: `update_option('grayfox_llm_api_key', $key)` without grayfox_encrypt() call.

**sec-004 [BLOCKER]** — `grayfox_encrypt()` and `grayfox_decrypt()` must derive their key from `AUTH_KEY . SECURE_AUTH_KEY . LOGGED_IN_KEY`. No hardcoded encryption keys.
Violation: hardcoded string used as encryption key, or key derived from any other source.

**sec-005 [MAJOR]** — License key stored in wp_options must be encrypted. Never stored plaintext.
Violation: `update_option('grayfox_license_key', $plaintext_key)`.

---

## CAT_2: DATABASE SECURITY

**db-001 [BLOCKER]** — ALL $wpdb queries must use `$wpdb->prepare()` with placeholders. No string interpolation in SQL.
Violation: `$wpdb->query("SELECT ... WHERE id = $id")` — any variable interpolated directly into SQL string.

**db-002 [MAJOR]** — Table names must always use `$wpdb->prefix . 'grayfox_'` prefix. Never hardcode table names.
Violation: hardcoded table name like `wp_grayfox_conversations` instead of `{$wpdb->prefix}grayfox_conversations`.

**db-003 [MAJOR]** — `create_tables()` must use `dbDelta()` for schema creation, not raw CREATE TABLE without IF NOT EXISTS.
Violation: schema creation that doesn't use dbDelta or doesn't handle existing tables gracefully.

**db-004 [MINOR]** — All custom tables must be created in the activation hook, not on plugin load.
Violation: table creation code called outside of `register_activation_hook`.

---

## CAT_3: WORDPRESS AJAX SECURITY

**ajax-001 [BLOCKER]** — Every AJAX handler (both `wp_ajax_` and `wp_ajax_nopriv_`) must verify a nonce before processing.
Violation: AJAX handler that does not call `check_ajax_referer()` or `wp_verify_nonce()`.

**ajax-002 [BLOCKER]** — Every admin-only AJAX handler must check `current_user_can('manage_options')` in addition to nonce verification.
Violation: admin AJAX handler missing capability check.

**ajax-003 [MAJOR]** — All AJAX handlers must call `wp_die()` at the end (or `wp_send_json_*()` which calls it).
Violation: AJAX handler that returns without calling `wp_die()` or a wp_send_json function.

**ajax-004 [MAJOR]** — Nonces must use action-specific strings, not generic values.
Violation: `wp_create_nonce('nonce')` — must use specific action like `wp_create_nonce('grayfox_chat')`.

---

## CAT_4: BACKGROUND JOBS

**job-001 [BLOCKER]** — Long-running tasks (document processing, license validation pings, LLM calls for summarization) must use Action Scheduler, never `wp_cron` or direct execution in request.
Violation: `wp_schedule_event()` used for document processing, or document processed synchronously in upload handler.

**job-002 [MAJOR]** — Action Scheduler must be loaded/confirmed available before scheduling jobs. Check `function_exists('as_schedule_single_action')`.
Violation: `as_schedule_single_action()` called without existence check.

**job-003 [MINOR]** — All Action Scheduler job callbacks must be registered with `add_action()` in the plugin init, not only when the job is scheduled.
Violation: job callback only registered inside the scheduling function.

---

## CAT_5: OUTPUT ESCAPING

**esc-001 [BLOCKER]** — All dynamic output in PHP templates must use appropriate escaping:
- HTML context: `esc_html()` or `esc_html_e()`
- Attribute context: `esc_attr()`
- URL context: `esc_url()`
- JS context: `wp_json_encode()` or `esc_js()`
Violation: unescaped `echo $variable` in template files.

**esc-002 [MAJOR]** — All translatable strings must use `__()`, `_e()`, `esc_html__()` etc. with 'grayfox' text domain.
Violation: hardcoded user-facing string without internationalization function.

---

## CAT_6: PLUGIN ARCHITECTURE

**arch-001 [MAJOR]** — Plugin must use singleton pattern for main class. Multiple instantiation must be impossible.
Violation: main plugin class without `private static $instance` guard.

**arch-002 [MAJOR]** — Plugin constants (GRAYFOX_VERSION, GRAYFOX_PATH, GRAYFOX_URL) must be defined in main plugin file before any includes.
Violation: constants defined inside a class or after includes.

**arch-003 [MAJOR]** — Uninstall logic (drop tables, delete options) must be in `uninstall.php` or registered with `register_uninstall_hook()`. Must NOT run on deactivation.
Violation: table drops in deactivation hook, or no uninstall hook registered.

**arch-004 [MINOR]** — Deactivation hook must only deregister scheduled jobs and clean up transients. Must not delete user data.
Violation: deactivation hook that deletes wp_options or DB table rows.

---

## CAT_7: JAVASCRIPT

**js-001 [BLOCKER]** — `GrayFoxConfig` object injected via `wp_localize_script` must NOT contain:
- `llm_api_key` or any LLM provider key
- `license_key`
- Any encrypted or plaintext secrets
Violation: any of these keys present in the localized script object.

**js-002 [MAJOR]** — Chat widget JS must use `sessionStorage` for conversation history, NOT `localStorage`.
Violation: `localStorage.setItem` or `localStorage.getItem` in session.js or any widget component.

**js-003 [MAJOR]** — All renamed references from nexpert-chat must be complete. No `NExpert`, `nexpert`, or `nxp_` remaining.
Violation: any of these strings in .js files under src/.

**js-004 [MINOR]** — package.json must pin exact esbuild version (no ^ or ~ prefix).
Violation: `"esbuild": "^0.x.x"` — must be `"esbuild": "0.x.x"`.

---

## CAT_8: BRANDING & NAMING

**brand-001 [BLOCKER]** — No references to N-Expert, NExpert, nexpert, or N-Expert.ai anywhere in PHP, JS, CSS, or template files.
Violation: any of these strings found in any file under grayfox-plugin/.

**brand-002 [BLOCKER]** — All class names must use `GrayFox_` prefix. All function names must use `grayfox_` prefix. All option names must use `grayfox_` prefix.
Violation: any `NExpert_`, `nexpert_`, `Nexpert_` naming.

**brand-003 [MAJOR]** — Plugin slug in plugin header comment (grayfox.php) must be `grayfox`.
Violation: Plugin Name or Text Domain set to nexpert or n-expert.

**brand-004 [MINOR]** — Default widget color, if hardcoded, should use GrayFox brand color. Not the nexpert teal (#14b8a6).
Violation: #14b8a6 hardcoded as default color without a GrayFox-appropriate alternative.
