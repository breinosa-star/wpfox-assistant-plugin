<?php
/**
 * Database table management.
 *
 * @package GrayFox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GrayFox_DB
 *
 * Creates, manages, and drops GrayFox custom database tables.
 */
class GrayFox_DB {

	/**
	 * Table name slugs.
	 *
	 * @var string[]
	 */
	private static array $tables = array(
		'knowledge_base',
		'conversations',
		'messages',
		'appointments',
		'google_tokens',
		'security_log',
	);

	/**
	 * Get the full prefixed table name.
	 *
	 * @param string $name Table slug (e.g. 'conversations').
	 * @return string Full table name with WordPress prefix.
	 */
	public static function get_table( string $name ): string {
		global $wpdb;
		return $wpdb->prefix . 'grayfox_' . $name;
	}

	/**
	 * Create all plugin tables using dbDelta().
	 *
	 * Called on plugin activation.
	 */
	public static function create_tables(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();

		$kb_table    = self::get_table( 'knowledge_base' );
		$conv_table  = self::get_table( 'conversations' );
		$msg_table   = self::get_table( 'messages' );
		$appt_table  = self::get_table( 'appointments' );
		$token_table = self::get_table( 'google_tokens' );

		$sql = array();

		// 1. Knowledge base.
		$sql[] = "CREATE TABLE {$kb_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			source_type ENUM('upload','google_drive','google_doc','manual') NOT NULL DEFAULT 'upload',
			source_id VARCHAR(255) DEFAULT NULL,
			source_name VARCHAR(255) DEFAULT NULL,
			content_json LONGTEXT DEFAULT NULL,
			token_estimate INT DEFAULT 0,
			last_processed_at DATETIME DEFAULT NULL,
			status ENUM('pending','active','pending_review') NOT NULL DEFAULT 'pending',
			topic_index TEXT DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_status (status)
		) {$charset};";

		// 2. Conversations.
		$sql[] = "CREATE TABLE {$conv_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			session_id VARCHAR(64) NOT NULL,
			visitor_id VARCHAR(64) DEFAULT NULL,
			visitor_name VARCHAR(255) DEFAULT NULL,
			visitor_email VARCHAR(255) DEFAULT NULL,
			started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			last_active_at DATETIME DEFAULT NULL,
			message_count INT NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			KEY idx_session_id (session_id)
		) {$charset};";

		// 3. Messages.
		$sql[] = "CREATE TABLE {$msg_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			conversation_id BIGINT UNSIGNED NOT NULL,
			role ENUM('user','assistant','system') NOT NULL DEFAULT 'user',
			content TEXT DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_conversation_id (conversation_id)
		) {$charset};";

		// 4. Appointments.
		$sql[] = "CREATE TABLE {$appt_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			customer_name VARCHAR(255) DEFAULT NULL,
			customer_email VARCHAR(255) DEFAULT NULL,
			service VARCHAR(255) DEFAULT NULL,
			start_time DATETIME DEFAULT NULL,
			end_time DATETIME DEFAULT NULL,
			google_event_id VARCHAR(255) DEFAULT NULL,
			status ENUM('confirmed','cancelled','pending') NOT NULL DEFAULT 'pending',
			notes TEXT DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) {$charset};";

		// 5. Google OAuth tokens.
		$sql[] = "CREATE TABLE {$token_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			scope_set TEXT DEFAULT NULL,
			access_token_encrypted TEXT DEFAULT NULL,
			refresh_token_encrypted TEXT DEFAULT NULL,
			expires_at DATETIME DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) {$charset};";

		// 6. Security log.
		$sec_table = self::get_table( 'security_log' );
		$sql[]     = "CREATE TABLE `{$sec_table}` (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			session_id VARCHAR(128) NOT NULL,
			ip_address VARCHAR(45) NOT NULL,
			message_excerpt VARCHAR(200) NOT NULL DEFAULT '',
			reason VARCHAR(100) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY ip_address (ip_address),
			KEY created_at (created_at)
		) {$charset};";

		foreach ( $sql as $query ) {
			dbDelta( $query );
		}
	}

	/**
	 * Drop all plugin tables.
	 *
	 * Called from uninstall.php ONLY.
	 */
	public static function drop_tables(): void {
		global $wpdb;

		foreach ( self::$tables as $slug ) {
			$table = self::get_table( $slug );
			$safe  = esc_sql( $table );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( "DROP TABLE IF EXISTS `{$safe}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}
}
