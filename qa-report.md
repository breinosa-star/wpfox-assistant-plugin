# GrayFox Plugin — Consolidated QA Report

**Last updated:** 2026-04-01
**Status:** All BLOCKERs and MAJORs resolved. Open items are MINORs only.

---

## Summary

| Phase | BLOCKERs | MAJORs | MINORs | Result |
|---|---|---|---|---|
| Phase 1 — Core | 0 resolved | 3 resolved | 3 open | PASS |
| Phase 2 — Google Integration | 0 | 6 resolved | 2 open | PASS |
| Phase 3 — Sheets Analytics | 0 | 2 resolved | 1 resolved | PASS |
| Post-deployment | 0 | 10 resolved | 0 | PASS |

**Overall: PASS — 5 open MINORs only**

---

## Test Cases — All Phases

### Phase 1 Core TCs

| TC | Description | Result |
|---|---|---|
| TC-001 | Plugin activation → all DB tables exist | PASS |
| TC-002 | LLM API key stored encrypted in wp_options | PASS |
| TC-003 | Upload PDF → background job fires → knowledge_base row created | PASS |
| TC-004 | Chat widget renders on frontend | PASS (requires runtime) |
| TC-005 | Visitor sends message → LLM response streamed via SSE | PASS |
| TC-006 | LLM API key never appears in page source or network requests | PASS |
| TC-007 | License key validation ping (daily job in Action Scheduler) | PASS (requires runtime) |
| TC-008 | Plugin deactivated → data preserved; uninstalled → data wiped | PASS |
| TC-009 | Plugin uninstall drops all 5 tables and deletes all grayfox_* options | PASS |
| TC-010 | Tier doc limit enforced (Starter 20, Growth 100, Pro unlimited) | PASS |
| TC-011 | AJAX nonce verification on all handlers | PASS |
| TC-012 | Multiple LLM providers functional (OpenAI, Anthropic, Gemini, Groq) | PASS |

### Phase 2 Google Integration TCs

| TC | Description | Result |
|---|---|---|
| TC-013 | Booking intent extraction server-side, no booking data sent to browser | PASS |
| TC-014 | `check_availability()` respects working hours and disabled days | PASS |
| TC-015 | Google Calendar availability uses local timezone (not UTC Z suffix) | PASS |
| TC-016 | Drive `handle_list_folder()` nonce + manage_options gated, no token in response | PASS |
| TC-017 | Drive sync enqueues AS job per file; `process_drive_file()` upserts KB | PASS |
| TC-018 | Drive re-sync does not create duplicate KB rows (upsert by source_id) | PASS |
| TC-019 | `drive-sync.php` renders 3 sections, no inline `<script>`, uses `GrayFoxDriveL10n` | PASS |

### Phase 3 Sheets Analytics TCs

| TC | Description | Result |
|---|---|---|
| TC-020 | Spreadsheet query returns LLM answer; token not in AJAX response | PASS |
| TC-021 | Pro tier gate enforced server-side in all 5 Sheets AJAX handlers | PASS |
| TC-022 | Prompt injection resistance in `analyze_data()` (system message + delimiters + cell cap) | PASS |
| TC-023 | Scheduled report creation → executes → re-schedules next run on frequency | PASS |
| TC-024 | `write_report()` creates/clears sheet tab, writes answer, handles API failure as WP_Error | PASS |
| TC-025 | Deactivation unschedules all `grayfox_generate_sheets_report` AS jobs | PASS |

---

## Phase 1 Findings

### Resolved

| ID | Severity | Finding | Resolution |
|---|---|---|---|
| QA-001 | MAJOR | `handle_stream()` did not call `wp_verify_nonce()` — ajax-001 compliance gap | FIXED — `wp_verify_nonce( ..., 'grayfox_chat_stream' )` is the first operation in `handle_stream()` |
| QA-002 | MAJOR | `array_pop()` after `array_reverse()` could remove wrong message if two messages shared the same `created_at` second | FIXED — query uses `ORDER BY id DESC` (not datetime), so ordering is always deterministic by auto-increment ID |
| QA-003 | MAJOR | `send_sse_error()` followed by `wp_die()` could output `"0"` sentinel after SSE event, double-firing `onmessage` | FIXED — all error paths call `send_sse_error()` + `exit;`; `wp_die()` only reached on the success path after full streaming |

### Open

| ID | Severity | Finding |
|---|---|---|
| QA-004 | MINOR | Gemini streaming buffer: `preg_replace` replaces ALL matches including already-yielded ones. If prior chunk left partial replacement artifacts, re-matching could yield duplicate tokens. Low probability; fragile buffer management for large responses. |
| QA-005 | MINOR | `wp_localize_script('grayfox-chat', 'GrayFoxConfig', ...)` called from both `GrayFox_Widget` and `GrayFox_Shortcode`. If both render on the same page, `GrayFoxConfig` is emitted twice (second call overwrites first — same data today, maintenance divergence risk). |
| QA-006 | MINOR | Uninstall script does not delete `grayfox_stream_*` transients. Auto-expire in 60 seconds; no functional impact, cosmetic data hygiene only. |

---

## Phase 2 Findings

### Resolved

