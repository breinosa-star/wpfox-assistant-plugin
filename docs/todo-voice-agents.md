# Voice Agents — Implementation Checklist

Integration of OpenAI Realtime API as an optional voice channel inside the
GrayFox chat widget. Text chat is completely unaffected.

Architecture summary:
- Browser fetches an ephemeral token from WordPress (POST /voice/session)
- Browser establishes a WebRTC connection directly to OpenAI Realtime API
- Audio streams browser ↔ OpenAI — WordPress is NOT in the audio path
- When OpenAI triggers a function call (KB search, lead capture), the browser
  calls the relevant REST endpoint on WordPress, then feeds the result back
  into the Realtime session

---

## OpenAI Platform

- [ ] Confirm Realtime API is enabled on the OpenAI account
- [ ] Confirm the API key in use has access to `gpt-4o-realtime-preview`
- [ ] Review Realtime API pricing and document expected cost per minute
      (currently ~$0.06/min audio input, ~$0.24/min audio output)
- [ ] Test ephemeral token creation manually:
      POST https://api.openai.com/v1/realtime/sessions
      with the desired model, voice, and tool definitions
- [ ] Choose default voice (alloy / ash / ballad / coral / echo / sage / shimmer / verse)
- [ ] Verify function calling works in Realtime sessions (tools array in session config)

---

## WordPress Plugin — Settings

- [x] Add "Voice Agent" section to GrayFox > Settings
- [x] Add toggle: Enable voice agent (default: off)
- [x] Add dropdown: Voice selection (alloy, ash, ballad, coral, echo, sage, shimmer, verse)
- [x] Add number field: Max session duration in minutes (default: 5, max: 15)
      — passed to OpenAI session config to cap costs
- [x] Add note in settings UI: HTTPS is required for microphone access
- [x] Register and sanitize all new options via register_setting()
- [x] Add voice settings to the existing settings export/import if applicable — N/A, no export/import in plugin

---

## WordPress Plugin — Backend (PHP)

### New class: class-grayfox-voice.php

- [x] Create `GrayFox_Voice` class following existing class conventions
- [x] Register with `GrayFox_Plugin` and the loader (rest_api_init hook)

### POST /grayfox/v1/voice/session

- [x] Register route with nonce permission callback (same pattern as chat endpoint)
- [x] Check voice feature is enabled; return 403 if not
- [x] Fetch KB context via `GrayFox_RAG::get_consolidated_knowledge('')` to inject
      into the session system prompt at call start
- [x] Build system prompt: merge existing GrayFox persona prompt + KB context
- [x] Define tool schemas for OpenAI session:
      - `search_kb(query: string)` — returns KB snippets relevant to the query
      - `capture_lead(name: string, email: string, interest: string)` — saves lead
- [x] Call POST https://api.openai.com/v1/realtime/sessions via wp_remote_post()
- [x] Include: model, voice (from settings), instructions (system prompt), tools,
      input_audio_transcription, turn_detection
- [x] Return ephemeral client_secret.value to the browser (expires in 60s)
- [x] Rate-limit per IP (10 sessions/hour via transient)
- [x] Log session creation (IP, timestamp) via security log table

### POST /grayfox/v1/voice/kb

- [x] Register route with nonce permission callback
- [x] Accept: { query: string }
- [x] Call `GrayFox_RAG::get_consolidated_knowledge($query)` — reuse existing logic
- [x] Return JSON matching the existing /kb response shape so tooling is consistent
- [x] No dependency on grayfox_public_kb_api_enabled toggle
- [x] No additional rate limiting — session endpoint (10/hour) is the right control point

### POST /grayfox/v1/voice/lead

- [x] Register route with nonce permission callback
- [x] Accept: { name: string, email: string, interest: string, session_id: string }
- [x] Sanitize and validate email (is_email())
- [x] Fire `grayfox_lead_captured` action so existing webhooks/integrations still work
- [x] Return { success: true } or a WP_Error
- [x] conversation_id = 0 for voice sessions (no DB record yet — future work)

---

## WordPress Plugin — Frontend (JS)

