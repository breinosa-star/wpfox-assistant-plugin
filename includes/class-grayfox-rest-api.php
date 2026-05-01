<?php
/**
 * Public KB REST API.
 *
 * Registers GET /wp-json/grayfox/v1/kb — an unauthenticated endpoint that
 * returns the site's active knowledge base so AI agents can query it.
 *
 * Only active when the site owner enables it in GrayFox > Settings.
 *
 * @package GrayFox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GrayFox_REST_API
 */
class GrayFox_REST_API {

	/**
	 * Register hooks via the loader.
	 *
	 * @param GrayFox_Loader $loader Hook loader instance.
	 */
	public function register( GrayFox_Loader $loader ): void {
		$loader->add_action( 'rest_api_init', $this, 'register_routes' );
		$loader->add_action( 'init', $this, 'register_rewrite_rule' );
		$loader->add_filter( 'query_vars', $this, 'add_query_vars' );
		$loader->add_action( 'template_redirect', $this, 'serve_llms_txt' );
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			'grayfox/v1',
			'/kb',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_kb_request' ),
				'permission_callback' => array( $this, 'check_enabled' ),
				'args'                => array(
					'query' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Permission callback — returns a WP_Error if the public API is disabled.
	 *
	 * @return true|WP_Error
	 */
	public function check_enabled() {
		if ( ! get_option( 'grayfox_public_kb_api_enabled', false ) ) {
			return new WP_Error(
				'grayfox_api_disabled',
				__( 'The public KB API is not enabled on this site.', 'kbfox' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}

	/**
	 * Handle GET /grayfox/v1/kb.
	 *
	 * Applies IP-based rate limiting, returns the consolidated KB JSON,
	 * and logs the request to wp_grayfox_api_log.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_kb_request( WP_REST_Request $request ) {
		$start_time = microtime( true );

		$rate_limit_error = $this->check_rate_limit();
		if ( is_wp_error( $rate_limit_error ) ) {
			return $rate_limit_error;
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

		$response_json     = wp_json_encode( $body );
		$response_size     = strlen( $response_json );
		$response_time_ms  = (int) round( ( microtime( true ) - $start_time ) * 1000 );

		$this->log_request( $query, $response_size, $response_time_ms );

		return new WP_REST_Response( $body, 200 );
	}

	/**
	 * Log a successful API request to wp_grayfox_api_log.
	 *
	 * @param string $query             The query parameter (may be empty).
	 * @param int    $response_size     Response body size in bytes.
	 * @param int    $response_time_ms  Time to build the response in milliseconds.
	 */
	private function log_request( string $query, int $response_size, int $response_time_ms ): void {
		global $wpdb;

		$user_agent   = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$agent_name   = $this->detect_ai_agent( $user_agent );
		$country_code = isset( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_IPCOUNTRY'] ) ) : '';

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			GrayFox_DB::get_table( 'api_log' ),
			array(
				'created_at'          => current_time( 'mysql' ),
				'ip_address'          => $this->get_client_ip(),
				'country_code'        => strtoupper( substr( $country_code, 0, 2 ) ),
				'user_agent'          => $user_agent,
				'is_ai_agent'         => ! empty( $agent_name ) ? 1 : 0,
				'agent_name'          => $agent_name,
				'query'               => substr( $query, 0, 500 ),
				'response_size_bytes' => $response_size,
				'response_time_ms'    => $response_time_ms,
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%d' )
		);
	}

	/**
	 * Detect known AI agent crawlers from the User-Agent string.
	 *
	 * Returns the agent name if matched, empty string otherwise.
	 *
	 * @param string $user_agent Raw User-Agent header value.
	 * @return string
	 */
	private function detect_ai_agent( string $user_agent ): string {
		$agents = array(
			'GPTBot'               => 'GPTBot',
			'ChatGPT-User'         => 'ChatGPT',
			'OAI-SearchBot'        => 'OAI-SearchBot',
			'ClaudeBot'            => 'ClaudeBot',
			'anthropic-ai'         => 'Claude',
			'Google-Extended'      => 'Google-Extended',
			'PerplexityBot'        => 'PerplexityBot',
			'YouBot'               => 'YouBot',
			'Applebot-Extended'    => 'Applebot',
			'cohere-ai'            => 'Cohere',
			'Amazonbot'            => 'Amazonbot',
			'meta-externalagent'   => 'MetaAI',
		);

		foreach ( $agents as $pattern => $name ) {
			if ( stripos( $user_agent, $pattern ) !== false ) {
				return $name;
			}
		}

		return '';
	}

	/**
	 * Register the llms.txt rewrite rule.
	 *
	 * Always registered regardless of enabled state so WP can serve the 404
	 * cleanly rather than falling through to the server.
	 */
	public function register_rewrite_rule(): void {
		add_rewrite_rule( '^llms\.txt$', 'index.php?grayfox_llms_txt=1', 'top' );
	}

	/**
	 * Expose the grayfox_llms_txt query var to WP.
	 *
	 * @param array $vars Registered query vars.
	 * @return array
	 */
	public function add_query_vars( array $vars ): array {
		$vars[] = 'grayfox_llms_txt';
		return $vars;
	}

	/**
	 * Serve llms.txt when the rewrite rule matches.
	 *
	 * Returns 404 if the public API is disabled. Otherwise outputs plain text
	 * describing the site and the KB API endpoint.
	 */
	public function serve_llms_txt(): void {
		if ( ! get_query_var( 'grayfox_llms_txt' ) ) {
			return;
		}

		if ( ! get_option( 'grayfox_public_kb_api_enabled', false ) ) {
			status_header( 404 );
			exit;
		}

		$site_name    = get_bloginfo( 'name' );
		$site_desc    = get_bloginfo( 'description' );
		$endpoint_url = rest_url( 'grayfox/v1/kb' );

		$lines = array();
		$lines[] = '# ' . $site_name;
		$lines[] = '';
		if ( $site_desc ) {
			$lines[] = '> ' . $site_desc;
			$lines[] = '';
		}
		$lines[] = 'This site exposes a customer-facing knowledge base via a public API. Use it to answer questions about the company\'s services, products, and expertise.';
		$lines[] = '';
		$lines[] = '## Knowledge Base API';
		$lines[] = '';
		$lines[] = '- [Knowledge Base](' . $endpoint_url . '): Returns the full knowledge base as JSON. Accepts an optional `query` parameter to filter results by topic.';
		$lines[] = '';
		$lines[] = '## Usage';
		$lines[] = '';
		$lines[] = 'GET ' . $endpoint_url;
		$lines[] = 'GET ' . $endpoint_url . '?query=<topic>';
		$lines[] = '';
		$lines[] = '### Response format';
		$lines[] = '';
		$lines[] = '```json';
		$lines[] = '{ "documents": [ { "source_name": "...", "content": { ... } } ] }';
		$lines[] = '```';

		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo implode( "\n", $lines );
		exit;
	}

	/**
	 * Check and increment the IP-based rate limit for the public API.
	 *
	 * Uses WP transients with a 1-hour TTL. Returns a WP_Error with a 429
	 * status and Retry-After header if the limit is exceeded.
	 *
	 * @return true|WP_Error
	 */
	private function check_rate_limit() {
		$limit  = (int) get_option( 'grayfox_public_kb_api_rate_limit', 60 );
		$ip     = $this->get_client_ip();
		$key    = 'grayfox_api_rl_' . md5( $ip );
		$count  = (int) get_transient( $key );

		if ( $count >= $limit ) {
			$error = new WP_Error(
				'grayfox_rate_limited',
				__( 'Too many requests. Please try again later.', 'kbfox' ),
				array( 'status' => 429 )
			);

			// Log to security log.
			global $wpdb;
			$table = $wpdb->prefix . 'grayfox_security_log';
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$table,
				array(
					'session_id'      => '',
					'ip_address'      => $ip,
					'message_excerpt' => '',
					'reason'          => 'api_rate_limit',
					'created_at'      => current_time( 'mysql' ),
				),
				array( '%s', '%s', '%s', '%s', '%s' )
			);

			return $error;
		}

		if ( 0 === $count ) {
			set_transient( $key, 1, HOUR_IN_SECONDS );
		} else {
			set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		}

		return true;
	}

	/**
	 * Get the client IP address, respecting common proxy headers.
	 *
	 * @return string
	 */
	private function get_client_ip(): string {
		foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ) as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				// X-Forwarded-For can be a comma-separated list; take the first.
				$ip = trim( explode( ',', $ip )[0] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}
		return '0.0.0.0';
	}
}
