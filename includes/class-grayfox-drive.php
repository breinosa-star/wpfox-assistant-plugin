<?php
/**
 * Google Drive / Docs auto-sync for the knowledge base.
 *
 * @package GrayFox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GrayFox_Drive
 *
 * Lists files in a Google Drive folder, allows admin selection, and syncs
 * selected files into the GrayFox knowledge base on a daily schedule via
 * Action Scheduler.  All heavy processing is dispatched asynchronously.
 *
 * WordPress Options used:
 *   grayfox_drive_folder_id       — selected Google Drive folder ID
 *   grayfox_drive_selected_files  — JSON array of selected file IDs
 *   grayfox_drive_last_sync       — datetime of last successful sync run
 */
class GrayFox_Drive {

	// -------------------------------------------------------------------------
	// Constants
	// -------------------------------------------------------------------------

	/**
	 * Google Drive REST API base URL.
	 *
	 * @var string
	 */
	const DRIVE_API_BASE = 'https://www.googleapis.com/drive/v3';

	/**
	 * MIME types supported for knowledge-base ingestion.
	 *
	 * @var string[]
	 */
	const SUPPORTED_MIME_TYPES = array(
		'application/vnd.google-apps.document',
		'text/plain',
		'application/pdf',
		'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
	);

	/**
	 * Action Scheduler hook: process a single Drive file.
	 *
	 * @var string
	 */
	const AS_HOOK_FILE = 'grayfox_sync_drive_file';

	/**
	 * Action Scheduler hook: daily recurring sync trigger.
	 *
	 * @var string
	 */
	const AS_HOOK_DAILY = 'grayfox_drive_daily_sync';

	// -------------------------------------------------------------------------
	// Singleton
	// -------------------------------------------------------------------------

