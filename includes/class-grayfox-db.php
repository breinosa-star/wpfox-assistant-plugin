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
if ( ! class_exists( 'GrayFox_DB' ) ) {
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
		'security_log',
		'api_log',
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

		$kb_table   = self::get_table( 'knowledge_base' );
		$conv_table = self::get_table( 'conversations' );
		$msg_table  = self::get_table( 'messages' );

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

		// 4. Security log.
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

		// 5. API log.
		$api_log_table = self::get_table( 'api_log' );
		$sql[]         = "CREATE TABLE `{$api_log_table}` (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL,
			ip_address VARCHAR(45) NOT NULL DEFAULT '',
			country_code VARCHAR(2) NOT NULL DEFAULT '',
			user_agent TEXT NOT NULL,
			is_ai_agent TINYINT(1) NOT NULL DEFAULT 0,
			agent_name VARCHAR(100) NOT NULL DEFAULT '',
			query VARCHAR(500) NOT NULL DEFAULT '',
			response_size_bytes INT NOT NULL DEFAULT 0,
			response_time_ms INT NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			KEY created_at (created_at),
			KEY is_ai_agent (is_ai_agent)
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
			$wpdb->query( "DROP TABLE IF EXISTS `{$safe}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}
}
} // end class_exists GrayFox_DB