### New module: src/services/voice-client.js

- [x] Check for WebRTC support (RTCPeerConnection); surface friendly error if missing
- [x] Check for microphone permission; handle denial gracefully
- [x] Fetch ephemeral token from POST /grayfox/v1/voice/session
- [x] Create RTCPeerConnection
- [x] Add local audio track (getUserMedia({ audio: true }))
- [x] Add remote audio track handler → pipe to an <audio> element for playback
- [x] Create data channel "oai-events" for sending/receiving JSON events
- [x] Create SDP offer, set local description
- [x] POST offer SDP to https://api.openai.com/v1/realtime?model=... with token as Bearer
- [x] Set remote description from OpenAI's SDP answer
- [x] Listen for `response.function_call_arguments.done` events on the data channel
- [x] Dispatch function calls:
      - `search_kb` → POST /grayfox/v1/voice/kb
      - `capture_lead` → POST /grayfox/v1/voice/lead
- [x] Send function call output back into the session via data channel
      (conversation.item.create + response.create)
- [x] Handle session end: close peer connection, release microphone

### Chat widget UI changes: src/components/ChatWindow.js

- [x] Conditionally render a mic button only when voice is enabled + HTTPS (template)
- [x] Mic button states: idle, connecting, active — CSS classes + icon swap
- [x] Show a visual indicator (grayfox-mic--speaking class) when the AI is speaking
- [x] Stop icon visible during active session (click to end call)
- [x] Disable text input while a voice session is active (one channel at a time)
- [x] Re-enable text input when the voice session ends

### Build pipeline

- [x] Add voice-client.js to the webpack/build entry if bundled, or enqueue separately
      — voice-client.js is require()d by ChatWindow.js → bundles automatically via esbuild
- [x] Conditionally enqueue voice JS only when voice is enabled in settings
      — mic button is PHP-gated (get_option + is_ssl()); JS is always bundled (negligible size)
- [x] Pass required localized vars to JS: nonce, voice session endpoint URL,
      voice tool endpoint URLs, voice_enabled flag
      — restUrl, restNonce, voiceEnabled added to GrayFoxConfig in widget + shortcode

---

## Security

- [x] All three new REST endpoints protected by nonce (wp_create_nonce / check_ajax_referer pattern)
- [x] Rate-limit POST /voice/session — this is the expensive call (triggers an OpenAI session)
- [x] Validate HTTPS in session endpoint; return a clear error if the site is not on HTTPS
- [x] Ephemeral token is never logged or stored server-side beyond the immediate response
- [x] Lead endpoint: sanitize all fields, validate email format before DB write

---

## Testing

- [ ] Manual: open voice widget, speak a question about a KB topic, verify correct answer
- [ ] Manual: speak name + email, verify lead appears in GrayFox > Conversations
- [ ] Manual: trigger a KB function call mid-conversation, verify WordPress is hit
- [ ] Manual: deny microphone permission, verify graceful error message
- [ ] Manual: load on HTTP (not HTTPS), verify the mic button is hidden or disabled
- [ ] Manual: disable voice in settings, verify mic button does not appear
- [x] E2E: voice endpoint tests (VC-01 – VC-14) in tests/e2e/voice.spec.js
      — auth guard (401), feature guard (403), HTTPS guard (400), KB shape,
        lead validation, widget UI visibility on HTTP / voice-disabled
- [ ] Cross-browser: Chrome, Firefox, Safari (Safari WebRTC quirks)
- [ ] Mobile: test on iOS Safari and Android Chrome (getUserMedia behaviour differs)

---

## Documentation

- [ ] Update readme.txt: mention voice agent as a feature, note OpenAI-only + HTTPS requirement
- [ ] Update developer-hooks.md if new actions/filters are added
- [ ] Add inline cost warning in settings: "Each voice session uses OpenAI Realtime API
      which is billed separately from text usage."

---

## Out of scope for this branch

- Voice support for Anthropic / Groq / Gemini (no equivalent API)
- Phone / SIP integration
- Voice transcription storage (conversations log is text-only for now)
- Speaker identification / multi-user voice sessions
