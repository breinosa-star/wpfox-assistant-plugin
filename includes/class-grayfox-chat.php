<?php
/**
 * Chat AJAX handler — two-step SSE streaming endpoint.
 *
 * Step 1: grayfox_chat (POST) — validates, saves user message, issues stream_token.
 * Step 2: grayfox_chat_stream (GET) — consumes token, streams SSE response.
 *
 * @package GrayFox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GrayFox_Chat
 *
 * Registers and handles the AJAX chat endpoints.
 */
class GrayFox_Chat {

	/**
	 * Register hooks with the loader.
	 *
	 * @param GrayFox_Loader $loader Loader instance.
	 */
	public function register( GrayFox_Loader $loader ): void {
		$loader->add_action( 'wp_ajax_grayfox_chat',        $this, 'handle_chat' );
		$loader->add_action( 'wp_ajax_nopriv_grayfox_chat', $this, 'handle_chat' );

		$loader->add_action( 'wp_ajax_grayfox_chat_stream',        $this, 'handle_stream' );
		$loader->add_action( 'wp_ajax_nopriv_grayfox_chat_stream', $this, 'handle_stream' );
	}

	/**
	 * Step 1: Handle the POST chat request.
	 *
	 * Validates nonce, saves user message, builds LLM messages array,
	 * stores it in a short-lived transient, and returns a stream_token.
	 */
	public function handle_chat(): void {
		// 1. Verify nonce.
		check_ajax_referer( 'grayfox_chat', 'nonce' );

		// 2. Sanitize inputs.
		$message    = isset( $_POST['message'] )    ? sanitize_text_field( wp_unslash( $_POST['message'] ) )    : '';
		$session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';

		if ( empty( $message ) ) {
			wp_send_json_error( array( 'message' => 'Empty message.' ), 400 );
		}

		// 2a. Scrub abuse patterns from message before classification and LLM delivery.
		// Original $message is preserved for DB storage; $clean_message goes to the LLM.
		$clean_message = GrayFox_Security::scrub_abuse_patterns( $message );

		// 2b. Security check.
		$client_ip = GrayFox_Security::get_client_ip();
		$security  = GrayFox_Security::check( $message, $session_id, $client_ip );

		if ( $security['blocked'] ) {
			wp_send_json_error( array(
				'message'  => $security['message'],
				'security' => 'blocked',
				'strikes'  => $security['strikes'],
			), 429 );
		}

		if ( $security['warning'] ) {
			wp_send_json_error( array(
				'message'  => $security['message'],
				'security' => 'warning',
				'strikes'  => $security['strikes'],
			), 200 );
		}

		// 2c. Layer 2: LLM classifier — runs on the scrubbed message.
		// 'injection' → visible strike + warning.
		// 'offtopic'  → silent internal counter only; message goes through.
		$llm_provider     = get_option( 'grayfox_llm_provider', 'openai' );
		$llm_enc_key      = get_option( 'grayfox_llm_api_key', '' );
		$llm_plain_key    = grayfox_decrypt( $llm_enc_key );
		$classifier_model = GrayFox_Settings::get_classifier_model( $llm_provider );
		$offtopic_wrap_up = false;

		if ( ! empty( $llm_plain_key ) && ! empty( $classifier_model ) ) {
			$classification = GrayFox_Security::classify_with_llm(
				$clean_message,
				$llm_provider,
				$llm_plain_key,
				$classifier_model,
				$session_id
			);

			// Use IP as surrogate session key for new sessions to avoid
			// poisoning the md5('') shared bucket for all new visitors.
			$effective_session = ! empty( $session_id ) ? $session_id : 'ip:' . $client_ip;

			if ( 'injection' === $classification ) {
				$strike_count = GrayFox_Security::add_strikes( $effective_session, 1 );

				if ( $strike_count >= GrayFox_Security::MAX_STRIKES ) {
					GrayFox_Security::block( $effective_session, $client_ip );
					GrayFox_Security::log_threat( $effective_session, $client_ip, $message, 'LLM: prompt injection detected.' );
					wp_send_json_error( array(
						'message'  => 'This chat session has been disconnected due to repeated policy violations. Your activity has been logged.',
						'security' => 'blocked',
						'strikes'  => $strike_count,
					), 429 );
				}

				wp_send_json_error( array(
					'message'  => GrayFox_Security::warning_message( $strike_count ),
					'security' => 'warning',
					'strikes'  => $strike_count,
				), 200 );

			} elseif ( 'offtopic' === $classification ) {
				// Silent internal counter — user sees nothing, message still goes through.
				$warn_count = GrayFox_Security::add_offtopic_warning( $effective_session );
				if ( $warn_count >= GrayFox_Security::OFFTOPIC_WARNING_MAX ) {
					$offtopic_wrap_up = true;
				}
			}
		}

		// 3. Generate session ID if missing; track whether this is a brand-new session.
		$is_new_session = empty( $session_id );
		if ( $is_new_session ) {
			$session_id = wp_generate_uuid4();
		}

		global $wpdb;

		$conv_table = esc_sql( GrayFox_DB::get_table( 'conversations' ) );
		$msg_table  = esc_sql( GrayFox_DB::get_table( 'messages' ) );

		// 3a. Session TTL check — reject stale sessions (>24h inactive).
		if ( ! $is_new_session ) {
			$ttl_row = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				"SELECT last_active_at FROM `{$conv_table}` WHERE session_id = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$session_id
			) );
			if ( $ttl_row && ! empty( $ttl_row->last_active_at ) ) {
				$last_active = strtotime( $ttl_row->last_active_at );
				if ( ( time() - $last_active ) > 86400 ) {
					$phone = get_option( 'grayfox_business_phone', '' );
					$msg   = __( 'Your session has expired after 24 hours of inactivity. Please start a new conversation.', 'kbfox' );
					if ( $phone ) {
						/* translators: %s: business phone number */
						$msg .= ' ' . sprintf( __( 'You can also reach us at %s.', 'kbfox' ), esc_html( $phone ) );
					}
					wp_send_json_error( array( 'session_expired' => true, 'message' => $msg ), 200 );
				}
			}
		}

		// 3b. IP rate limiting — enforce per-IP session quotas for new sessions only.
		if ( $is_new_session ) {
			$ip_hash = md5( GrayFox_Security::get_client_ip() );
			$h_limit = (int) get_option( 'grayfox_ip_sessions_per_hour', 5 );
			$d_limit = (int) get_option( 'grayfox_ip_sessions_per_day', 10 );
			$h_key   = 'grayfox_ip_h_' . $ip_hash;
			$d_key   = 'grayfox_ip_d_' . $ip_hash;
			$h_count = (int) get_transient( $h_key );
			$d_count = (int) get_transient( $d_key );

			if ( $h_count >= $h_limit || $d_count >= $d_limit ) {
				$phone = get_option( 'grayfox_business_phone', '' );
				$msg   = __( 'Too many chat sessions from your location. Please try again later.', 'kbfox' );
				if ( $phone ) {
					/* translators: %s: business phone number */
					$msg .= ' ' . sprintf( __( 'You can also reach us at %s.', 'kbfox' ), esc_html( $phone ) );
				}
				wp_send_json_error( array( 'rate_limited' => true, 'message' => $msg ), 429 );
			}

			// Increment counters (create or increment — preserve remaining TTL isn't possible
			// with WP transients, so we always reset TTL on increment which is intentional).
			if ( false === get_transient( $h_key ) ) {
				set_transient( $h_key, 1, 3600 );
			} else {
				set_transient( $h_key, $h_count + 1, 3600 );
			}
			if ( false === get_transient( $d_key ) ) {
				set_transient( $d_key, 1, 86400 );
			} else {
				set_transient( $d_key, $d_count + 1, 86400 );
			}
		}

		// 4. Ensure a conversation record exists.
		$conversation = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT id, message_count FROM `{$conv_table}` WHERE session_id = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$session_id
		) );

		if ( ! $conversation ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$conv_table,
				array(
					'session_id'     => $session_id,
					'visitor_id'     => '',
					'started_at'     => current_time( 'mysql' ),
					'last_active_at' => current_time( 'mysql' ),
					'message_count'  => 0,
				),
				array( '%s', '%s', '%s', '%s', '%d' )
			);
			$conversation_id = (int) $wpdb->insert_id;
			$msg_count       = 0;

			/**
			 * Fires when a new visitor conversation is created.
			 *
			 * @since 1.0.0
			 * @param int    $conversation_id DB row ID of the new conversation.
			 * @param string $session_id      Browser session identifier tied to this conversation.
			 */
			do_action( 'grayfox_conversation_started', $conversation_id, $session_id );
		} else {
			$conversation_id = (int) $conversation->id;
			$msg_count       = (int) $conversation->message_count;
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$conv_table,
				array( 'last_active_at' => current_time( 'mysql' ) ),
				array( 'id' => $conversation_id ),
				array( '%s' ),
				array( '%d' )
			);
		}

		// 4a. Message limit check.
		$msg_limit           = (int) get_option( 'grayfox_session_message_limit', 21 );
		$warm_down_instruction = '';

		if ( $msg_count >= $msg_limit ) {
			$phone = get_option( 'grayfox_business_phone', '' );
			$limit_msg = __( 'You have reached the maximum number of messages for this session. Please start a new conversation or contact us directly.', 'kbfox' );
			if ( $phone ) {
				/* translators: %s: business phone number */
				$limit_msg .= ' ' . sprintf( __( 'Phone: %s', 'kbfox' ), esc_html( $phone ) );
			}
			wp_send_json_error( array( 'limit_reached' => true, 'message' => $limit_msg ), 200 );
		}

		if ( $msg_count === ( $msg_limit - 2 ) ) {
			$phone = get_option( 'grayfox_business_phone', '' );
			$warm_down_instruction = __( 'IMPORTANT: This is one of the last messages in this session. Wrap up the conversation naturally and encourage the user to contact the business directly if they need further assistance.', 'kbfox' );
			if ( $phone ) {
				/* translators: %s: business phone number */
				$warm_down_instruction .= ' ' . sprintf( __( 'Mention the business phone: %s.', 'kbfox' ), esc_html( $phone ) );
			}
		}

		// If the message contains a question, force the LLM to search the KB
		// before answering — prevents responses from training data instead of
		// business-specific knowledge. Applied per-turn only via system injection.
		$has_question = (bool) preg_match(
			'/\?|^\s*(what|how|when|where|who|why|can|do|does|is|are|will|would|could|should)\b/im',
			$clean_message
		);
		if ( $has_question ) {
			$warm_down_instruction .= ( ! empty( $warm_down_instruction ) ? "\n" : '' )
				. 'The customer has asked a question. You MUST call search_knowledge_base before responding. Do not answer from memory or training data. If the knowledge base has no answer, say so honestly.';
		}

		// If the offtopic threshold was reached and no other wrap-up is already set,
		// silently steer the assistant toward closing out the conversation.
		if ( $offtopic_wrap_up && empty( $warm_down_instruction ) ) {
			$phone                 = get_option( 'grayfox_business_phone', '' );
			$warm_down_instruction = 'IMPORTANT: This conversation has drifted significantly off-topic. Gently steer back to how you can help the customer with our business, and encourage them to contact us directly for anything further.';
			if ( $phone ) {
				/* translators: %s: business phone number */
				$warm_down_instruction .= ' ' . sprintf( __( 'Mention the business phone: %s.', 'kbfox' ), esc_html( $phone ) );
			}
		}

		// 5. Save user message to DB.
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$msg_table,
			array(
				'conversation_id' => $conversation_id,
				'role'            => 'user',
				'content'         => $message,
				'created_at'      => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s' )
		);

		// 5a. Increment message count.
		$wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			"UPDATE `{$conv_table}` SET message_count = message_count + 1 WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$conversation_id
		) );

		// 6. Load conversation history.
		$history_rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT role, content FROM `{$msg_table}` WHERE conversation_id = %d ORDER BY id DESC LIMIT 10", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$conversation_id
		), ARRAY_A );

		$history_rows = array_reverse( $history_rows );

		// Remove the user message we just inserted (last element after reverse).
		if ( ! empty( $history_rows ) ) {
			array_pop( $history_rows );
		}

		$history = array_map( function ( $row ) {
			return array(
				'role'    => $row['role'],
				'content' => $row['content'],
			);
		}, $history_rows );

		// 7. Load LLM configuration.
		$encrypted_key = get_option( 'grayfox_llm_api_key', '' );
		$api_key       = grayfox_decrypt( $encrypted_key );
		$provider      = get_option( 'grayfox_llm_provider', 'openai' );
		$model         = get_option( 'grayfox_llm_model', '' );

		if ( empty( $api_key ) || empty( $model ) ) {
			wp_send_json_error( array( 'message' => __( 'The AI assistant is not configured. Please contact the site administrator.', 'kbfox' ) ), 503 );
		}

		// 7a. Build initial messages — no pre-fetched KB; LLM fetches via tools.
		$llm             = new GrayFox_LLM();
		$welcome_message = get_option( 'grayfox_widget_welcome_message', 'Hello! Who am I speaking with today?' );

		// Load any email already captured in a previous turn so the LLM knows
		// not to ask for it again and can reference it directly.
		$captured_email = ! empty( $session_id )
			? (string) get_transient( 'grayfox_captured_email_' . md5( $session_id ) )
			: '';

		$messages = $llm->build_messages( '', $history, $clean_message, $warm_down_instruction, $welcome_message, $captured_email );
		/**
		 * Filters the LLM message array before it enters the agentic loop.
		 *
		 * Each element is an associative array with 'role' (system|user|assistant)
		 * and 'content' (string). Modifications here affect every turn of the
		 * conversation — inject instructions, rewrite content, or append context.
		 *
		 * @since 1.0.0
		 * @param array $messages        Ordered message array ready for the LLM.
		 * @param int   $conversation_id DB row ID of the current conversation.
		 */
		$messages = apply_filters( 'grayfox_chat_messages', $messages, $conversation_id );

		// 7b. Get tool definitions.
		$tool_defs = GrayFox_Tools::get_definitions();

		// 7c. Set conversation context so tools (e.g. capture_customer_email) can reference it.
		GrayFox_Tools::set_context( $conversation_id );

		// 7d. Agentic loop — execute tool calls until the LLM is ready to respond.
		$max_iterations = 5;
		$pre_resolved   = null;

		for ( $i = 0; $i < $max_iterations; $i++ ) {
			$result = $llm->request_with_tools( $provider, $api_key, $model, $messages, $tool_defs );

			if ( 'complete' === $result['status'] ) {
				// LLM gave a direct response (no tools needed). Capture it.
				$pre_resolved = $result['content'];
				if ( ! empty( $result['assistant_message'] ) ) {
					$messages[] = $result['assistant_message'];
				}
				break;
			}

			// LLM called one or more tools — execute each and append results.
			if ( ! empty( $result['assistant_message'] ) ) {
				$messages[] = $result['assistant_message'];
			}

			foreach ( $result['tool_calls'] as $call ) {
				$tool_result = GrayFox_Tools::execute( $call['name'], $call['args'] );
				$messages[]  = array(
					'role'         => 'tool',
					'tool_call_id' => $call['id'],
					'name'         => $call['name'], // needed for Gemini functionResponse
					'content'      => $tool_result,
				);
			}
		}

		// 7e. Persist captured email for future turns.
		// Scan tool results from this turn — if capture_customer_email succeeded,
		// store the email in a session transient so it can be injected on every
		// subsequent turn and the LLM never asks for it again.
		if ( empty( $captured_email ) && ! empty( $session_id ) ) {
			foreach ( $messages as $msg ) {
				if ( ( $msg['role'] ?? '' ) !== 'tool' ) {
					continue;
				}
				$tool_data = json_decode( $msg['content'] ?? '', true );
				if ( ! empty( $tool_data['email_captured'] ) ) {
					set_transient(
						'grayfox_captured_email_' . md5( $session_id ),
						$tool_data['email_captured'],
						86400 // 24 h — matches session TTL
					);
					break;
				}
			}
		}

		// 8. Generate single-use stream token and store context in transient.
		$stream_token = wp_generate_password( 32, false );
		$stream_data  = array(
			'session_id'      => $session_id,
			'conversation_id' => $conversation_id,
			'messages'        => $messages,
			'pre_resolved'    => $pre_resolved, // non-null = LLM responded without tools
		);
		set_transient( 'grayfox_stream_' . $stream_token, $stream_data, 60 );

		// 8. Return JSON with token.
		wp_send_json_success( array(
			'session_id'   => $session_id,
			'stream_token' => $stream_token,
		) );
	}

	/**
	 * Step 2: Handle the GET stream request.
	 *
	 * Validates the single-use stream token, then streams the LLM response
	 * as Server-Sent Events.
	 */
	public function handle_stream(): void {
		// 1. Verify nonce — EventSource passes nonce as query param.
		if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'grayfox_chat_stream' ) ) {
			header( 'Content-Type: text/event-stream' );
			header( 'Cache-Control: no-cache' );
			echo "data: " . wp_json_encode( [ 'error' => 'Unauthorized' ] ) . "\n\n";
			flush();
			exit;
		}

		// 2. Get and sanitize parameters.
		$stream_token = isset( $_GET['stream_token'] ) ? sanitize_text_field( wp_unslash( $_GET['stream_token'] ) ) : '';
		$session_id   = isset( $_GET['session_id'] )   ? sanitize_text_field( wp_unslash( $_GET['session_id'] ) )   : '';

		if ( empty( $stream_token ) || empty( $session_id ) ) {
			$this->send_sse_error( 'Missing stream token or session ID.' );
			exit;
		}

		// 3. Look up transient.
		$stream_data = get_transient( 'grayfox_stream_' . $stream_token );

		if ( ! $stream_data ) {
			$this->send_sse_error( 'Invalid or expired stream token.' );
			exit;
		}

		// 4. Validate session ID match.
		if ( $stream_data['session_id'] !== $session_id ) {
			$this->send_sse_error( 'Session mismatch.' );
			exit;
		}

		// 5. Delete transient immediately (single-use).
		delete_transient( 'grayfox_stream_' . $stream_token );

		$conversation_id = (int) $stream_data['conversation_id'];
		$messages        = $stream_data['messages'];

		// 6. Get LLM configuration.
		$encrypted_key = get_option( 'grayfox_llm_api_key', '' );
		$api_key       = grayfox_decrypt( $encrypted_key );
		$provider      = get_option( 'grayfox_llm_provider', 'openai' );
		$model         = get_option( 'grayfox_llm_model', '' );

		if ( empty( $api_key ) ) {
			$this->send_sse_error( 'LLM not configured.' );
			exit;
		}

		// 7. Set SSE headers.
		if ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
		header( 'Content-Type: text/event-stream; charset=UTF-8' );
		header( 'Cache-Control: no-cache' );
		header( 'X-Accel-Buffering: no' );
		header( 'Connection: keep-alive' );

		// 8. Stream LLM response — or replay a pre-resolved response from the agentic loop.
		$llm           = new GrayFox_LLM();
		$full_response = '';
		$pre_resolved  = $stream_data['pre_resolved'] ?? null;

		try {
			if ( null !== $pre_resolved && '' !== $pre_resolved ) {
				// Agentic loop captured a direct response (no tools used).
				// Calculate a natural delay based on response length and punctuation,
				// then sleep upfront so the delay is felt as "thinking time" (dots)
				// rather than word-by-word drip. The message then appears all at once.
				$full_response  = $pre_resolved;
				$words          = preg_split( '/(\s+)/u', $pre_resolved, -1, PREG_SPLIT_DELIM_CAPTURE );
				$total_delay_us = 0;

				foreach ( $words as $chunk ) {
					if ( '' === $chunk || preg_match( '/^\s+$/u', $chunk ) ) {
						continue;
					}
					$total_delay_us += 35000 + wp_rand( -12000, 12000 ); // ~35ms per word
					if ( preg_match( '/[.!?]$/u', $chunk ) ) {
						$total_delay_us += 100000; // +100ms after sentence end
					} elseif ( preg_match( '/[,;:]$/u', $chunk ) ) {
						$total_delay_us += 40000;  // +40ms after clause
					}
				}

				// Cap at 2.5 s so long responses don't feel sluggish.
				if ( $total_delay_us > 0 ) {
					usleep( min( $total_delay_us, 2500000 ) );
				}

				// Send all tokens immediately after the delay.
				foreach ( $words as $chunk ) {
					if ( '' === $chunk ) {
						continue;
					}
					echo 'data: ' . wp_json_encode( array( 'token' => $chunk ) ) . "\n\n";
					flush();
				}
			} else {
				// Tool calls were made — stream the final LLM response with enriched messages.
				$generator = $llm->send_message( $provider, $api_key, $model, $messages );

				foreach ( $generator as $token ) {
					$full_response .= $token;
					echo 'data: ' . wp_json_encode( array( 'token' => $token ) ) . "\n\n";
					flush();
				}
			}
		} catch ( Throwable $e ) {
			echo 'data: ' . wp_json_encode( array( 'error' => 'LLM error occurred.' ) ) . "\n\n";
			flush();
			exit;
		}

		// 9. Save complete assistant response to DB.
		if ( ! empty( $full_response ) ) {
			global $wpdb;
			$msg_table = esc_sql( GrayFox_DB::get_table( 'messages' ) );
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$msg_table,
				array(
					'conversation_id' => $conversation_id,
					'role'            => 'assistant',
					'content'         => $full_response,
					'created_at'      => current_time( 'mysql' ),
				),
				array( '%d', '%s', '%s', '%s' )
			);

		}

		/**
		 * Fires after the LLM response has been saved to the database and
		 * streamed to the visitor, before the SSE done event is sent.
		 *
		 * @since 1.0.0
		 * @param int    $conversation_id DB row ID of the current conversation.
		 * @param string $full_response   Complete assistant response text.
		 */
		do_action( 'grayfox_chat_response', $conversation_id, $full_response );

		// 10. Send done event.
		echo 'data: ' . wp_json_encode( array( 'done' => true ) ) . "\n\n";
		flush();

		wp_die();
	}

	/**
	 * Emit a single SSE error event (before stream headers are set).
	 *
	 * @param string $message Error message.
	 */
	private function send_sse_error( string $message ): void {
		if ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
		header( 'Content-Type: text/event-stream; charset=UTF-8' );
		header( 'Cache-Control: no-cache' );
		echo 'data: ' . wp_json_encode( array( 'error' => $message ) ) . "\n\n";
		flush();
	}
}
