# GrayFox Plugin — Phase 2: Google Integration

**Documented:** 2026-03-23
**Covers:** Components 2B (Google Calendar Booking) and 2C (Google Drive / Docs Auto-Sync)
**Tier requirement:** Growth or Pro for all features in this document

---

## Overview

Phase 2 adds two Growth-tier capabilities that connect the plugin to Google's APIs via a shared `GrayFox_Google` token manager (established in a prior phase):

- **Component 2B — Google Calendar Appointment Booking.** Visitors can describe a desired appointment in chat. The LLM response is parsed for a structured booking intent, which triggers an async Action Scheduler job that creates a Google Calendar event and writes a local DB record. Admins manage appointments through two new admin pages.
- **Component 2C — Google Drive / Docs Auto-Sync.** Admins select files from a Drive folder. Each selected file is fetched and pushed into the existing RAG knowledge base on a daily recurring schedule (or on demand). Re-syncs update the existing KB row rather than insert duplicates.

Both components are server-gated: every AJAX handler, every AS job, and every admin page template checks the license tier before doing any work. The Google OAuth token is never exposed to JavaScript.

---

## Component 2B: Google Calendar Appointment Booking

### Architecture

**Class:** `GrayFox_Booking` (singleton via `get_instance()`)
**File:** `includes/class-grayfox-booking.php`

The class owns four responsibilities:

1. **Service and schedule configuration** — reads `grayfox_booking_*` options for services, working hours, buffer time, calendar ID, and timezone.
2. **Availability checking** — queries Google Calendar for existing events on a requested date, generates candidate slots from working hours and service duration, and filters out overlapping slots.
3. **Appointment creation and cancellation** — calls the Google Calendar Events API and keeps the local `grayfox_appointments` table in sync.
4. **Booking intent extraction** — parses an LLM response string for a `{"booking": {...}}` JSON block.

**Booking intent extraction (called from `GrayFox_Chat` after the stream completes):**

```php
// GrayFox_Booking::extract_booking_intent()
// Pattern 1: nested JSON with "booking" key
preg_match('/\{[^{}]*"booking"\s*:[^{}]*\{[^{}]*\}[^{}]*\}/s', $llm_response, $matches);
// Pattern 2 fallback: any JSON object ≤ 2000 chars
preg_match('/\{[^{}]{0,2000}\}/s', $llm_response, $matches);
```

Required fields for a valid intent: `service`, `date`, `time`, `customer_name`, `customer_email`. All are sanitized before return. `notes` is optional.

**Calendar API call flow:**

1. `GrayFox_Google::get_instance()->get_access_token()` — returns the stored OAuth token or null.
2. `calendar_request(method, url, body)` — sends an authenticated `wp_remote_request()` with `Authorization: Bearer <token>` and `Content-Type: application/json`. Timeout is 15 seconds.
3. Responses are decoded and returned as `['code' => int, 'body' => array]`.

**Data storage — `grayfox_appointments` table:**

| Column | Type | Notes |
|---|---|---|
| `id` | int, PK, auto-increment | |
| `customer_name` | varchar | sanitized |
| `customer_email` | varchar | sanitized |
| `service` | varchar | matched against configured services |
| `start_time` | datetime | stored as UTC via `gmdate()` |
| `end_time` | datetime | stored as UTC via `gmdate()` |
| `google_event_id` | varchar | returned by Calendar API on creation |
| `status` | varchar | `confirmed`, `pending`, `cancelled` |
| `notes` | text | optional |
| `created_at` | datetime | `current_time('mysql', true)` — UTC |

### AJAX Endpoints

**`grayfox_check_availability`**
- Actions: `wp_ajax_grayfox_check_availability`, `wp_ajax_nopriv_grayfox_check_availability`
- Nonce: `grayfox_booking` (field: `nonce`)
- Capability check: none (public); tier check via `is_booking_enabled()` — 403 if not Growth/Pro
- Parameters (POST): `date` (Y-m-d), `service` (string)
- Response on success: `{ success: true, data: { slots: [ { time: "HH:MM", label: "H:MM AM/PM" }, ... ] } }`
- Response on failure: `{ success: false, data: { message: "..." } }` with HTTP 400 or 403

