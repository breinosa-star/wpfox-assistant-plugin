<?php
/**
 * LLM provider client — streaming responses.
 *
 * @package GrayFox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GrayFox_LLM
 *
 * Sends messages to LLM providers and yields streaming tokens.
 * Supports: openai, anthropic, gemini, groq.
 */
if ( ! class_exists( 'GrayFox_LLM' ) ) {
class GrayFox_LLM {

	/**
	 * Send a message to the specified LLM provider and stream the response.
	 *
	 * @param string $provider  One of: openai, anthropic, gemini, groq.
	 * @param string   $api_key   Provider API key (plaintext).
	 * @param string   $model     Model identifier.
	 * @param array    $messages  Messages array in OpenAI format.
	 * @return string Complete response text.
	 */
	public function send_message( string $provider, string $api_key, string $model, array $messages ): string {
		return match ( $provider ) {
			'openai'    => $this->fetch_openai( $api_key, $model, $messages ),
			'anthropic' => $this->fetch_anthropic( $api_key, $model, $messages ),
			'gemini'    => $this->fetch_gemini( $api_key, $model, $messages ),
			'groq'      => $this->fetch_openai( $api_key, $model, $messages, 'https://api.groq.com/openai/v1/chat/completions' ),
			default     => $this->fetch_openai( $api_key, $model, $messages ),
		};
	}

	/**
	 * Build a messages array from knowledge base, history, and user input.
	 *
	 * @param string $knowledge_json            Consolidated knowledge base JSON.
	 * @param array  $history                   Array of previous messages (role, content).
	 * @param string $user_message              The current user message.
	 * @param string $extra_system_instruction  Optional text appended to the system prompt.
	 * @return array OpenAI-format messages array.
	 */
	public function build_messages( string $knowledge_json, array $history, string $user_message, string $extra_system_instruction = '', string $welcome_message = '', string $captured_email = '' ): array {
		// Choose and inject the correct KB section.
		if ( ! empty( $knowledge_json ) ) {
			// Legacy pre-fetch mode (e.g. site-builder): inject KB content directly.
			$kb_section = str_replace( '{{KNOWLEDGE_JSON}}', $knowledge_json, GRAYFOX_PROMPT_CHAT_KB_PREFETCH );
		} else {
			// Tool mode: LLM fetches KB via the search_knowledge_base tool.
			$kb_section = GRAYFOX_PROMPT_CHAT_KB_TOOL;
		}

		$system_content = str_replace( '{{KB_SECTION}}', $kb_section, GRAYFOX_PROMPT_CHAT_SYSTEM );

		// Embed the welcome message as system context so the LLM always knows
		// what was already said to the customer — on every turn, not just the first.
		$welcome_section = ! empty( $welcome_message )
			? 'The customer was already greeted with: "' . $welcome_message . '"'
			: 'No specific welcome message was shown — treat this as the start of a fresh conversation.';
		$system_content  = str_replace( '{{WELCOME_SECTION}}', $welcome_section, $system_content );

		// Inject captured email so the LLM never asks for it again.
		if ( ! empty( $captured_email ) ) {
			$system_content .= "\n\nEMAIL ALREADY CAPTURED: You have already successfully saved this customer's email: {$captured_email}. Do not ask for it again under any circumstances. If they ask about follow-up, pricing details, or next steps, reference it directly: \"We'll send that to {$captured_email}.\"";
		}

		// Inject business contact info so the LLM can use it as a fallback
		// if the email capture tool fails after retrying.
		$biz_phone = get_option( 'grayfox_business_phone', '' );
		$biz_email = get_option( 'grayfox_business_email', '' );
		$contact_parts = array();
		if ( ! empty( $biz_phone ) ) $contact_parts[] = 'Phone: ' . $biz_phone;
		if ( ! empty( $biz_email ) ) $contact_parts[] = 'Email: ' . $biz_email;
		if ( ! empty( $contact_parts ) ) {
			$system_content .= "\n\nBUSINESS CONTACT INFO: " . implode( ' | ', $contact_parts ) . '. Use this only as a fallback if the email capture tool fails after retrying.';
		}

		if ( ! empty( $extra_system_instruction ) ) {
			$system_content .= "\n" . $extra_system_instruction;
		}

		$messages = array(
			array(
				'role'    => 'system',
				'content' => $system_content,
			),
		);

		foreach ( $history as $entry ) {
			$role    = in_array( $entry['role'] ?? '', array( 'user', 'assistant', 'system' ), true )
				? $entry['role']
				: 'user';
			$content = $entry['content'] ?? '';
			if ( ! empty( $content ) ) {
				$messages[] = array(
					'role'    => $role,
					'content' => $content,
				);
			}
		}

		$messages[] = array(
			'role'    => 'user',
			'content' => $user_message,
		);

		return $messages;
	}

	/**
	 * Send a non-streaming JSON-mode request to the specified LLM provider.
	 *
	 * Forces JSON output via provider-specific mechanisms and returns the raw
	 * response string. Returns empty string on any failure.
	 *
	 * @param string $provider    One of: openai, anthropic, gemini, groq.
	 * @param string $api_key     Provider API key (plaintext).
	 * @param string $model       Model identifier.
	 * @param array  $messages    Messages array in OpenAI format.
	 * @param float  $temperature Sampling temperature (0.0 = deterministic).
	 * @return string Raw JSON string, or empty string on failure.
	 */
	public function request_json( string $provider, string $api_key, string $model, array $messages, float $temperature = 0.0 ): string {
		try {
			return match ( $provider ) {
				'openai'    => $this->json_openai( $api_key, $model, $messages, $temperature ),
				'anthropic' => $this->json_anthropic( $api_key, $model, $messages, $temperature ),
				'gemini'    => $this->json_gemini( $api_key, $model, $messages, $temperature ),
				'groq'      => $this->json_openai( $api_key, $model, $messages, $temperature, 'https://api.groq.com/openai/v1/chat/completions' ),
				default     => $this->json_openai( $api_key, $model, $messages, $temperature ),
			};
		} catch ( \Throwable $e ) {
			return '';
		}
	}

	/**
	 * Non-streaming plain-text request (no JSON mode enforced).
	 *
	 * Use this for calls that return prose rather than structured JSON — e.g.
	 * intent translation, short summaries. Returns the raw text content.
	 *
	 * @param string $provider    Provider slug.
	 * @param string $api_key     API key.
	 * @param string $model       Model identifier.
	 * @param array  $messages    Chat messages array.
	 * @param float  $temperature Sampling temperature.
	 * @return string Plain text response, or empty string on failure.
	 */
	public function request_text( string $provider, string $api_key, string $model, array $messages, float $temperature = 0.3 ): string {
		try {
			return match ( $provider ) {
				'openai'    => $this->text_openai( $api_key, $model, $messages, $temperature ),
				'anthropic' => $this->text_anthropic( $api_key, $model, $messages, $temperature ),
				'gemini'    => $this->text_gemini( $api_key, $model, $messages, $temperature ),
				'groq'      => $this->text_openai( $api_key, $model, $messages, $temperature, 'https://api.groq.com/openai/v1/chat/completions' ),
				default     => $this->text_openai( $api_key, $model, $messages, $temperature ),
			};
		} catch ( \Throwable $e ) {
			return '';
		}
	}

	/**
	 * Plain-text request to OpenAI — no response_format constraint.
	 */
	private function text_openai( string $api_key, string $model, array $messages, float $temperature, string $endpoint = 'https://api.openai.com/v1/chat/completions' ): string {
		$max_tokens = 256; // Translation calls are short.
		$payload    = wp_json_encode( array(
			'model'                 => $model,
			'messages'              => $messages,
			'temperature'           => $temperature,
			'max_completion_tokens' => $max_tokens,
		) );

		$response = wp_remote_post( $endpoint, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'body'    => $payload,
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return trim( $body['choices'][0]['message']['content'] ?? '' );
	}

	/**
	 * Plain-text request to Anthropic — no JSON prefill.
	 */
	private function text_anthropic( string $api_key, string $model, array $messages, float $temperature ): string {
		$system_content = '';
		$filtered       = array();
		foreach ( $messages as $msg ) {
			if ( 'system' === $msg['role'] ) {
				$system_content = $msg['content'];
			} else {
				$filtered[] = $msg;
			}
		}

		$body = array(
			'model'       => $model,
			'max_tokens'  => 256,
			'temperature' => $temperature,
			'messages'    => $filtered,
		);
		if ( ! empty( $system_content ) ) {
			$body['system'] = $system_content;
		}

		$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
			'headers' => array(
				'x-api-key'         => $api_key,
				'anthropic-version' => '2023-06-01',
				'Content-Type'      => 'application/json',
			),
			'body'    => wp_json_encode( $body ),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$result = json_decode( wp_remote_retrieve_body( $response ), true );
		return trim( $result['content'][0]['text'] ?? '' );
	}

	/**
	 * Plain-text request to Google Gemini — no JSON MIME type.
	 */
	private function text_gemini( string $api_key, string $model, array $messages, float $temperature ): string {
		$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent?key=' . rawurlencode( $api_key );

		$contents       = array();
		$system_content = '';
		foreach ( $messages as $msg ) {
			if ( 'system' === $msg['role'] ) {
				$system_content = $msg['content'];
				continue;
			}
			$role       = ( 'assistant' === $msg['role'] ) ? 'model' : 'user';
			$contents[] = array(
				'role'  => $role,
				'parts' => array( array( 'text' => $msg['content'] ) ),
			);
		}

		$body = array(
			'contents'         => $contents,
			'generationConfig' => array(
				'maxOutputTokens' => 256,
				'temperature'     => $temperature,
			),
		);
		if ( ! empty( $system_content ) ) {
			$body['systemInstruction'] = array(
				'parts' => array( array( 'text' => $system_content ) ),
			);
		}

		$response = wp_remote_post( $url, array(
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( $body ),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$result = json_decode( wp_remote_retrieve_body( $response ), true );
		return trim( $result['candidates'][0]['content']['parts'][0]['text'] ?? '' );
	}

	/**
	 * Non-streaming JSON request to OpenAI or OpenAI-compatible endpoint.
	 */
	private function json_openai( string $api_key, string $model, array $messages, float $temperature, string $endpoint = 'https://api.openai.com/v1/chat/completions' ): string {
		$max_tokens = max( 64, min( 32000, (int) get_option( 'grayfox_llm_max_tokens', 4096 ) ) );
		$payload    = wp_json_encode( array(
			'model'                  => $model,
			'messages'               => $messages,
			'temperature'            => $temperature,
			'max_completion_tokens'  => $max_tokens,
			'response_format'        => array( 'type' => 'json_object' ),
		) );

		$response = wp_remote_post( $endpoint, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'body'    => $payload,
			'timeout' => 90,
		) );

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$raw  = wp_remote_retrieve_body( $response );
		$body = json_decode( $raw, true );

		if ( isset( $body['error'] ) ) {
			return '';
		}

		return $body['choices'][0]['message']['content'] ?? '';
	}

	/**
	 * Non-streaming JSON request to Anthropic.
	 */
	private function json_anthropic( string $api_key, string $model, array $messages, float $temperature ): string {
		$system_content = '';
		$filtered       = array();
		foreach ( $messages as $msg ) {
			if ( 'system' === $msg['role'] ) {
				$system_content = $msg['content'];
			} else {
				$filtered[] = $msg;
			}
		}

		// JSON prefill: append assistant turn starting with '{' to force JSON output.
		$filtered[] = array( 'role' => 'assistant', 'content' => '{' );

		$max_tokens = max( 64, min( 32000, (int) get_option( 'grayfox_llm_max_tokens', 4096 ) ) );
		$body       = array(
			'model'       => $model,
			'max_tokens'  => $max_tokens,
			'temperature' => $temperature,
			'messages'    => $filtered,
		);
		if ( ! empty( $system_content ) ) {
			$body['system'] = $system_content;
		}

		$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
			'headers' => array(
				'x-api-key'         => $api_key,
				'anthropic-version' => '2023-06-01',
				'Content-Type'      => 'application/json',
			),
			'body'    => wp_json_encode( $body ),
			'timeout' => 90,
		) );

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$result = json_decode( wp_remote_retrieve_body( $response ), true );
		$text   = $result['content'][0]['text'] ?? '';
		// Prepend the '{' we used as prefill since Anthropic continues from it.
		return '{' . $text;
	}

	/**
	 * Non-streaming JSON request to Google Gemini.
	 */
	private function json_gemini( string $api_key, string $model, array $messages, float $temperature ): string {
		$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent?key=' . rawurlencode( $api_key );

		$contents       = array();
		$system_content = '';
		foreach ( $messages as $msg ) {
			if ( 'system' === $msg['role'] ) {
				$system_content = $msg['content'];
				continue;
			}
			$role       = ( 'assistant' === $msg['role'] ) ? 'model' : 'user';
			$contents[] = array(
				'role'  => $role,
				'parts' => array( array( 'text' => $msg['content'] ) ),
			);
		}

		$max_tokens = max( 64, min( 32000, (int) get_option( 'grayfox_llm_max_tokens', 4096 ) ) );
		$body       = array(
			'contents'         => $contents,
			'generationConfig' => array(
				'maxOutputTokens'  => $max_tokens,
				'temperature'      => $temperature,
				'responseMimeType' => 'application/json',
			),
		);
		if ( ! empty( $system_content ) ) {
			$body['systemInstruction'] = array(
				'parts' => array( array( 'text' => $system_content ) ),
			);
		}

		$response = wp_remote_post( $url, array(
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( $body ),
			'timeout' => 90,
		) );

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$result = json_decode( wp_remote_retrieve_body( $response ), true );
		return $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
	}

	/**
	 * Non-streaming vision request — sends an image alongside a text prompt.
	 *
	 * The caller passes a standard messages array (same format as request_json()).
	 * The image is injected into the last user message internally using each
	 * provider's native multimodal format. Returns raw JSON string or empty string.
	 *
	 * Supported providers: openai, anthropic, gemini.
	 * Groq vision support is partial; callers should check provider_supports_vision()
	 * before calling this method and skip gracefully if unsupported.
	 *
	 * @param string $provider     One of: openai, anthropic, gemini.
	 * @param string $api_key      Provider API key (plaintext).
	 * @param string $model        Model identifier (must support vision).
	 * @param array  $messages     Messages array in OpenAI format.
	 * @param string $image_base64 Raw base64-encoded image data (no data URI prefix).
	 * @param string $media_type   MIME type: image/jpeg, image/png, image/gif, image/webp.
	 * @param float  $temperature  Sampling temperature.
	 * @return string Raw JSON string, or empty string on failure.
	 */
	public function request_vision(
		string $provider,
		string $api_key,
		string $model,
		array  $messages,
		string $image_base64,
		string $media_type  = 'image/jpeg',
		float  $temperature = 0.1
	): string {
		try {
			return match ( $provider ) {
				'openai'    => $this->vision_openai(   $api_key, $model, $messages, $image_base64, $media_type, $temperature ),
				'anthropic' => $this->vision_anthropic( $api_key, $model, $messages, $image_base64, $media_type, $temperature ),
				'gemini'    => $this->vision_gemini(   $api_key, $model, $messages, $image_base64, $media_type, $temperature ),
				default     => '', // Groq / unknown: caller must check provider_supports_vision() first.
			};
		} catch ( \Throwable $e ) {
			return '';
		}
	}

	/**
	 * Vision request to OpenAI — injects image into last user message content array.
	 */
	private function vision_openai( string $api_key, string $model, array $messages, string $image_base64, string $media_type, float $temperature ): string {
		// Inject image into the last user message.
		$last_idx = count( $messages ) - 1;
		for ( $i = $last_idx; $i >= 0; $i-- ) {
			if ( 'user' === ( $messages[ $i ]['role'] ?? '' ) ) {
				$text_content = $messages[ $i ]['content'];
				$messages[ $i ]['content'] = array(
					array( 'type' => 'text', 'text' => is_string( $text_content ) ? $text_content : '' ),
					array(
						'type'      => 'image_url',
						'image_url' => array( 'url' => 'data:' . $media_type . ';base64,' . $image_base64 ),
					),
				);
				break;
			}
		}

		$payload = wp_json_encode( array(
			'model'                 => $model,
			'messages'              => $messages,
			'temperature'           => $temperature,
			'max_completion_tokens' => 1024,
			'response_format'       => array( 'type' => 'json_object' ),
		) );

		$response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'body'    => $payload,
			'timeout' => 60,
		) );

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$raw  = wp_remote_retrieve_body( $response );
		$body = json_decode( $raw, true );

		if ( isset( $body['error'] ) ) {
			return '';
		}

		return $body['choices'][0]['message']['content'] ?? '';
	}

	/**
	 * Vision request to Anthropic — injects image block before text in last user message.
	 */
	private function vision_anthropic( string $api_key, string $model, array $messages, string $image_base64, string $media_type, float $temperature ): string {
		$system_content = '';
		$filtered       = array();
		foreach ( $messages as $msg ) {
			if ( 'system' === $msg['role'] ) {
				$system_content = $msg['content'];
			} else {
				$filtered[] = $msg;
			}
		}

		// Inject image into the last user message.
		$last_idx = count( $filtered ) - 1;
		for ( $i = $last_idx; $i >= 0; $i-- ) {
			if ( 'user' === ( $filtered[ $i ]['role'] ?? '' ) ) {
				$text_content = $filtered[ $i ]['content'];
				$filtered[ $i ]['content'] = array(
					array(
						'type'   => 'image',
						'source' => array(
							'type'       => 'base64',
							'media_type' => $media_type,
							'data'       => $image_base64,
						),
					),
					array( 'type' => 'text', 'text' => is_string( $text_content ) ? $text_content : '' ),
				);
				break;
			}
		}

		// JSON prefill.
		$filtered[] = array( 'role' => 'assistant', 'content' => '{' );

		$body = array(
			'model'       => $model,
			'max_tokens'  => 1024,
			'temperature' => $temperature,
			'messages'    => $filtered,
		);
		if ( ! empty( $system_content ) ) {
			$body['system'] = $system_content;
		}

		$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
			'headers' => array(
				'x-api-key'         => $api_key,
				'anthropic-version' => '2023-06-01',
				'Content-Type'      => 'application/json',
			),
			'body'    => wp_json_encode( $body ),
			'timeout' => 60,
		) );

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$result = json_decode( wp_remote_retrieve_body( $response ), true );
		$text   = $result['content'][0]['text'] ?? '';
		return '{' . $text;
	}

	/**
	 * Vision request to Google Gemini — uses inlineData part.
	 */
	private function vision_gemini( string $api_key, string $model, array $messages, string $image_base64, string $media_type, float $temperature ): string {
		$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent?key=' . rawurlencode( $api_key );

		$contents       = array();
		$system_content = '';
		foreach ( $messages as $msg ) {
			if ( 'system' === $msg['role'] ) {
				$system_content = $msg['content'];
				continue;
			}
			$role = ( 'assistant' === $msg['role'] ) ? 'model' : 'user';

			// Inject image into the last user turn.
			if ( 'user' === $role ) {
				$text = is_string( $msg['content'] ) ? $msg['content'] : '';
				$contents[] = array(
					'role'  => $role,
					'parts' => array(
						array( 'inlineData' => array( 'mimeType' => $media_type, 'data' => $image_base64 ) ),
						array( 'text' => $text ),
					),
				);
			} else {
				$contents[] = array(
					'role'  => $role,
					'parts' => array( array( 'text' => is_string( $msg['content'] ) ? $msg['content'] : '' ) ),
				);
			}
		}

		$body = array(
			'contents'         => $contents,
			'generationConfig' => array(
				'maxOutputTokens'  => 1024,
				'temperature'      => $temperature,
				'responseMimeType' => 'application/json',
			),
		);
		if ( ! empty( $system_content ) ) {
			$body['systemInstruction'] = array(
				'parts' => array( array( 'text' => $system_content ) ),
			);
		}

		$response = wp_remote_post( $url, array(
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( $body ),
			'timeout' => 60,
		) );

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$result = json_decode( wp_remote_retrieve_body( $response ), true );
		return $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
	}

	/* ------------------------------------------------------------------
	 * Private: provider-specific blocking fetch methods
	 * ------------------------------------------------------------------ */

	/**
	 * Blocking fetch from OpenAI (or OpenAI-compatible endpoint).
	 *
	 * @param string $api_key  API key.
	 * @param string $model    Model name.
	 * @param array  $messages Messages array.
	 * @param string $endpoint API endpoint URL.
	 * @return string Complete response text.
	 */
	private function fetch_openai( string $api_key, string $model, array $messages, string $endpoint = 'https://api.openai.com/v1/chat/completions' ): string {
		$max_tokens = max( 64, min( 32000, (int) get_option( 'grayfox_llm_max_tokens', 1024 ) ) );
		$response   = wp_remote_post( $endpoint, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( array(
				'model'                 => $model,
				'messages'              => $messages,
				'max_completion_tokens' => $max_tokens,
			) ),
			'timeout' => 60,
		) );

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return $body['choices'][0]['message']['content'] ?? '';
	}

	/**
	 * Blocking fetch from Anthropic.
	 *
	 * @param string $api_key  API key.
	 * @param string $model    Model name.
	 * @param array  $messages Messages array.
	 * @return string Complete response text.
	 */
	private function fetch_anthropic( string $api_key, string $model, array $messages ): string {
		$system_content = '';
		$filtered       = array();
		foreach ( $messages as $msg ) {
			if ( 'system' === $msg['role'] ) {
				$system_content = $msg['content'];
			} else {
				$filtered[] = $msg;
			}
		}

		$max_tokens = max( 64, min( 32000, (int) get_option( 'grayfox_llm_max_tokens', 1024 ) ) );
		$body       = array(
			'model'      => $model,
			'max_tokens' => $max_tokens,
			'messages'   => $filtered,
		);
		if ( ! empty( $system_content ) ) {
			$body['system'] = $system_content;
		}

		$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
			'headers' => array(
				'x-api-key'         => $api_key,
				'anthropic-version' => '2023-06-01',
				'Content-Type'      => 'application/json',
			),
			'body'    => wp_json_encode( $body ),
			'timeout' => 60,
		) );

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
		return $decoded['content'][0]['text'] ?? '';
	}

	/**
	 * Blocking fetch from Google Gemini.
	 *
	 * @param string $api_key  API key.
	 * @param string $model    Model name.
	 * @param array  $messages Messages array.
	 * @return string Complete response text.
	 */
	private function fetch_gemini( string $api_key, string $model, array $messages ): string {
		$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent?key=' . rawurlencode( $api_key );

		$contents       = array();
		$system_content = '';
		foreach ( $messages as $msg ) {
			if ( 'system' === $msg['role'] ) {
				$system_content = $msg['content'];
				continue;
			}
			$role       = ( 'assistant' === $msg['role'] ) ? 'model' : 'user';
			$contents[] = array(
				'role'  => $role,
				'parts' => array( array( 'text' => $msg['content'] ) ),
			);
		}

		$max_tokens = max( 64, min( 32000, (int) get_option( 'grayfox_llm_max_tokens', 1024 ) ) );
		$body       = array(
			'contents'         => $contents,
			'generationConfig' => array( 'maxOutputTokens' => $max_tokens ),
		);
		if ( ! empty( $system_content ) ) {
			$body['systemInstruction'] = array(
				'parts' => array( array( 'text' => $system_content ) ),
			);
		}

		$response = wp_remote_post( $url, array(
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( $body ),
			'timeout' => 60,
		) );

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
		return $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
	}

	/* ------------------------------------------------------------------
	 * Public: tool-calling (agentic) interface
	 * ------------------------------------------------------------------ */

	/**
	 * Make a non-streaming LLM call with tool definitions.
	 *
	 * Returns a normalized result array:
	 *   ['status' => 'tool_calls', 'tool_calls' => [...], 'assistant_message' => [...]]
	 *   ['status' => 'complete',   'content'    => '...', 'assistant_message' => [...]]
	 *
	 * tool_calls entries: ['id' => string, 'name' => string, 'args' => array]
	 * assistant_message:  OpenAI-format message array to append to history.
	 *
	 * @param string  $provider         Provider key.
	 * @param string  $api_key          Plaintext API key.
	 * @param string  $model            Model identifier.
	 * @param array   $messages         Messages array (OpenAI format, including tool messages).
	 * @param array[] $tool_definitions OpenAI-format tool definitions.
	 * @return array Normalized result.
	 */
	public function request_with_tools( string $provider, string $api_key, string $model, array $messages, array $tool_definitions ): array {
		try {
			return match ( $provider ) {
				'openai'    => $this->tools_openai( $api_key, $model, $messages, $tool_definitions ),
				'groq'      => $this->tools_openai( $api_key, $model, $messages, $tool_definitions, 'https://api.groq.com/openai/v1/chat/completions' ),
				'anthropic' => $this->tools_anthropic( $api_key, $model, $messages, $tool_definitions ),
				'gemini'    => $this->tools_gemini( $api_key, $model, $messages, $tool_definitions ),
				default     => $this->tools_openai( $api_key, $model, $messages, $tool_definitions ),
			};
		} catch ( \Throwable $e ) {
			return array( 'status' => 'complete', 'content' => '', 'tool_calls' => array(), 'assistant_message' => array() );
		}
	}

	/* ------------------------------------------------------------------
	 * Private: provider-specific tool-calling methods
	 * ------------------------------------------------------------------ */

	/**
	 * Tool-calling for OpenAI and OpenAI-compatible endpoints (Groq).
	 *
	 * Messages array is already in OpenAI format — no translation needed.
	 * Tool result messages use role='tool' with tool_call_id.
	 */
	private function tools_openai( string $api_key, string $model, array $messages, array $tool_definitions, string $endpoint = 'https://api.openai.com/v1/chat/completions' ): array {
		$max_tokens = max( 64, min( 32000, (int) get_option( 'grayfox_llm_max_tokens', 4096 ) ) );

		$payload = wp_json_encode( array(
			'model'                 => $model,
			'messages'              => $messages,
			'tools'                 => $tool_definitions,
			'max_completion_tokens' => $max_tokens,
			'parallel_tool_calls'   => false,
		) );

		$response = wp_remote_post( $endpoint, array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'body'    => $payload,
			'timeout' => 90,
		) );

		if ( is_wp_error( $response ) ) {
			return array( 'status' => 'complete', 'content' => '', 'tool_calls' => array(), 'assistant_message' => array() );
		}

		$raw_body  = wp_remote_retrieve_body( $response );
		$http_code = wp_remote_retrieve_response_code( $response );
		$body      = json_decode( $raw_body, true );

		if ( ! is_array( $body ) ) {
			return array( 'status' => 'error', 'content' => '', 'tool_calls' => array(), 'assistant_message' => array() );
		}

		if ( isset( $body['error'] ) ) {
			return array( 'status' => 'error', 'content' => '', 'tool_calls' => array(), 'assistant_message' => array() );
		}

		$choice        = $body['choices'][0] ?? array();
		$finish_reason = $choice['finish_reason'] ?? 'stop';
		$message       = $choice['message'] ?? array();

		if ( 'tool_calls' === $finish_reason && ! empty( $message['tool_calls'] ) ) {
			$normalized = array();
			foreach ( $message['tool_calls'] as $tc ) {
				$normalized[] = array(
					'id'   => $tc['id'] ?? '',
					'name' => $tc['function']['name'] ?? '',
					'args' => json_decode( $tc['function']['arguments'] ?? '{}', true ) ?? array(),
				);
			}
			return array(
				'status'            => 'tool_calls',
				'tool_calls'        => $normalized,
				'content'           => '',
				'assistant_message' => $message,
			);
		}

		return array(
			'status'            => 'complete',
			'tool_calls'        => array(),
			'content'           => $message['content'] ?? '',
			'assistant_message' => $message,
		);
	}

	/**
	 * Tool-calling for Anthropic.
	 *
	 * Translates from OpenAI internal format → Anthropic request format,
	 * then normalizes the response back to OpenAI format for the agentic loop.
	 */
	private function tools_anthropic( string $api_key, string $model, array $messages, array $tool_definitions ): array {
		// Translate tool definitions: parameters → input_schema.
		$anthropic_tools = array_map( static function ( $def ) {
			return array(
				'name'         => $def['function']['name'],
				'description'  => $def['function']['description'],
				'input_schema' => $def['function']['parameters'],
			);
		}, $tool_definitions );

		// Separate system message; convert tool messages to Anthropic format.
		$system_content      = '';
		$anthropic_messages  = array();

		foreach ( $messages as $msg ) {
			$role = $msg['role'] ?? 'user';

			if ( 'system' === $role ) {
				$system_content = $msg['content'] ?? '';

			} elseif ( 'tool' === $role ) {
				// Tool result: Anthropic expects role='user' with tool_result content block.
				$anthropic_messages[] = array(
					'role'    => 'user',
					'content' => array(
						array(
							'type'        => 'tool_result',
							'tool_use_id' => $msg['tool_call_id'] ?? '',
							'content'     => $msg['content'] ?? '',
						),
					),
				);

			} elseif ( 'assistant' === $role && ! empty( $msg['tool_calls'] ) ) {
				// Assistant tool call message: convert to Anthropic content blocks.
				$blocks = array();
				if ( ! empty( $msg['content'] ) ) {
					$blocks[] = array( 'type' => 'text', 'text' => $msg['content'] );
				}
				foreach ( $msg['tool_calls'] as $tc ) {
					$blocks[] = array(
						'type'  => 'tool_use',
						'id'    => $tc['id'] ?? '',
						'name'  => $tc['function']['name'] ?? '',
						'input' => json_decode( $tc['function']['arguments'] ?? '{}', true ) ?? array(),
					);
				}
				$anthropic_messages[] = array( 'role' => 'assistant', 'content' => $blocks );

			} else {
				$anthropic_messages[] = array( 'role' => $role, 'content' => $msg['content'] ?? '' );
			}
		}

		$max_tokens = max( 64, min( 32000, (int) get_option( 'grayfox_llm_max_tokens', 4096 ) ) );
		$body       = array(
			'model'      => $model,
			'max_tokens' => $max_tokens,
			'tools'      => $anthropic_tools,
			'messages'   => $anthropic_messages,
		);
		if ( ! empty( $system_content ) ) {
			$body['system'] = $system_content;
		}

		$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
			'headers' => array(
				'x-api-key'         => $api_key,
				'anthropic-version' => '2023-06-01',
				'Content-Type'      => 'application/json',
			),
			'body'    => wp_json_encode( $body ),
			'timeout' => 90,
		) );

		if ( is_wp_error( $response ) ) {
			return array( 'status' => 'error', 'content' => '', 'tool_calls' => array(), 'assistant_message' => array() );
		}

		$raw_anthropic  = wp_remote_retrieve_body( $response );
		$http_anthropic = wp_remote_retrieve_response_code( $response );
		$result         = json_decode( $raw_anthropic, true );

		if ( ! is_array( $result ) ) {
			return array( 'status' => 'error', 'content' => '', 'tool_calls' => array(), 'assistant_message' => array() );
		}

		if ( isset( $result['error'] ) ) {
			return array( 'status' => 'error', 'content' => '', 'tool_calls' => array(), 'assistant_message' => array() );
		}

		$stop_reason = $result['stop_reason'] ?? 'end_turn';
		$blocks      = $result['content'] ?? array();

		if ( 'tool_use' === $stop_reason ) {
			$normalized        = array();
			$openai_tool_calls = array();
			$text_parts        = array();

			foreach ( $blocks as $block ) {
				if ( 'tool_use' === ( $block['type'] ?? '' ) ) {
					$id    = $block['id'] ?? '';
					$name  = $block['name'] ?? '';
					$input = $block['input'] ?? array();

					$normalized[]        = array( 'id' => $id, 'name' => $name, 'args' => $input );
					$openai_tool_calls[] = array(
						'id'       => $id,
						'type'     => 'function',
						'function' => array( 'name' => $name, 'arguments' => wp_json_encode( $input ) ),
					);
				} elseif ( 'text' === ( $block['type'] ?? '' ) ) {
					$text_parts[] = $block['text'] ?? '';
				}
			}

			$text_content = implode( '', $text_parts );

			return array(
				'status'            => 'tool_calls',
				'tool_calls'        => $normalized,
				'content'           => '',
				'assistant_message' => array(
					'role'       => 'assistant',
					'content'    => $text_content ?: null,
					'tool_calls' => $openai_tool_calls,
				),
			);
		}

		// end_turn — direct response.
		$text = '';
		foreach ( $blocks as $block ) {
			if ( 'text' === ( $block['type'] ?? '' ) ) {
				$text .= $block['text'] ?? '';
			}
		}

		return array(
			'status'            => 'complete',
			'tool_calls'        => array(),
			'content'           => $text,
			'assistant_message' => array( 'role' => 'assistant', 'content' => $text ),
		);
	}

	/**
	 * Tool-calling for Google Gemini.
	 *
	 * Gemini uses functionDeclarations, functionCall parts, and functionResponse parts.
	 * Since Gemini doesn't generate tool call IDs, we generate them to maintain
	 * consistency with the internal OpenAI-format message array.
	 */
	private function tools_gemini( string $api_key, string $model, array $messages, array $tool_definitions ): array {
		$url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent?key=' . rawurlencode( $api_key );

		// Translate tool definitions to Gemini functionDeclarations format.
		$type_map           = array( 'string' => 'STRING', 'number' => 'NUMBER', 'integer' => 'INTEGER', 'boolean' => 'BOOLEAN', 'object' => 'OBJECT', 'array' => 'ARRAY' );
		$function_decls     = array();
		foreach ( $tool_definitions as $def ) {
			$props_raw  = $def['function']['parameters']['properties'] ?? array();
			$properties = array();
			foreach ( $props_raw as $prop_name => $prop_def ) {
				$properties[ $prop_name ] = array(
					'type'        => $type_map[ strtolower( $prop_def['type'] ?? 'string' ) ] ?? 'STRING',
					'description' => $prop_def['description'] ?? '',
				);
			}
			$function_decls[] = array(
				'name'        => $def['function']['name'],
				'description' => $def['function']['description'],
				'parameters'  => array(
					'type'       => 'OBJECT',
					'properties' => $properties,
					'required'   => $def['function']['parameters']['required'] ?? array(),
				),
			);
		}

		// Convert messages to Gemini contents format.
		$contents       = array();
		$system_content = '';

		foreach ( $messages as $msg ) {
			$role = $msg['role'] ?? 'user';

			if ( 'system' === $role ) {
				$system_content = $msg['content'] ?? '';

			} elseif ( 'tool' === $role ) {
				// functionResponse part — Gemini needs the function name.
				$contents[] = array(
					'role'  => 'user',
					'parts' => array(
						array(
							'functionResponse' => array(
								'name'     => $msg['name'] ?? 'unknown',
								'response' => array( 'content' => $msg['content'] ?? '' ),
							),
						),
					),
				);

			} elseif ( 'assistant' === $role && ! empty( $msg['tool_calls'] ) ) {
				// Assistant message with tool calls → functionCall parts.
				$parts = array();
				if ( ! empty( $msg['content'] ) ) {
					$parts[] = array( 'text' => $msg['content'] );
				}
				foreach ( $msg['tool_calls'] as $tc ) {
					$parts[] = array(
						'functionCall' => array(
							'name' => $tc['function']['name'] ?? '',
							'args' => json_decode( $tc['function']['arguments'] ?? '{}', true ) ?? array(),
						),
					);
				}
				$contents[] = array( 'role' => 'model', 'parts' => $parts );

			} else {
				$gemini_role = ( 'assistant' === $role ) ? 'model' : 'user';
				$contents[]  = array(
					'role'  => $gemini_role,
					'parts' => array( array( 'text' => $msg['content'] ?? '' ) ),
				);
			}
		}

		$max_tokens = max( 64, min( 32000, (int) get_option( 'grayfox_llm_max_tokens', 4096 ) ) );
		$body       = array(
			'contents'         => $contents,
			'tools'            => array( array( 'functionDeclarations' => $function_decls ) ),
			'generationConfig' => array( 'maxOutputTokens' => $max_tokens ),
		);
		if ( ! empty( $system_content ) ) {
			$body['systemInstruction'] = array(
				'parts' => array( array( 'text' => $system_content ) ),
			);
		}

		$response = wp_remote_post( $url, array(
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( $body ),
			'timeout' => 90,
		) );

		if ( is_wp_error( $response ) ) {
			return array( 'status' => 'error', 'content' => '', 'tool_calls' => array(), 'assistant_message' => array() );
		}

		$raw_gemini  = wp_remote_retrieve_body( $response );
		$http_gemini = wp_remote_retrieve_response_code( $response );
		$result      = json_decode( $raw_gemini, true );

		if ( ! is_array( $result ) ) {
			return array( 'status' => 'error', 'content' => '', 'tool_calls' => array(), 'assistant_message' => array() );
		}

		if ( isset( $result['error'] ) ) {
			return array( 'status' => 'error', 'content' => '', 'tool_calls' => array(), 'assistant_message' => array() );
		}

		$parts = $result['candidates'][0]['content']['parts'] ?? array();

		$has_function_call = false;
		$normalized        = array();
		$openai_tool_calls = array();
		$text_parts        = array();

		foreach ( $parts as $part ) {
			if ( isset( $part['functionCall'] ) ) {
				$has_function_call = true;
				$name              = $part['functionCall']['name'] ?? '';
				$args              = $part['functionCall']['args'] ?? array();
				$id                = 'gemini_' . wp_generate_password( 8, false );

				$normalized[]        = array( 'id' => $id, 'name' => $name, 'args' => $args );
				$openai_tool_calls[] = array(
					'id'       => $id,
					'type'     => 'function',
					'function' => array( 'name' => $name, 'arguments' => wp_json_encode( $args ) ),
				);
			} elseif ( isset( $part['text'] ) ) {
				$text_parts[] = $part['text'];
			}
		}

		$text_content = implode( '', $text_parts );

		if ( $has_function_call ) {
			return array(
				'status'            => 'tool_calls',
				'tool_calls'        => $normalized,
				'content'           => '',
				'assistant_message' => array(
					'role'       => 'assistant',
					'content'    => $text_content ?: null,
					'tool_calls' => $openai_tool_calls,
				),
			);
		}

		return array(
			'status'            => 'complete',
			'tool_calls'        => array(),
			'content'           => $text_content,
			'assistant_message' => array( 'role' => 'assistant', 'content' => $text_content ),
		);
	}

}
} // end class_exists GrayFox_LLM
