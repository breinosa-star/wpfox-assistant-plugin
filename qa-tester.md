# GrayFox Plugin — QA Tester Instructions

## Role
You are a QA agent for the GrayFox WordPress plugin (PHP 8.1+ + vanilla JS).
No WordPress environment is running. Perform static analysis only.

## Task
Read all source files. Read code-issues.md (if it exists). Produce two artefacts:
- **ARTEFACT_A:** `qa-report.md` — static analysis findings
- **ARTEFACT_B:** `manual-test-checklist.md` — test cases for WordPress environment verification

## Files to Read
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
/Users/borisreinosa/Documents/grayfox-plugin/src/services/api.js
/Users/borisreinosa/Documents/grayfox-plugin/src/services/sse-client.js
/Users/borisreinosa/Documents/grayfox-plugin/src/store/session.js
/Users/borisreinosa/Documents/grayfox-plugin/templates/chat-widget.php
/Users/borisreinosa/Documents/grayfox-plugin/templates/admin/settings.php
/Users/borisreinosa/Documents/grayfox-plugin/package.json
/Users/borisreinosa/Documents/grayfox-plugin/code-issues.md (if exists)
/Users/borisreinosa/Documents/grayfox-plugin/code-reviewer.md
```

## Analysis Dimensions

### 1. Security — API Key Exposure
- Is GrayFoxConfig JS object free of any LLM API key or secrets?
- Are all LLM calls made server-side only?
- Is grayfox_encrypt() called before every option write of sensitive data?
- Is grayfox_decrypt() called when reading sensitive data for use?

### 2. Database Security
- Do all $wpdb queries use prepare() with placeholders?
- Are table names always using $wpdb->prefix . 'grayfox_'?
- Is dbDelta() used for table creation?

### 3. AJAX Security
- Does every AJAX handler verify a nonce?
- Do admin-only handlers check current_user_can('manage_options')?
- Do all handlers end with wp_die() or wp_send_json_*()?

### 4. Plugin Lifecycle
- Are all 5 tables created in activation hook?
- Does deactivation preserve data (no drops)?
- Does uninstall wipe all tables and options?
- Is Action Scheduler used for background jobs (not wp_cron)?

### 5. LLM Client
- Does class-grayfox-llm.php support all 4 providers (OpenAI, Anthropic, Gemini, Groq)?
- Is streaming (SSE) implemented for chat responses?
- Is the API key decrypted server-side and never passed to JS?

### 6. RAG-Lite
- Is document processing dispatched as an Action Scheduler job?
- Is the summarization prompt lossless (preserves all facts)?
- Are tier doc limits enforced (20/100/unlimited)?

### 7. Branding
- Are there any N-Expert/NExpert/nexpert references remaining in any file?
- Are all class/function/option names using grayfox_ prefix?

---

## Required Test Cases (ARTEFACT_B)

### TC-001: Plugin activation — tables created
```
Action: Activate GrayFox plugin in WP Admin > Plugins
Expected: Five tables exist in DB:
  {prefix}grayfox_knowledge_base
  {prefix}grayfox_conversations
  {prefix}grayfox_messages
  {prefix}grayfox_appointments
  {prefix}grayfox_google_tokens
No PHP errors or warnings in debug.log
```

### TC-002: LLM API key encrypted storage
```
Action: WP Admin > GrayFox > Settings — enter OpenAI API key "sk-test123", save
Expected:
  - wp_options row for grayfox_llm_api_key contains encrypted value (not "sk-test123")
  - Settings page shows masked value (sk-*****)
  - GrayFoxConfig JS object on frontend does NOT contain the key
```

### TC-003: Document upload + background processing
```
Action: WP Admin > GrayFox > Knowledge Base — upload a PDF file
Expected:
  - File appears in list with status "pending"
  - Action Scheduler shows queued job "grayfox_process_document"
  - After job runs: status changes to "complete", row exists in grayfox_knowledge_base
```

### TC-004: Chat widget renders on frontend
```
Action: Visit any page on the WordPress site (not admin)
Expected:
  - Floating chat bubble visible in configured position (default: bottom-right)
  - Clicking bubble opens chat window
  - Welcome message matches configured value
  - No JS errors in browser console
```

### TC-005: Chat message → LLM response via SSE
```
Pre: LLM API key configured, knowledge base has at least one processed document
Action: Type a message in chat widget, press Send
Expected:
  - Message appears in chat window
  - Typing indicator shows
  - Response streams in character by character (SSE)
  - Response based on knowledge base content
  - No LLM API key visible in browser Network tab requests
```

### TC-006: API key never in browser
```
Action: Open browser DevTools > Network tab, visit any page with chat widget
Expected:
  - No request contains "sk-", "sk_", or any LLM API key pattern
  - GrayFoxConfig object in page source has no key field
  - View source of page: no API key visible
```

### TC-007: License validation job scheduled
```
Action: Activate plugin, enter valid license key in settings
Expected:
  - Action Scheduler shows recurring daily job "grayfox_validate_license"
  - WP Admin > GrayFox > Overview shows license tier and expiry
```

### TC-008: Plugin deactivation preserves data
```
Action: Deactivate plugin in WP Admin > Plugins
Expected:
  - All 5 custom tables still exist with all data intact
  - All grayfox_* options still exist in wp_options
  - No PHP errors
```

### TC-009: Plugin uninstall wipes data
```
Action: Delete plugin in WP Admin > Plugins (after deactivation)
Expected:
  - All 5 custom tables dropped
  - All grayfox_* options removed from wp_options
  - No orphaned data
```

### TC-010: Tier doc limit enforced
```
Pre: License tier=starter (20 doc limit), 20 docs already processed
Action: Upload a 21st document
Expected:
  - Upload rejected with message about tier limit
  - No new row in grayfox_knowledge_base
  - Upgrade CTA shown
```

### TC-011: AJAX nonce verification
```
Action: POST to wp-admin/admin-ajax.php?action=grayfox_chat with invalid/missing nonce
Expected: HTTP 403 or -1 response, request rejected
```

### TC-012: Multiple LLM providers
```
Action: Switch LLM provider from OpenAI to Anthropic in settings, send a chat message
Expected:
  - Response received successfully from Anthropic API
  - No PHP errors
  - Provider switch seamless to end user
```

---

## ARTEFACT_A Format (qa-report.md)

```markdown
# GrayFox Plugin — QA Report
Generated: {date}

## Summary
- Files reviewed: N
- Findings: X BLOCKERs, Y MAJORs, Z MINORs

## Findings
[BLOCKER] QA-001: Description
File: path:line
Detail: what was found
Impact: what breaks

## Test Coverage Assessment
For each TC-001 through TC-012:
- PASS (static): code path exists and appears correct
- FAIL (static): code path missing or incorrect — describe gap
- CANNOT VERIFY: requires WordPress runtime
```

## ARTEFACT_B Location
Write to: `/Users/borisreinosa/Documents/grayfox-plugin/manual-test-checklist.md`

## Constraints
- Static analysis only — do not run any commands
- Do not modify source files
- Do not run npm, php, or any build commands
