<?php
/**
 * Admin dashboard menus and page renderers.
 *
 * @package GrayFox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GrayFox_Admin
 *
 * Registers the top-level GrayFox admin menu and subpages.
 */
class GrayFox_Admin {

	/**
	 * Register hooks with the loader.
	 *
	 * @param GrayFox_Loader $loader Loader instance.
	 */
	public function register( GrayFox_Loader $loader ): void {
		$loader->add_action( 'admin_menu', $this, 'register_menus' );
		$loader->add_action( 'admin_enqueue_scripts', $this, 'enqueue_admin_assets' );
		$loader->add_action( 'admin_post_grayfox_upload_document', $this, 'handle_document_upload' );

		// KB document management.
		$loader->add_action( 'wp_ajax_grayfox_delete_kb_document', $this, 'handle_delete_kb_document' );
		$loader->add_action( 'wp_ajax_grayfox_retry_kb_document',  $this, 'handle_retry_kb_document' );

		// Conflict resolution.
		$loader->add_action( 'wp_ajax_grayfox_resolve_conflict',   $this, 'handle_resolve_conflict' );
		$loader->add_action( 'wp_ajax_grayfox_get_conflict_diff',  $this, 'handle_get_conflict_diff' );

	}

	/**
	 * Enqueue admin CSS and page-specific scripts.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( string $hook ): void {
		if ( false === strpos( $hook, 'grayfox' ) ) {
			return;
		}

		wp_enqueue_style(
			'grayfox-admin',
			GRAYFOX_URL . 'assets/dist/grayfox-admin.min.css',
			array(),
			GRAYFOX_VERSION
		);

	}

	/**
	 * Register admin menus and submenus.
	 */
	public function register_menus(): void {
		// Top-level menu.
		add_menu_page(
			__( 'KBFox', 'kbfox' ),
			__( 'KBFox', 'kbfox' ),
			'manage_options',
			'grayfox',
			array( $this, 'render_overview' ),
			'dashicons-format-chat',
			25
		);

		// Overview subpage (same as top-level).
		add_submenu_page(
			'grayfox',
			__( 'Overview', 'kbfox' ),
			__( 'Overview', 'kbfox' ),
			'manage_options',
			'grayfox',
			array( $this, 'render_overview' )
		);

		// Settings subpage (renders via Settings API).
		add_submenu_page(
			'grayfox',
			__( 'Settings', 'kbfox' ),
			__( 'Settings', 'kbfox' ),
			'manage_options',
			'grayfox-settings',
			array( $this, 'render_settings' )
		);

		// Knowledge Base subpage.
		add_submenu_page(
			'grayfox',
			__( 'Knowledge Base', 'kbfox' ),
			__( 'Knowledge Base', 'kbfox' ),
			'manage_options',
			'grayfox-knowledge-base',
			array( $this, 'render_knowledge_base' )
		);

		// Conversations subpage.
		add_submenu_page(
			'grayfox',
			__( 'Conversations', 'kbfox' ),
			__( 'Conversations', 'kbfox' ),
			'manage_options',
			'grayfox-conversations',
			array( $this, 'render_conversations' )
		);

	}

	/**
	 * Render the Overview admin page.
	 */
	public function render_overview(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		include GRAYFOX_PATH . 'templates/admin/overview.php';
	}