**`grayfox_confirm_booking`**
- Actions: `wp_ajax_grayfox_confirm_booking`, `wp_ajax_nopriv_grayfox_confirm_booking`
- Nonce: `grayfox_booking` (field: `nonce`)
- Capability check: none (public); tier check via `is_booking_enabled()` — 403 if not Growth/Pro
- Parameters (POST): `customer_name`, `customer_email`, `service`, `start_datetime` (ISO 8601), `end_datetime` (ISO 8601), `notes` (optional)
- Response on success: `{ success: true, data: { appointment_id: int, confirmation_message: "..." } }`
- Response on failure: 500 with WP_Error message

**`grayfox_cancel_booking`**
- Action: `wp_ajax_grayfox_cancel_booking` (admin only — no `nopriv` variant registered)
- Nonce: `grayfox_cancel_booking` (field: `nonce`)
- Capability check: `manage_options` — 403 if not met
- Parameters (POST): `appointment_id` (int)
- Response on success: `{ success: true, data: { message: "Appointment cancelled." } }`
- Response on failure: 400 (invalid ID) or 500 (DB/API error)

**`grayfox_save_booking_settings`**
- Action: `wp_ajax_grayfox_save_booking_settings` (admin only)
- Nonce: `grayfox_booking_settings` (field: `nonce`)
- Capability check: `manage_options` — 403 if not met
- Parameters (POST): `services` (JSON string), `working_hours` (JSON string), `buffer_minutes` (int), `calendar_id` (string), `timezone` (string)
- Buffer minutes is validated against an allowlist: `[0, 15, 30, 45, 60]`; invalid values fall back to 15.
- Response on success: `{ success: true, data: { message: "Booking settings saved." } }`

### Admin Pages

**Appointments page** (`templates/admin/appointments.php`)

- Gated: renders upgrade CTA for non-Growth tiers; page returns early.
- Default date range: current week (Monday–Sunday based on `current_time('Y-m-d')`).
- URL parameters: `date_from` (Y-m-d), `date_to` (Y-m-d), `status_filter` (confirmed / pending / cancelled / empty = all).
- Calls `GrayFox_Booking::get_appointments($filters)` which runs a direct `$wpdb->get_results()` with `$wpdb->prepare()`.
- Renders a WP-style `wp-list-table` with columns: Customer (name + email), Service, Start, End, Status (badge), Actions (Cancel button for non-cancelled rows).
- Cancel button posts to `grayfox_cancel_booking` via `fetch()` with inline FormData. On success, the row's status cell and action cell are updated in-DOM without a page reload.
- The cancel nonce (`grayfox_cancel_booking`) is generated server-side and embedded in each button's `data-nonce` attribute.

**Booking Settings page** (`templates/admin/booking-settings.php`)

- Gated: renders upgrade CTA for non-Growth tiers; page returns early.
- Configurable fields:
  - **Services:** name (text), duration_minutes (number, min 5, step 5), price (number, min 0, step 0.01). Rows can be added/removed dynamically via inline JS. Service rows with empty name are excluded on save.
  - **Working Hours:** per-day toggle (enabled/disabled) + open/close time inputs (HTML `<input type="time">`). Disabled days have their time inputs disabled in the DOM. Defaults are Mon–Fri 09:00–17:00, Sat–Sun closed.
  - **Buffer Time:** dropdown with values 0, 15, 30, 45, 60 minutes.
  - **Calendar ID:** text field, default "primary".
  - **Booking Timezone:** full `<select>` populated from `timezone_identifiers_list()`. Defaults to `wp_timezone_string()`.
- Save posts to `grayfox_save_booking_settings` via `fetch()`. Success/error displayed via a dismissing notice that hides after 5 seconds.

### Data Flow Diagram

```
Visitor chat message
        |
        v
GrayFox_Chat::handle_chat()   — saves user message, builds LLM context, issues stream_token
        |
        v
GrayFox_Chat::handle_stream() — streams LLM response as SSE, assembles full_response
        |
        v
GrayFox_Booking::extract_booking_intent($full_response)
        |
   Intent found?
    Yes |                      No |
        v                         v
as_enqueue_async_action(        (no booking action)
  'grayfox_process_booking',
  ['data' => $intent]
)
        |
        v (async, via Action Scheduler)
GrayFox_Booking::process_booking_job($booking_data)
        |
        v
Builds ISO 8601 start/end from date + time + service duration
        |
        v
GrayFox_Booking::create_appointment($data)
        |
        +---> Calendar API POST  → Google Calendar event created
        |
        +---> $wpdb->insert() → grayfox_appointments row inserted
```

