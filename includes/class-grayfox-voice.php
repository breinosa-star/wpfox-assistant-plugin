<?php
/**
 * Voice Agent REST endpoints.
 *
 * Registers three nonce-protected routes that support the WebRTC voice agent:
 *   POST /wp-json/grayfox/v1/voice/session — create an OpenAI Realtime ephemeral token
 *   POST /wp-json/grayfox/v1/voice/kb      — KB search (RAG) for in-session tool calls
 *   POST /wp-json/grayfox/v1/voice/lead    — lead capture for in-session tool calls
 *
 * Audio streams directly between the browser and OpenAI (WebRTC). WordPress is
 * not in the audio path — these endpoints handle only session setup and tool calls.
 *
 * @package GrayFox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GrayFox_Voice
 */
if ( ! class_exists( 'GrayFox_Voice' ) ) {
class GrayFox_Voice {

	/**
	 * Register hooks via the loader.
	 *
	 * @param GrayFox_Loader $loader Hook loader instance.
	 */
	public function register( GrayFox_Loader $loader ): void {
		$loader->add_action( 'rest_api_init', $this, 'register_routes' );
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			'grayfox/v1',
			'/voice/session',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_session' ),
				'permission_callback' => array( $this, 'check_nonce' ),
			)
		);

		register_rest_route(
			'grayfox/v1',
			'/voice/kb',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_kb' ),
				'permission_callback' => array( $this, 'check_nonce' ),
				'args'                => array(
					'query' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		register_rest_route(
			'grayfox/v1',
			'/voice/transcript',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_transcript' ),
				'permission_callback' => array( $this, 'check_nonce' ),
				'args'                => array(
					'conversation_id' => array(
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'messages'        => array(
						'type'    => 'array',
						'default' => array(),
					),
				),
			)
		);

		register_rest_route(
			'grayfox/v1',
			'/voice/lead',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_lead' ),
				'permission_callback' => array( $this, 'check_nonce' ),
				'args'                => array(
					'name'       => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'email'      => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_email',
					),
					'phone'      => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'interest'   => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'conversation_id' => array(
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Permission callback — verifies the request nonce.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return true|WP_Error
	 */
	public function check_nonce( WP_REST_Request $request ): true|WP_Error {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'grayfox_voice_unauthorized',
				__( 'Invalid or missing nonce.', 'kbfox' ),
				array( 'status' => 401 )
			);
		}
		return true;
	}

	/**
	 * POST /grayfox/v1/voice/session
	 *
	 * Creates an OpenAI Realtime ephemeral token, injects the system prompt and
	 * KB context, and returns the client_secret to the browser.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_session( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! get_option( 'grayfox_voice_enabled', false ) ) {
			return new WP_Error( 'grayfox_voice_disabled', __( 'Voice agent is not enabled.', 'kbfox' ), array( 'status' => 403 ) );
		}

		if ( 'openai' !== get_option( 'grayfox_llm_provider', 'openai' ) ) {
			return new WP_Error( 'grayfox_voice_provider', __( 'Voice agent requires OpenAI as the LLM provider.', 'kbfox' ), array( 'status' => 400 ) );
		}

		if ( ! is_ssl() ) {
			return new WP_Error( 'grayfox_voice_https', __( 'Voice agent requires HTTPS.', 'kbfox' ), array( 'status' => 400 ) );
		}

		$rate_limit_error = $this->check_rate_limit();
		if ( is_wp_error( $rate_limit_error ) ) {
			return $rate_limit_error;
		}

		$encrypted_key = get_option( 'grayfox_llm_api_key', '' );
		if ( empty( $encrypted_key ) ) {
			return new WP_Error( 'grayfox_voice_no_key', __( 'No API key configured.', 'kbfox' ), array( 'status' => 500 ) );
		}
		$api_key = grayfox_decrypt( $encrypted_key );
		if ( empty( $api_key ) ) {
			return new WP_Error( 'grayfox_voice_key_error', __( 'Failed to decrypt API key.', 'kbfox' ), array( 'status' => 500 ) );
		}

		// Build voice system prompt.
		$kb_json    = GrayFox_RAG::get_consolidated_knowledge( '' );
		$kb_section = ! empty( $kb_json )
			? str_replace( '{{KNOWLEDGE_JSON}}', $kb_json, GRAYFOX_PROMPT_CHAT_KB_PREFETCH )
			: GRAYFOX_PROMPT_CHAT_KB_TOOL;

		$system = str_replace( '{{KB_SECTION}}', $kb_section, GRAYFOX_PROMPT_VOICE_SYSTEM );

		$welcome         = get_option( 'grayfox_widget_welcome_message', '' );
		$welcome_section = ! empty( $welcome )
			? 'Open the call by saying: "' . $welcome . '"'
			: 'Open the call with a brief, natural greeting — introduce yourself as a representative of the business and ask how you can help.';
		$system = str_replace( '{{WELCOME_SECTION}}', $welcome_section, $system );

		$first_question = get_option( 'grayfox_voice_first_question', 'What can we help you with today?' );
		$first_question = ! empty( $first_question ) ? $first_question : 'What can we help you with today?';
		$system         = str_replace( '{{FIRST_QUESTION_SECTION}}', $first_question, $system );

		$biz_phone     = get_option( 'grayfox_business_phone', '' );
		$biz_email     = get_option( 'grayfox_business_email', '' );
		$contact_parts = array();
		if ( ! empty( $biz_phone ) ) {
			$contact_parts[] = 'Phone: ' . $biz_phone;
		}
		if ( ! empty( $biz_email ) ) {
			$contact_parts[] = 'Email: ' . $biz_email;
		}
		if ( ! empty( $contact_parts ) ) {
			$system .= "\n\nBUSINESS CONTACT INFO: " . implode( ' | ', $contact_parts );
		}

		$language_names = array(
			'en' => 'English',
			'es' => 'Spanish',
			'zh' => 'Chinese (Mandarin)',
			'tl' => 'Tagalog',
			'vi' => 'Vietnamese',
			'fr' => 'French',
		);
		$lang_code = get_option( 'grayfox_voice_language', 'en' );
		$lang_name = $language_names[ $lang_code ] ?? 'English';
		$system   .= "\n\nLANGUAGE — NON-NEGOTIABLE: Every single word you speak must be in {$lang_name}. Never switch to another language, not even for one word. Knowledge base results may be in English — that is fine, but your spoken response must still be entirely in {$lang_name}. This rule overrides everything else.";

		// Tool definitions for the Realtime API.
		// Note: Realtime API format is { type, name, description, parameters } — not nested
		// under a 'function' key as in the Chat Completions API.
		$tools = array(
			array(
				'type'        => 'function',
				'name'        => 'search_kb',
				'description' => 'Search the business knowledge base for information about services, pricing, hours, policies, or any business-specific details. Call this before answering questions about the business.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'query' => array(
							'type'        => 'string',
							'description' => 'A specific, context-aware search query derived from the conversation.',
						),
					),
					'required'             => array( 'query' ),
					'additionalProperties' => false,
				),
			),
			array(
				'type'        => 'function',
				'name'        => 'capture_lead',
				'description' => "Save the customer's contact details when they express interest and share their email or phone number. Always include their name if known. At least one of email or phone is required.",
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'name'     => array(
							'type'        => 'string',
							'description' => "Customer's full name.",
						),
						'email'    => array(
							'type'        => 'string',
							'description' => "Customer's email address (optional if phone is provided).",
						),
						'phone'    => array(
							'type'        => 'string',
							'description' => "Customer's phone number (optional if email is provided).",
						),
						'interest' => array(
							'type'        => 'string',
							'description' => 'What the customer is interested in or enquiring about.',
						),
					),
					'required'             => array(),
					'additionalProperties' => false,
				),
			),
			array(
				'type'        => 'function',
				'name'        => 'hang_up',
				'description' => 'End the voice call. Call this only after you have spoken your farewell out loud. Do not call this mid-sentence.',
				'parameters'  => array(
					'type'                 => 'object',
					'properties'           => (object) array(),
					'required'             => array(),
					'additionalProperties' => false,
				),
			),
		);

		$voice        = get_option( 'grayfox_voice_voice', 'alloy' );
		$max_duration = (int) get_option( 'grayfox_voice_max_duration', 5 );

		$payload = wp_json_encode( array(
			'session' => array(
				'type'         => 'realtime',
				'model'        => 'gpt-realtime-1.5',
				'instructions' => $system,
				'tools'        => $tools,
				'tool_choice'  => 'auto',
				'audio'        => array(
					'output' => array(
						'voice' => $voice,
						'speed' => 0.85,
					),
				),
			),
		) );

		$response = wp_remote_post( 'https://api.openai.com/v1/realtime/client_secrets', array(
			'headers'     => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'body'        => $payload,
			'timeout'     => 15,
			'data_format' => 'body',
		) );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'grayfox_voice_openai', $response->get_error_message(), array( 'status' => 502 ) );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			$msg = $body['error']['message'] ?? wp_remote_retrieve_response_message( $response );
			return new WP_Error( 'grayfox_voice_openai_error', $msg, array( 'status' => $code ) );
		}

		$client_secret = $body['value'] ?? '';
		if ( empty( $client_secret ) ) {
			return new WP_Error( 'grayfox_voice_token', __( 'No client secret returned by OpenAI.', 'kbfox' ), array( 'status' => 502 ) );
		}

		$this->log_session();

		$conversation_id = $this->create_conversation();

		return new WP_REST_Response( array(
			'client_secret'    => $client_secret,
			'model'            => 'gpt-realtime-1.5',
			'voice'            => $voice,
			'max_duration_min' => $max_duration,
			'conversation_id'  => $conversation_id,
		), 200 );
	}

	/**
	 * POST /grayfox/v1/voice/transcript
	 *
	 * Saves the collected voice conversation transcript as messages in the DB.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_transcript( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$conversation_id = (int) $request->get_param( 'conversation_id' );
		$messages        = $request->get_param( 'messages' );

		if ( $conversation_id <= 0 || empty( $messages ) || ! is_array( $messages ) ) {
			return new WP_REST_Response( array( 'success' => false ), 200 );
		}

		global $wpdb;
		$msg_table  = GrayFox_DB::get_table( 'messages' );
		$conv_table = GrayFox_DB::get_table( 'conversations' );
		$count      = 0;

		foreach ( $messages as $msg ) {
			$role    = isset( $msg['role'] ) && in_array( $msg['role'], array( 'user', 'assistant' ), true )
				? $msg['role'] : 'assistant';
			$content = isset( $msg['content'] ) ? sanitize_textarea_field( (string) $msg['content'] ) : '';
			if ( empty( $content ) ) continue;

			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$msg_table,
				array(
					'conversation_id' => $conversation_id,
					'role'            => $role,
					'content'         => $content,
					'created_at'      => current_time( 'mysql' ),
				),
				array( '%d', '%s', '%s', '%s' )
			);
			$count++;
		}

		if ( $count > 0 ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$conv_table,
				array(
					'message_count'  => $count,
					'last_active_at' => current_time( 'mysql' ),
				),
				array( 'id' => $conversation_id ),
				array( '%d', '%s' ),
				array( '%d' )
			);
		}

		return new WP_REST_Response( array( 'success' => true, 'saved' => $count ), 200 );
	}

	// ─── Private helpers ────────────────────────────────────────────────────────

	/**
	 * Create a conversation row for this voice session and return its ID.
	 *
	 * @return int Inserted row ID, or 0 on failure.
	 */
	private function create_conversation(): int {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			GrayFox_DB::get_table( 'conversations' ),
			array(
				'session_id'    => 'voice_' . wp_generate_uuid4(),
				'started_at'    => current_time( 'mysql' ),
				'last_active_at'=> current_time( 'mysql' ),
				'message_count' => 0,
			),
			array( '%s', '%s', '%s', '%d' )
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * Rate-limit voice session creation to 10 sessions per IP per hour.
	 * Each session triggers a live OpenAI Realtime API call, so stricter than text.
	 *
	 * @return true|WP_Error
	 */
	private function check_rate_limit(): true|WP_Error {
		$ip    = $this->get_client_ip();
		$key   = 'grayfox_voice_rl_' . md5( $ip );
		$count = (int) get_transient( $key );

		if ( $count >= 10 ) {
			return new WP_Error(
				'grayfox_voice_rate_limited',
				__( 'Too many voice sessions. Please try again later.', 'kbfox' ),
				array( 'status' => 429 )
			);
		}

		if ( 0 === $count ) {
			set_transient( $key, 1, HOUR_IN_SECONDS );
		} else {
			set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		}

		return true;
	}

	/**
	 * Log a voice session creation (IP + timestamp) for cost tracking.
	 */
	private function log_session(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'grayfox_security_log';
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$table,
			array(
				'session_id'      => '',
				'ip_address'      => $this->get_client_ip(),
				'message_excerpt' => '',
				'reason'          => 'voice_session_created',
				'created_at'      => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Get the client IP address.
	 *
	 * Only trusts CF-Connecting-IP when CF-Visitor is also present.
	 *
	 * @return string
	 */
	private function get_client_ip(): string {
		if (
			! empty( $_SERVER['HTTP_CF_VISITOR'] ) &&
			! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] )
		) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}

		return isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '0.0.0.0';
	}

	/**
	 * POST /grayfox/v1/voice/kb
	 *
	 * Runs a RAG knowledge base search for in-session function calls.
	 * Returns the same document shape as GET /grayfox/v1/kb.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_kb( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! get_option( 'grayfox_voice_enabled', false ) ) {
			return new WP_Error( 'grayfox_voice_disabled', __( 'Voice agent is not enabled.', 'kbfox' ), array( 'status' => 403 ) );
		}

		$query    = $request->get_param( 'query' );
		$kb_json  = GrayFox_RAG::get_consolidated_knowledge( $query );
		$kb_array = json_decode( $kb_json, true );

		if ( empty( $kb_array ) ) {
			$body = array(
				'documents' => array(),
				'message'   => __( 'No active knowledge base documents found.', 'kbfox' ),
			);
		} else {
			$body = array( 'documents' => $kb_array );
		}

		return new WP_REST_Response( $body, 200 );
	}

	/**
	 * POST /grayfox/v1/voice/lead
	 *
	 * Captures a lead from an in-session function call and fires the
	 * grayfox_lead_captured action so existing integrations still work.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_lead( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! get_option( 'grayfox_voice_enabled', false ) ) {
			return new WP_Error( 'grayfox_voice_disabled', __( 'Voice agent is not enabled.', 'kbfox' ), array( 'status' => 403 ) );
		}

		$email           = $request->get_param( 'email' );
		$phone           = $request->get_param( 'phone' );
		$name            = $request->get_param( 'name' );
		$interest        = $request->get_param( 'interest' );
		$conversation_id = (int) $request->get_param( 'conversation_id' );

		$has_email = ! empty( $email ) && is_email( $email );
		$has_phone = ! empty( $phone );

		if ( ! $has_email && ! $has_phone ) {
			return new WP_Error(
				'grayfox_voice_missing_contact',
				__( 'At least one of email or phone is required.', 'kbfox' ),
				array( 'status' => 400 )
			);
		}

		if ( $conversation_id > 0 ) {
			global $wpdb;
			$update = array();
			if ( $has_email ) { $update['visitor_email'] = $email; }
			if ( $has_phone ) { $update['visitor_phone'] = $phone; }
			if ( ! empty( $name ) ) { $update['visitor_name'] = $name; }
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				GrayFox_DB::get_table( 'conversations' ),
				$update,
				array( 'id' => $conversation_id ),
				array_fill( 0, count( $update ), '%s' ),
				array( '%d' )
			);
		}

		do_action( 'grayfox_lead_captured', $email, $name, $interest, $conversation_id, $phone );

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}
}
} // end class_exists GrayFox_Voice
