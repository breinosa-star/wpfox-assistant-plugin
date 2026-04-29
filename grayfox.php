<?php
/**
 * Plugin Name: GrayFox AI Assistant
 * Plugin URI:  https://grayfox.io
 * Description: AI-powered chatbot with RAG knowledge base for WordPress
 * Version:     1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author:      GrayFox
 * Author URI:  https://grayfox.io
 * License:     GPL-2.0+
 * Text Domain: grayfox
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GRAYFOX_VERSION', '1.0.0' );
define( 'GRAYFOX_PATH', plugin_dir_path( __FILE__ ) );
define( 'GRAYFOX_URL', plugin_dir_url( __FILE__ ) );
define( 'GRAYFOX_PLUGIN_FILE', __FILE__ );


/* ------------------------------------------------------------------
 * Encryption helpers
 * ------------------------------------------------------------------ */

/**
 * Derive encryption key from WordPress secret keys.
 *
 * @return string 64-character hex key.
 */
function grayfox_get_encryption_key(): string {
	return hash( 'sha256', AUTH_KEY . SECURE_AUTH_KEY . LOGGED_IN_KEY );
}

/**
 * Encrypt a plaintext string with AES-256-CBC.
 *
 * Output is prefixed with 'gf1:' so the sanitize callback can detect an
 * already-encrypted value and skip re-encryption (WordPress calls the
 * sanitize callback twice when adding a new option for the first time).
 *
 * @param string $plaintext Value to encrypt.
 * @return string Prefixed base64-encoded IV + ciphertext.
 */
function grayfox_encrypt( string $plaintext ): string {
	$key = grayfox_get_encryption_key();
	$iv  = random_bytes( 16 );
	$enc = openssl_encrypt( $plaintext, 'AES-256-CBC', $key, 0, $iv );
	return 'gf1:' . base64_encode( $iv . $enc );
}

/**
 * Decrypt a value produced by grayfox_encrypt().
 *
 * Accepts both prefixed ('gf1:...') and legacy (bare base64) values so
 * keys stored before the prefix was introduced continue to work.
 *
 * @param string $ciphertext Encrypted value.
 * @return string Plaintext, or empty string on failure.
 */
function grayfox_decrypt( string $ciphertext ): string {
	if ( empty( $ciphertext ) ) {
		return '';
	}
	// Strip prefix if present (current format).
	if ( strpos( $ciphertext, 'gf1:' ) === 0 ) {
		$ciphertext = substr( $ciphertext, 4 );
	}
	$key  = grayfox_get_encryption_key();
	$data = base64_decode( $ciphertext );
	if ( strlen( $data ) <= 16 ) {
		return '';
	}
	$iv  = substr( $data, 0, 16 );
	$enc = substr( $data, 16 );
	$dec = openssl_decrypt( $enc, 'AES-256-CBC', $key, 0, $iv );
	return ( false === $dec ) ? '' : $dec;
}

/* ------------------------------------------------------------------
 * Autoload classes
 * ------------------------------------------------------------------ */

require_once GRAYFOX_PATH . 'includes/grayfox-prompts.php';
require_once GRAYFOX_PATH . 'includes/class-grayfox-loader.php';
require_once GRAYFOX_PATH . 'includes/class-grayfox-db.php';
require_once GRAYFOX_PATH . 'includes/class-grayfox-security.php';
require_once GRAYFOX_PATH . 'includes/class-grayfox-settings.php';
require_once GRAYFOX_PATH . 'includes/class-grayfox-llm.php';
require_once GRAYFOX_PATH . 'includes/class-grayfox-rag.php';
require_once GRAYFOX_PATH . 'includes/class-grayfox-tools.php';
require_once GRAYFOX_PATH . 'includes/class-grayfox-chat.php';
require_once GRAYFOX_PATH . 'includes/class-grayfox-widget.php';
require_once GRAYFOX_PATH . 'includes/class-grayfox-shortcode.php';
require_once GRAYFOX_PATH . 'includes/class-grayfox-admin.php';
require_once GRAYFOX_PATH . 'includes/class-grayfox-plugin.php';

/* ------------------------------------------------------------------
 * Action Scheduler — load if available
 * ------------------------------------------------------------------ */

$grayfox_as_path = GRAYFOX_PATH . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
if ( file_exists( $grayfox_as_path ) ) {
	require_once $grayfox_as_path;
}

/* ------------------------------------------------------------------
 * Runtime DB upgrade check — runs once per version bump on plugins_loaded
 * ------------------------------------------------------------------ */

add_action( 'plugins_loaded', function () {
	if ( get_option( 'grayfox_db_version' ) !== '1.2.0' ) {
		GrayFox_DB::create_tables(); // dbDelta adds missing columns non-destructively
		update_option( 'grayfox_db_version', '1.2.0' );
	}

	// Migrate old default welcome message to the new one that asks for the customer's name.
	if ( get_option( 'grayfox_widget_welcome_message' ) === 'Hello! How can I help you today?' ) {
		update_option( 'grayfox_widget_welcome_message', 'Hello! Who am I speaking with today?' );
	}
} );

/* ------------------------------------------------------------------
 * Activation / Deactivation / Uninstall hooks
 * ------------------------------------------------------------------ */

register_activation_hook( GRAYFOX_PLUGIN_FILE, function () {
	GrayFox_DB::create_tables();
	update_option( 'grayfox_db_version', '1.2.0' );
	// Set default options on first activation.
	if ( ! get_option( 'grayfox_widget_name' ) ) {
		update_option( 'grayfox_widget_name', 'Chat with us' );
	}
	if ( ! get_option( 'grayfox_widget_color' ) ) {
		update_option( 'grayfox_widget_color', '#6366f1' );
	}
	if ( ! get_option( 'grayfox_widget_position' ) ) {
		update_option( 'grayfox_widget_position', 'bottom-right' );
	}
	if ( false === get_option( 'grayfox_enable_widget' ) ) {
		update_option( 'grayfox_enable_widget', true );
	}
} );

register_deactivation_hook( GRAYFOX_PLUGIN_FILE, function () {
	// Clear scheduled Action Scheduler jobs only — do NOT drop tables.
	if ( function_exists( 'as_unschedule_all_actions' ) ) {
		as_unschedule_all_actions( 'grayfox_process_document' );
		as_unschedule_all_actions( 'grayfox_generate_site_pages' );
	}
} );

// Uninstall logic lives in uninstall.php (registered automatically by WordPress).

/* ------------------------------------------------------------------
 * Boot
 * ------------------------------------------------------------------ */

GrayFox_Plugin::get_instance();