### Action Scheduler Jobs

**Hook: `grayfox_process_booking`**

- Registered at `init` priority 5 via `register_as_callbacks()` so AS can fire the job before later hooks run.
- Argument: `array $booking_data` — the output of `extract_booking_intent()`.
- Builds ISO 8601 `start_datetime` and `end_datetime` from `date` + `time` + service duration. Falls back to 60 minutes if service not found in config.
- Calls `create_appointment()`. Any WP_Error returned is swallowed (no retry); failures are logged via `error_log()` inside `create_appointment()`.

### Tier Gating

`is_booking_enabled()` returns `true` only when `grayfox_license_tier` option is `growth` or `pro`.

Enforcement points:
- `handle_check_availability()` — rejects with 403 if not enabled.
- `handle_confirm_booking()` — rejects with 403 if not enabled.
- `templates/admin/appointments.php` — renders upgrade CTA and returns early.
- `templates/admin/booking-settings.php` — renders upgrade CTA and returns early.
- `GrayFox_Widget::enqueue_assets()` — `bookingEnabled` is `'false'` string when not Growth/Pro; the chat widget JS can use this to suppress the booking UI.

### Security Notes

- The Google OAuth token is fetched server-side inside `calendar_request()` via `GrayFox_Google::get_instance()->get_access_token()`. It is never passed to JavaScript.
- All public AJAX handlers (`check_availability`, `confirm_booking`) verify the `grayfox_booking` nonce before any processing.
- Admin-only handlers (`cancel_booking`, `save_booking_settings`) additionally assert `current_user_can('manage_options')`.
- All POST inputs are passed through `sanitize_text_field()`, `sanitize_email()`, or `sanitize_textarea_field()` before use.
- Table names are wrapped with `esc_sql(GrayFox_DB::get_table(...))` before interpolation into SQL strings.
- Buffer minutes is validated against a strict allowlist; any value not in `[0, 15, 30, 45, 60]` is replaced with 15.

---

## Component 2C: Google Drive / Docs Auto-Sync

### Architecture

**Class:** `GrayFox_Drive` (singleton via `get_instance()`)
**File:** `includes/class-grayfox-drive.php`

The class manages the full lifecycle of Drive-to-knowledge-base synchronization:

1. **Folder listing** — queries the Drive v3 files API filtered to supported MIME types.
2. **File selection persistence** — saves chosen file IDs to `grayfox_drive_selected_files` (JSON array in `wp_options`).
3. **Incremental sync logic** — compares Drive `modifiedTime` against `last_processed_at` in the KB table; only enqueues files that are new or changed.
4. **Per-file AS jobs** — each file is processed in its own `grayfox_sync_drive_file` async job, keeping the sync request fast.
5. **Daily recurring trigger** — `grayfox_drive_daily_sync` recurring action calls `sync_selected_files()` once per day.

**Supported MIME types:**

| MIME type | Fetch method |
|---|---|
| `application/vnd.google-apps.document` | Drive export endpoint (`/export?mimeType=text/plain`) |
| `text/plain` | Drive media download (`?alt=media`) |
| `application/pdf` | Drive media download (`?alt=media`) |
| `application/vnd.openxmlformats-officedocument.wordprocessingml.document` | Drive media download (`?alt=media`) |

**Upsert into `GrayFox_RAG::summarize_to_knowledge_base()`:**

```php
// process_drive_file() in GrayFox_Drive calls:
GrayFox_RAG::get_instance()->summarize_to_knowledge_base(
    (string) $content,   // raw text from Drive
    $file_name,          // human-readable name from metadata
    $file_id             // Drive file ID used as source_id key
);

// Inside summarize_to_knowledge_base(), when source_id is non-null:
$existing_id = $wpdb->get_var(
    $wpdb->prepare("SELECT id FROM `{$safe_kb_table}` WHERE source_id = %s LIMIT 1", $source_id)
);

if ( $existing_id ) {
    $wpdb->update( ... ['content_json', 'token_estimate', 'last_processed_at'] ... );
} else {
    $wpdb->insert( ... ['source_type' => 'google_drive', 'source_id' => $source_id, ...] ... );
}
```

This upsert pattern prevents duplicate KB rows when a file is re-synced. The `source_id` column is the Drive file ID.

### AJAX Endpoints

All Drive AJAX handlers are admin-only (no `nopriv` variants). All use nonce `grayfox_drive` (field: `nonce`) and require `manage_options`. All additionally check `is_growth_or_above()` — 403 if not met.