	/**
	 * Render the Settings admin page.
	 */
	public function render_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		include GRAYFOX_PATH . 'templates/admin/settings.php';
	}

	/**
	 * Render the Knowledge Base admin page.
	 */
	public function render_knowledge_base(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		include GRAYFOX_PATH . 'templates/admin/knowledge-base.php';
	}

	/**
	 * Render the Conversations admin page.
	 */
	public function render_conversations(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		include GRAYFOX_PATH . 'templates/admin/conversations.php';
	}

	/**
	 * Handle document upload form submission.
	 */
	public function handle_document_upload(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized', '', array( 'response' => 403 ) );
		}

		check_admin_referer( 'grayfox_upload_document' );

		if ( empty( $_FILES['grayfox_document']['name'] ) ) {
			wp_safe_redirect( add_query_arg( array( 'page' => 'grayfox-knowledge-base', 'error' => 'no_file' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$allowed_extensions = array( 'pdf', 'docx', 'txt', 'csv', 'md' );
		$uploaded_ext       = strtolower( pathinfo( sanitize_file_name( $_FILES['grayfox_document']['name'] ?? '' ), PATHINFO_EXTENSION ) );

		if ( ! in_array( $uploaded_ext, $allowed_extensions, true ) ) {
			wp_safe_redirect( add_query_arg( array( 'page' => 'grayfox-knowledge-base', 'error' => 'upload_failed' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		// Register .md as text/plain so wp_check_filetype() maps it before finfo
		// validation runs. Without this, $type is false when WordPress checks whether
		// the detected text/plain MIME is acceptable, causing it to reject the file.
		add_filter( 'upload_mimes', function ( $mimes ) {
			$mimes['md'] = 'text/plain';
			return $mimes;
		} );

		// Safety net: explicitly confirm ext/type after all WordPress checks complete.
		add_filter( 'wp_check_filetype_and_ext', function ( $data, $file, $filename ) {
			if ( 'md' === strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) ) ) {
				$data['ext']  = 'md';
				$data['type'] = 'text/plain';
			}
			return $data;
		}, 10, 3 );

		$attachment_id = media_handle_upload( 'grayfox_document', 0 );

		if ( is_wp_error( $attachment_id ) ) {
			wp_safe_redirect( add_query_arg( array( 'page' => 'grayfox-knowledge-base', 'error' => 'upload_failed' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		// Schedule background processing.
		GrayFox_RAG::schedule_processing( $attachment_id );

		wp_safe_redirect( add_query_arg( array( 'page' => 'grayfox-knowledge-base', 'uploaded' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/* -----------------------------------------------------------
	 * KB document management AJAX handlers
	 * --------------------------------------------------------- */

	/**
	 * AJAX: Delete a KB document row (and its media attachment if type=upload).
	 */
	public function handle_delete_kb_document(): void {
		check_ajax_referer( 'grayfox_delete_kb_document' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'kbfox' ), 403 );
		}

		$doc_id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		if ( ! $doc_id ) {
			wp_send_json_error( __( 'Invalid document ID.', 'kbfox' ) );
		}

		global $wpdb;
		$kb_table = esc_sql( GrayFox_DB::get_table( 'knowledge_base' ) );

		$row = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT source_type, source_id FROM `{$kb_table}` WHERE id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$doc_id
		) );

		if ( ! $row ) {
			wp_send_json_error( __( 'Document not found.', 'kbfox' ) );
		}

		if ( 'upload' === $row->source_type && ! empty( $row->source_id ) ) {
			wp_delete_attachment( (int) $row->source_id, true );
		}

		$wpdb->delete( $kb_table, array( 'id' => $doc_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$doc_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$kb_table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		wp_send_json_success( array( 'doc_count' => $doc_count ) );
	}

	/**
	 * AJAX: Re-queue a failed KB document for processing.
	 */
	public function handle_retry_kb_document(): void {
		check_ajax_referer( 'grayfox_retry_kb_document' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'kbfox' ), 403 );
		}

		$doc_id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		if ( ! $doc_id ) {
			wp_send_json_error( __( 'Invalid document ID.', 'kbfox' ) );
		}

		global $wpdb;
		$kb_table = esc_sql( GrayFox_DB::get_table( 'knowledge_base' ) );

		$row = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT source_id FROM `{$kb_table}` WHERE id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$doc_id
		) );

		if ( ! $row ) {
			wp_send_json_error( __( 'Document not found.', 'kbfox' ) );
		}

		GrayFox_RAG::schedule_processing( (int) $row->source_id );

		wp_send_json_success( array( 'queued' => true ) );
	}

	/* -----------------------------------------------------------
	 * Conflict resolution AJAX handlers
	 * --------------------------------------------------------- */

	/**
	 * AJAX: Resolve a KB document conflict.
	 */
	public function handle_resolve_conflict(): void {
		check_ajax_referer( 'grayfox_resolve_conflict' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'kbfox' ), 403 );
		}

		$new_doc_id = isset( $_POST['new_doc_id'] ) ? (int) $_POST['new_doc_id'] : 0;
		$old_doc_id = isset( $_POST['old_doc_id'] ) ? (int) $_POST['old_doc_id'] : 0;
		$resolution = isset( $_POST['resolution'] ) ? sanitize_text_field( wp_unslash( $_POST['resolution'] ) ) : '';

		if ( ! $new_doc_id || ! $old_doc_id || ! in_array( $resolution, array( 'keep_new', 'keep_old', 'keep_both' ), true ) ) {
			wp_send_json_error( __( 'Invalid parameters.', 'kbfox' ) );
		}

		global $wpdb;
		$kb_table = esc_sql( GrayFox_DB::get_table( 'knowledge_base' ) );

		switch ( $resolution ) {
			case 'keep_new':
				// Activate new, delete old.
				$old_row = $wpdb->get_row( $wpdb->prepare( "SELECT source_type, source_id FROM `{$kb_table}` WHERE id = %d", $old_doc_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				if ( $old_row && 'upload' === $old_row->source_type ) {
					wp_delete_attachment( (int) $old_row->source_id, true );
				}
				$wpdb->delete( $kb_table, array( 'id' => $old_doc_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update( $kb_table, array( 'status' => 'active' ), array( 'id' => $new_doc_id ), array( '%s' ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				break;

			case 'keep_old':
				// Keep old, delete new.
				$new_row = $wpdb->get_row( $wpdb->prepare( "SELECT source_type, source_id FROM `{$kb_table}` WHERE id = %d", $new_doc_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				if ( $new_row && 'upload' === $new_row->source_type ) {
					wp_delete_attachment( (int) $new_row->source_id, true );
				}
				$wpdb->delete( $kb_table, array( 'id' => $new_doc_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				break;

			case 'keep_both':
				// Activate both.
				$wpdb->update( $kb_table, array( 'status' => 'active' ), array( 'id' => $new_doc_id ), array( '%s' ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update( $kb_table, array( 'status' => 'active' ), array( 'id' => $old_doc_id ), array( '%s' ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				break;
		}

		// Remove this conflict from the pending list.
		$conflicts = (array) get_option( 'grayfox_pending_conflicts', array() );
		$conflicts  = array_filter( $conflicts, function ( $c ) use ( $new_doc_id, $old_doc_id ) {
			return ! ( (int) ( $c['new_doc_id'] ?? 0 ) === $new_doc_id && (int) ( $c['old_doc_id'] ?? 0 ) === $old_doc_id );
		} );
		update_option( 'grayfox_pending_conflicts', array_values( $conflicts ) );

		wp_send_json_success( array( 'resolved' => true ) );
	}

	/**
	 * AJAX: Get a plain-English diff between two conflicting KB documents.
	 */
	public function handle_get_conflict_diff(): void {
		check_ajax_referer( 'grayfox_get_conflict_diff' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'kbfox' ), 403 );
		}

		$new_doc_id = isset( $_POST['new_doc_id'] ) ? (int) $_POST['new_doc_id'] : 0;
		$old_doc_id = isset( $_POST['old_doc_id'] ) ? (int) $_POST['old_doc_id'] : 0;

		if ( ! $new_doc_id || ! $old_doc_id ) {
			wp_send_json_error( __( 'Invalid document IDs.', 'kbfox' ) );
		}

		global $wpdb;
		$kb_table = esc_sql( GrayFox_DB::get_table( 'knowledge_base' ) );

		$new_row = $wpdb->get_row( $wpdb->prepare( "SELECT source_name, content_json FROM `{$kb_table}` WHERE id = %d", $new_doc_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$old_row = $wpdb->get_row( $wpdb->prepare( "SELECT source_name, content_json FROM `{$kb_table}` WHERE id = %d", $old_doc_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( ! $new_row || ! $old_row ) {
			wp_send_json_error( __( 'Documents not found.', 'kbfox' ) );
		}

		$provider  = get_option( 'grayfox_llm_provider', 'openai' );
		$enc_key   = get_option( 'grayfox_llm_api_key', '' );
		$api_key   = grayfox_decrypt( $enc_key );
		$model     = get_option( 'grayfox_llm_model', '' );

		if ( empty( $api_key ) || empty( $model ) ) {
			wp_send_json_error( __( 'LLM not configured.', 'kbfox' ) );
		}

		$llm      = new GrayFox_LLM();
		$messages = array(
			array(
				'role'    => 'user',
				'content' => sprintf(
					"Compare these two knowledge base documents and summarize the key differences in plain English (2-3 sentences max).\n\nDocument A (%s):\n%s\n\nDocument B (%s):\n%s",
					esc_html( $old_row->source_name ?? 'Old' ),
					wp_strip_all_tags( $old_row->content_json ?? '' ),
					esc_html( $new_row->source_name ?? 'New' ),
					wp_strip_all_tags( $new_row->content_json ?? '' )
				),
			),
		);

		$diff_json = $llm->request_json( $provider, $api_key, $model, $messages, 0.3 );
		$diff_data = json_decode( $diff_json, true );
		$diff_text = is_array( $diff_data ) ? ( $diff_data['summary'] ?? $diff_json ) : $diff_json;

		wp_send_json_success( array( 'diff' => wp_strip_all_tags( $diff_text ) ) );
	}

}
