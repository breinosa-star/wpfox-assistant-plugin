<?php
/**
 * PHPUnit bootstrap — minimal WordPress stubs for unit testing GrayFox classes.
 *
 * No WordPress installation is required. All WP globals and functions are
 * stubbed here so class logic can be tested in isolation.
 *
 * @package GrayFox
 */

define( 'ABSPATH', __DIR__ . '/' );

// -----------------------------------------------------------------------
// WordPress constants
// -----------------------------------------------------------------------
define( 'AUTH_KEY',        'test-auth-key' );
define( 'SECURE_AUTH_KEY', 'test-secure-key' );
define( 'LOGGED_IN_KEY',   'test-logged-in-key' );

// -----------------------------------------------------------------------
// WordPress function stubs
// -----------------------------------------------------------------------
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( string $str ): string {
		return trim( strip_tags( $str ) );
	}
}

if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( string $email ): string {
		return filter_var( $email, FILTER_SANITIZE_EMAIL );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( string $str ): string {
		return trim( strip_tags( $str ) );
	}
}

if ( ! function_exists( 'is_email' ) ) {
	function is_email( string $email ): bool {
		return (bool) filter_var( $email, FILTER_VALIDATE_EMAIL );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $val ): int {
		return abs( (int) $val );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $key, $default = false ) {
		return $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( string $key, $value ): bool {
		return true;
	}
}

if ( ! function_exists( 'wp_timezone_string' ) ) {
	function wp_timezone_string(): string {
		return 'UTC';
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, int $options = 0 ): string {
		return (string) json_encode( $data, $options );
	}
}

if ( ! function_exists( 'current_time' ) ) {
	function current_time( string $type, bool $gmt = false ): string {
		return gmdate( 'Y-m-d H:i:s' );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( 'check_ajax_referer' ) ) {
	function check_ajax_referer( string $action, string $query_arg = false ): bool {
		return true;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( string $capability ): bool {
		return true;
	}
}

if ( ! function_exists( 'wp_send_json_success' ) ) {
	function wp_send_json_success( $data = null, int $status_code = null ): void {}
}

if ( ! function_exists( 'wp_send_json_error' ) ) {
	function wp_send_json_error( $data = null, int $status_code = null ): void {}
}

if ( ! function_exists( 'as_enqueue_async_action' ) ) {
	function as_enqueue_async_action( string $hook, array $args = array() ): int {
		return 0;
	}
}

if ( ! function_exists( 'wp_remote_request' ) ) {
	function wp_remote_request( string $url, array $args = array() ) {
		return array(
			'response' => array( 'code' => 200 ),
			'body'     => '{}',
		);
	}
}

if ( ! function_exists( 'wp_remote_get' ) ) {
	function wp_remote_get( string $url, array $args = array() ) {
		return array(
			'response' => array( 'code' => 200 ),
			'body'     => '{}',
		);
	}
}

if ( ! function_exists( 'wp_remote_post' ) ) {
	function wp_remote_post( string $url, array $args = array() ) {
		return array(
			'response' => array( 'code' => 200 ),
			'body'     => '{}',
		);
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( array $args, string $url ): string {
		$query = http_build_query( $args );
		return $url . ( strpos( $url, '?' ) === false ? '?' : '&' ) . $query;
	}
}

if ( ! function_exists( 'esc_sql' ) ) {
	function esc_sql( string $str ): string {
		return addslashes( $str );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( string $str ): string {
		return htmlspecialchars( $str, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( string $str ): string {
		return htmlspecialchars( $str, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		return true;
	}
}

if ( ! function_exists( 'as_has_scheduled_action' ) ) {
	function as_has_scheduled_action( string $hook, array $args = array(), string $group = '' ): bool {
		return false;
	}
}

if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
	function as_schedule_recurring_action( int $timestamp, int $interval_in_seconds, string $hook, array $args = array(), string $group = '' ): int {
		return 0;
	}
}

if ( ! function_exists( 'as_next_scheduled_action' ) ) {
	function as_next_scheduled_action( string $hook, array $args = array(), string $group = '' ) {
		return false;
	}
}

if ( ! function_exists( 'error_log' ) ) {
	function error_log( string $message, int $message_type = 0 ): bool {
		return true;
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ): bool {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( $response ): int {
		return $response['response']['code'] ?? 0;
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( $response ): string {
		return $response['body'] ?? '';
	}
}

if ( ! function_exists( 'rawurlencode' ) ) {
	// Built-in PHP, but stub here just in case.
	function rawurlencode_stub( string $str ): string {
		return rawurlencode( $str );
	}
}

// -----------------------------------------------------------------------
// WP_Error stub
// -----------------------------------------------------------------------
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private string $code;
		private string $message;
		private mixed  $data;

		public function __construct( string $code = '', string $message = '', mixed $data = '' ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_code(): string {
			return $this->code;
		}

		public function get_error_message( string $code = '' ): string {
			return $this->message;
		}

		public function get_error_data( string $code = '' ): mixed {
			return $this->data;
		}
	}
}

// -----------------------------------------------------------------------
// GrayFox_DB stub (table name helper only)
// -----------------------------------------------------------------------
if ( ! class_exists( 'GrayFox_DB' ) ) {
	class GrayFox_DB {
		public static function get_table( string $name ): string {
			return 'wp_grayfox_' . $name;
		}
	}
}

// -----------------------------------------------------------------------
// GrayFox_Loader stub
// -----------------------------------------------------------------------
if ( ! class_exists( 'GrayFox_Loader' ) ) {
	class GrayFox_Loader {
		public function add_action( string $hook, object $object, string $method, int $priority = 10, int $args = 1 ): void {}
		public function run(): void {}
	}
}