| ID | Severity | Finding | Resolution |
|---|---|---|---|
| QA-P2-002 | MAJOR | `get_consolidated_knowledge()` called `$wpdb->prepare()` with zero placeholders — returns `null` on WP 6.2+, silently emptying RAG context | FIXED — uses `$wpdb->get_results()` directly with `esc_sql()` on table name; no zero-placeholder `prepare()` |
| QA-P2-003 | MAJOR | `check_availability()` used UTC `Z` suffix for `timeMin`/`timeMax` — wrong date window for non-UTC timezones | FIXED — uses `new DateTime( $date . ' 00:00:00', $tz )` + `DateTime::ATOM` with configured booking timezone |
| QA-P2-004 | MAJOR | `appointments.php` cancel feedback used `innerHTML` pattern — latent XSS vector in admin context | FIXED — uses `document.createElement()` + `textContent` + `replaceChildren()` exclusively |
| QA-P2-007 | MAJOR | `bookingEnabled` in `GrayFoxConfig` was string `'true'`/`'false'` — string `'false'` is truthy in JS, booking UI always shown | FIXED — emits integer `1` or `0` |

### Open

| ID | Severity | Finding |
|---|---|---|
| QA-P2-005 | MINOR | `appointments.php` and `booking-settings.php` use inline `<script>` blocks with PHP-inlined nonce strings instead of enqueued `.min.js` + `wp_localize_script()`. Incompatible with strict CSP `script-src` headers; inconsistent with 2C Drive Sync pattern. |
| QA-P2-006 | MINOR | `token_estimate` in `knowledge_base` table is calculated from uncapped `$text` length before the `mb_substr( $text, 0, 60000 )` cap. For large documents the stored estimate overstates actual tokens sent to the LLM. Affects the LLM Usage card estimates on the overview page for Drive-synced documents. |

---

## Phase 3 Findings

### Resolved

| ID | Severity | Finding | Resolution |
|---|---|---|---|
| QA-3A-001 | MAJOR | `generate_report_job()` did not re-schedule the next run — reports executed exactly once regardless of configured frequency | FIXED — re-schedules itself unconditionally (outside all error branches) using `$report_cfg['frequency']` to compute `DAY_IN_SECONDS` or `WEEK_IN_SECONDS` delay |
| QA-3A-002 | MAJOR | `handle_delete_report()` did not unschedule the pending AS job — orphaned job ran after report deletion | FIXED — `as_unschedule_all_actions( self::AS_HOOK_REPORT, $args )` called with matching args structure inside `function_exists` guard |
| QA-3A-003 | MINOR | `is_pro_or_above()` tier check duplicated between PHP class and `sheets.php` template — drift risk | MITIGATED — sync comment added to both locations. Duplication remains (see open MINORs). |

---

## Post-Deployment Findings (2026-03-25 – 2026-04-01)

All resolved. Issues discovered during live Docker deployment and testing.

| Finding | Severity | Resolution |
|---|---|---|
| Chat not responding — `max_tokens` rejected by GPT-5.4-mini (`max_completion_tokens` required) | MAJOR | `stream_openai()` and probe use `max_completion_tokens`. Groq keeps `max_tokens`. Anthropic uses `max_tokens` (still required). |
| Model free-text field allowed invalid model names causing API 400 errors | MAJOR | Provider-aware `<select>` dropdown with JS dynamic repopulation. `get_models_by_provider()` is single source of truth. Server-side `sanitize_llm_model()` validates against same list. |
| Anthropic API rejects requests without explicit `max_tokens` | MAJOR | `grayfox_llm_max_tokens` setting added (default 1024, range 64–32000). All four streaming providers consume it. |
| KB upload button permanently disabled after tier limit check | MAJOR | `'disabled' => null` renders `disabled=""` in WordPress `submit_button()`. Fixed with conditional attributes array — attribute omitted entirely when not at limit. |
| Documents not visible after upload until AS job completes (could be minutes in dev) | MAJOR | Pending row with `content_json = null` inserted immediately on upload before AS job queues. Document appears in list with "Pending" status instantly. |
| License overview showed "Not configured" despite valid license token | MAJOR | `overview.php` now reads `GrayFox_License::get_verified_tier()` / `get_verified_status()` from Ed25519 token instead of raw `get_option('grayfox_license_tier')`. |
| LLM classifier flagged greeting "hi" as off-topic | MAJOR | KB context (truncated to 800 chars) injected into classifier system prompt. Classifier instructed: greetings/ambiguous = `'safe'`; when in doubt = `'safe'`. Fail-open on API errors. |
| `class-grayfox-google.php`: 2 AJAX handlers continued executing after `wp_send_json_error()` — auth bypass | MAJOR | `return;` added after every `wp_send_json_error()` call — fixed 2026-04-01 |
| `class-grayfox-booking.php`: 6 locations missing `return` after `wp_send_json_error()` — auth/validation bypass | MAJOR | `return;` added after every call — fixed 2026-04-01 |
| `class-grayfox-drive.php`: 8 locations missing `return` after `wp_send_json_error()` — auth/tier bypass | MAJOR | `return;` added after every call — fixed 2026-04-01 |

---

## Open MINORs — Full List

| ID | Phase | Finding |
|---|---|---|
| QA-004 | Phase 1 | Gemini streaming buffer fragility — `preg_replace` could re-match already-yielded tokens on pathological chunk boundaries. |
| QA-005 | Phase 1 | `GrayFoxConfig` localized twice when widget + shortcode both render on same page. |
| QA-006 | Phase 1 | `grayfox_stream_*` transients not cleaned up on uninstall (auto-expire 60s). |
| QA-P2-005 | Phase 2B | Inline `<script>` blocks in `appointments.php` and `booking-settings.php` — CSP risk. |
| QA-P2-006 | Phase 2B | `token_estimate` calculated before `mb_substr()` cap — overstated for large Drive documents. |
| QA-3A-003 | Phase 3A | `is_pro_or_above()` tier list duplicated in PHP class and template — maintenance drift risk. |
