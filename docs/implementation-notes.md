# GrayFox Plugin — Implementation Notes

**Audience:** Developers maintaining or extending the GrayFox WordPress plugin.  
**Schema version covered:** 1.1.0  
**Last updated:** 2026-04-01

---

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Database Schema](#database-schema)
3. [Feature Set 1 — RAG / Knowledge Base](#feature-set-1--rag--knowledge-base)
4. [Feature Set 2 — Conversation Limits and Abuse Protection](#feature-set-2--conversation-limits-and-abuse-protection)
5. [Feature Set 3 — Build Site from Knowledge Base](#feature-set-3--build-site-from-knowledge-base)
6. [Configuration Reference](#configuration-reference)
7. [Dependency Notes](#dependency-notes)
8. [Gotchas and Non-Obvious Behaviors](#gotchas-and-non-obvious-behaviors)

---

## Architecture Overview

GrayFox follows a loader-based hook registration pattern. Every major component is a class with a `register(GrayFox_Loader $loader)` method. The plugin entry point is `GrayFox_Plugin` (singleton), which constructs each component, calls `register()`, then runs the loader.

```
grayfox.php
  └── GrayFox_Plugin::__construct()
        ├── DB version guard → GrayFox_DB::create_tables()
        ├── new GrayFox_Settings()  → registers Settings API fields
        ├── new GrayFox_Admin()     → registers admin menus + 15 AJAX handlers
        ├── new GrayFox_Chat()      → registers chat AJAX endpoints
        ├── GrayFox_RAG::get_instance()       → registers AS callback + doc processing
        ├── GrayFox_SiteBuilder::get_instance() → registers AS callback + wizard
        └── GrayFox_Loader::run()  → iterates collected hooks, calls add_action/add_filter
```

All long-running work is dispatched via **Action Scheduler (AS)**. Synchronous fallback runs the job inline when AS is not available (upload processing path only). Site generation refuses to start if AS is absent and returns an error to the wizard.

---

## Database Schema

Schema is managed by `GrayFox_DB::create_tables()` using `dbDelta()`. It is called unconditionally at boot when the stored `grayfox_db_version` option is not `1.1.0`, then the version is updated.

### `wp_grayfox_knowledge_base`

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `source_type` | ENUM('upload','google_drive','google_doc','manual') | |
| `source_id` | VARCHAR(255) | WP attachment ID (uploads) or Drive file ID |
| `source_name` | VARCHAR(255) | Human-readable label |
| `content_json` | LONGTEXT | Structured JSON summary produced by LLM |
| `token_estimate` | INT | `ceil(mb_strlen(raw_text) / 4)` |
| `last_processed_at` | DATETIME | UTC; set on each upsert |
| `status` | ENUM('pending','active','pending_review') | **New in 1.1.0** |
| `topic_index` | TEXT | JSON array of lowercase keyword strings; **New in 1.1.0** |
| `created_at` | DATETIME | |

Indexes: `PRIMARY KEY (id)`, `KEY idx_status (status)`.

### `wp_grayfox_conversations`

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `session_id` | VARCHAR(64) | UUID4 generated client-side on first message |
| `visitor_id` | VARCHAR(64) | Reserved |
| `started_at` | DATETIME | |
| `last_active_at` | DATETIME | Updated on every message; drives 24h TTL check |
| `message_count` | INT DEFAULT 0 | **New in 1.1.0**; incremented after each user INSERT |

### Schema Upgrade Guard

Located in `includes/class-grayfox-plugin.php`:

```php
if ( get_option( 'grayfox_db_version', '1.0.0' ) !== '1.1.0' ) {
    GrayFox_DB::create_tables();
    update_option( 'grayfox_db_version', '1.1.0' );
}
```

`dbDelta()` is additive; it adds missing columns and indexes but does not drop them. Existing rows in both tables are unaffected by the upgrade — `message_count` defaults to 0 and `status` defaults to `'pending'` for any rows that pre-date 1.1.0.

---

## Feature Set 1 — RAG / Knowledge Base

**Primary file:** `includes/class-grayfox-rag.php`  
**Support files:** `includes/class-grayfox-admin.php` (AJAX handlers), `templates/admin/knowledge-base.php`

### Document Ingestion Pipeline

```
Admin uploads file
  → handle_document_upload() (admin-post)
      ├── tier limit check
      ├── media_handle_upload() → WP attachment created
      ├── PDF + library missing? → set transient, redirect with error
      └── GrayFox_RAG::schedule_processing($attachment_id)
            ├── INSERT pending row into knowledge_base
            └── as_enqueue_async_action(AS_HOOK, [$attachment_id])

Action Scheduler fires
  → GrayFox_RAG::process_document($attachment_id)
        ├── extract_text() → dispatches by extension
        │     ├── txt/csv: file_get_contents
        │     ├── pdf:     extract_pdf_text()   ← sentinel returns
        │     └── docx:    extract_docx_text()  ← ZipArchive + XML
        ├── sentinel '' (library missing) → upsert {_warning: pdf_library}, return
        ├── sentinel '__PDF_NO_TEXT__'    → upsert {_warning: pdf_no_text}, return
        ├── mb_substr($raw_text, 0, 60000)  ← 60k char cap
        ├── summarize_with_llm()
        │     └── GrayFox_LLM::request_json() non-streaming, temperature=0
        ├── build_topic_index(decoded_json)
        ├── upsert_kb_row() with status='active'  ← upsert FIRST
        └── detect_and_flag_conflicts()
              ├── detect_conflicts() → overlap ratio > 0.3 threshold
              └── if conflicts: update_option('grayfox_pending_conflicts')
                               set status → 'pending_review'
```

### `extract_pdf_text()` — Sentinel Contract

```php
private function extract_pdf_text(string $file_path): string
```

- Returns `''` when `vendor/smalot/pdfparser` is not installed. The caller (`process_document`) treats this as "library missing" and stores `{_warning: 'pdf_library'}` in `content_json`. The KB row is saved as `active` so the document shows in the admin with an install-library notice.
- Returns `'__PDF_NO_TEXT__'` when the parser succeeds but fewer than 50 meaningful words are extracted (scan-only PDF). The caller stores `{_warning: 'pdf_no_text'}`.
- Returns extracted text on success.

The library is installed via an AS background action (`grayfox_install_dependencies`) that runs `composer install`. The admin template polls `grayfox_check_pdf_support` every few seconds after the user clicks Install.

### `summarize_with_llm()`

```php
private function summarize_with_llm(string $raw_text, string $source_name = ''): string
```

Uses `GrayFox_LLM::request_json()` (non-streaming, provider-enforced JSON mode) at `temperature=0`. The system prompt embeds a concrete schema example covering `services`, `prices`, `hours`, `policies`, `faqs`, and `contact_info`. If the LLM returns an empty string or invalid JSON, the method returns `''` and logs to `error_log`. The caller stores `{_error: 'summarization_failed'}` in `content_json`, and a Retry button is surfaced in the admin.

There is **no raw-text fallback**. A failed summarization means the document has no usable KB entry until retried.

### `build_topic_index()`

```php
public static function build_topic_index(array $content_json): array
```

Walks the structured JSON recursively:
- Top-level keys become topics.
- String values are word-split on `[\s\p{P}]+` and words >= 3 chars are added.
- Nested arrays are traversed one level deep (sub-key + sub-value words).

Output: deduplicated, lowercase, capped at 200 items. Stored as a JSON-encoded array in `topic_index`.

### `detect_and_flag_conflicts()`

```php
private function detect_and_flag_conflicts(
    array  $new_topics,
    int    $exclude_id,
    string $source_name,
    int    $new_doc_id = 0
): string
```

Key ordering constraint: the upsert to `knowledge_base` **must run before** this method is called so that `$new_doc_id` is a valid row ID. This is enforced by `process_document()`'s call order.

Conflict detection compares the new document's topic array against every `status='active'` row (excluding itself). An overlap ratio > `0.3` triggers a conflict:

```
overlap_ratio = count(intersect(new_topics, existing_topics))
                / max(1, min(count(new_topics), count(existing_topics)))
```

Conflict entries are pushed to the `grayfox_pending_conflicts` WP option (array of objects). Each entry contains:
- `new_doc_id`, `new_source_name`
- `old_doc_id`, `old_source_name`
- `overlapping_topics` (up to 10 items)
- `detected_at`

Returns `'pending_review'` if any conflicts were found; the caller then does a second `UPDATE` on the row to set `status='pending_review'`.

### `retrieve_relevant_sections()`

```php
public static function retrieve_relevant_sections(string $query, array $kb_rows): array
```

Scoring:
1. Tokenize query: split on whitespace, filter words <= 3 chars, remove stop words.
2. For each KB row, count how many query terms appear as substrings in the `topic_index` array.
3. Sort descending by score.

Fallback: if the top score is 0, return short summaries (first 3 keys) of all documents.

Deduplication: pairs with topic overlap ratio > `0.8` are considered duplicates. The older document (lower in the already-newest-first list) is dropped. If content differs between near-duplicate documents, both are included with a `_conflict_note` key instructing the LLM to surface the conflict to the user.

### `get_consolidated_knowledge()`

```php
public static function get_consolidated_knowledge(string $query = ''): string
```

- Filters: `status = 'active'` and `content_json IS NOT NULL AND content_json != ''`.
- With query: delegates to `retrieve_relevant_sections()`.
- Without query: returns all active documents in newest-first order.
- Total output cap: 80,000 chars. Documents are trimmed from the end (oldest) until under cap.

Called by `GrayFox_Chat::handle_chat()` with the current user message as the query.

### Document Delete

`GrayFox_Admin::handle_delete_kb_document()` AJAX handler:
- Deletes the WP attachment via `wp_delete_attachment($source_id, true)` when `source_type = 'upload'`.
- Deletes the KB row.
- Returns updated `doc_count` to the frontend.

Google Drive-sourced documents delete the KB row only; the Drive file is not touched.

---

## Feature Set 2 — Conversation Limits and Abuse Protection

**Primary files:** `includes/class-grayfox-chat.php`, `includes/class-grayfox-settings.php`

### Settings

Registered under the `grayfox_conversation_limits` section in the WordPress Settings API:

| Option | Default | Range | Sanitizer |
|---|---|---|---|
| `grayfox_session_message_limit` | 21 | 5–50 | `sanitize_session_message_limit()` |
| `grayfox_ip_sessions_per_hour` | 5 | 1–10 | `sanitize_ip_sessions_per_hour()` |
| `grayfox_ip_sessions_per_day` | 10 | 1–25 | `sanitize_ip_sessions_per_day()` |

`grayfox_business_phone` is in the existing `grayfox_behavior` section. It is appended to every user-facing limit/expiry message when non-empty.

`grayfox_platform_url` sanitizer enforces `https://` scheme via `sanitize_platform_url()`.

### Chat Request Lifecycle (`handle_chat()`)

The complete check order on every `wp_ajax_grayfox_chat` / `wp_ajax_nopriv_grayfox_chat` request:

```
1. check_ajax_referer('grayfox_chat')
2. sanitize message + session_id
3. GrayFox_Security regex check → block/warn on injection/profanity
4. LLM classifier check (if API key configured)
5. Determine is_new_session = empty(session_id)
   ├── Existing session → Session TTL check
   │     └── last_active_at > 86400s ago?
   │           → wp_send_json_error({session_expired: true}, 200)
   └── New session → IP rate limit check
         ├── md5(client_ip) → transient keys grayfox_ip_h_{hash} (TTL 3600)
         │                                   grayfox_ip_d_{hash} (TTL 86400)
         ├── h_count >= h_limit OR d_count >= d_limit?
         │     → wp_send_json_error({rate_limited: true}, 429)
         └── else: increment both counters
6. Upsert conversation row, fetch message_count
7. message_count >= msg_limit?
   → wp_send_json_error({limit_reached: true}, 200)
8. message_count == (msg_limit - 2)?
   → set warm_down_instruction (passed to LLM build_messages)
9. INSERT user message
10. UPDATE message_count = message_count + 1
11. GrayFox_RAG::get_consolidated_knowledge($message)
12. Build LLM messages + stream transient
13. Return {session_id, stream_token}
```

### IP Rate Limiting — Transient Increment Behavior

WP transients cannot increment without resetting TTL. The current implementation resets the TTL on every increment. This means a user who sends their 4th session at hour 0:59 gets another full hour window starting at 0:59. This is documented in the source code comments as intentional ("preserve remaining TTL isn't possible with WP transients").

Practical impact: a user who starts sessions spread across an hour boundary can accumulate more than the hourly limit across the boundary. This is acceptable for abuse protection at this level of strictness.

### Message Limit and Warm-Down

The message limit check compares `message_count` (the count of **user** messages in this session) against `grayfox_session_message_limit`. The assistant response messages are not counted.

`warm_down_instruction` is injected at exactly `msg_limit - 2` user messages. It instructs the LLM to wrap up the conversation and optionally mentions the business phone. It is passed as a parameter to `GrayFox_LLM::build_messages()` and is not stored in the DB.

Error response keys by scenario:

| Scenario | Key in error data | HTTP status |
|---|---|---|
| Session expired | `session_expired: true` | 200 |
| IP rate limited | `rate_limited: true` | 429 |
| Message limit reached | `limit_reached: true` | 200 |

The frontend is expected to read these keys and surface the appropriate UX.

---

## Feature Set 3 — Build Site from Knowledge Base

**Primary files:** `includes/class-grayfox-site-builder.php`, `includes/class-grayfox-admin.php` (wizard AJAX handlers), `src/site-builder.js`, `templates/admin/site-builder.php`

### GrayFox_SiteBuilder Class

Singleton. Registered on `init` at priority 5 to ensure AS loads first.

Constants:

| Constant | Value | Purpose |
|---|---|---|
| `AS_HOOK_GENERATE` | `grayfox_generate_site_pages` | AS action name |
| `LOCK_TRANSIENT` | `grayfox_site_generation_lock` | Prevents concurrent runs (TTL 1800s) |
| `BUILD_OPTION` | `grayfox_site_build` | Generation progress stored here |
| `SITEMAP_OPTION` | `grayfox_sitemap_draft` | Approved sitemap from wizard |
| `FORMAT_OPTION` | `grayfox_site_build_format` | `'blocks'` or `'elementor'` |
| `UNSPLASH_OPTION` | `grayfox_unsplash_api_key` | Encrypted Unsplash key |
| `META_GENERATED` | `_grayfox_generated` | Post meta flag on generated pages |
| `ELEMENTOR_WIDGET_WHITELIST` | `['heading','text-editor','image']` | Allowed Elementor widget types |

### 5-Step Wizard Flow

```
Step 1 — Sitemap
  JS: grayfox_generate_sitemap_preview AJAX
        → aggregates topic_index from all active KB rows
        → LLM call: request_json() returns {pages:[{title,children}]}
  User edits page titles in DOM (input elements, no innerHTML with server data)
  JS: grayfox_save_sitemap AJAX → sanitize_sitemap_pages() → SITEMAP_OPTION

Step 2 — Environment Detection
  Auto-runs when step 2 becomes active
  JS: grayfox_detect_environment AJAX
        → GrayFox_SiteBuilder::detect_environment()
        → checks ELEMENTOR_VERSION constant + is_plugin_active()
        → checks Divi, Beaver Builder, WPBakery
        → checks wp_is_block_theme()
  JS: disables Elementor radio if elementor_version_ok is false

Step 3 — Format Choice
  User picks 'blocks' or 'elementor'
  JS: grayfox_set_build_format AJAX
        → validates Elementor availability if elementor chosen
        → FORMAT_OPTION

Step 4 — Generate
  JS: grayfox_estimate_generation_cost AJAX → estimate_tokens()
  JS: grayfox_start_site_generation AJAX
        ├── checks LOCK_TRANSIENT (abort if already running)
        ├── checks as_enqueue_async_action exists (abort if AS absent)
        ├── set_transient(LOCK_TRANSIENT, 1, 1800)
        ├── init BUILD_OPTION {status:running, total:N, completed:0, pages:[]}
        └── as_enqueue_async_action(AS_HOOK_GENERATE, [sitemap, format])
  JS: polls grayfox_get_build_progress every 2000ms
        → reads BUILD_OPTION
        → moves to Step 5 when status='complete'

Step 5 — Results
  Renders page list from BUILD_OPTION['pages']
  Each entry: {post_id, status, title, edit_url}
  Undo: grayfox_undo_site_build → wp_trash_post() for all META_GENERATED pages
```

### `generate_page()` — Per-Page Generation

```php
public function generate_page(array $page_def, string $format, int $parent_id): array
```

1. Retrieves relevant KB rows via `GrayFox_RAG::retrieve_relevant_sections($title, $all_rows)`.
2. Concatenates context, capped at 8,000 chars.
3. Calls `GrayFox_LLM::request_json()` at temperature 0.3. Expected return: `{title, blocks:[{type,level?,content?,keyword?}]}`.
4. Sanitizes all `content` fields with `wp_kses_post()`, `keyword` fields with `sanitize_text_field()`.
5. Builds content in chosen format:
   - `'blocks'`: serialized WordPress block markup via `build_wp_blocks()`.
   - `'elementor'`: nested array for `_elementor_data` post meta via `build_elementor_data()`.
6. Checks slug collision: appends `-v2` on conflict.
7. Inserts page as `post_status='draft'`.
8. Adds `_grayfox_generated = '1'` post meta.
9. Fetches Unsplash image using the first `image`-type block's `keyword` (or falls back to page title). Sideloads via `media_sideload_image()`. Sets as featured image.

All exceptions are caught; failures return `{status:'failed'}` without crashing the AS job.

Generated pages are limited to 3 revisions (`limit_revisions_for_generated_pages` filter on `wp_revisions_to_keep`).

### Elementor Data Structure

The generated Elementor data follows this shape:

```json
[
  {
    "id": "<7-char random>",
    "elType": "section",
    "settings": {},
    "elements": [
      {
        "id": "<7-char random>",
        "elType": "column",
        "settings": { "_column_size": 100 },
        "elements": [ /* widget objects */ ]
      }
    ]
  }
]
```

Widget IDs are generated with `wp_generate_password(7, false)`. Only widget types in `ELEMENTOR_WIDGET_WHITELIST` (`heading`, `text-editor`, `image`) are emitted. The data is stored via `update_post_meta($post_id, '_elementor_data', wp_slash(wp_json_encode($data)))` — the `wp_slash()` call is required by Elementor's meta storage contract.

### `estimate_tokens()`

```php
public function estimate_tokens(array $sitemap): array
```

Formula:
- `input_tokens = sum(kb token_estimates) * page_count * 0.3`
- `output_tokens = 500 * page_count`

Pricing data is sourced from `GrayFox_Settings::get_model_pricing($model)`. The pricing table is hardcoded in `includes/class-grayfox-settings.php` with a `verified_date` per model. **This table must be updated when provider pricing changes.**

### Site Builder JS (`src/site-builder.js`)

Key design decisions:
- Uses the Fetch API (`fetch(ajaxUrl, {method:'POST', body:FormData})`), not jQuery AJAX.
- All server-returned data is rendered via DOM APIs (`document.createElement`, `textContent`, `appendChild`). No `innerHTML` is used with any server-provided string. This prevents XSS even if the LLM returns malicious content in a page title or topic.
- Progress polling uses `setInterval`-equivalent (`pollTimer`) at 2000ms intervals.
- The sitemap editor stores the live page tree in `window._grayfoxSitemapPages`. User edits to title inputs are bound with `addEventListener('input', ...)` that writes back to that object.
- Nonces are passed via `wp_localize_script` as `GrayFoxSiteBuilderL10n.nonces` (one nonce per action).

---

## Configuration Reference

### WordPress Options

| Option | Type | Default | Notes |
|---|---|---|---|
| `grayfox_session_message_limit` | int | 21 | 5–50; user messages per session |
| `grayfox_ip_sessions_per_hour` | int | 5 | 1–10; new sessions per IP per hour |
| `grayfox_ip_sessions_per_day` | int | 10 | 1–25; new sessions per IP per day |
| `grayfox_business_phone` | string | '' | Appended to limit/expiry messages |
| `grayfox_platform_url` | string | `https://api.grayfox.io` | Must be https:// |
| `grayfox_llm_provider` | string | `openai` | One of: openai, anthropic, gemini, groq |
| `grayfox_llm_api_key` | string | '' | Stored encrypted via `grayfox_encrypt()` |
| `grayfox_llm_model` | string | '' | Must be in the pricing table for cost estimates |
| `grayfox_db_version` | string | `1.0.0` | Upgraded to `1.1.0` by schema guard |
| `grayfox_pending_conflicts` | array | [] | Serialized conflict queue |
| `grayfox_sitemap_draft` | array | [] | Approved sitemap from wizard Step 1 |
| `grayfox_site_build_format` | string | `blocks` | `'blocks'` or `'elementor'` |
| `grayfox_site_build` | array | [] | Generation progress and results |
| `grayfox_unsplash_api_key` | string | '' | Stored encrypted |

### Transients

| Transient | TTL | Purpose |
|---|---|---|
| `grayfox_stream_{token}` | 60s | Single-use SSE stream context |
| `grayfox_ip_h_{md5(ip)}` | 3600s | Hourly session counter per IP |
| `grayfox_ip_d_{md5(ip)}` | 86400s | Daily session counter per IP |
| `grayfox_site_generation_lock` | 1800s | Prevents concurrent site builds |
| `grayfox_pdf_library_missing` | persistent (0) | Shows install notice in KB admin |
| `grayfox_conflict_notice` | persistent (0) | Admin notice for new conflicts |
| `grayfox_kb_first_doc_ready` | persistent (0) | Onboarding hint trigger |

### Action Scheduler Actions

| Hook | Args | Registered by |
|---|---|---|
| `grayfox_process_document` | `[int $attachment_id]` | `GrayFox_RAG::register_as_callback()` on `init` priority 5 |
| `grayfox_generate_site_pages` | `[array $sitemap, string $format]` | `GrayFox_SiteBuilder::register_as_callback()` on `init` priority 5 |
| `grayfox_install_dependencies` | `[]` | `GrayFox_RAG::register()` via loader |

---

## Dependency Notes

### smalot/pdfparser

- **Path checked:** `GRAYFOX_PATH . 'vendor/smalot/pdfparser/src/Smalot/PdfParser/Parser.php'`
- **Installed via:** `composer install` run through `GrayFox_RAG::run_install_dependencies()` AS action.
- **Composer candidates checked in order:** `{plugin_dir}/composer.phar`, `/usr/local/bin/composer`, `/usr/bin/composer`.
- If none are executable, the install silently fails and logs to `error_log`.
- The admin can trigger the install from the KB page's Install button, which enqueues the `grayfox_install_dependencies` AS action and polls `grayfox_check_pdf_support` until the file exists.

### Action Scheduler

Required for site generation. Document processing has an inline fallback; site generation does not. The check in `handle_start_site_generation()` calls `function_exists('as_enqueue_async_action')` **before** setting the lock transient.

### Elementor

Minimum version 3.0.0. Detected via the `ELEMENTOR_VERSION` constant or `is_plugin_active('elementor/elementor.php')`. The wizard disables the Elementor format radio if the version check fails.

---

## Gotchas and Non-Obvious Behaviors

**PDF sentinel values are stored in `content_json`, not `status`.**  
A document with `{_warning: 'pdf_library'}` in `content_json` has `status = 'active'`. It will appear in the KB list and in `get_consolidated_knowledge()` queries, but the LLM will receive a JSON object with only a `_warning` key. The admin template is expected to detect this and hide it from chat context; confirm this in the template before relying on it.

**Conflict detection runs after upsert, not before.**  
The document is written to the DB with `status='active'` first. If conflicts are found, a second `UPDATE` sets it to `pending_review`. There is a brief window (milliseconds under AS) where the document is `active` and visible to concurrent chat requests. This is unlikely to cause problems in practice but is worth knowing if you add real-time conflict checks.

**`message_count` counts only user messages.**  
Assistant responses are saved to the `messages` table but do not increment `message_count` in `conversations`. The limit is user-turn-based, not total-turn-based.

**IP rate-limit transients reset TTL on every increment.**  
See the note in [Feature Set 2](#ip-rate-limiting--transient-increment-behavior). A database-backed counter would give more accurate sliding-window behavior.

**Unsplash rate limiting is a courtesy sleep, not a queue.**  
`usleep(1200000)` (1.2 seconds) is called before each Unsplash API request inside `fetch_unsplash_image()`. For a large sitemap (e.g., 50 pages), this adds roughly 60 seconds to the AS job. The AS job has no timeout by default, but server-level PHP timeouts may apply depending on the host configuration.

**Elementor widget IDs must be unique per page but are generated randomly.**  
`wp_generate_password(7, false)` has a 1/62^7 ≈ 1 in 3.5 billion collision probability per widget pair. This is negligible in practice, but if Elementor ever enforces uniqueness validation at import, IDs could theoretically collide across many generated pages.

**Slug collision handling appends `-v2` once.**  
`generate_page()` checks `get_page_by_path($slug)` and appends `-v2` on collision. If `-v2` also exists (e.g., running the wizard twice), `wp_insert_post()` will handle it with its own slug uniqueness logic (appending `-3`, `-4`, etc.), but the `edit_url` in the result will point to the actual created page regardless.

**The 80k char cap in `get_consolidated_knowledge()` drops oldest documents.**  
The sort is stable (newest first from the SQL query). When truncation is needed, `array_pop()` removes the last (oldest) element until under the cap. If a single document's JSON exceeds 80k chars, the cap still applies and only that document is served.

**Warm-down instruction is never stored.**  
`warm_down_instruction` is built in `handle_chat()`, passed to `build_messages()`, and included in the stream transient. It is consumed during the SSE stream and is not persisted anywhere. If the stream fails before the final message, the instruction is lost silently.

**`request_json()` is used for all non-streaming LLM calls.**  
Summarization (`summarize_with_llm`), sitemap generation, page content generation, and conflict diff all use `GrayFox_LLM::request_json()`. The streaming `send_message()` generator is only used for the live chat SSE endpoint. Do not confuse the two paths when debugging LLM failures.

**The pricing table in `GrayFox_Settings::get_model_pricing()` is hardcoded.**  
It carries a `verified_date` per model. When providers change pricing, this table must be updated manually in `includes/class-grayfox-settings.php`. Cost estimates shown in the wizard will be wrong until updated.
