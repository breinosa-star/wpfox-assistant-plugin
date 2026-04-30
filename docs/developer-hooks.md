# KBFox Developer Hooks

Reference for plugin developers building on top of KBFox — including other Gray Fox plugins (site builder, theme builder, etc.).

---

## Quick reference

| Hook | Type | File | When it fires |
|---|---|---|---|
| `grayfox_conversation_started` | action | class-grayfox-chat.php | New visitor conversation row created |
| `grayfox_chat_messages` | filter | class-grayfox-chat.php | Before message array enters the LLM agentic loop |
| `grayfox_chat_response` | action | class-grayfox-chat.php | After LLM response saved and streamed to visitor |
| `grayfox_document_processed` | action | class-grayfox-rag.php | KB document successfully summarized and set active |
| `grayfox_knowledge_context` | filter | class-grayfox-rag.php | Before consolidated KB JSON is returned to any consumer |
| `grayfox_register_tools` | action | class-grayfox-tools.php | After built-in tools are registered; add custom LLM tools here |
| `grayfox_lead_captured` | action | class-grayfox-tools.php | Visitor submits name/email via chat capture tool |

---

## Accessing the knowledge base directly

Before reaching for hooks, note that the KB can be queried directly from PHP without a hook:

```php
if ( class_exists( 'GrayFox_RAG' ) ) {
    $kb_json = GrayFox_RAG::get_consolidated_knowledge( 'company services pricing' );
}
```

Pass a query string to get only relevant documents. Pass an empty string to get everything (up to the 80k character cap). Returns a JSON-encoded array of active KB document objects.

**Use this when:** a plugin needs to pull KB content at a specific moment (page build, theme generation, export) rather than reacting to an event.

---

## Hook details

### `grayfox_conversation_started`

Fires when a new visitor conversation row is inserted into the database. Does not fire on returning visitors resuming an existing session.

```php
add_action( 'grayfox_conversation_started', function( int $conversation_id, string $session_id ) {
    // e.g. log to external CRM, initialize session state
}, 10, 2 );
```

**Use when:** you need to react the moment a new chat session begins — before any messages are exchanged.

---

### `grayfox_chat_messages`

Filters the ordered message array sent to the LLM before every turn of the agentic loop. Each element has `role` (system|user|assistant) and `content` (string).

```php
add_filter( 'grayfox_chat_messages', function( array $messages, int $conversation_id ): array {
    // Inject an extra system instruction
    $messages[] = [
        'role'    => 'system',
        'content' => 'The user is a premium subscriber. Offer detailed answers.',
    ];
    return $messages;
}, 10, 2 );
```

**Use when:** you need to inject context, instructions, or persona into every conversation turn — for example, membership tier, locale, or custom persona overrides.

**Note:** this runs on every message in a conversation, not just the first. Keep callbacks lightweight.

---

### `grayfox_chat_response`

Fires after the full LLM response has been saved to `wp_grayfox_messages` and streamed to the visitor. The SSE connection is still open at this point but the done event has not been sent yet.

```php
add_action( 'grayfox_chat_response', function( int $conversation_id, string $full_response ) {
    // e.g. send to analytics, trigger a workflow, post to Slack
}, 10, 2 );
```

**Use when:** you need the final assistant message for logging, analytics, or triggering downstream actions.

---

### `grayfox_document_processed`

Fires when a KB document is successfully summarized by the LLM and set to `active` status. Fires from two code paths — uploaded attachments and external sources (Google Drive, Google Doc, manual) — so the first argument type differs.

```php
add_action( 'grayfox_document_processed', function( $source_id, array $content_json, string $source_name = '' ) {
    // $source_id is an int (attachment ID) for uploads
    // $source_id is a string (e.g. Drive file ID) for external sources
    // $content_json is the structured LLM summary
}, 10, 3 );
```

**Use when:** another plugin needs to react to KB content changing — re-indexing, cache invalidation, triggering a site rebuild, etc.

---

### `grayfox_knowledge_context`

Filters the consolidated KB JSON before it is returned to any consumer. This is the single choke point through which all KB content passes — chatbot, direct PHP calls, and any future consumers all go through here.

```php
add_filter( 'grayfox_knowledge_context', function( string $knowledge_json, string $query ): string {
    $knowledge = json_decode( $knowledge_json, true );

    // Append a synthetic document
    $knowledge[] = [
        'source_name' => 'Live Pricing',
        'content'     => my_plugin_get_live_pricing(),
    ];

    return wp_json_encode( $knowledge );
}, 10, 2 );
```

**Use when:** a plugin needs to augment the KB with live data (pricing, availability, CRM fields) without uploading a static document, or needs to strip/reorder KB content for a specific context.

---

### `grayfox_register_tools`

Fires after the two built-in LLM tools (`search_knowledge_base`, `capture_customer_email`) are registered. Use it to add custom tools the LLM can call during the agentic loop.

Each tool must extend `GrayFox_Tool` and implement `get_name()`, `get_definition()`, and `execute()`.

```php
add_action( 'grayfox_register_tools', function( string $tools_class ) {

    class My_Book_Appointment_Tool extends GrayFox_Tool {

        public function get_name(): string {
            return 'book_appointment';
        }

        public function get_definition(): array {
            return [
                'name'        => 'book_appointment',
                'description' => 'Books a consultation appointment for the visitor.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'date'  => [ 'type' => 'string', 'description' => 'Preferred date (YYYY-MM-DD).' ],
                        'email' => [ 'type' => 'string', 'description' => 'Visitor email address.' ],
                    ],
                    'required' => [ 'date', 'email' ],
                ],
            ];
        }

        public function execute( array $args ): string {
            // Call your booking API here
            return 'Appointment booked for ' . $args['date'];
        }
    }

    $tools_class::register( new My_Book_Appointment_Tool() );
}, 10, 1 );
```

**Use when:** you want the chatbot to take actions beyond answering questions — booking, lookups, form submissions, CRM writes, etc. The LLM decides when to call the tool based on its description.

---

### `grayfox_lead_captured`

Fires when a visitor submits their name and email via the `capture_customer_email` built-in tool during a chat.

```php
add_action( 'grayfox_lead_captured', function( string $email, string $name, string $interest, int $conversation_id ) {
    // e.g. subscribe to mailing list, push to CRM, notify sales team
}, 10, 4 );
```

**Use when:** you need to act on a captured lead — sync to Mailchimp, HubSpot, send a notification, etc. `$name` and `$interest` may be empty strings if the visitor did not provide them.

---

## Checking if KBFox is active

Always guard your integration code so it degrades gracefully if KBFox is not installed:

```php
// For direct class calls
if ( class_exists( 'GrayFox_RAG' ) ) {
    $kb = GrayFox_RAG::get_consolidated_knowledge( $query );
}

// For hooks — hooks only fire if the plugin is active, so no guard needed,
// but you may want to register conditionally to avoid loading your class:
add_action( 'plugins_loaded', function() {
    if ( ! class_exists( 'GrayFox_Tools' ) ) {
        return;
    }
    add_action( 'grayfox_register_tools', 'my_plugin_register_tools', 10, 1 );
} );
```
