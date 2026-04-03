<?php
/**
 * Google OAuth2 integration — foundation for all Google API access.
 *
 * @package GrayFox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GrayFox_Google
 *
 * Handles Google OAuth2 authorization, token exchange, token refresh,
 * disconnect, and REST/AJAX route registration.
 */
class GrayFox_Google {

	/**
	 * Singleton instance.
	 *
	 * @var GrayFox_Google|null
	 */
	private static ?GrayFox_Google $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return GrayFox_Google
	 */
	public static function get_instance(): GrayFox_Google {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Google OAuth2 authorization endpoint.
	 *
	 * @var string
	 */
	const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

	/**
	 * Google OAuth2 token endpoint.
	 *
	 * @var string
	 */
	const TOKEN_URL = 'https://oauth2.googleapis.com/token';

	/**
	 * Google userinfo endpoint.
	 *
	 * @var string
	 */
	const USERINFO_URL = 'https://www.googleapis.com/oauth2/v3/userinfo';

	/**
	 * OAuth2 scopes requested.
	 *
	 * @var string
	 */
	const SCOPES = 'openid email https://www.googleapis.com/auth/calendar.events https://www.googleapis.com/auth/drive.readonly https://www.googleapis.com/auth/documents.readonly https://www.googleapis.com/auth/spreadsheets';

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	const REST_NAMESPACE = 'grayfox/v1';

	/**
	 * Register hooks with the loader.
	 *
	 * @param GrayFox_Loader $loader Loader instance.
	 */
	public function register( GrayFox_Loader $loader ): void {
		$loader->add_action( 'rest_api_init', $this, 'register_rest_routes' );
		$loader->add_action( 'wp_ajax_grayfox_google_disconnect', $this, 'handle_disconnect_ajax' );
		$loader->add_action( 'wp_ajax_grayfox_save_google_credentials', $this, 'handle_save_credentials_ajax' );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/google/callback',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_oauth_callback' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Build the Google OAuth2 authorization URL.
	 *
	 * @return string Full authorization URL with all required parameters.
	 */
	public function get_authorization_url(): string {
		$raw_client_id = get_option( 'grayfox_google_client_id', '' );
		$params = array(
			'client_id'     => ! empty( $raw_client_id ) ? grayfox_decrypt( $raw_client_id ) : '',
			'redirect_uri'  => rest_url( self::REST_NAMESPACE . '/google/callback' ),
			'response_type' => 'code',
			'scope'         => self::SCOPES,
			'access_type'   => 'offline',
			'prompt'        => 'consent',
			'state'         => wp_create_nonce( 'grayfox_google_oauth' ),
		);

		return self::AUTH_URL . '?' . http_build_query( $params );
	}

	/**
	 * Handle the OAuth2 callback from Google.
	 *
	 * Verifies the state nonce, exchanges the authorization code for tokens,
	 * stores encrypted tokens, fetches the connected email, and redirects.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response|void Redirects on success; returns error response on failure.
	 */
	public function handle_oauth_callback( WP_REST_Request $request ) {
		$state = $request->get_param( 'state' );
		if ( ! wp_verify_nonce( $state, 'grayfox_google_oauth' ) ) {
			return new WP_REST_Response(
				array( 'error' => 'Invalid state parameter. Possible CSRF attack.' ),
				403
			);
		}

		$code = $request->get_param( 'code' );
		if ( empty( $code ) ) {
			$error = $request->get_param( 'error' );
			wp_redirect(
				add_query_arg(
					array(
						'page'  => 'grayfox-google',
						'error' => $error ? sanitize_text_field( $error ) : 'missing_code',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$client_id_raw = get_option( 'grayfox_google_client_id', '' );
		$client_id     = ! empty( $client_id_raw ) ? grayfox_decrypt( $client_id_raw ) : '';
		$client_secret = get_option( 'grayfox_google_client_secret', '' );
		if ( ! empty( $client_secret ) ) {
			$client_secret = grayfox_decrypt( $client_secret );
		}

		$token_response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'headers'     => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
				'body'        => array(
					'code'          => $code,
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'redirect_uri'  => rest_url( self::REST_NAMESPACE . '/google/callback' ),
					'grant_type'    => 'authorization_code',
				),
				'timeout'     => 15,
			)
		);

		if ( is_wp_error( $token_response ) ) {
			wp_redirect(
				add_query_arg(
					array( 'page' => 'grayfox-google', 'error' => 'token_request_failed' ),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$token_code = wp_remote_retrieve_response_code( $token_response );
		$token_body = json_decode( wp_remote_retrieve_body( $token_response ), true );

		if ( $token_code !== 200 || empty( $token_body['access_token'] ) ) {
			wp_redirect(
				add_query_arg(
					array( 'page' => 'grayfox-google', 'error' => 'token_exchange_failed' ),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$access_token  = $token_body['access_token'];
		$refresh_token = $token_body['refresh_token'] ?? '';
		$expires_in    = isset( $token_body['expires_in'] ) ? (int) $token_body['expires_in'] : 3600;
		$expires_at    = gmdate( 'Y-m-d H:i:s', time() + $expires_in );
		$scope         = $token_body['scope'] ?? self::SCOPES;

		$this->store_tokens( $access_token, $refresh_token, $expires_at, $scope );

		$userinfo_response = wp_remote_get(
			self::USERINFO_URL,
			array(
				'headers' => array( 'Authorization' => 'Bearer ' . $access_token ),
				'timeout' => 10,
			)
		);

		$connected_email = '';
		if ( ! is_wp_error( $userinfo_response ) ) {
			$userinfo = json_decode( wp_remote_retrieve_body( $userinfo_response ), true );
			if ( ! empty( $userinfo['email'] ) ) {
				$connected_email = sanitize_email( $userinfo['email'] );
			}
		}

		update_option( 'grayfox_google_connected_email', $connected_email );
		update_option( 'grayfox_google_connected', true );

		wp_redirect(
			add_query_arg(
				array( 'page' => 'grayfox-google', 'connected' => '1' ),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Store access and refresh tokens (encrypted) in the DB.
	 * Deletes any existing row first — only one row at a time.
	 *
	 * @param string $access_token  Plain-text access token.
	 * @param string $refresh_token Plain-text refresh token.
	 * @param string $expires_at    MySQL DATETIME string.
	 * @param string $scope         Space-separated scope string.
	 */
	private function store_tokens( string $access_token, string $refresh_token, string $expires_at, string $scope ): void {
		global $wpdb;

		$table      = GrayFox_DB::get_table( 'google_tokens' );
		$safe_table = esc_sql( $table );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "DELETE FROM `{$safe_table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$table,
			array(
				'scope_set'               => sanitize_text_field( $scope ),
				'access_token_encrypted'  => grayfox_encrypt( $access_token ),
				'refresh_token_encrypted' => ! empty( $refresh_token ) ? grayfox_encrypt( $refresh_token ) : '',
				'expires_at'              => $expires_at,
				'created_at'              => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Disconnect Google account: delete tokens and connection options.
	 * Client ID and client secret are preserved.
	 */
	public function disconnect(): void {
		global $wpdb;

		$table      = GrayFox_DB::get_table( 'google_tokens' );
		$safe_table = esc_sql( $table );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "DELETE FROM `{$safe_table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		delete_option( 'grayfox_google_connected_email' );
		delete_option( 'grayfox_google_connected' );
	}

	/**
	 * Get a valid access token, refreshing if needed.
	 *
	 * @return string|null Decrypted access token, or null if unavailable.
	 */
	public function get_access_token(): ?string {
		global $wpdb;

		$table = GrayFox_DB::get_table( 'google_tokens' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row( "SELECT * FROM `{$table}` LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! $row ) {
			return null;
		}

		$expires_at    = strtotime( $row->expires_at );
		$expiry_buffer = 5 * MINUTE_IN_SECONDS;

		if ( ( $expires_at - $expiry_buffer ) <= time() ) {
			$refreshed = $this->refresh_access_token();
			if ( ! $refreshed ) {
				return null;
			}
			// Re-fetch updated row.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$row = $wpdb->get_row( "SELECT * FROM `{$table}` LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( ! $row ) {
				return null;
			}
		}

		$decrypted = grayfox_decrypt( $row->access_token_encrypted );
		return ! empty( $decrypted ) ? $decrypted : null;
	}

	/**
	 * Refresh the access token using the stored refresh token.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function refresh_access_token(): bool {
		global $wpdb;

		$table = GrayFox_DB::get_table( 'google_tokens' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row( "SELECT * FROM `{$table}` LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! $row || empty( $row->refresh_token_encrypted ) ) {
			return false;
		}

		$refresh_token = grayfox_decrypt( $row->refresh_token_encrypted );
		if ( empty( $refresh_token ) ) {
			return false;
		}

		$client_id_raw = get_option( 'grayfox_google_client_id', '' );
		$client_id     = ! empty( $client_id_raw ) ? grayfox_decrypt( $client_id_raw ) : '';
		$client_secret = get_option( 'grayfox_google_client_secret', '' );
		if ( ! empty( $client_secret ) ) {
			$client_secret = grayfox_decrypt( $client_secret );
		}

		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
				'body'    => array(
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'refresh_token' => $refresh_token,
					'grant_type'    => 'refresh_token',
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 200 || empty( $body['access_token'] ) ) {
			return false;
		}

		$new_access_token = $body['access_token'];
		$expires_in       = isset( $body['expires_in'] ) ? (int) $body['expires_in'] : 3600;
		$new_expires_at   = gmdate( 'Y-m-d H:i:s', time() + $expires_in );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$table,
			array(
				'access_token_encrypted' => grayfox_encrypt( $new_access_token ),
				'expires_at'             => $new_expires_at,
			),
			array( 'id' => $row->id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return true;
	}

	/**
	 * Check whether a Google account is currently connected.
	 *
	 * @return bool
	 */
	public function is_connected(): bool {
		return (bool) get_option( 'grayfox_google_connected', false );
	}

	/**
	 * AJAX handler: disconnect Google account.
	 * Requires nonce and manage_options capability.
	 */
	public function handle_disconnect_ajax(): void {
		check_ajax_referer( 'grayfox_google_disconnect' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'grayfox' ) );
			return;
		}

		$this->disconnect();
		wp_send_json_success( array( 'message' => __( 'Google account disconnected.', 'grayfox' ) ) );
	}

	/**
	 * AJAX handler: save Google client ID and client secret.
	 * Requires nonce and manage_options capability.
	 */
	public function handle_save_credentials_ajax(): void {
		check_ajax_referer( 'grayfox_save_google_credentials' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'grayfox' ) );
			return;
		}

		$client_id = isset( $_POST['client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['client_id'] ) ) : '';
		$client_secret = isset( $_POST['client_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['client_secret'] ) ) : '';

		if ( ! empty( $client_id ) ) {
			update_option( 'grayfox_google_client_id', grayfox_encrypt( $client_id ) );
		}

		if ( ! empty( $client_secret ) && ! preg_match( '/^\*+$/', $client_secret ) ) {
			update_option( 'grayfox_google_client_secret', grayfox_encrypt( $client_secret ) );
		}

		wp_send_json_success( array( 'message' => __( 'Credentials saved.', 'grayfox' ) ) );
	}
}
