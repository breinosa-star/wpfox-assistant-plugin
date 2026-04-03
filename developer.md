# GrayFox Plugin — Developer Instructions

## Objective
Build the GrayFox WordPress plugin: a PHP 8.1+ plugin delivering AI chatbot,
RAG-Lite knowledge base, appointment booking, and Google Workspace integration
to small businesses. All data lives on the client's own WordPress server.

## Constraints
- PHP 8.1+ only. No deprecated WordPress functions (no extract(), no mysql_*).
- All DB operations via $wpdb with prepared statements — ZERO raw string interpolation in SQL.
- All sensitive values (LLM API key, Google tokens) encrypted before DB write via grayfox_encrypt().
- Encryption key derived from WordPress AUTH_KEY + SECURE_AUTH_KEY — never hardcoded.
- No LLM API key ever sent to browser — all LLM calls are server-side PHP only.
- GrayFoxConfig JS object injected via wp_localize_script must NEVER contain the LLM API key.
- Background jobs use Action Scheduler ONLY — never register wp_cron for long-running tasks.
- All AJAX handlers must: verify nonce AND check current_user_can('manage_options').
- CSS/JS built via esbuild — no webpack, no Vite, no Parcel.
- Chat widget JS is vanilla JS only — no React, no Vue, no jQuery dependency.
- SSE endpoint: LLM API key used server-side only. Browser sends session token, never key.
- Plugin slug: grayfox
- PHP class prefix: GrayFox_ (e.g. GrayFox_Plugin, GrayFox_DB)
- JS global config object: GrayFoxConfig
- WordPress option prefix: grayfox_ (e.g. grayfox_llm_key)
- DB table prefix: grayfox_ appended after $wpdb->prefix (e.g. {$wpdb->prefix}grayfox_conversations)
- PHP constants prefix: GRAYFOX_ (e.g. GRAYFOX_VERSION, GRAYFOX_PATH)
- No N-Expert, NExpert, nexpert, or N-Expert.ai references anywhere

## DB Tables (created on plugin activation)
```sql
{prefix}grayfox_knowledge_base:
  id, source_type ENUM('upload','google_drive','google_doc','manual'),
  source_id VARCHAR(255), source_name VARCHAR(255),
  content_json LONGTEXT, token_estimate INT,
  last_processed_at DATETIME, created_at DATETIME

{prefix}grayfox_conversations:
  id, session_id VARCHAR(64), visitor_id VARCHAR(64),
  started_at DATETIME, last_active_at DATETIME

{prefix}grayfox_messages:
  id, conversation_id BIGINT, role ENUM('user','assistant','system'),
  content TEXT, created_at DATETIME

{prefix}grayfox_appointments:
  id, customer_name VARCHAR(255), customer_email VARCHAR(255),
  service VARCHAR(255), start_time DATETIME, end_time DATETIME,
  google_event_id VARCHAR(255), status ENUM('confirmed','cancelled','pending'),
  notes TEXT, created_at DATETIME

{prefix}grayfox_google_tokens:
  id, scope_set TEXT, access_token_encrypted TEXT,
  refresh_token_encrypted TEXT, expires_at DATETIME, created_at DATETIME
```

## WordPress Options (wp_options)
All stored under grayfox_ prefix:
- grayfox_license_key (encrypted)
- grayfox_license_tier (starter/growth/pro)
- grayfox_license_valid_until
- grayfox_license_features (JSON array)
- grayfox_llm_provider (openai/anthropic/gemini/groq)
- grayfox_llm_api_key (encrypted)
- grayfox_llm_model
- grayfox_widget_name
- grayfox_widget_color
- grayfox_widget_position
- grayfox_widget_welcome_message
- grayfox_platform_url

## LLM Providers to Support
- OpenAI: POST https://api.openai.com/v1/chat/completions (stream: true)
- Anthropic: POST https://api.anthropic.com/v1/messages (stream: true)
- Google Gemini: POST https://generativelanguage.googleapis.com/v1beta/models/{model}:streamGenerateContent
- Groq: POST https://api.groq.com/openai/v1/chat/completions (stream: true, OpenAI-compatible)

## Encryption Functions (global helpers in grayfox.php or a helpers file)
```php
function grayfox_get_encryption_key(): string {
    return hash('sha256', AUTH_KEY . SECURE_AUTH_KEY . LOGGED_IN_KEY);
}
function grayfox_encrypt(string $plaintext): string {
    $key = grayfox_get_encryption_key();
    $iv  = random_bytes(16);
    $enc = openssl_encrypt($plaintext, 'AES-256-CBC', $key, 0, $iv);
    return base64_encode($iv . $enc);
}
function grayfox_decrypt(string $ciphertext): string {
    $key  = grayfox_get_encryption_key();
    $data = base64_decode($ciphertext);
    $iv   = substr($data, 0, 16);
    $enc  = substr($data, 16);
    return openssl_decrypt($enc, 'AES-256-CBC', $key, 0, $iv);
}
```