	/**
	 * Singleton instance.
	 *
	 * @var GrayFox_Drive|null
	 */
	private static ?GrayFox_Drive $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return GrayFox_Drive
	 */
	public static function get_instance(): GrayFox_Drive {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor — use get_instance().
	 */
	private function __construct() {}

	// -------------------------------------------------------------------------
	// Hook registration
	// -------------------------------------------------------------------------

	/**
	 * Register all hooks via the loader.
	 *
	 * @param GrayFox_Loader $loader Loader instance.
	 */
	public function register( GrayFox_Loader $loader ): void {
		// Admin-only AJAX handlers.
		$loader->add_action( 'wp_ajax_grayfox_drive_list_folder',    $this, 'handle_list_folder' );
		$loader->add_action( 'wp_ajax_grayfox_drive_save_selection', $this, 'handle_save_selection' );
		$loader->add_action( 'wp_ajax_grayfox_drive_sync_now',       $this, 'handle_sync_now' );
		$loader->add_action( 'wp_ajax_grayfox_drive_resync_file',    $this, 'handle_resync_file' );

		// Action Scheduler callbacks must be registered early (priority 5).
		$loader->add_action( 'init', $this, 'register_as_callbacks', 5 );

		// Ensure the daily recurring action is scheduled.
		$loader->add_action( 'init', $this, 'schedule_daily_sync', 20 );
	}

	/**
	 * Register Action Scheduler callbacks on init (priority 5).
	 *
	 * Called early so AS can dispatch jobs before later hooks fire.
	 */
	public function register_as_callbacks(): void {
		add_action( self::AS_HOOK_FILE,  array( $this, 'process_drive_file' ) );
		add_action( self::AS_HOOK_DAILY, array( $this, 'run_daily_sync' ) );
	}

	// -------------------------------------------------------------------------
	// Google Drive API methods
	// -------------------------------------------------------------------------

	/**
	 * List files inside a Drive folder, filtered to supported MIME types.
	 *
	 * @param string $folder_id Google Drive folder ID.
	 * @return array|WP_Error Array of file objects [{id,name,mimeType,modifiedTime}]
	 *                        on success, WP_Error on failure.
	 */
	public function list_folder_files( string $folder_id ): array|WP_Error {
		$google = GrayFox_Google::get_instance();

		if ( ! $google->is_connected() ) {
			return new WP_Error(
				'not_connected',
				__( 'Google account is not connected.', 'grayfox' )
			);
		}

		$token = $google->get_access_token();
		if ( empty( $token ) ) {
			return new WP_Error(
				'no_token',
				__( 'Could not retrieve a valid Google access token.', 'grayfox' )
			);
		}

		// Build query: files in the specified folder that have not been trashed.
		$safe_folder = sanitize_text_field( $folder_id );
		$query       = "'{$safe_folder}' in parents and trashed=false";

		$url = add_query_arg(
			array(
				'q'       => $query,
				'fields'  => 'files(id,name,mimeType,modifiedTime,size)',
				'orderBy' => 'name',
			),
			self::DRIVE_API_BASE . '/files'
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 20,
				'headers' => array( 'Authorization' => 'Bearer ' . $token ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 200 || ! isset( $body['files'] ) ) {
			$message = $body['error']['message'] ?? __( 'Drive API error.', 'grayfox' );
			return new WP_Error( 'drive_api_error', $message );
		}

		// Filter to supported MIME types only.
		return array_values(
			array_filter(
				$body['files'],
				static function ( array $file ): bool {
					return in_array( $file['mimeType'] ?? '', self::SUPPORTED_MIME_TYPES, true );
				}
			)
		);
	}

	/**
	 * Fetch the plain-text content of a Drive file.
	 *
	 * Google Docs are exported as plain text via the export endpoint.
	 * All other supported types are downloaded with alt=media.
	 *
	 * @param string $file_id   Google Drive file ID.
	 * @param string $mime_type MIME type of the file.
	 * @return string|WP_Error Raw text body string, or WP_Error on failure.
	 */
	public function fetch_file_content( string $file_id, string $mime_type ): string|WP_Error {
		$google = GrayFox_Google::get_instance();

		if ( ! $google->is_connected() ) {
			return new WP_Error(
				'not_connected',
				__( 'Google account is not connected.', 'grayfox' )
			);
		}

		$token = $google->get_access_token();
		if ( empty( $token ) ) {
			return new WP_Error(
				'no_token',
				__( 'Could not retrieve a valid Google access token.', 'grayfox' )
			);
		}

		$safe_id = sanitize_text_field( $file_id );

		if ( 'application/vnd.google-apps.document' === $mime_type ) {
			$url = add_query_arg(
				array( 'mimeType' => 'text/plain' ),
				self::DRIVE_API_BASE . '/files/' . rawurlencode( $safe_id ) . '/export'
			);
		} else {
			$url = add_query_arg(
				array( 'alt' => 'media' ),
				self::DRIVE_API_BASE . '/files/' . rawurlencode( $safe_id )
			);
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 30,
				'headers' => array( 'Authorization' => 'Bearer ' . $token ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code !== 200 ) {
			$body    = json_decode( wp_remote_retrieve_body( $response ), true );
			$message = $body['error']['message'] ?? __( 'Drive file fetch error.', 'grayfox' );
			return new WP_Error( 'drive_fetch_error', $message );
		}

		return wp_remote_retrieve_body( $response );
	}

	/**
	 * Fetch metadata (name, mimeType, modifiedTime) for a single Drive file.
	 *
	 * @param string $file_id Google Drive file ID.
	 * @return array|WP_Error Associative array or WP_Error.
	 */
	public function get_file_metadata( string $file_id ): array|WP_Error {
		$google = GrayFox_Google::get_instance();

		if ( ! $google->is_connected() ) {
			return new WP_Error( 'not_connected', __( 'Google account not connected.', 'grayfox' ) );
		}

		$token = $google->get_access_token();
		if ( empty( $token ) ) {
			return new WP_Error(
				'no_token',
				__( 'Could not retrieve a valid Google access token.', 'grayfox' )
			);
		}

		$safe_id = sanitize_text_field( $file_id );
		$url     = add_query_arg(
			array( 'fields' => 'id,name,mimeType,modifiedTime' ),
			self::DRIVE_API_BASE . '/files/' . rawurlencode( $safe_id )
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array( 'Authorization' => 'Bearer ' . $token ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 200 ) {
			$message = $body['error']['message'] ?? __( 'Failed to fetch file metadata.', 'grayfox' );
			return new WP_Error( 'drive_metadata_error', $message );
		}

		return $body;
	}

	/**
	 * Compare Drive modified times against knowledge-base last-processed times,
	 * then enqueue async jobs for files that are new or have changed.
	 *
	 * @return array{scheduled: int, skipped: int}
	 */
	public function sync_selected_files(): array {
		$raw      = get_option( 'grayfox_drive_selected_files', '[]' );
		$file_ids = json_decode( $raw, true );

		if ( ! is_array( $file_ids ) || empty( $file_ids ) ) {
			return array( 'scheduled' => 0, 'skipped' => 0 );
		}

		$scheduled = 0;
		$skipped   = 0;

		foreach ( $file_ids as $file_id ) {
			$file_id = sanitize_text_field( (string) $file_id );
			if ( empty( $file_id ) ) {
				continue;
			}

			// Fetch current modifiedTime from Drive.
			$metadata = $this->get_file_metadata( $file_id );
			if ( is_wp_error( $metadata ) ) {
				// Cannot verify — schedule for safety.
				$this->enqueue_file_job( $file_id );
				++$scheduled;
				continue;
			}

			$modified_time  = $metadata['modifiedTime'] ?? '';
			$last_processed = $this->get_last_processed_at( $file_id );

			$needs_sync = (
				null === $last_processed
				|| ( ! empty( $modified_time ) && strtotime( $modified_time ) > strtotime( $last_processed ) )
			);

			if ( $needs_sync ) {
				$this->enqueue_file_job( $file_id );
				++$scheduled;
			} else {
				++$skipped;
			}
		}

		update_option( 'grayfox_drive_last_sync', current_time( 'mysql', true ) );

		return array( 'scheduled' => $scheduled, 'skipped' => $skipped );
	}

	/**
	 * Action Scheduler callback: fetch and process a single Drive file into
	 * the knowledge base.
	 *
	 * @param string $file_id Google Drive file ID.
	 */
	public function process_drive_file( string $file_id ): void {
		// Fetch full metadata.
		$metadata = $this->get_file_metadata( $file_id );
		if ( is_wp_error( $metadata ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'GrayFox Drive: metadata fetch failed for file ' . $file_id . ' — ' . $metadata->get_error_message() );
			return;
		}

		$file_name = $metadata['name']     ?? $file_id;
		$mime_type = $metadata['mimeType'] ?? '';

		// Fetch raw text content.
		$content = $this->fetch_file_content( $file_id, $mime_type );
		if ( is_wp_error( $content ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'GrayFox Drive: content fetch failed for file ' . $file_id . ' (' . $file_name . ') — ' . $content->get_error_message() );
			return;
		}

		if ( empty( trim( (string) $content ) ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'GrayFox Drive: empty content for file ' . $file_id . ' (' . $file_name . ')' );
			return;
		}

		// Upsert into knowledge base, keyed by Drive file ID as source_id.
		GrayFox_RAG::get_instance()->summarize_to_knowledge_base( (string) $content, $file_name, $file_id );
	}

	/**
	 * Return sync status for each selected file.
	 *
	 * @return array[] Array of status records: [{file_id, file_name, status, last_synced}]
	 */
	public function get_sync_status(): array {
		$raw      = get_option( 'grayfox_drive_selected_files', '[]' );
		$file_ids = json_decode( $raw, true );

		if ( ! is_array( $file_ids ) || empty( $file_ids ) ) {
			return array();
		}

		global $wpdb;
		$kb_table      = GrayFox_DB::get_table( 'knowledge_base' );
		$safe_kb_table = esc_sql( $kb_table );
		$statuses      = array();

		foreach ( $file_ids as $file_id ) {
			$file_id = sanitize_text_field( (string) $file_id );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT source_name, last_processed_at FROM `{$safe_kb_table}` WHERE source_id = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$file_id
				)
			);

			$pending = $this->is_job_pending( $file_id );

			if ( $pending ) {
				$status = 'pending';
			} elseif ( $row ) {
				$status = 'synced';
			} else {
				$status = 'never';
			}

			$statuses[] = array(
				'file_id'     => $file_id,
				'file_name'   => $row ? $row->source_name : $file_id,
				'status'      => $status,
				'last_synced' => $row ? $row->last_processed_at : null,
			);
		}

		return $statuses;
	}

	/**
	 * Ensure the daily recurring Action Scheduler action is scheduled.
	 *
	 * Safe to call multiple times — only schedules once.
	 */
	public function schedule_daily_sync(): void {
		if ( ! function_exists( 'as_has_scheduled_action' ) ) {
			return;
		}

		if ( function_exists( 'as_schedule_recurring_action' ) && ! as_has_scheduled_action( self::AS_HOOK_DAILY, array(), 'grayfox' ) ) {
			as_schedule_recurring_action(
				time(),
				DAY_IN_SECONDS,
				self::AS_HOOK_DAILY,
				array(),
				'grayfox'
			);
		}
	}

	/**
	 * Action Scheduler callback for the daily recurring sync.
	 */
	public function run_daily_sync(): void {
		$this->sync_selected_files();
	}

	// -------------------------------------------------------------------------
	// AJAX handlers
	// -------------------------------------------------------------------------

	/**
	 * AJAX: list files in a Google Drive folder.
	 *
	 * POST: nonce (field: nonce), folder_id
	 * Action: wp_ajax_grayfox_drive_list_folder
	 */
	public function handle_list_folder(): void {
		check_ajax_referer( 'grayfox_drive', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'grayfox' ) ), 403 );
			return;
		}