**`grayfox_drive_list_folder`**
- Action: `wp_ajax_grayfox_drive_list_folder`
- Parameters (POST): `folder_id` (string)
- Calls `list_folder_files($folder_id)` which queries Drive v3 `/files` with `q="<folder_id>" in parents and trashed=false`, fields `id,name,mimeType,modifiedTime,size`, ordered by name.
- Returns only files matching `SUPPORTED_MIME_TYPES`.
- Response on success: `{ success: true, data: { files: [ {id, name, mimeType, modifiedTime, size}, ... ] } }`

**`grayfox_drive_save_selection`**
- Action: `wp_ajax_grayfox_drive_save_selection`
- Parameters (POST): `file_ids[]` (array of strings), `folder_id` (optional string)
- Sanitizes each ID with `sanitize_text_field()`, filters empty strings, saves JSON-encoded array to `grayfox_drive_selected_files`. Optionally updates `grayfox_drive_folder_id`.
- Response on success: `{ success: true, data: { message: "Selection saved." } }`

**`grayfox_drive_sync_now`**
- Action: `wp_ajax_grayfox_drive_sync_now`
- Parameters (POST): none beyond nonce
- Calls `sync_selected_files()` which fetches metadata for each selected file and enqueues only files that are new or modified since last sync. Updates `grayfox_drive_last_sync` to `current_time('mysql', true)`.
- Response on success: `{ success: true, data: { scheduled: int, skipped: int, message: "..." } }`

**`grayfox_drive_resync_file`**
- Action: `wp_ajax_grayfox_drive_resync_file`
- Parameters (POST): `file_id` (string)
- Force-enqueues a single `grayfox_sync_drive_file` async action without a modified-time check.
- Response on success: `{ success: true, data: { message: "File queued for re-sync." } }`

### Admin Page

**Template:** `templates/admin/drive-sync.php`

The page has three sections, all within `<div class="wrap">`:

**Section 1 — Folder Setup**
- Text input for Drive Folder ID (pre-populated from `grayfox_drive_folder_id` option).
- "Load Files" button triggers `grayfox_drive_list_folder` AJAX. The file list `<div>` is hidden until files load.
- File list table (injected by JS): columns are a select-all checkbox, File Name, Type (human-readable MIME label), Last Modified.
- Each row has a checkbox with `value="{file_id}"` and class `grayfox-drive-file-cb`. Previously selected IDs from `GrayFoxDriveL10n.preSelected` are pre-checked when rows are rendered.
- "Save Selection" button triggers `grayfox_drive_save_selection` with collected checked IDs and folder ID.

**Section 2 — Sync Status**
- Shows `grayfox_drive_last_sync` formatted with WP site date/time format.
- Table of per-file status (file_name, status badge, last_synced, Re-sync button) rendered server-side from `GrayFox_Drive::get_sync_status()`. Status values: `synced` (green), `pending` (amber), `never` (grey).
- "Sync All Now" button triggers `grayfox_drive_sync_now` and displays scheduled/skipped counts inline.
- Re-sync buttons delegate via `document.addEventListener('click')` in the JS; each button carries `data-file-id`.

**Section 3 — Sync Schedule**
- Reads next scheduled timestamp via `as_next_scheduled_action(GrayFox_Drive::AS_HOOK_DAILY, [], 'grayfox')` and formats it with the WP site date/time format.

**JS interactions — `assets/dist/grayfox-drive-sync.min.js` + `GrayFoxDriveL10n` localization**

The script is enqueued by the admin page registration (not by `GrayFox_Widget`). It reads all configuration from the `GrayFoxDriveL10n` object localized via `wp_localize_script()`:

| Key | Source |
|---|---|
| `ajaxUrl` | `admin_url('admin-ajax.php')` |
| `nonce` | `wp_create_nonce('grayfox_drive')` |
| `preSelected` | JSON-decoded `grayfox_drive_selected_files` option (array of file IDs) |
| `saving`, `saved`, `syncing`, `syncNow`, `queuing`, `reSync`, `networkError` | i18n strings |

`preSelected` is passed via `GrayFoxDriveL10n` rather than inline PHP in the template. This is intentional: the file list is rendered asynchronously after "Load Files" is clicked, so pre-checking must happen inside the JS `loadFiles()` callback rather than in PHP-rendered HTML. Embedding the selection in the localized object avoids duplicating the event listener.

