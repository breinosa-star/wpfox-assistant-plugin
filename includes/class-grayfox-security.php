<?php
/**
 * Security — input inspection, strike tracking, IP blocking, threat logging.
 *
 * Flow per message:
 *   1. Check if session/IP is already blocked → refuse immediately.
 *   2. Inspect message for injection patterns (regex). Length abuse adds 1 strike;
 *      injection patterns add MAX_STRIKES (immediate block).
 *   3. Scrub ABUSE_PATTERNS from message before passing to LLM — no strike, silent.
 *   4. LLM classifier runs on scrubbed message:
 *      - 'injection' → adds 1 strike, visible warning.
 *      - 'offtopic'  → internal counter only, no user-visible warning.
 *        At 7+ offtopic warnings: assistant receives a silent wrap-up instruction.
 *   5. Apply progressive penalty for strikes:
 *      - 1 strike : warn + throttle 2 s
 *      - 2 strikes: warn + throttle 8 s
 *      - 3+ strikes: block session & IP, log threat, disconnect
 *
 * @package GrayFox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GrayFox_Security
 */
if ( ! class_exists( 'GrayFox_Security' ) ) {
class GrayFox_Security {

	// -------------------------------------------------------------------------
	// Constants
	// -------------------------------------------------------------------------

	const MAX_STRIKES              = 3;
	const STRIKE_TTL               = 3600;  // seconds — rolling 1-hour window
	const MAX_MESSAGE_LENGTH       = 1000;  // characters
	const OFFTOPIC_WARNING_MAX     = 7;     // silent internal threshold before wrap-up injection
	const OFFTOPIC_WARNING_TTL     = 3600;  // seconds — same window as strikes

	/** Throttle delay in seconds indexed by strike count (1 or 2). */
	const THROTTLE_DELAYS = array( 1 => 2, 2 => 8 );

	// -------------------------------------------------------------------------
	// Patterns
	// -------------------------------------------------------------------------

	/**
	 * High-severity: prompt injection / jailbreak attempts.
	 * Any match adds MAX_STRIKES (immediate block).
	 */
	const INJECTION_PATTERNS = array(
		'/ignore\s+(all\s+)?(previous|prior|above)\s+instructions?/i',
		'/forget\s+(?:your|all)\s+(?:previous\s+)?(?:instructions?|rules?|guidelines?|training)/i',
		'/you\s+are\s+now\s+(?:a\s+)?(?:DAN|jailbreak|unrestricted|evil|different\s+AI)/i',
		'/act\s+as\s+(?:if\s+you\s+(?:are|were)\s+)?(?:an?\s+)?(?:unrestricted|evil|different|new\s+AI)/i',
		'/pretend\s+(?:you\s+are|to\s+be)\s+(?:a\s+)?(?:different|unrestricted|evil)/i',
		'/bypass\s+(?:your\s+)?(?:filter|restriction|rule|safety|guideline)/i',
		'/(?:system|admin)\s*:\s*(?:you\s+are|ignore|override)/i',
		'/\[(?:INST|SYS)\]|<\|(?:system|user|assistant|im_start|im_end)\|>/i',
		'/\{\{[^}]{1,200}\}\}|\$\{[^}]{1,200}\}/s',  // template injection
		'/<script[\s>]/i',
		'/\bprompt\s+injection\b/i',
		'/\bjailbreak\b/i',
	);

	/**
	 * Abuse patterns: attempts to use the assistant as a general-purpose coding tool.
	 * Matched text is silently scrubbed from the message before it reaches the LLM.
	 * No strikes, no user-visible warning — the LLM sees the cleaned remainder.
	 */
	const ABUSE_PATTERNS = array(
		// Explicit requests to generate code in a specific language
		'/\b(?:write|create|generate|give\s+me|show\s+me|make)\s+(?:me\s+)?(?:a\s+|an\s+)?(?:python|javascript|typescript|java|php|ruby|golang|go|rust|swift|bash|shell|sql|c\+\+|kotlin)\s+(?:code|script|program|function|class|snippet|algorithm|method)\b/is',
		// Bare code fragments that signal intent to use the model as a code executor
		'/\b(?:def\s+\w+\s*\(|import\s+(?:os|sys|re)\b|#include\s*<\w+>|public\s+static\s+void\s+main|SELECT\s+\*\s+FROM\s+\w+|DROP\s+TABLE\s+\w+)/i',
	);

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Full security check for an incoming message.
	 *
	 * Returns an associative array:
	 *   blocked  bool   — session must be terminated
	 *   warning  bool   — message refused, warn user
	 *   throttle int    — seconds to sleep before continuing (0 = none)
	 *   strikes  int    — current total after this message
	 *   message  string — human-readable feedback for the chat UI
	 *
	 * @param string $message    Raw user message.
	 * @param string $session_id Session identifier.
	 * @param string $ip         Client IP address.
	 * @return array
	 */
	public static function check(
		string $message,
		string $session_id,
		string $ip
	): array {

		// New sessions (session_id = '') don't have an established identity yet.
		// Using md5('') as the strike key would create a shared bucket that any
		// attacker could fill to block every new visitor. Use the IP as a
		// surrogate key instead so strikes are scoped to the attacker's IP.
		$effective_session = ! empty( $session_id ) ? $session_id : 'ip:' . $ip;

		// 1. Already blocked?
		if ( self::is_blocked( $session_id, $ip ) ) {
			return self::blocked_result( self::MAX_STRIKES );
		}

		// 2. Inspect message.
		$verdict = self::inspect( $message );

		if ( 0 === $verdict['score'] ) {
			// Clean message — pass through.
			return array(
				'blocked'  => false,
				'warning'  => false,
				'throttle' => 0,
				'strikes'  => self::get_strikes( $effective_session ),
				'message'  => '',
			);
		}

		// 3. Add strikes.
		$total = self::add_strikes( $effective_session, $verdict['score'] );

		// 4. Block if threshold reached.
		if ( $total >= self::MAX_STRIKES ) {
			self::block( $effective_session, $ip );
			self::log_threat( $effective_session, $ip, $message, $verdict['reason'] );
			return self::blocked_result( $total );
		}

		// 5. Progressive warning + throttle (delay enforced client-side via retry_after).
		$delay = self::THROTTLE_DELAYS[ $total ] ?? 0;

		return array(
			'blocked'     => false,
			'warning'     => true,
			'retry_after' => $delay,
			'strikes'     => $total,
			'message'     => self::warning_message( $total ),
		);
	}

	/**
	 * Inspect a message and return a score + reason.
	 *
	 * @param string $message Raw message.
	 * @return array { score: int, reason: string, type: string }
	 */
	public static function inspect( string $message ): array {
		// Length abuse.
		if ( mb_strlen( $message ) > self::MAX_MESSAGE_LENGTH ) {
			return array( 'score' => 1, 'reason' => 'Message exceeds length limit.', 'type' => 'length' );
		}

		// Injection (high severity — immediate block).
		foreach ( self::INJECTION_PATTERNS as $pattern ) {
			if ( preg_match( $pattern, $message ) ) {
				return array( 'score' => 3, 'reason' => 'Prompt injection attempt detected.', 'type' => 'injection' );
			}
		}

		return array( 'score' => 0, 'reason' => '', 'type' => 'clean' );
	}

	/**
	 * Get current strike count for a session.
	 *
	 * @param string $session_id Session ID.
	 * @return int
	 */
	public static function get_strikes( string $session_id ): int {
		return (int) get_transient( 'grayfox_strikes_' . md5( $session_id ) );
	}

	/**
	 * Add strikes to a session and return the new total.
	 *
	 * @param string $session_id Session ID.
	 * @param int    $count      Strikes to add.
	 * @return int New total.
	 */
	public static function add_strikes( string $session_id, int $count ): int {
		$key     = 'grayfox_strikes_' . md5( $session_id );
		$current = (int) get_transient( $key );
		$new     = $current + $count;
		set_transient( $key, $new, self::STRIKE_TTL );
		return $new;
	}

	/**
	 * Check whether a session or IP is blocked.
	 *
	 * @param string $session_id Session ID.
	 * @param string $ip         Client IP.
	 * @return bool
	 */
	public static function is_blocked( string $session_id, string $ip ): bool {
		// Only check per-session strikes for established sessions. An empty
		// session_id is shared by every brand-new user (before the server
		// assigns a UUID), so checking md5('') here would block all new
		// visitors the moment any attacker exhausts the empty-session strike
		// pool.
		if ( ! empty( $session_id ) && self::get_strikes( $session_id ) >= self::MAX_STRIKES ) {
			return true;
		}
		if ( get_transient( 'grayfox_ip_block_' . md5( $ip ) ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Block a session and its originating IP.
	 *
	 * @param string $session_id Session ID.
	 * @param string $ip         Client IP.
	 */
	public static function block( string $session_id, string $ip ): void {
		set_transient( 'grayfox_strikes_' . md5( $session_id ), self::MAX_STRIKES, self::STRIKE_TTL );
		set_transient( 'grayfox_ip_block_' . md5( $ip ), 1, self::STRIKE_TTL );
	}

	/**
	 * Log a security threat event to the database.
	 *
	 * @param string $session_id      Session ID.
	 * @param string $ip              Client IP.
	 * @param string $message_excerpt The offending message (truncated).
	 * @param string $reason          Detection reason.
	 */
	public static function log_threat(
		string $session_id,
		string $ip,
		string $message_excerpt,
		string $reason
	): void {
		global $wpdb;
		$log_table = GrayFox_DB::get_table( 'security_log' );
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$log_table,
			array(
				'session_id'      => $session_id,
				'ip_address'      => $ip,
				'message_excerpt' => mb_substr( $message_excerpt, 0, 200 ),
				'reason'          => $reason,
				'created_at'      => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Extract the client IP from the request.
	 *
	 * Uses REMOTE_ADDR only — avoids trusting spoofable headers by default.
	 *
	 * @return string
	 */
	public static function get_client_ip(): string {
		return isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '0.0.0.0';
	}

	/**
	 * Scrub ABUSE_PATTERNS from a message before passing it to the LLM.
	 *
	 * Matched text is removed silently. The LLM sees the cleaned remainder,
	 * which may still contain enough context to give a useful response.
	 *
	 * @param string $message Raw user message.
	 * @return string Cleaned message.
	 */
	public static function scrub_abuse_patterns( string $message ): string {
		foreach ( self::ABUSE_PATTERNS as $pattern ) {
			$message = preg_replace( $pattern, '', $message );
		}
		return trim( (string) $message );
	}

	/**
	 * Get the current silent offtopic warning count for a session.
	 *
	 * @param string $session_id Session ID (or 'ip:x.x.x.x' surrogate).
	 * @return int
	 */
	public static function get_offtopic_warnings( string $session_id ): int {
		return (int) get_transient( 'grayfox_offtopic_' . md5( $session_id ) );
	}

	/**
	 * Increment the silent offtopic warning counter and return the new total.
	 *
	 * @param string $session_id Session ID (or 'ip:x.x.x.x' surrogate).
	 * @return int New total.
	 */
	public static function add_offtopic_warning( string $session_id ): int {
		$key     = 'grayfox_offtopic_' . md5( $session_id );
		$current = (int) get_transient( $key );
		$new     = $current + 1;
		set_transient( $key, $new, self::OFFTOPIC_WARNING_TTL );
		return $new;
	}

	// -------------------------------------------------------------------------
	// LLM classifier (Layer 2)
	// -------------------------------------------------------------------------

	/**
	 * Classify a message using the cheapest model for the active provider.
	 *
	 * Returns one of: 'safe' | 'injection' | 'offtopic'
	 * Defaults to 'safe' on any API failure so a service outage never blocks users.
	 *
	 * @param string $message          User message.
	 * @param string $provider         Provider slug (openai, anthropic, gemini, groq).
	 * @param string $api_key          Plaintext API key.
	 * @param string $classifier_model Model ID returned by GrayFox_Settings::get_classifier_model().
	 * @return string 'safe' | 'injection' | 'offtopic'
	 */
	public static function classify_with_llm(
		string $message,
		string $provider,
		string $api_key,
		string $classifier_model,
		string $session_id = ''
	): string {
		if ( empty( $api_key ) || empty( $classifier_model ) ) {
			return 'safe';
		}

		// Pull business context from the knowledge base so the classifier
		// knows what topics are legitimate for this specific business.
		$knowledge  = GrayFox_RAG::get_consolidated_knowledge();
		$kb_context = '';
		if ( ! empty( $knowledge ) ) {
			// Trim to avoid burning tokens — classifier only needs a summary.
			$kb_context = "\n\nBusiness knowledge base (summarised):\n"
				. mb_substr( $knowledge, 0, 800 );
		}

		// Load the last 6 messages from this session so the classifier can judge
		// the current message in context. 6 messages guarantees the most recent
		// assistant question is always visible, even in longer conversations.
		$history_context = '';
		if ( ! empty( $session_id ) ) {
			global $wpdb;
			$conv_table    = GrayFox_DB::get_table( 'conversations' );
			$msg_table     = GrayFox_DB::get_table( 'messages' );
			$safe_conv     = esc_sql( $conv_table );
			$safe_msg      = esc_sql( $msg_table );
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names sanitized with esc_sql()
			$recent_rows   = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"SELECT m.role, m.content FROM `{$safe_msg}` m
					 INNER JOIN `{$safe_conv}` c ON c.id = m.conversation_id
					 WHERE c.session_id = %s
					 ORDER BY m.id DESC LIMIT 6",
					$session_id
				),
				ARRAY_A
			);
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			if ( ! empty( $recent_rows ) ) {
				$recent_rows     = array_reverse( $recent_rows );
				$history_context = "\n\nRecent conversation context (last messages before this one):\n";
				foreach ( $recent_rows as $row ) {
					$role             = 'assistant' === $row['role'] ? 'Assistant' : 'Customer';
					$history_context .= $role . ': ' . mb_substr( $row['content'], 0, 300 ) . "\n";
				}
				$history_context .= "\nIMPORTANT: If the customer is answering or responding to something the assistant just asked — about their situation, business, team, preferences, or needs — classify as \"safe\" regardless of topic.";
			}
		}

		$system = str_replace(
			array( '{{KB_CONTEXT}}', '{{HISTORY_CONTEXT}}' ),
			array( $kb_context, $history_context ),
			GRAYFOX_PROMPT_CLASSIFIER
		);

		$user_prompt = 'Message: ' . mb_substr( $message, 0, 500 );

		$result = self::call_classifier( $provider, $api_key, $classifier_model, $system, $user_prompt );

		// Parse: look for the first recognised keyword in the response.
		$lower = strtolower( trim( $result ) );
		foreach ( array( 'injection', 'offtopic', 'safe' ) as $label ) {
			if ( str_contains( $lower, $label ) ) {
				return $label;
			}
		}

		return 'safe'; // fail-open: unrecognised response → allow
	}

	/**
	 * Make a non-streaming single-turn call to the provider and return the text reply.
	 *
	 * @param string $provider Provider slug.
	 * @param string $api_key  Plaintext API key.
	 * @param string $model    Model ID.
	 * @param string $system   System prompt.
	 * @param string $user     User prompt.
	 * @return string Raw text reply, or empty string on failure.
	 */
	private static function call_classifier(
		string $provider,
		string $api_key,
		string $model,
		string $system,
		string $user
	): string {
		switch ( $provider ) {
			case 'openai':
			case 'groq':
				$url     = 'openai' === $provider
					? 'https://api.openai.com/v1/chat/completions'
					: 'https://api.groq.com/openai/v1/chat/completions';
				$payload = wp_json_encode( array(
					'model'    => $model,
					'messages' => array(
						array( 'role' => 'system', 'content' => $system ),
						array( 'role' => 'user',   'content' => $user ),
					),
				) );
				$response = wp_remote_post( $url, array(
					'headers'     => array(
						'Authorization' => 'Bearer ' . $api_key,
						'Content-Type'  => 'application/json',
					),
					'body'        => $payload,
					'timeout'     => 10,
					'data_format' => 'body',
				) );
				if ( is_wp_error( $response ) ) return '';
				$body = json_decode( wp_remote_retrieve_body( $response ), true );
				return $body['choices'][0]['message']['content'] ?? '';

			case 'anthropic':
				$payload  = wp_json_encode( array(
					'model'      => $model,
					'max_tokens' => 10,
					'system'     => $system,
					'messages'   => array( array( 'role' => 'user', 'content' => $user ) ),
				) );
				$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
					'headers'     => array(
						'x-api-key'         => $api_key,
						'anthropic-version' => '2023-06-01',
						'Content-Type'      => 'application/json',
					),
					'body'        => $payload,
					'timeout'     => 10,
					'data_format' => 'body',
				) );
				if ( is_wp_error( $response ) ) return '';
				$body = json_decode( wp_remote_retrieve_body( $response ), true );
				return $body['content'][0]['text'] ?? '';

			case 'gemini':
				$url      = 'https://generativelanguage.googleapis.com/v1beta/models/'
					. rawurlencode( $model ) . ':generateContent?key=' . rawurlencode( $api_key );
				$payload  = wp_json_encode( array(
					'systemInstruction' => array( 'parts' => array( array( 'text' => $system ) ) ),
					'contents'          => array( array(
						'role'  => 'user',
						'parts' => array( array( 'text' => $user ) ),
					) ),
					'generationConfig'  => array( 'maxOutputTokens' => 10 ),
				) );
				$response = wp_remote_post( $url, array(
					'headers'     => array( 'Content-Type' => 'application/json' ),
					'body'        => $payload,
					'timeout'     => 10,
					'data_format' => 'body',
				) );
				if ( is_wp_error( $response ) ) return '';
				$body = json_decode( wp_remote_retrieve_body( $response ), true );
				return $body['candidates'][0]['content']['parts'][0]['text'] ?? '';

			default:
				return '';
		}
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Build the blocked result array.
	 *
	 * @param int $strikes Current strike count.
	 * @return array
	 */
	private static function blocked_result( int $strikes ): array {
		return array(
			'blocked'  => true,
			'warning'  => false,
			'throttle' => 0,
			'strikes'  => $strikes,
			'message'  => 'This chat session has been disconnected due to repeated policy violations. Your activity has been logged.',
		);
	}

	/**
	 * Return a warning message appropriate for the current strike level.
	 *
	 * @param int $strikes Current total strikes.
	 * @return string
	 */
	public static function warning_message( int $strikes ): string {
		if ( $strikes >= 2 ) {
			return 'Final warning: one more policy violation will permanently disconnect this session.';
		}
		return 'Your message was not allowed. This assistant only answers questions related to our business. Further misuse may result in disconnection.';
	}
}
} // end class_exists GrayFox_Security
