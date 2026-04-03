# GrayFox AI Assistant — Technical Documentation

**Plugin version:** 1.0.0
**WordPress minimum:** 6.0
**PHP minimum:** 8.1
**Text domain:** `grayfox`
**Last documented:** 2026-03-22

---

## Table of Contents

1. [Overview](#1-overview)
2. [Plugin Structure](#2-plugin-structure)
3. [Database Schema](#3-database-schema)
4. [Encryption](#4-encryption)
5. [Chat Flow (End-to-End)](#5-chat-flow-end-to-end)
6. [LLM Client](#6-llm-client)
7. [RAG-Lite Processor](#7-rag-lite-processor)
8. [License Validation](#8-license-validation)
9. [Admin Dashboard](#9-admin-dashboard)
10. [Build System](#10-build-system)
11. [Installation & Configuration](#11-installation--configuration)

---

## 1. Overview

GrayFox AI Assistant is a WordPress plugin that embeds an AI-powered chat widget into any WordPress site. It is designed for small businesses that want a customer-facing chatbot backed by their own documents, running entirely on their own WordPress server.

**Core design principles:**

- **Client-side-only data residency.** All conversations, knowledge base documents, and credentials are stored exclusively in the WordPress database on the site owner's server. No data is sent to an external GrayFox platform except for license key validation pings.
- **Bring Your Own LLM (BYOLLM).** The site owner supplies their own API key for one of four supported providers: OpenAI, Anthropic, Google Gemini, or Groq. The key is stored encrypted in `wp_options` and is decrypted server-side only — it is never sent to the browser.
- **RAG-Lite knowledge base.** Uploaded documents (PDF, DOCX, TXT, CSV) are processed by the LLM into structured JSON summaries and stored in a custom database table. Each chat request injects the consolidated knowledge base as the system context.
- **No external JavaScript frameworks.** The front-end widget is plain ES5-compatible JavaScript bundled with esbuild. There is no React, Vue, or jQuery dependency.

---

## 2. Plugin Structure

### 2.1 File Tree

```
grayfox-plugin/
├── grayfox.php                              Entry point, constants, encryption helpers, boot
├── includes/
│   ├── class-grayfox-loader.php             Hook registry (actions + filters queue)
│   ├── class-grayfox-plugin.php             Singleton orchestrator; wires all components
│   ├── class-grayfox-db.php                 Custom table creation and teardown
│   ├── class-grayfox-settings.php           WordPress Settings API integration
│   ├── class-grayfox-license.php            License validation and feature gating
│   ├── class-grayfox-llm.php                LLM provider client (streaming, 4 providers)
│   ├── class-grayfox-chat.php               AJAX chat endpoint with SSE streaming output
│   ├── class-grayfox-widget.php             Front-end asset enqueue + floating widget render
│   ├── class-grayfox-shortcode.php          [grayfox_chat] shortcode handler
│   ├── class-grayfox-admin.php              Admin menu pages and document upload handler
│   └── class-grayfox-rag.php                RAG document processor via Action Scheduler
├── templates/
│   ├── chat-widget.php                      Floating bubble HTML (injected via wp_footer)
│   ├── chat-embed.php                       Inline embed HTML (rendered by shortcode)
│   └── admin/
│       ├── overview.php                     Admin: Overview page template
│       ├── settings.php                     Admin: Settings page template
│       ├── knowledge-base.php               Admin: Knowledge Base page template
│       └── conversations.php               Admin: Conversations page template
├── src/
│   ├── chat-widget.js                       JS entry point; initializes all widget instances
│   ├── components/
│   │   ├── ChatWindow.js                    Main chat controller (open/close/send/stream)
│   │   ├── MessageList.js                   DOM helper: append, update, scroll messages
│   │   ├── MessageInput.js                  Textarea + send button wiring
│   │   └── TypingIndicator.js               Show/hide typing animation
│   ├── services/
│   │   ├── api.js                           fetch() wrapper for the WP AJAX POST
│   │   └── sse-client.js                    EventSource wrapper with retry/backoff logic
│   ├── store/
│   │   └── session.js                       sessionStorage persistence for session ID + messages
│   └── styles/
│       ├── widget.css                       Widget CSS source
│       └── admin.css                        Admin CSS source
├── assets/dist/                             esbuild output (committed or build-generated)
│   ├── grayfox-chat.min.js
│   ├── grayfox-chat.min.css
│   └── grayfox-admin.min.css
├── vendor/woocommerce/action-scheduler/     Action Scheduler library (optional, bundled)
├── package.json                             esbuild build config
└── uninstall.php                            Calls GrayFox_DB::drop_tables() on uninstall
```

### 2.2 PHP Class Descriptions

| File | Class | Responsibility |
|---|---|---|
| `grayfox.php` | — | Defines constants (`GRAYFOX_VERSION`, `GRAYFOX_PATH`, `GRAYFOX_URL`, `GRAYFOX_PLUGIN_FILE`), the two global encryption functions, `require_once` chain, activation/deactivation/uninstall hooks, and calls `GrayFox_Plugin::get_instance()`. |
| `class-grayfox-loader.php` | `GrayFox_Loader` | Collects `add_action` and `add_filter` calls into internal arrays, then applies them all to WordPress in a single `run()` call. |
| `class-grayfox-plugin.php` | `GrayFox_Plugin` | Singleton that constructs every component instance, calls each component's `register($loader)` method, and fires `$loader->run()`. |
| `class-grayfox-db.php` | `GrayFox_DB` | Creates all five custom tables via `dbDelta()` on activation; drops them on uninstall. Provides `get_table(string $name): string` to resolve prefixed table names. |
| `class-grayfox-settings.php` | `GrayFox_Settings` | Registers all `wp_options` keys, Settings API sections/fields, field renderers, sanitizers (including encryption), and the `grayfox_verify_key` AJAX handler. |
| `class-grayfox-license.php` | `GrayFox_License` | Schedules and executes daily license validation via Action Scheduler; caches the result in a transient; provides `get_tier()`, `get_features()`, `is_feature_enabled()`. |
| `class-grayfox-llm.php` | `GrayFox_LLM` | Opens raw HTTP streaming connections to LLM APIs via `fopen`/`fgets`; yields tokens as a PHP `Generator`; supports OpenAI, Anthropic, Gemini, Groq. |
| `class-grayfox-chat.php` | `GrayFox_Chat` | AJAX handler for `grayfox_chat` / `grayfox_chat_stream`; verifies nonce, manages conversation + message records, loads knowledge base, decrypts API key, drives the LLM generator, and emits SSE. |
| `class-grayfox-widget.php` | `GrayFox_Widget` | Enqueues `grayfox-chat.min.js` + `grayfox-chat.min.css` and `wp_localize_script` config on `wp_enqueue_scripts`; renders `templates/chat-widget.php` via `wp_footer`. |
| `class-grayfox-shortcode.php` | `GrayFox_Shortcode` | Registers `[grayfox_chat]` shortcode; enqueues assets on demand; renders `templates/chat-embed.php` into the page. |
| `class-grayfox-admin.php` | `GrayFox_Admin` | Registers top-level menu and four subpages; enqueues admin CSS; handles the `admin_post_grayfox_upload_document` form POST. |
| `class-grayfox-rag.php` | `GrayFox_RAG` | Processes WordPress attachment files into the knowledge base: extracts text (TXT/CSV/PDF/DOCX), calls the LLM for a structured JSON summary, upserts into `wp_grayfox_knowledge_base`; provides `get_consolidated_knowledge()` and `check_tier_limit()`. |

### 2.3 WordPress Hooks Registered by Each Class

All hooks are registered through `GrayFox_Loader` and fired when `$loader->run()` executes in `GrayFox_Plugin::__construct()`. The priority column shows the value passed to `add_action`/`add_filter`; default is 10.

#### `GrayFox_Settings`

| Type | Hook | Callback | Priority |
|---|---|---|---|
| action | `admin_init` | `GrayFox_Settings::register_settings` | 10 |
| action | `wp_ajax_grayfox_verify_key` | `GrayFox_Settings::ajax_verify_key` | 10 |

#### `GrayFox_License`

| Type | Hook | Callback | Priority |
|---|---|---|---|
| action | `init` | `GrayFox_License::schedule_validation` | 10 |
| action | `grayfox_validate_license` | `GrayFox_License::validate_license` | 10 |

The `grayfox_validate_license` hook is an Action Scheduler recurring action, not a native WordPress hook.

#### `GrayFox_Widget`

| Type | Hook | Callback | Priority |
|---|---|---|---|
| action | `wp_enqueue_scripts` | `GrayFox_Widget::enqueue_assets` | 10 |
| action | `wp_footer` | `GrayFox_Widget::render_floating_widget` | 10 |

#### `GrayFox_Shortcode`

| Type | Hook | Callback | Priority |
|---|---|---|---|
| action | `init` | `GrayFox_Shortcode::register_shortcode` | 10 |

`register_shortcode` calls `add_shortcode('grayfox_chat', ...)` internally.

#### `GrayFox_Admin`

| Type | Hook | Callback | Priority |
|---|---|---|---|
| action | `admin_menu` | `GrayFox_Admin::register_menus` | 10 |
| action | `admin_enqueue_scripts` | `GrayFox_Admin::enqueue_admin_assets` | 10 |
| action | `admin_post_grayfox_upload_document` | `GrayFox_Admin::handle_document_upload` | 10 |

#### `GrayFox_Chat`

| Type | Hook | Callback | Priority |
|---|---|---|---|
| action | `wp_ajax_grayfox_chat` | `GrayFox_Chat::handle_chat` | 10 |
| action | `wp_ajax_nopriv_grayfox_chat` | `GrayFox_Chat::handle_chat` | 10 |

Both authenticated and unauthenticated visitors reach the same handler. Visitor identity is tracked by the server-generated `session_id`, not by WordPress user authentication.

#### `GrayFox_RAG`

| Type | Hook | Callback | Priority | Args |
|---|---|---|---|---|
| action | `grayfox_process_document` | `GrayFox_RAG::process_document` | 10 | 1 |

`grayfox_process_document` is an Action Scheduler async action enqueued by `GrayFox_RAG::schedule_processing()`.

---

## 3. Database Schema

All tables are created on plugin activation via `GrayFox_DB::create_tables()` using WordPress's `dbDelta()`. All table names carry the WordPress `$wpdb->prefix` plus the `grayfox_` namespace (e.g., `wp_grayfox_conversations`). All tables are dropped on plugin uninstall via `GrayFox_DB::drop_tables()`. Deactivation does **not** drop tables.

The charset and collation for all tables follow `$wpdb->get_charset_collate()`.

### 3.1 `{prefix}grayfox_knowledge_base`

Stores one row per processed source document.

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NOT NULL | AUTO_INCREMENT | Primary key |
| `source_type` | `ENUM('upload','google_drive','google_doc','manual')` | NOT NULL | `'upload'` | Origin of the content |
| `source_id` | `VARCHAR(255)` | NULL | NULL | For `upload`: WP attachment post ID as string |
| `source_name` | `VARCHAR(255)` | NULL | NULL | Human-readable name (attachment title or filename) |
| `content_json` | `LONGTEXT` | NULL | NULL | LLM-produced structured JSON summary of the document |
| `token_estimate` | `INT` | — | `0` | Rough estimate: `ceil(strlen / 4)` |
| `last_processed_at` | `DATETIME` | NULL | NULL | Timestamp of most recent successful processing |
| `created_at` | `DATETIME` | NOT NULL | `CURRENT_TIMESTAMP` | Row creation timestamp |

**Indexes:** Primary key on `id`.

**Notes:** `source_type` values `google_drive`, `google_doc`, and `manual` are reserved for Phase 2 (Google integration, not yet implemented).

### 3.2 `{prefix}grayfox_conversations`

One row per visitor chat session.

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NOT NULL | AUTO_INCREMENT | Primary key |
| `session_id` | `VARCHAR(64)` | NOT NULL | — | UUID4 generated by `wp_generate_uuid4()` |
| `visitor_id` | `VARCHAR(64)` | NULL | NULL | Reserved; currently stored as empty string |
| `started_at` | `DATETIME` | NOT NULL | `CURRENT_TIMESTAMP` | First message time |
| `last_active_at` | `DATETIME` | NULL | NULL | Updated on every message |

**Indexes:** Primary key on `id`; key `idx_session_id` on `session_id`.

### 3.3 `{prefix}grayfox_messages`

Every individual message in every conversation.

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NOT NULL | AUTO_INCREMENT | Primary key |
| `conversation_id` | `BIGINT UNSIGNED` | NOT NULL | — | FK to `grayfox_conversations.id` (not enforced at DB level) |
| `role` | `ENUM('user','assistant','system')` | NOT NULL | `'user'` | Message author role |
| `content` | `TEXT` | NULL | NULL | Message body |
| `created_at` | `DATETIME` | NOT NULL | `CURRENT_TIMESTAMP` | Insert timestamp |

**Indexes:** Primary key on `id`; key `idx_conversation_id` on `conversation_id`.

**Notes:** The chat handler loads the last 10 messages for a conversation using `ORDER BY id DESC LIMIT 10`, then calls `array_reverse()` to restore chronological order before building LLM context. Ordering by `id` (not `created_at`) is intentional: `id` is a strictly monotonic auto-increment key that correctly sequences messages sent within the same second, whereas `DATETIME` precision is 1 second and would produce an ambiguous order for rapid consecutive sends.

### 3.4 `{prefix}grayfox_appointments`

Appointment bookings created through the chatbot (Phase 1 schema; UI not yet implemented).

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NOT NULL | AUTO_INCREMENT | Primary key |
| `customer_name` | `VARCHAR(255)` | NULL | NULL | |
| `customer_email` | `VARCHAR(255)` | NULL | NULL | |
| `service` | `VARCHAR(255)` | NULL | NULL | Service requested |
| `start_time` | `DATETIME` | NULL | NULL | |
| `end_time` | `DATETIME` | NULL | NULL | |
| `google_event_id` | `VARCHAR(255)` | NULL | NULL | Google Calendar event ID (Phase 2) |
| `status` | `ENUM('confirmed','cancelled','pending')` | NOT NULL | `'pending'` | |
| `notes` | `TEXT` | NULL | NULL | |
| `created_at` | `DATETIME` | NOT NULL | `CURRENT_TIMESTAMP` | |

**Indexes:** Primary key on `id`.

### 3.5 `{prefix}grayfox_google_tokens`

Stores encrypted OAuth 2.0 tokens for Google API integration (Phase 2, not yet implemented).

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | NOT NULL | AUTO_INCREMENT | Primary key |
| `scope_set` | `TEXT` | NULL | NULL | Scopes granted by the OAuth grant |
| `access_token_encrypted` | `TEXT` | NULL | NULL | AES-256-CBC encrypted access token |
| `refresh_token_encrypted` | `TEXT` | NULL | NULL | AES-256-CBC encrypted refresh token |
| `expires_at` | `DATETIME` | NULL | NULL | Access token expiry |
| `created_at` | `DATETIME` | NOT NULL | `CURRENT_TIMESTAMP` | |

**Indexes:** Primary key on `id`.

---

## 4. Encryption

### 4.1 Key Derivation

The encryption key is derived entirely from WordPress's own secret key constants, which are defined in `wp-config.php` and unique per installation. No separate key material is generated or stored.

```
grayfox_get_encryption_key() → hash('sha256', AUTH_KEY . SECURE_AUTH_KEY . LOGGED_IN_KEY)
```

The result is a 64-character lowercase hex string (256 bits). Because it is derived from WordPress constants, the key is stable across requests and requires no storage, but changes if the site owner rotates their WordPress secret keys.

### 4.2 Encrypt / Decrypt

**`grayfox_encrypt(string $plaintext): string`**

1. Derives the 256-bit key via `grayfox_get_encryption_key()`.
2. Generates a cryptographically random 16-byte IV using `random_bytes(16)`.
3. Encrypts with `openssl_encrypt($plaintext, 'AES-256-CBC', $key, 0, $iv)`.
4. Returns `base64_encode($iv . $ciphertext)` — the IV is prepended to the ciphertext so decryption can extract it without separate storage.

**`grayfox_decrypt(string $ciphertext): string`**

1. `base64_decode($ciphertext)`.
2. Validates that the decoded length is greater than 16 bytes (minimum: 16 IV bytes + at least 1 byte ciphertext).
3. Slices `$data[0..15]` as IV, `$data[16..]` as ciphertext.
4. Decrypts with `openssl_decrypt`. Returns empty string on failure.

Both functions are defined as global functions in `grayfox.php` and are available to all plugin classes without namespacing.

### 4.3 Which Options Are Encrypted

| `wp_options` key | Encrypted on | Decrypted in |
|---|---|---|
| `grayfox_license_key` | `GrayFox_Settings::sanitize_license_key()` on Settings API save | `GrayFox_License::validate_license()`, `GrayFox_Settings::ajax_verify_key()` |
| `grayfox_llm_api_key` | `GrayFox_Settings::sanitize_llm_api_key()` on Settings API save | `GrayFox_Chat::handle_chat()`, `GrayFox_RAG::summarize_with_llm()` |

In both cases the field renderer displays either 24 or 32 asterisks (`str_repeat('*', N)`) when a saved value exists, and re-saving an all-asterisk string is detected by a `preg_match('/^\*+$/', $input)` guard that preserves the existing encrypted value rather than encrypting the mask.

**The LLM API key is never injected into `GrayFoxConfig` / `wp_localize_script` output.** The `GrayFox_Widget::enqueue_assets()` source code contains a comment explicitly marking this constraint.

`GrayFox_Widget::enqueue_assets()` passes the following properties to the front-end via `wp_localize_script` as the global `GrayFoxConfig` object:

| Property | Type | Source | Description |
|---|---|---|---|
| `ajaxUrl` | `string` | `admin_url('admin-ajax.php')` | WordPress AJAX endpoint for both POST and GET requests |
| `nonce` | `string` | `wp_create_nonce('grayfox_chat')` | WordPress nonce verified by `check_ajax_referer` in `handle_chat()` (POST endpoint) |
| `streamNonce` | `string` | `wp_create_nonce('grayfox_chat_stream')` | WordPress nonce verified by `wp_verify_nonce` in `handle_stream()` (GET stream endpoint); passed as `&nonce=` query parameter in the `EventSource` URL |
| `sessionId` | `string` | `''` | Initial session ID (empty on first load; populated from `sessionStorage` by `ChatWindow.restoreSession()`) |
| `primaryColor` | `string` | `get_option('grayfox_widget_color', '#6366f1')` | CSS custom property `--grayfox-primary` applied at widget init |
| `title` | `string` | `get_option('grayfox_widget_name', 'Chat with us')` | Widget header title text |
| `welcomeMessage` | `string` | `get_option('grayfox_widget_welcome_message', '')` | First assistant message shown when the widget is opened with no prior messages |

---

## 5. Chat Flow (End-to-End)

### 5.1 Sequence

```
BROWSER                              WORDPRESS SERVER                    LLM PROVIDER
-------                              ----------------                    ------------
Visitor types message
  |
  | ── STEP 1 ──────────────────────────────────────────────────────────
  | FormData POST (admin-ajax.php)
  | action=grayfox_chat
  | nonce (grayfox_chat), message, session_id
  |------------------------------->
                                     GrayFox_Chat::handle_chat()
                                       1. check_ajax_referer('grayfox_chat')
                                       2. Sanitize message + session_id
                                       3. Generate session_id if missing
                                       4. Upsert conversations row
                                       5. INSERT user message into messages
                                       6. GrayFox_RAG::get_consolidated_knowledge()
                                          → SELECT all content_json from knowledge_base
                                       7. SELECT last 10 messages ORDER BY id DESC
                                          array_reverse() → chronological order
                                          array_pop() → remove just-inserted user msg
                                       8. GrayFox_LLM::build_messages()
                                          → system prompt + knowledge base + history + user msg
                                       9. wp_generate_password(32) → stream_token
                                      10. set_transient('grayfox_stream_{token}',
                                            {session_id, conversation_id, messages}, 60)
                                      11. wp_send_json_success({session_id, stream_token})
  |<---------- HTTP 200 JSON {success:true, data:{session_id, stream_token}} --|

  | ── STEP 2 ──────────────────────────────────────────────────────────
JS receives session_id + stream_token
JS opens EventSource (GET) to grayfox_chat_stream URL
  |
  | GET admin-ajax.php
  | action=grayfox_chat_stream
  | &nonce=<grayfox_chat_stream nonce>
  | &stream_token=<token>
  | &session_id=<uuid>
  |------------------------------->
                                     GrayFox_Chat::handle_stream()
                                       1. wp_verify_nonce($_GET['nonce'],
                                            'grayfox_chat_stream') ← FIRST action
                                       2. get_transient('grayfox_stream_{token}')
                                       3. delete_transient() immediately (single-use)
                                       4. Verify session_id matches transient data
                                       5. grayfox_decrypt(grayfox_llm_api_key)
                                       6. Set SSE headers:
                                          Content-Type: text/event-stream; charset=UTF-8
                                          Cache-Control: no-cache
                                          X-Accel-Buffering: no
                                          Connection: keep-alive
                                       7. GrayFox_LLM::send_message() → Generator
                                          fopen() HTTP stream to LLM endpoint
                                                                         POST /v1/chat/completions
                                                                         stream: true
                                                                         |<----- token ----
                                                                         |<----- token ----
                                          foreach generator as $token:
                                            echo SSE data event, flush()
  |<--- data: {"token":"Hello"} ------------------------------------|
  |<--- data: {"token":" there"} -----------------------------------|
  |<--- data: {"token":"!"} ----------------------------------------|
                                       8. INSERT full assistant response into messages
                                       9. echo 'data: {"done":true}'
  |<--- data: {"done":true} ----------------------------------------|
                                      10. wp_die()  ← normal completion path
                                          exit      ← error paths (prevents double output)

SSEClient receives done:true → calls self.close()
ChatWindow polls sseClient.closed === true
  → re-enables input
  → saves assistant message to sessionStorage
```

### 5.2 Step 1 — POST `grayfox_chat`

`GrayFox_Chat::handle_chat()` is the handler for `wp_ajax_grayfox_chat` and `wp_ajax_nopriv_grayfox_chat`. It performs the following in order:

1. Verifies nonce with `check_ajax_referer('grayfox_chat', 'nonce')`.
2. Sanitizes `$_POST['message']` and `$_POST['session_id']`.
3. Generates a UUID4 session ID via `wp_generate_uuid4()` if none is supplied.
4. Upserts the `grayfox_conversations` row (INSERT on first message; UPDATE `last_active_at` thereafter).
5. Saves the user message to `grayfox_messages`.
6. Loads the knowledge base via `GrayFox_RAG::get_consolidated_knowledge()`.
7. Fetches conversation history: `SELECT role, content … ORDER BY id DESC LIMIT 10`, reverses to chronological order, then removes the user message just inserted (via `array_pop`).
8. Builds the LLM messages array via `GrayFox_LLM::build_messages()`.
9. Generates a 32-character alphanumeric single-use stream token via `wp_generate_password(32, false)`.
10. Stores `{ session_id, conversation_id, messages }` in transient `grayfox_stream_{token}` with a 60-second TTL.
11. Returns `wp_send_json_success({ session_id, stream_token })`.

### 5.3 Step 2 — GET `grayfox_chat_stream`

`GrayFox_Chat::handle_stream()` is the handler for `wp_ajax_grayfox_chat_stream` and `wp_ajax_nopriv_grayfox_chat_stream`. It performs the following in order:

1. Verifies `$_GET['nonce']` against the distinct nonce action `grayfox_chat_stream` — this is the **first** action executed. Failure emits an SSE error event and `exit`s.
2. Reads `$_GET['stream_token']` and `$_GET['session_id']`.
3. Looks up transient `grayfox_stream_{token}`. Missing or expired token emits SSE error and `exit`s.
4. Calls `delete_transient()` immediately — the token is consumed before streaming begins (single-use guarantee).
5. Verifies the session ID in the request matches the session ID in the transient data. Mismatch emits SSE error and `exit`s.
6. Decrypts `grayfox_llm_api_key`. Missing key emits SSE error and `exit`s.
7. Sets SSE response headers: `Content-Type: text/event-stream; charset=UTF-8`, `Cache-Control: no-cache`, `X-Accel-Buffering: no`, `Connection: keep-alive`.
8. Iterates the `GrayFox_LLM::send_message()` generator, echoing each token as `data: {"token":"..."}` and flushing immediately.
9. On LLM `Throwable`, echoes `data: {"error":"LLM error occurred."}` and `exit`s.
10. Saves the complete accumulated response to `grayfox_messages` as role `assistant`.
11. Echoes `data: {"done":true}` and calls `wp_die()` to end the normal response path.

**Security note:** The stream token is single-use — the transient is deleted at step 4, before streaming begins. The nonce uses the distinct action string `grayfox_chat_stream`, separate from `grayfox_chat` used by the POST endpoint. All error paths use `exit` (not `wp_die`) to prevent double output.

### 5.4 SSE Event Types

| Event `data` value | Meaning |
|---|---|
| `{"token":"<text>"}` | One streamed token from the LLM |
| `{"error":"<message>"}` | Error condition (auth failure, expired token, LLM error) |
| `{"done":true}` | Stream complete; normal end of response |

### 5.5 Client-Side Session State

`src/store/session.js` persists the session data in `window.sessionStorage` under the key `grayfox_session` as a JSON object `{ sessionId: string, messages: [] }`. `sessionStorage` is intentionally used (not `localStorage`) so the session clears when the browser tab is closed. On page load, `ChatWindow.prototype.restoreSession()` reads this store and re-renders previous messages locally without making any server round-trip.

### 5.6 SSE Retry / Backoff

`SSEClient` implements reconnection logic:

- On `EventSource.onerror`, if at least one message was previously received, it retries up to `maxRetries = 3` times.
- Delay between retries uses exponential backoff: `2^retryCount * 1000ms` (2 s, 4 s, 8 s).
- If no message is received within 15 seconds of connecting (`connectTimer`), the connection is treated as a permanent failure.
- If the error occurs before any message is received, no retry is attempted.

---

## 6. LLM Client

### 6.1 Supported Providers

| Provider slug | Class method | API endpoint |
|---|---|---|
| `openai` | `stream_openai()` | `https://api.openai.com/v1/chat/completions` |
| `anthropic` | `stream_anthropic()` | `https://api.anthropic.com/v1/messages` |
| `gemini` | `stream_gemini()` | `https://generativelanguage.googleapis.com/v1beta/models/{model}:streamGenerateContent` |
| `groq` | `stream_groq()` | `https://api.groq.com/openai/v1/chat/completions` |

Groq is implemented by calling `stream_openai()` with the Groq endpoint URL — the APIs are compatible.

### 6.2 Provider Selection

The active provider is read from the `grayfox_llm_provider` wp_option at request time inside `GrayFox_Chat::handle_chat()`:

```php
$provider = get_option('grayfox_llm_provider', 'openai');
```

`GrayFox_LLM::send_message()` dispatches to the correct streaming method using a PHP `match` expression. An unknown provider falls back to `stream_openai()`.

The allowed values are enforced on save by `GrayFox_Settings::sanitize_llm_provider()`, which whitelists `['openai', 'anthropic', 'gemini', 'groq']` and defaults to `'openai'` for any other value.

### 6.3 API Key Handling

- The API key is stored in `wp_options` as `grayfox_llm_api_key` encrypted with AES-256-CBC (see Section 4).
- It is decrypted at request time by `GrayFox_Chat::handle_chat()`:
  ```php
  $api_key = grayfox_decrypt(get_option('grayfox_llm_api_key', ''));
  ```
- The plaintext key is passed to `GrayFox_LLM::send_message()` as an argument and lives only in PHP memory for the duration of the request.
- If decryption yields an empty string, `handle_chat()` calls `wp_die('LLM not configured.', '', ['response' => 503])` before any LLM call is made.
- The key is **never** written to any log, HTTP response body, or JavaScript-accessible variable.

### 6.4 Streaming Mechanism

All four providers use PHP's native `fopen()` with a custom stream context (`stream_context_create`) to open a persistent HTTP connection. This allows `fgets()` / `fread()` to consume the SSE or chunked response line-by-line as it arrives from the provider, without buffering the entire response.

- **OpenAI / Groq:** Each line is read with `fgets()`. Lines starting with `data: ` are parsed; `[DONE]` terminates the loop. Token is extracted from `choices[0].delta.content`.
- **Anthropic:** Same SSE line format. Token is extracted when `type === 'content_block_delta'` from `delta.text`. Loop terminates on `message_stop` event type.
- **Gemini:** Uses `fread($stream, 4096)` into a buffer. Regex `/"text"\s*:\s*"(...)"/u` extracts text parts from the partial JSON array response. Matched portions are cleared from the buffer to prevent re-yielding.

Each method is a PHP `Generator` (`yield $token`). The `foreach ($generator as $token)` loop in `GrayFox_Chat::handle_chat()` echoes each token as an SSE event and calls `flush()` immediately, pushing tokens to the browser as they arrive.

### 6.5 Message Construction

`GrayFox_LLM::build_messages()` assembles the messages array:

1. **System message:** Fixed instruction text + knowledge base JSON appended inline.
2. **History:** Up to 10 prior messages from the database (roles validated against `['user', 'assistant', 'system']`).
3. **Current user message:** Appended last as `role: user`.

The format is the OpenAI messages array format. Anthropic and Gemini streaming methods convert it to their native formats before sending (Anthropic extracts the system message to the top-level `system` field; Gemini maps roles to `user`/`model` and restructures `parts`).

---

## 7. RAG-Lite Processor

### 7.1 Document Upload Flow

1. Admin visits **GrayFox > Knowledge Base** and submits the upload form (`POST admin-post.php`, action `grayfox_upload_document`).
2. `GrayFox_Admin::handle_document_upload()` fires:
   - Verifies `manage_options` capability and `grayfox_upload_document` nonce.
   - Calls `GrayFox_RAG::check_tier_limit()`. If the knowledge base row count has reached the tier limit, redirects with `?error=tier_limit`.
   - Registers a temporary `upload_mimes` filter to allow PDF, DOCX, TXT, and CSV.
   - Passes the file to WordPress's `media_handle_upload('grayfox_document', 0)`, which stores it in the WP media library.
   - On success, calls `GrayFox_RAG::schedule_processing($attachment_id)` and redirects with `?uploaded=1`.

### 7.2 Background Processing via Action Scheduler

`GrayFox_RAG::schedule_processing(int $attachment_id)`:

- If Action Scheduler is available (`as_enqueue_async_action` exists), enqueues an **async** (single-run, no delay) action: hook `grayfox_process_document`, args `[$attachment_id]`, group `grayfox`.
- If Action Scheduler is **not** available, calls `process_document()` inline (synchronously, subject to PHP execution time limits).

`GrayFox_RAG::process_document(int $attachment_id)`:

1. Resolves the file path via `get_attached_file($attachment_id)`.
2. Checks `check_tier_limit()` again (guards against concurrent uploads that might exceed limits).
3. Detects extension and calls `extract_text()`.
4. Estimates token count as `ceil(mb_strlen($raw_text) / 4)`.
5. Calls `summarize_with_llm($raw_text)`.
6. If LLM returns valid JSON, stores it in `content_json`; otherwise stores `{"raw_text": "..."}` as fallback.
7. Upserts into `grayfox_knowledge_base` (UPDATE if `source_id` already exists, INSERT otherwise).

### 7.3 Text Extraction by File Type

| Extension | Method | Notes |
|---|---|---|
| `.txt`, `.csv` | `file_get_contents()` | Full file content read directly |
| `.pdf` | `extract_pdf_text()` | Regex on `BT...ET` markers for PDF text streams; falls back to printable ASCII extraction if no text found. Production use should replace with a proper parser library (e.g., `smalot/pdfparser`). |
| `.docx` | `extract_docx_text()` | Opens ZIP archive (DOCX is ZIP), reads `word/document.xml`, strips tags, decodes HTML entities. Requires PHP `ZipArchive` extension. |
| other | — | Returns empty string; no knowledge base row is created |

### 7.4 Summarization Prompt

The prompt sent to the LLM for document summarization is:

> "You are a knowledge base builder for a small business AI assistant. Produce a structured JSON summary of the following document that preserves ALL factual information: prices, services, policies, hours, contact info, procedures, FAQs, and any specific data. Do not omit or generalize specific facts. The summary will be used to answer customer questions accurately. Return only valid JSON."

This is sent as the `system` message. The extracted document text is sent as the `user` message. The LLM response is validated as JSON (`json_decode` + `json_last_error`). If validation fails, the method attempts to extract a JSON object with a `\{.+\}` regex before falling back to storing raw text.

### 7.5 Consolidated Knowledge Base for Chat

`GrayFox_RAG::get_consolidated_knowledge(): string` (called on every chat request):

- Queries all rows from `grayfox_knowledge_base` where `content_json IS NOT NULL AND content_json != ''`, ordered by `last_processed_at DESC`.
- Builds an array: `[{ "document": "<source_name>", "content": <decoded json or raw string> }, ...]`.
- Returns `wp_json_encode($knowledge)` as a single JSON string injected into the LLM system prompt.

All documents are concatenated into one context block. There is no vector similarity search or chunking — the full knowledge base is sent on every request. This is the "Lite" characteristic of RAG-Lite; suitability depends on the total token count of all documents versus the LLM's context window.

### 7.6 Document Limits by Tier

| License tier | Max knowledge base rows |
|---|---|
| `''` (no license / trial) | 5 |
| `starter` | 20 |
| `growth` | 100 |
| `pro` | unlimited (`PHP_INT_MAX`) |

The limit is enforced in `check_tier_limit()` by counting all rows in `grayfox_knowledge_base` and comparing to the `$tier_limits` map using `get_option('grayfox_license_tier', '')`.

---

## 8. License Validation

### 8.1 Storage

The license key is stored encrypted in `wp_options` under `grayfox_license_key` (see Section 4.3). The derived license state is stored in three separate unencrypted options after a successful validation:

| Option key | Content |
|---|---|
| `grayfox_license_tier` | Tier slug returned by platform: `starter`, `growth`, or `pro` |
| `grayfox_license_features` | JSON-encoded array of feature slug strings |
| `grayfox_license_valid_until` | ISO 8601 date string (if returned by platform) |

### 8.2 Daily Ping via Action Scheduler

`GrayFox_License::schedule_validation()` runs on the `init` hook every request:

- If Action Scheduler is not available, returns immediately.
- Checks `as_has_scheduled_action('grayfox_validate_license')`.
- If no action is scheduled, schedules a recurring action: first run in `DAY_IN_SECONDS` from now, interval `DAY_IN_SECONDS`, group `grayfox`.

`GrayFox_License::validate_license()` (the Action Scheduler callback):

1. Reads and decrypts `grayfox_license_key`.
2. POSTs to `{grayfox_platform_url}/v1/validate` with `{ license_key, domain }` (15-second timeout).
3. On HTTP 2xx with `body.valid === true`: updates the three options, caches result in transient `grayfox_license_status` for **23 hours**, returns `true`.
4. On network error (`is_wp_error`): does **not** overwrite the transient — the existing cached status is preserved. Returns `false`.
5. On non-2xx or `valid !== true`: calls `store_invalid()`, which caches `{ valid: false, tier: '', features: [] }` in the transient for **1 hour**.

### 8.3 Transient Cache

| Transient key | TTL | Shape |
|---|---|---|
| `grayfox_license_status` | 23 hours (valid) / 1 hour (invalid) | `{ valid: bool, tier: string, features: string[] }` |

### 8.4 Feature Gating

`GrayFox_License` exposes:

- `get_tier(): string` — reads `grayfox_license_tier` from `wp_options`.
- `get_features(): array` — JSON-decodes `grayfox_license_features` from `wp_options`.
- `is_feature_enabled(string $feature): bool` — checks if `$feature` is in the features array.

Feature gating at the RAG document limit level is implemented directly in `GrayFox_RAG::check_tier_limit()` using `get_option('grayfox_license_tier')` without instantiating `GrayFox_License`. Feature gating for admin page access is handled per-page by checking `current_user_can('manage_options')` (WordPress capability, not license tier).

---

## 9. Admin Dashboard

All admin pages require the `manage_options` WordPress capability. The top-level menu is registered at position 25 with the `dashicons-format-chat` icon.

### 9.1 Pages

| Slug | Menu label | Renderer | Template |
|---|---|---|---|
| `grayfox` | GrayFox / Overview | `GrayFox_Admin::render_overview()` | `templates/admin/overview.php` |
| `grayfox-settings` | Settings | `GrayFox_Admin::render_settings()` | `templates/admin/settings.php` |
| `grayfox-knowledge-base` | Knowledge Base | `GrayFox_Admin::render_knowledge_base()` | `templates/admin/knowledge-base.php` |
| `grayfox-conversations` | Conversations | `GrayFox_Admin::render_conversations()` | `templates/admin/conversations.php` |

#### Overview (`grayfox`)
Top-level menu entry and first submenu item point to the same callback. Intended to show plugin status, license information, and summary statistics. Rendered from `templates/admin/overview.php`.

#### Settings (`grayfox-settings`)
Rendered using the WordPress Settings API. Contains four sections:

| Section | Option keys registered |
|---|---|
| **License** | `grayfox_license_key`, `grayfox_license_tier` |
| **LLM Provider** | `grayfox_llm_provider`, `grayfox_llm_api_key`, `grayfox_llm_model`, `grayfox_platform_url` |
| **Widget Appearance** | `grayfox_widget_name`, `grayfox_widget_color`, `grayfox_widget_position`, `grayfox_widget_welcome_message` |
| **Behavior** | `grayfox_enable_widget` |

The License section includes an inline JavaScript **Verify Key** button that fires `wp_ajax_grayfox_verify_key` via `fetch()` to the same admin-ajax.php, using a `wp_create_nonce('grayfox_verify_key')` created server-side in the field renderer.

The LLM API Key field renders a `type="password"` input with an inline **Show/Hide** toggle button.

#### Knowledge Base (`grayfox-knowledge-base`)
Lists documents in `grayfox_knowledge_base`. Contains the document upload form that POSTs to `admin-post.php?action=grayfox_upload_document`. Error and success states are communicated via query-string parameters (`?error=no_file|upload_failed|tier_limit` or `?uploaded=1`).

#### Conversations (`grayfox-conversations`)
Lists rows from `grayfox_conversations` and their associated messages. Read-only view.

### 9.2 Feature Gating by Tier

| Feature | Gating mechanism |
|---|---|
| Uploading documents beyond 5 | `GrayFox_RAG::check_tier_limit()` — blocks at 5 without a license, 20 on `starter`, 100 on `growth` |
| All admin pages | `current_user_can('manage_options')` only — no tier requirement |
| Chat widget on front-end | `get_option('grayfox_enable_widget')` toggle only — no tier requirement |

No admin pages are hidden or disabled based on license tier in the current implementation. The tier only affects document upload limits.

### 9.3 Admin CSS

`grayfox-admin.min.css` is enqueued by `GrayFox_Admin::enqueue_admin_assets()` on any admin page whose hook name contains the string `'grayfox'` (checked with `strpos($hook, 'grayfox')`).

---

## 10. Build System

The build system uses [esbuild](https://esbuild.github.io/) version `0.24.0` (dev dependency only). There are no runtime JavaScript dependencies.

### 10.1 Commands

All commands are defined in `/Users/borisreinosa/Documents/grayfox-plugin/package.json`.

| npm script | esbuild command | Input | Output |
|---|---|---|---|
| `build` | `esbuild src/chat-widget.js --bundle --minify --outfile=assets/dist/grayfox-chat.min.js` | `src/chat-widget.js` (entry point) | `assets/dist/grayfox-chat.min.js` |
| `build:css` | `esbuild src/styles/widget.css --bundle --minify --loader=css --outfile=assets/dist/grayfox-chat.min.css` | `src/styles/widget.css` | `assets/dist/grayfox-chat.min.css` |
| `build:admin` | `esbuild src/styles/admin.css --bundle --minify --loader=css --outfile=assets/dist/grayfox-admin.min.css` | `src/styles/admin.css` | `assets/dist/grayfox-admin.min.css` |
| `build:all` | Runs `build`, `build:css`, `build:admin` in sequence | all sources | all three output files |
| `watch` | `esbuild src/chat-widget.js --bundle --watch --outfile=assets/dist/grayfox-chat.min.js` | `src/chat-widget.js` | `assets/dist/grayfox-chat.min.js` (live rebuild) |

### 10.2 Output Files

| File | Enqueued by | Handle |
|---|---|---|
| `assets/dist/grayfox-chat.min.js` | `GrayFox_Widget`, `GrayFox_Shortcode` | `grayfox-chat` |
| `assets/dist/grayfox-chat.min.css` | `GrayFox_Widget`, `GrayFox_Shortcode` | `grayfox-chat` |
| `assets/dist/grayfox-admin.min.css` | `GrayFox_Admin` | `grayfox-admin` |

The JS bundle uses CommonJS `require()` calls; esbuild resolves and inlines all modules (`ChatWindow`, `MessageList`, `MessageInput`, `TypingIndicator`, `api`, `SSEClient`, `session`) into the single output file. The output targets the browser's native `EventSource` and `fetch` APIs; no polyfills are included.

---

## 11. Installation & Configuration

### 11.1 Server Requirements

- WordPress 6.0 or higher
- PHP 8.1 or higher (uses `match` expressions, named arguments pattern, `str_starts_with`, union types)
- PHP `openssl` extension (AES-256-CBC encryption)
- PHP `ZipArchive` extension (DOCX parsing; optional but required for DOCX support)
- MySQL / MariaDB with `DATETIME`, `ENUM`, `LONGTEXT`, `BIGINT UNSIGNED` support

### 11.2 Activation Steps

When the plugin is activated via the WordPress Plugins screen, the activation hook fires:

1. `GrayFox_DB::create_tables()` is called; all five custom tables are created or updated via `dbDelta()`.
2. Default options are set if not already present:

| Option | Default |
|---|---|
| `grayfox_widget_name` | `'Chat with us'` |
| `grayfox_widget_color` | `'#6366f1'` |
| `grayfox_widget_position` | `'bottom-right'` |
| `grayfox_platform_url` | `'https://api.grayfox.io'` |
| `grayfox_enable_widget` | `true` |

### 11.3 Required Settings Before Chat Works

Navigate to **GrayFox > Settings** and complete the following before the chat widget will respond to visitors:

1. **License Key** — Enter the GrayFox license key and click **Verify Key** to validate. The key is stored encrypted.
2. **LLM Provider** — Select one of: OpenAI, Anthropic, Google Gemini, Groq.
3. **API Key** — Enter the API key for the selected provider. Stored encrypted; never sent to the browser.
4. **Model** — Enter the model identifier string for the selected provider (e.g., `gpt-4o-mini`, `claude-3-haiku-20240307`, `gemini-1.5-flash`, `llama-3.1-8b-instant`).

Without a valid API key and model, `GrayFox_Chat::handle_chat()` returns HTTP 503 and the widget displays the message: *"The AI assistant is not configured. Please contact the site administrator."*

### 11.4 Deploying the Widget

**Floating bubble (automatic):** The floating chat widget is shown on all front-end pages when `grayfox_enable_widget` is `true`. It is injected via `wp_footer` and rendered from `templates/chat-widget.php`.

**Inline embed (shortcode):** Place `[grayfox_chat]` in any post, page, or widget area. Optional attributes:

| Attribute | Default | Description |
|---|---|---|
| `title` | `grayfox_widget_name` option | Header title text |
| `color` | `grayfox_widget_color` option | Primary color hex value |

### 11.5 Document Upload

1. Navigate to **GrayFox > Knowledge Base**.
2. Use the upload form to submit a PDF, DOCX, TXT, or CSV file.
3. The file is stored in the WordPress media library.
4. An Action Scheduler async job (`grayfox_process_document`) is queued for background processing.
5. When the job runs, the document is extracted and summarized by the configured LLM into structured JSON stored in `grayfox_knowledge_base`.
6. All subsequent chat requests include the consolidated knowledge base in the LLM system prompt.

### 11.6 Deactivation

Deactivation cancels all Action Scheduler jobs for `grayfox_validate_license` and `grayfox_process_document`. Database tables and all stored options are **preserved**.

### 11.7 Uninstall

Uninstall (deleting the plugin from the WordPress admin) triggers `uninstall.php`, which calls `GrayFox_DB::drop_tables()`, permanently removing all five custom tables and their data. WordPress options written by the plugin are not automatically removed by the current uninstall logic (only tables are dropped).

### 11.8 Google Integration (Phase 2 — Not Yet Implemented)

The database schema includes the `grayfox_appointments` and `grayfox_google_tokens` tables. The `knowledge_base` table's `source_type` ENUM includes `google_drive` and `google_doc` values. These structures are in place to support a future Google Calendar (appointment booking) and Google Drive (document sync) integration. No PHP classes, admin pages, or OAuth flows implementing this integration exist in the current version.

---

*End of documentation.*