The script uses `escHtml()` and `escAttr()` helper functions (manual HTML-entity replacement) when injecting Drive-API-sourced file names and MIME types into the DOM to prevent XSS.

### Action Scheduler Jobs

**Hook: `grayfox_sync_drive_file`** (`GrayFox_Drive::AS_HOOK_FILE`)
- Registered at `init` priority 5 via `register_as_callbacks()`.
- Argument: `string $file_id`
- Fetches file metadata via `get_file_metadata()`, fetches content via `fetch_file_content()`, calls `GrayFox_RAG::get_instance()->summarize_to_knowledge_base()`.
- On metadata or content fetch failure: logs via `error_log()` and returns without retrying (AS default retry behavior applies).
- Falls back to inline `process_drive_file()` if `as_enqueue_async_action()` is not available.

**Hook: `grayfox_drive_daily_sync`** (`GrayFox_Drive::AS_HOOK_DAILY`)
- Registered at `init` priority 5 via `register_as_callbacks()`.
- Scheduled as a recurring action (interval: `DAY_IN_SECONDS`) by `schedule_daily_sync()` called at `init` priority 20.
- `as_has_scheduled_action()` guard prevents duplicate scheduling.
- Calls `run_daily_sync()` which delegates to `sync_selected_files()`.

### Tier Gating

`is_growth_or_above()` (private) returns `true` only when `grayfox_license_tier` is `growth` or `pro`.

Enforcement points:
- `handle_list_folder()` — 403 if not Growth/Pro.
- `handle_save_selection()` — 403 if not Growth/Pro.
- `handle_sync_now()` — 403 if not Growth/Pro.
- `handle_resync_file()` — 403 if not Growth/Pro.
- `templates/admin/drive-sync.php` — renders upgrade CTA (`notice-warning`) and returns early for Starter tier before any interactive content.
- Same template also checks `$is_connected` and renders a `notice-error` if the Google account is not connected, then returns early.

### Key Design Decisions

**`preSelected` via `GrayFoxDriveL10n` (not inline PHP in template)**
The file list table is built in JS after the async "Load Files" AJAX call. If pre-selection state were emitted as PHP-rendered `checked` attributes in the template, those attributes would be on elements that don't exist at page load. Passing `preSelected` through the localized object lets the `loadFiles()` callback check `preSelected.indexOf(f.id) !== -1` when injecting each row.

**Content cap: `mb_substr($text, 0, 60000)`**
Applied in `GrayFox_RAG::summarize_to_knowledge_base()` before the LLM call. Limits input to approximately 15,000 tokens, staying well within the context windows of all four supported providers. The `token_estimate` is computed from the uncapped length before the cap is applied, so it reflects the true document size.

**UTC timestamps: `current_time('mysql', true)`**
All timestamps written by Phase 2 code (`created_at`, `last_processed_at` in the knowledge base; `created_at` in appointments) use `current_time('mysql', true)` (the `true` parameter requests UTC). This is consistent across `GrayFox_Booking::create_appointment()`, `GrayFox_Drive::sync_selected_files()`, and `GrayFox_RAG::summarize_to_knowledge_base()`.

**Upsert by `source_id` prevents duplicate KB rows on re-sync**
When Drive calls `summarize_to_knowledge_base()` with a non-null `source_id`, the function checks for an existing row by that ID before deciding INSERT vs UPDATE. This means re-syncing a modified Drive file updates the existing KB entry's content and timestamp in place, rather than accumulating duplicate rows.

### Security Notes

- The Google OAuth token is retrieved server-side in `GrayFox_Drive` via `GrayFox_Google::get_instance()->get_access_token()`. It is attached to `Authorization: Bearer` headers in `wp_remote_get()` calls only. It is never returned to the browser.
- All Drive API calls use `wp_remote_get()` with the `Authorization` header; no credentials are embedded in URL query strings.
- Every AJAX handler calls `check_ajax_referer('grayfox_drive', 'nonce')` first.
- Every AJAX handler asserts `current_user_can('manage_options')` before taking any action.
- `is_growth_or_above()` is checked on every handler after the capability check.
- `is_connected()` is checked in `list_folder_files()` and `fetch_file_content()` before any API call; a `WP_Error` is returned immediately if not connected.
- File IDs from POST are passed through `sanitize_text_field()`. File names and MIME types returned from the Drive API are escaped via `escHtml()` / `escAttr()` before DOM injection in JS.
- KB table names are wrapped with `esc_sql()` before interpolation.

