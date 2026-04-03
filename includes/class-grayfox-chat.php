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

		// 2c. Layer 2: LLM classifier (only reached when regex passes).
		$llm_provider  = get_option( 'grayfox_llm_provider', 'openai' );
		$llm_enc_key   = get_option( 'grayfox_llm_api_key', '' );
		$llm_plain_key = grayfox_decrypt( $llm_enc_key );
		$classifier_model = GrayFox_Settings::get_classifier_model( $llm_provider );

		if ( ! empty( $llm_plain_key ) && ! empty( $classifier_model ) ) {
			$classification = GrayFox_Security::classify_with_llm(
				$message,
				$llm_provider,
				$llm_plain_key,
				$classifier_model,
				$session_id
			);

			if ( 'injection' === $classification || 'offtopic' === $classification ) {
				$reason            = 'injection' === $classification ? 'LLM: prompt injection detected.' : 'LLM: off-topic request.';
				// Use IP as surrogate session key for new sessions to avoid
				// poisoning the md5('') shared bucket for all new visitors.
				$effective_session = ! empty( $session_id ) ? $session_id : 'ip:' . $client_ip;
				$strike_count      = GrayFox_Security::add_strikes( $effective_session, 1 );

				if ( $strike_count >= GrayFox_Security::MAX_STRIKES ) {
					GrayFox_Security::block( $effective_session, $client_ip );
					GrayFox_Security::log_threat( $effective_session, $client_ip, $message, $reason );
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
			}
		}

		// 3. Generate session ID if missing; track whether this is a brand-new session.
		$is_new_session = empty( $session_id );
		if ( $is_new_session ) {
			$session_id = wp_generate_uuid4();
		}

		global $wpdb;

		$conv_table = GrayFox_DB::get_table( 'conversations' );
		$msg_table  = GrayFox_DB::get_table( 'messages' );

		// 3a. Session TTL check — reject stale sessions (>24h inactive).
		if ( ! $is_new_session ) {
			$ttl_row = $wpdb->get_row( $wpdb->prepare(
				"SELECT last_active_at FROM `{$conv_table}` WHERE session_id = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$session_id
			) );
			if ( $ttl_row && ! empty( $ttl_row->last_active_at ) ) {
				$last_active = strtotime( $ttl_row->last_active_at );
				if ( ( time() - $last_active ) > 86400 ) {
					$phone = get_option( 'grayfox_business_phone', '' );
					$msg   = __( 'Your session has expired after 24 hours of inactivity. Please start a new conversation.', 'grayfox' );
					if ( $phone ) {
						/* translators: %s: business phone number */
						$msg .= ' ' . sprintf( __( 'You can also reach us at %s.', 'grayfox' ), esc_html( $phone ) );
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
				$msg   = __( 'Too many chat sessions from your location. Please try again later.', 'grayfox' );
				if ( $phone ) {
					/* translators: %s: business phone number */
					$msg .= ' ' . sprintf( __( 'You can also reach us at %s.', 'grayfox' ), esc_html( $phone ) );
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
		$conversation = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, message_count FROM `{$conv_table}` WHERE session_id = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$session_id
		) );

		if ( ! $conversation ) {
			$wpdb->insert(
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
		} else {
			$conversation_id = (int) $conversation->id;
			$msg_count       = (int) $conversation->message_count;
			$wpdb->update(
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
			$limit_msg = __( 'You have reached the maximum number of messages for this session. Please start a new conversation or contact us directly.', 'grayfox' );
			if ( $phone ) {
				/* translators: %s: business phone number */
				$limit_msg .= ' ' . sprintf( __( 'Phone: %s', 'grayfox' ), esc_html( $phone ) );
			}
			wp_send_json_error( array( 'limit_reached' => true, 'message' => $limit_msg ), 200 );
		}

		if ( $msg_count === ( $msg_limit - 2 ) ) {
			$phone = get_option( 'grayfox_business_phone', '' );
			$warm_down_instruction = __( 'IMPORTANT: This is one of the last messages in this session. Wrap up the conversation naturally and encourage the user to contact the business directly if they need further assistance.', 'grayfox' );
			if ( $phone ) {
				/* translators: %s: business phone number */
				$warm_down_instruction .= ' ' . sprintf( __( 'Mention the business phone: %s.', 'grayfox' ), esc_html( $phone ) );
			}
		}

		// 5. Save user message to DB.
		$wpdb->insert(
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
		$wpdb->query( $wpdb->prepare(
			"UPDATE `{$conv_table}` SET message_count = message_count + 1 WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$conversation_id
		) );

		// 6. Load conversation history.
		$history_rows = $wpdb->get_results( $wpdb->prepare(
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
			wp_send_json_error( array( 'message' => __( 'The AI assistant is not configured. Please contact the site administrator.', 'grayfox' ) ), 503 );
		}

		// 7a. Build initial messages — no pre-fetched KB; LLM fetches via tools.
		$llm             = new GrayFox_LLM();
		$welcome_message = get_option( 'grayfox_widget_welcome_message', 'Hello! Who am I speaking with today?' );
		$messages        = $llm->build_messages( '', $history, $message, $warm_down_instruction, $welcome_message );

		// 7b. Get tool definitions for this license tier.
		$license_tier = (string) get_option( 'grayfox_license_tier', '' );
		$tool_defs    = GrayFox_Tools::get_definitions_for_tier( $license_tier );

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
					$total_delay_us += 35000 + mt_rand( -12000, 12000 ); // ~35ms per word
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
			$msg_table = GrayFox_DB::get_table( 'messages' );
			$wpdb->insert(
				$msg_table,
				array(
					'conversation_id' => $conversation_id,
					'role'            => 'assistant',
					'content'         => $full_response,
					'created_at'      => current_time( 'mysql' ),
				),
				array( '%d', '%s', '%s', '%s' )
			);

			// 9a. Check for booking intent in the LLM response and schedule async processing.
			if ( class_exists( 'GrayFox_Booking' ) ) {
				$intent = GrayFox_Booking::get_instance()->extract_booking_intent( $full_response );
				if ( $intent && function_exists( 'as_enqueue_async_action' ) ) {
					as_enqueue_async_action( 'grayfox_process_booking', array( 'data' => $intent ) );
				}
			}
		}

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