## Success Criteria
- Plugin activates without PHP errors on WordPress 6.x + PHP 8.1
- All 5 custom DB tables created on activation
- Plugin deactivation preserves all data; uninstall wipes all data + options
- Chat widget renders on frontend (floating bubble)
- Visitor message → server-side LLM call → SSE stream back to widget
- LLM API key never appears in page source, JS globals, or network requests
- RAG-Lite: uploaded PDF/DOCX/TXT processed into knowledge_base table via background job
- Admin dashboard accessible at WP Admin > GrayFox
- License validation job scheduled daily via Action Scheduler

## Exclusions (Phase 1 only)
- No Google integration (Phase 2)
- No Elementor widget (Phase 2+)
- No appointment booking UI (Phase 2)
- No multi-location, no white-label
- Do NOT modify any files under /N-expert-ai/ directories

## File Locations to Write (Phase 1)
```
/Users/borisreinosa/Documents/grayfox-plugin/grayfox.php
/Users/borisreinosa/Documents/grayfox-plugin/includes/class-grayfox-loader.php
/Users/borisreinosa/Documents/grayfox-plugin/includes/class-grayfox-plugin.php
/Users/borisreinosa/Documents/grayfox-plugin/includes/class-grayfox-db.php
/Users/borisreinosa/Documents/grayfox-plugin/includes/class-grayfox-settings.php
/Users/borisreinosa/Documents/grayfox-plugin/includes/class-grayfox-license.php
/Users/borisreinosa/Documents/grayfox-plugin/includes/class-grayfox-llm.php
/Users/borisreinosa/Documents/grayfox-plugin/includes/class-grayfox-chat.php
/Users/borisreinosa/Documents/grayfox-plugin/includes/class-grayfox-widget.php
/Users/borisreinosa/Documents/grayfox-plugin/includes/class-grayfox-shortcode.php
/Users/borisreinosa/Documents/grayfox-plugin/includes/class-grayfox-admin.php
/Users/borisreinosa/Documents/grayfox-plugin/includes/class-grayfox-rag.php
/Users/borisreinosa/Documents/grayfox-plugin/src/chat-widget.js
/Users/borisreinosa/Documents/grayfox-plugin/src/components/ChatWindow.js
/Users/borisreinosa/Documents/grayfox-plugin/src/components/MessageInput.js
/Users/borisreinosa/Documents/grayfox-plugin/src/components/MessageList.js
/Users/borisreinosa/Documents/grayfox-plugin/src/components/TypingIndicator.js
/Users/borisreinosa/Documents/grayfox-plugin/src/services/api.js
/Users/borisreinosa/Documents/grayfox-plugin/src/services/sse-client.js
/Users/borisreinosa/Documents/grayfox-plugin/src/store/session.js
/Users/borisreinosa/Documents/grayfox-plugin/src/styles/widget.css
/Users/borisreinosa/Documents/grayfox-plugin/src/styles/admin.css
/Users/borisreinosa/Documents/grayfox-plugin/templates/chat-widget.php
/Users/borisreinosa/Documents/grayfox-plugin/templates/chat-embed.php
/Users/borisreinosa/Documents/grayfox-plugin/templates/admin/overview.php
/Users/borisreinosa/Documents/grayfox-plugin/templates/admin/settings.php
/Users/borisreinosa/Documents/grayfox-plugin/templates/admin/knowledge-base.php
/Users/borisreinosa/Documents/grayfox-plugin/templates/admin/conversations.php
/Users/borisreinosa/Documents/grayfox-plugin/package.json
```

## Reference Patterns (read + adapt — do not copy verbatim, rename everything)
- /Users/borisreinosa/Documents/N-expert-ai/nexpert-chat/includes/class-nexpert-loader.php
- /Users/borisreinosa/Documents/N-expert-ai/nexpert-chat/includes/class-nexpert-plugin.php
- /Users/borisreinosa/Documents/N-expert-ai/nexpert-chat/includes/class-nexpert-settings.php
- /Users/borisreinosa/Documents/N-expert-ai/nexpert-chat/includes/class-nexpert-widget.php
- /Users/borisreinosa/Documents/N-expert-ai/nexpert-chat/src/chat-widget.js
- /Users/borisreinosa/Documents/N-expert-ai/nexpert-chat/src/components/
- /Users/borisreinosa/Documents/N-expert-ai/nexpert-chat/src/services/sse-client.js
- /Users/borisreinosa/Documents/N-expert-ai/nexpert-chat/src/store/session.js
- /Users/borisreinosa/Documents/N-expert-ai/nexpert-chat/templates/chat-widget.php
- /Users/borisreinosa/Documents/N-expert-ai/nexpert-chat/package.json

## Workflow Steps
1. Read this file completely before writing any code.
2. Read all reference pattern files listed above.
3. Read /Users/borisreinosa/Documents/N-expert-ai/Plugin-as-a-Platform.md for full component specs.
4. Read the specific component brief in your task prompt.
5. Check files already written in /grayfox-plugin/ before writing to avoid duplication.
6. Write to exact file paths listed above.
7. Self-review before finishing: all constraints met? No N-Expert references? No LLM key in JS?
8. Do not commit. Do not run esbuild/npm unless explicitly asked.