		if ( ! $this->is_growth_or_above() ) {
			wp_send_json_error( array( 'message' => __( 'This feature requires a Growth or Pro licence.', 'grayfox' ) ), 403 );
			return;
		}

		$folder_id = isset( $_POST['folder_id'] ) ? sanitize_text_field( wp_unslash( $_POST['folder_id'] ) ) : '';

		if ( empty( $folder_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Folder ID is required.', 'grayfox' ) ) );
			return;
		}

		$files = $this->list_folder_files( $folder_id );

		if ( is_wp_error( $files ) ) {
			wp_send_json_error( array( 'message' => $files->get_error_message() ) );
			return;
		}

		wp_send_json_success( array( 'files' => $files ) );
	}

	/**
	 * AJAX: save the admin's file selection (and optionally the folder ID).
	 *
	 * POST: nonce, file_ids (array), folder_id (optional)
	 * Action: wp_ajax_grayfox_drive_save_selection
	 */
	public function handle_save_selection(): void {
		check_ajax_referer( 'grayfox_drive', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'grayfox' ) ), 403 );
			return;
		}

		if ( ! $this->is_growth_or_above() ) {
			wp_send_json_error( array( 'message' => __( 'This feature requires a Growth or Pro licence.', 'grayfox' ) ), 403 );
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw_ids = isset( $_POST['file_ids'] ) ? wp_unslash( $_POST['file_ids'] ) : array();
		if ( ! is_array( $raw_ids ) ) {
			$raw_ids = array();
		}

		$sanitized_ids = array_values(
			array_filter(
				array_map( 'sanitize_text_field', $raw_ids )
			)
		);

		update_option( 'grayfox_drive_selected_files', wp_json_encode( $sanitized_ids ) );

		$folder_id = isset( $_POST['folder_id'] ) ? sanitize_text_field( wp_unslash( $_POST['folder_id'] ) ) : '';
		if ( ! empty( $folder_id ) ) {
			update_option( 'grayfox_drive_folder_id', $folder_id );
		}

		wp_send_json_success( array( 'message' => __( 'Selection saved.', 'grayfox' ) ) );
	}

	/**
	 * AJAX: trigger an immediate sync of all selected files.
	 *
	 * POST: nonce
	 * Action: wp_ajax_grayfox_drive_sync_now
	 */
	public function handle_sync_now(): void {
		check_ajax_referer( 'grayfox_drive', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'grayfox' ) ), 403 );
			return;
		}

		if ( ! $this->is_growth_or_above() ) {
			wp_send_json_error( array( 'message' => __( 'This feature requires a Growth or Pro licence.', 'grayfox' ) ), 403 );
			return;
		}

		$result = $this->sync_selected_files();

		wp_send_json_success(
			array(
				'scheduled' => $result['scheduled'],
				'skipped'   => $result['skipped'],
				'message'   => sprintf(
					/* translators: 1: scheduled count, 2: skipped count */
					__( '%1$d file(s) scheduled for sync. %2$d file(s) already up to date.', 'grayfox' ),
					$result['scheduled'],
					$result['skipped']
				),
			)
		);
	}

	/**
	 * AJAX: re-sync a single file immediately (force-enqueue).
	 *
	 * POST: nonce, file_id
	 * Action: wp_ajax_grayfox_drive_resync_file
	 */
	public function handle_resync_file(): void {
		check_ajax_referer( 'grayfox_drive', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized.', 'grayfox' ) ), 403 );
			return;
		}

		if ( ! $this->is_growth_or_above() ) {
			wp_send_json_error( array( 'message' => __( 'This feature requires a Growth or Pro licence.', 'grayfox' ) ), 403 );
			return;
		}

		$file_id = isset( $_POST['file_id'] ) ? sanitize_text_field( wp_unslash( $_POST['file_id'] ) ) : '';

		if ( empty( $file_id ) ) {
			wp_send_json_error( array( 'message' => __( 'No file ID provided.', 'grayfox' ) ) );
			return;
		}

		$this->enqueue_file_job( $file_id );

		wp_send_json_success( array( 'message' => __( 'File queued for re-sync.', 'grayfox' ) ) );
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Enqueue an async Action Scheduler job to process a single Drive file.
	 *
	 * Falls back to inline processing if Action Scheduler is unavailable.
	 *
	 * @param string $file_id Google Drive file ID.
	 */
	private function enqueue_file_job( string $file_id ): void {
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			$this->process_drive_file( $file_id );
			return;
		}

		as_enqueue_async_action(
			self::AS_HOOK_FILE,
			array( 'file_id' => $file_id ),
			'grayfox'
		);
	}

	/**
	 * Query the knowledge base for the last_processed_at value of a Drive file.
	 *
	 * @param string $file_id Google Drive file ID used as source_id.
	 * @return string|null MySQL DATETIME string, or null if not found.
	 */
	private function get_last_processed_at( string $file_id ): ?string {
		global $wpdb;

		$table      = GrayFox_DB::get_table( 'knowledge_base' );
		$safe_table = esc_sql( $table );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT last_processed_at FROM `{$safe_table}` WHERE source_id = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$file_id
			)
		);

		return $result ? (string) $result : null;
	}

	/**
	 * Check whether an Action Scheduler job is already pending for this file.
	 *
	 * @param string $file_id Google Drive file ID.
	 * @return bool True if a pending job exists.
	 */
	private function is_job_pending( string $file_id ): bool {
		if ( ! function_exists( 'as_has_scheduled_action' ) ) {
			return false;
		}

		return (bool) as_has_scheduled_action(
			self::AS_HOOK_FILE,
			array( 'file_id' => $file_id ),
			'grayfox'
		);
	}

	/**
	 * Return true when the current licence tier is Growth or Pro.
	 *
	 * @return bool
	 */
	private function is_growth_or_above(): bool {
		return in_array( GrayFox_License::get_verified_tier(), array( 'growth', 'pro' ), true );
	}
}