---

## Modified: GrayFox_RAG (Phase 2 Additions)

**File:** `includes/class-grayfox-rag.php`

A new public method `summarize_to_knowledge_base(string $text, string $source_name, string $source_id = null)` was added. Changes relative to the pre-Phase-2 `process_document()` path:

- **`source_id` parameter:** when non-null (Drive file ID), the method performs an upsert (SELECT existing row → UPDATE or INSERT) with `source_type = 'google_drive'`. When null, it falls through to a plain INSERT (manual upload path, unchanged behavior).
- **Upsert logic:** SELECT by `source_id`, then UPDATE `content_json`, `token_estimate`, `last_processed_at` on an existing row, or INSERT a full new row.
- **UTC timestamps:** `last_processed_at` and `created_at` use `current_time('mysql', true)` throughout — both in the new method and in the existing `process_document()` method (which was already using this pattern from Phase 1).
- **60,000 character cap:** `$text = mb_substr($text, 0, 60000)` is applied before the LLM call in `summarize_to_knowledge_base()`. `token_estimate` is computed from the original uncapped length.

The existing `process_document()` method (upload path) and `get_consolidated_knowledge()` are unchanged.

---

## Modified: GrayFox_Chat (Phase 2 Addition)

**File:** `includes/class-grayfox-chat.php`

After the full LLM response is assembled at the end of `handle_stream()` (step 9a), the following was added:

```php
if ( class_exists( 'GrayFox_Booking' ) ) {
    $intent = GrayFox_Booking::get_instance()->extract_booking_intent( $full_response );
    if ( $intent && function_exists( 'as_enqueue_async_action' ) ) {
        as_enqueue_async_action( 'grayfox_process_booking', array( 'data' => $intent ) );
    }
}
```

This is a non-blocking path: if `GrayFox_Booking` is not loaded, or if Action Scheduler is unavailable, or if no intent is found, the stream response is still sent to the visitor normally. The booking job is always dispatched asynchronously — it never blocks or delays the SSE response.

---

## Modified: GrayFox_Widget (Phase 2 Addition)

**File:** `includes/class-grayfox-widget.php`

Two keys were added to the `GrayFoxConfig` object localized via `wp_localize_script()`:

| Key | Value | Notes |
|---|---|---|
| `bookingNonce` | `wp_create_nonce('grayfox_booking')` | Used by the chat widget JS to authenticate `grayfox_check_availability` and `grayfox_confirm_booking` AJAX calls |
| `bookingEnabled` | `'true'` or `'false'` (string) | `'true'` only when `GrayFox_Booking` class exists AND `is_booking_enabled()` returns true (i.e. Growth/Pro tier) |

`bookingEnabled` is a string `'true'`/`'false'`, not a boolean, due to `wp_localize_script()` converting all values to strings. The chat widget JS must compare with the string value.

---

## Known Limitations / Future Work

- **Token estimate computed pre-cap.** In `summarize_to_knowledge_base()`, `$token_estimate` is calculated from the full uncapped text length (`mb_strlen($text) / 4`), then the text is capped at 60,000 characters. The stored `token_estimate` therefore overestimates the actual tokens sent to the LLM for documents larger than ~60,000 characters.

- **Non-minified dist JS.** `assets/dist/grayfox-drive-sync.min.js` has a `.min.js` extension but the source is readable (unminified). This is a build pipeline gap — the file was committed directly without running through esbuild minification.

- **Calendar event deletion is non-fatal.** In `cancel_appointment()`, if the Google Calendar API DELETE call fails (e.g. token expired, network error), the appointment's DB status is still updated to `cancelled`. The Google Calendar event may remain active. No retry or admin notification is implemented.

- **PDF text extraction is basic.** `GrayFox_RAG::extract_pdf_text()` uses regex on raw PDF bytes (BT/ET markers). Complex PDFs with non-standard encodings, embedded fonts, or scanned images will produce poor extraction. This is unchanged from Phase 1 but becomes more relevant now that PDFs can enter the KB via Drive sync.

- **No duplicate-job guard on `enqueue_file_job()`.** `as_enqueue_async_action()` can schedule multiple pending jobs for the same `file_id` if `sync_now` is triggered rapidly. `is_job_pending()` is only used for status display, not as a pre-enqueue guard.
