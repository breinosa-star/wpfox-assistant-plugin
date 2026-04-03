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

		// Site builder wizard.
		$loader->add_action( 'wp_ajax_grayfox_generate_sitemap_preview', $this, 'handle_generate_sitemap_preview' );
		$loader->add_action( 'wp_ajax_grayfox_save_sitemap',             $this, 'handle_save_sitemap' );
		$loader->add_action( 'wp_ajax_grayfox_detect_environment',       $this, 'handle_detect_environment' );
		$loader->add_action( 'wp_ajax_grayfox_set_build_format',         $this, 'handle_set_build_format' );
		$loader->add_action( 'wp_ajax_grayfox_estimate_generation_cost', $this, 'handle_estimate_generation_cost' );
		$loader->add_action( 'wp_ajax_grayfox_start_site_generation',    $this, 'handle_start_site_generation' );
		$loader->add_action( 'wp_ajax_grayfox_get_build_progress',       $this, 'handle_get_build_progress' );
		$loader->add_action( 'wp_ajax_grayfox_undo_site_build',          $this, 'handle_undo_site_build' );
		$loader->add_action( 'wp_ajax_grayfox_save_unsplash_key',        $this, 'handle_save_unsplash_key' );
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

		// Enqueue Google Connect JS only on the grayfox-google page.
		if ( false !== strpos( $hook, 'grayfox-google' ) ) {
			wp_enqueue_script(
				'grayfox-google-connect',
				GRAYFOX_URL . 'src/styles/google-connect.js',
				array(),
				GRAYFOX_VERSION,
				true
			);

			wp_localize_script(
				'grayfox-google-connect',
				'GrayFoxGoogleL10n',
				array(
					'confirmDisconnect' => __( 'Disconnect Google account? This will remove all stored tokens. You will need to reconnect to use Google features.', 'grayfox' ),
					'disconnectLabel'   => __( 'Disconnect Google Account', 'grayfox' ),
					'disconnecting'     => __( 'Disconnecting\u2026', 'grayfox' ),
					'disconnectFailed'  => __( 'Disconnect failed. Please try again.', 'grayfox' ),
					'saving'            => __( 'Saving\u2026', 'grayfox' ),
					'saved'             => __( 'Saved!', 'grayfox' ),
					'saveFailed'        => __( 'Save failed.', 'grayfox' ),
					'networkError'      => __( 'Network error. Please try again.', 'grayfox' ),
					'saveNonce'         => wp_create_nonce( 'grayfox_save_google_credentials' ),
				)
			);
		}

		// Enqueue Drive Sync JS only on the grayfox-drive-sync page.
		if ( false !== strpos( $hook, 'grayfox-drive-sync' ) ) {
			wp_enqueue_script(
				'grayfox-drive-sync',
				GRAYFOX_URL . 'assets/dist/grayfox-drive-sync.min.js',
				array(),
				GRAYFOX_VERSION,
				true
			);

			wp_localize_script(
				'grayfox-drive-sync',
				'GrayFoxDriveL10n',
				array(
					'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
					'nonce'         => wp_create_nonce( 'grayfox_drive' ),
					'loading'       => __( 'Loading\u2026', 'grayfox' ),
					'syncNow'       => __( 'Sync Now', 'grayfox' ),
					'syncing'       => __( 'Syncing\u2026', 'grayfox' ),
					'saving'        => __( 'Saving\u2026', 'grayfox' ),
					'saved'         => __( 'Saved!', 'grayfox' ),
					'networkError'  => __( 'Network error. Please try again.', 'grayfox' ),
				'queuing'       => __( 'Queuing\u2026', 'grayfox' ),
				'reSync'        => __( 'Re-sync', 'grayfox' ),
				'preSelected'   => array_values( array_filter( (array) json_decode( get_option( 'grayfox_drive_selected_files', '[]' ), true ) ) ),
				)
			);
		}

		// Enqueue Site Builder JS only on the grayfox-site-builder page.
		if ( false !== strpos( $hook, 'grayfox-site-builder' ) ) {
			wp_enqueue_script(
				'grayfox-site-builder',
				GRAYFOX_URL . 'assets/dist/grayfox-site-builder.min.js',
				array(),
				GRAYFOX_VERSION,
				true
			);

			wp_localize_script(
				'grayfox-site-builder',
				'GrayFoxSiteBuilderL10n',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonces'  => array(
						'generateSitemapPreview' => wp_create_nonce( 'grayfox_generate_sitemap_preview' ),
						'saveSitemap'            => wp_create_nonce( 'grayfox_save_sitemap' ),
						'detectEnvironment'      => wp_create_nonce( 'grayfox_detect_environment' ),
						'setBuildFormat'         => wp_create_nonce( 'grayfox_set_build_format' ),
						'estimateGenerationCost' => wp_create_nonce( 'grayfox_estimate_generation_cost' ),
						'startSiteGeneration'    => wp_create_nonce( 'grayfox_start_site_generation' ),
						'getBuildProgress'       => wp_create_nonce( 'grayfox_get_build_progress' ),
						'undoSiteBuild'          => wp_create_nonce( 'grayfox_undo_site_build' ),
						'saveUnsplashKey'        => wp_create_nonce( 'grayfox_save_unsplash_key' ),
					),
				)
			);
		}

		// Enqueue Sheets Analytics JS only on the grayfox-sheets page.
		if ( false !== strpos( $hook, 'grayfox-sheets' ) ) {
			wp_enqueue_script(
				'grayfox-sheets',
				GRAYFOX_URL . 'assets/dist/grayfox-sheets.min.js',
				array(),
				GRAYFOX_VERSION,
				true
			);

			wp_localize_script(
				'grayfox-sheets',
				'GrayFoxSheetsL10n',
				array(
					'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
					'nonce'        => wp_create_nonce( 'grayfox_sheets' ),
					'loading'      => __( 'Loading\u2026', 'grayfox' ),
					'saving'       => __( 'Saving\u2026', 'grayfox' ),
					'saved'        => __( 'Saved!', 'grayfox' ),
					'analyzing'    => __( 'Analyzing\u2026', 'grayfox' ),
					'scheduling'   => __( 'Scheduling\u2026', 'grayfox' ),
					'networkError' => __( 'Network error. Please try again.', 'grayfox' ),
					'noReports'    => __( 'No scheduled reports yet.', 'grayfox' ),
					'loadSheets'   => __( 'Load Sheets', 'grayfox' ),
					'saveSettings' => __( 'Save Settings', 'grayfox' ),
					'analyze'      => __( 'Analyze', 'grayfox' ),
					'schedule'     => __( 'Schedule', 'grayfox' ),
					'delete'       => __( 'Delete', 'grayfox' ),
				)
			);
		}
	}

	/**
	 * Register admin menus and submenus.
	 */
	public function register_menus(): void {
		// Top-level menu.
		add_menu_page(
			__( 'GrayFox AI', 'grayfox' ),
			__( 'GrayFox', 'grayfox' ),
			'manage_options',
			'grayfox',
			array( $this, 'render_overview' ),
			'dashicons-format-chat',
			25
		);

		// Overview subpage (same as top-level).
		add_submenu_page(
			'grayfox',
			__( 'Overview', 'grayfox' ),
			__( 'Overview', 'grayfox' ),
			'manage_options',
			'grayfox',
			array( $this, 'render_overview' )
		);

		// Settings subpage (renders via Settings API).
		add_submenu_page(
			'grayfox',
			__( 'Settings', 'grayfox' ),
			__( 'Settings', 'grayfox' ),
			'manage_options',
			'grayfox-settings',
			array( $this, 'render_settings' )
		);

		// Knowledge Base subpage.
		add_submenu_page(
			'grayfox',
			__( 'Knowledge Base', 'grayfox' ),
			__( 'Knowledge Base', 'grayfox' ),
			'manage_options',
			'grayfox-knowledge-base',
			array( $this, 'render_knowledge_base' )
		);

		// Conversations subpage.
		add_submenu_page(
			'grayfox',
			__( 'Conversations', 'grayfox' ),
			__( 'Conversations', 'grayfox' ),
			'manage_options',
			'grayfox-conversations',
			array( $this, 'render_conversations' )
		);

		// Google Connect subpage (Growth tier and above).
		add_submenu_page(
			'grayfox',
			__( 'Google Connect', 'grayfox' ),
			__( 'Google Connect', 'grayfox' ),
			'manage_options',
			'grayfox-google',
			array( $this, 'render_google_connect' )
		);

		// Appointments subpage (Growth tier and above).
		add_submenu_page(
			'grayfox',
			__( 'Appointments', 'grayfox' ),
			__( 'Appointments', 'grayfox' ),
			'manage_options',
			'grayfox-appointments',
			array( $this, 'render_appointments' )
		);

		// Booking Settings subpage (Growth tier and above).
		add_submenu_page(
			'grayfox',
			__( 'Booking Settings', 'grayfox' ),
			__( 'Booking Settings', 'grayfox' ),
			'manage_options',
			'grayfox-booking-settings',
			array( $this, 'render_booking_settings' )
		);

		// Drive Sync subpage (Growth tier and above).
		add_submenu_page(
			'grayfox',
			__( 'Drive Sync', 'grayfox' ),
			__( 'Drive Sync', 'grayfox' ),
			'manage_options',
			'grayfox-drive-sync',
			array( $this, 'render_drive_sync' )
		);

		// Analytics subpage (Pro tier only).
		add_submenu_page(
			'grayfox',
			__( 'Analytics', 'grayfox' ),
			__( 'Analytics', 'grayfox' ),
			'manage_options',
			'grayfox-sheets',
			array( $this, 'render_sheets_analytics' )
		);

		// Build Site subpage (Free tier and above).
		add_submenu_page(
			'grayfox',
			__( 'Build Site', 'grayfox' ),
			__( 'Build Site', 'grayfox' ),
			'manage_options',
			'grayfox-site-builder',
			array( $this, 'render_site_builder' )
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
	 * Render the Google Connect admin page.
	 * Shows an upgrade CTA if the license tier is starter.
	 */
	public function render_google_connect(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		include GRAYFOX_PATH . 'templates/admin/google-connect.php';
	}

	/**
	 * Render the Appointments admin page (Growth tier and above).
	 */
	public function render_appointments(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		include GRAYFOX_PATH . 'templates/admin/appointments.php';
	}

	/**
	 * Render the Booking Settings admin page (Growth tier and above).
	 */
	public function render_booking_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		include GRAYFOX_PATH . 'templates/admin/booking-settings.php';
	}

	/**
	 * Render the Drive Sync admin page.
	 *
	 * Shows an upgrade CTA for Starter tier users; full UI for Growth and Pro.
	 */
	public function render_drive_sync(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		include GRAYFOX_PATH . 'templates/admin/drive-sync.php';
	}

	/**
	 * Render the Sheets Analytics admin page (Pro tier only).
	 */
	public function render_sheets_analytics(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		include GRAYFOX_PATH . 'templates/admin/sheets.php';
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
			wp_redirect( add_query_arg( array( 'page' => 'grayfox-knowledge-base', 'error' => 'no_file' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		// Check tier limit before uploading.
		$rag = new GrayFox_RAG();
		if ( ! $rag->check_tier_limit() ) {
			wp_redirect( add_query_arg( array( 'page' => 'grayfox-knowledge-base', 'error' => 'tier_limit' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$allowed_extensions = array( 'pdf', 'docx', 'txt', 'csv', 'md' );
		$uploaded_ext       = strtolower( pathinfo( $_FILES['grayfox_document']['name'] ?? '', PATHINFO_EXTENSION ) );

		if ( ! in_array( $uploaded_ext, $allowed_extensions, true ) ) {
			wp_redirect( add_query_arg( array( 'page' => 'grayfox-knowledge-base', 'error' => 'upload_failed' ), admin_url( 'admin.php' ) ) );
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
			wp_redirect( add_query_arg( array( 'page' => 'grayfox-knowledge-base', 'error' => 'upload_failed' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		// Schedule background processing.
		GrayFox_RAG::schedule_processing( $attachment_id );

		wp_redirect( add_query_arg( array( 'page' => 'grayfox-knowledge-base', 'uploaded' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Render the Site Builder admin page.
	 */
	public function render_site_builder(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		include GRAYFOX_PATH . 'templates/admin/site-builder.php';
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
			wp_send_json_error( __( 'Unauthorized.', 'grayfox' ), 403 );
		}

		$doc_id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		if ( ! $doc_id ) {
			wp_send_json_error( __( 'Invalid document ID.', 'grayfox' ) );
		}

		global $wpdb;
		$kb_table = GrayFox_DB::get_table( 'knowledge_base' );

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT source_type, source_id FROM `{$kb_table}` WHERE id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$doc_id
		) );

		if ( ! $row ) {
			wp_send_json_error( __( 'Document not found.', 'grayfox' ) );
		}

		if ( 'upload' === $row->source_type && ! empty( $row->source_id ) ) {
			wp_delete_attachment( (int) $row->source_id, true );
		}

		$wpdb->delete( $kb_table, array( 'id' => $doc_id ), array( '%d' ) );

		$doc_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$kb_table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching

		wp_send_json_success( array( 'doc_count' => $doc_count ) );
	}

	/**
	 * AJAX: Re-queue a failed KB document for processing.
	 */
	public function handle_retry_kb_document(): void {
		check_ajax_referer( 'grayfox_retry_kb_document' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'grayfox' ), 403 );
		}

		$doc_id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		if ( ! $doc_id ) {
			wp_send_json_error( __( 'Invalid document ID.', 'grayfox' ) );
		}

		global $wpdb;
		$kb_table = GrayFox_DB::get_table( 'knowledge_base' );

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT source_id FROM `{$kb_table}` WHERE id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$doc_id
		) );

		if ( ! $row ) {
			wp_send_json_error( __( 'Document not found.', 'grayfox' ) );
		}

		GrayFox_RAG::schedule_processing( (int) $row->source_id );

		wp_send_json_success( array( 'queued' => true ) );
	}

	/* -----------------------------------------------------------
	/* -----------------------------------------------------------
	 * Conflict resolution AJAX handlers
	 * --------------------------------------------------------- */

	/**
	 * AJAX: Resolve a KB document conflict.
	 */
	public function handle_resolve_conflict(): void {
		check_ajax_referer( 'grayfox_resolve_conflict' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'grayfox' ), 403 );
		}

		$new_doc_id = isset( $_POST['new_doc_id'] ) ? (int) $_POST['new_doc_id'] : 0;
		$old_doc_id = isset( $_POST['old_doc_id'] ) ? (int) $_POST['old_doc_id'] : 0;
		$resolution = isset( $_POST['resolution'] ) ? sanitize_text_field( wp_unslash( $_POST['resolution'] ) ) : '';

		if ( ! $new_doc_id || ! $old_doc_id || ! in_array( $resolution, array( 'keep_new', 'keep_old', 'keep_both' ), true ) ) {
			wp_send_json_error( __( 'Invalid parameters.', 'grayfox' ) );
		}

		global $wpdb;
		$kb_table = GrayFox_DB::get_table( 'knowledge_base' );

		switch ( $resolution ) {
			case 'keep_new':
				// Activate new, delete old.
				$old_row = $wpdb->get_row( $wpdb->prepare( "SELECT source_type, source_id FROM `{$kb_table}` WHERE id = %d", $old_doc_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				if ( $old_row && 'upload' === $old_row->source_type ) {
					wp_delete_attachment( (int) $old_row->source_id, true );
				}
				$wpdb->delete( $kb_table, array( 'id' => $old_doc_id ), array( '%d' ) );
				$wpdb->update( $kb_table, array( 'status' => 'active' ), array( 'id' => $new_doc_id ), array( '%s' ), array( '%d' ) );
				break;

			case 'keep_old':
				// Keep old, delete new.
				$new_row = $wpdb->get_row( $wpdb->prepare( "SELECT source_type, source_id FROM `{$kb_table}` WHERE id = %d", $new_doc_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				if ( $new_row && 'upload' === $new_row->source_type ) {
					wp_delete_attachment( (int) $new_row->source_id, true );
				}
				$wpdb->delete( $kb_table, array( 'id' => $new_doc_id ), array( '%d' ) );
				break;

			case 'keep_both':
				// Activate both.
				$wpdb->update( $kb_table, array( 'status' => 'active' ), array( 'id' => $new_doc_id ), array( '%s' ), array( '%d' ) );
				$wpdb->update( $kb_table, array( 'status' => 'active' ), array( 'id' => $old_doc_id ), array( '%s' ), array( '%d' ) );
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
			wp_send_json_error( __( 'Unauthorized.', 'grayfox' ), 403 );
		}

		$new_doc_id = isset( $_POST['new_doc_id'] ) ? (int) $_POST['new_doc_id'] : 0;
		$old_doc_id = isset( $_POST['old_doc_id'] ) ? (int) $_POST['old_doc_id'] : 0;

		if ( ! $new_doc_id || ! $old_doc_id ) {
			wp_send_json_error( __( 'Invalid document IDs.', 'grayfox' ) );
		}

		global $wpdb;
		$kb_table = GrayFox_DB::get_table( 'knowledge_base' );

		$new_row = $wpdb->get_row( $wpdb->prepare( "SELECT source_name, content_json FROM `{$kb_table}` WHERE id = %d", $new_doc_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$old_row = $wpdb->get_row( $wpdb->prepare( "SELECT source_name, content_json FROM `{$kb_table}` WHERE id = %d", $old_doc_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! $new_row || ! $old_row ) {
			wp_send_json_error( __( 'Documents not found.', 'grayfox' ) );
		}

		$provider  = get_option( 'grayfox_llm_provider', 'openai' );
		$enc_key   = get_option( 'grayfox_llm_api_key', '' );
		$api_key   = grayfox_decrypt( $enc_key );
		$model     = get_option( 'grayfox_llm_model', '' );

		if ( empty( $api_key ) || empty( $model ) ) {
			wp_send_json_error( __( 'LLM not configured.', 'grayfox' ) );
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

	/* -----------------------------------------------------------
	 * Site builder wizard AJAX handlers
	 * --------------------------------------------------------- */

	/**
	 * AJAX: Generate a sitemap preview from the knowledge base.
	 */
	public function handle_generate_sitemap_preview(): void {
		check_ajax_referer( 'grayfox_generate_sitemap_preview' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'grayfox' ), 403 );
		}

		// Guard against concurrent generation.
		if ( get_transient( GrayFox_SiteBuilder::LOCK_TRANSIENT ) ) {
			wp_send_json_error( __( 'Site generation is already running. Please wait.', 'grayfox' ) );
		}

		global $wpdb;
		$kb_table = GrayFox_DB::get_table( 'knowledge_base' );

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT topic_index FROM `{$kb_table}` WHERE status = 'active' AND topic_index IS NOT NULL" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		);

		if ( empty( $rows ) ) {
			wp_send_json_error( __( 'No active knowledge base documents found. Please add and process documents first.', 'grayfox' ) );
		}

		$topics = array();
		foreach ( $rows as $row ) {
			$t = json_decode( $row->topic_index, true );
			if ( is_array( $t ) ) {
				$topics = array_merge( $topics, $t );
			}
		}
		$topics = array_unique( $topics );

		$provider = get_option( 'grayfox_llm_provider', 'openai' );
		$enc_key  = get_option( 'grayfox_llm_api_key', '' );
		$api_key  = grayfox_decrypt( $enc_key );
		$model    = get_option( 'grayfox_llm_model', '' );

		if ( empty( $api_key ) || empty( $model ) ) {
			wp_send_json_error( __( 'LLM not configured.', 'grayfox' ) );
		}

		$topic_list = implode( ', ', array_slice( $topics, 0, 100 ) );
		$messages   = array(
			array(
				'role'    => 'user',
				'content' => sprintf(
					'Based on these topics from a business knowledge base, suggest a logical page hierarchy for a website. Return JSON only. Format: {"pages":[{"title":"Home","children":[{"title":"About","children":[]}]}]}. Topics: %s',
					$topic_list
				),
			),
		);

		$llm       = new GrayFox_LLM();
		$raw       = $llm->request_json( $provider, $api_key, $model, $messages, 0.3 );
		$parsed    = json_decode( $raw, true );

		if ( ! is_array( $parsed ) || empty( $parsed['pages'] ) ) {
			wp_send_json_error( __( 'Failed to generate sitemap. Please try again.', 'grayfox' ) );
		}

		wp_send_json_success( array(
			'sitemap' => $parsed['pages'],
			'notice'  => __( 'This is a preview. You can edit the page names before saving.', 'grayfox' ),
		) );
	}

	/**
	 * AJAX: Save the approved sitemap draft.
	 */
	public function handle_save_sitemap(): void {
		check_ajax_referer( 'grayfox_save_sitemap' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'grayfox' ), 403 );
		}

		$raw_pages = isset( $_POST['pages'] ) ? wp_unslash( $_POST['pages'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$pages     = json_decode( $raw_pages, true );

		if ( ! is_array( $pages ) ) {
			wp_send_json_error( __( 'Invalid sitemap data.', 'grayfox' ) );
		}

		// Recursively sanitize page titles.
		$pages = $this->sanitize_sitemap_pages( $pages );

		update_option( GrayFox_SiteBuilder::SITEMAP_OPTION, $pages );

		wp_send_json_success( array( 'saved' => true ) );
	}

	/**
	 * Recursively sanitize page titles in the sitemap array.
	 *
	 * @param array $pages Sitemap pages array.
	 * @return array Sanitized pages array.
	 */
	private function sanitize_sitemap_pages( array $pages ): array {
		foreach ( $pages as &$page ) {
			$page['title'] = sanitize_text_field( $page['title'] ?? '' );
			if ( ! empty( $page['children'] ) && is_array( $page['children'] ) ) {
				$page['children'] = $this->sanitize_sitemap_pages( $page['children'] );
			}
		}
		return $pages;
	}

	/**
	 * AJAX: Detect the active page builder/theme environment.
	 */
	public function handle_detect_environment(): void {
		check_ajax_referer( 'grayfox_detect_environment' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'grayfox' ), 403 );
		}

		wp_send_json_success( GrayFox_SiteBuilder::detect_environment() );
	}

	/**
	 * AJAX: Set the page build format (blocks or elementor).
	 */
	public function handle_set_build_format(): void {
		check_ajax_referer( 'grayfox_set_build_format' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'grayfox' ), 403 );
		}

		$format = isset( $_POST['format'] ) ? sanitize_text_field( wp_unslash( $_POST['format'] ) ) : '';

		if ( ! in_array( $format, array( 'blocks', 'elementor' ), true ) ) {
			wp_send_json_error( __( 'Invalid format.', 'grayfox' ) );
		}

		if ( 'elementor' === $format ) {
			$env = GrayFox_SiteBuilder::detect_environment();
			if ( empty( $env['has_elementor'] ) || empty( $env['elementor_version_ok'] ) ) {
				wp_send_json_error( __( 'Elementor is not active or the version is too old (requires 3.0.0+).', 'grayfox' ) );
			}
		}

		update_option( GrayFox_SiteBuilder::FORMAT_OPTION, $format );
		wp_send_json_success( array( 'format' => $format ) );
	}

	/**
	 * AJAX: Estimate token usage and cost for site generation.
	 */
	public function handle_estimate_generation_cost(): void {
		check_ajax_referer( 'grayfox_estimate_generation_cost' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'grayfox' ), 403 );
		}

		$sitemap = get_option( GrayFox_SiteBuilder::SITEMAP_OPTION, array() );

		if ( empty( $sitemap ) ) {
			wp_send_json_error( __( 'No sitemap saved. Complete step 1 first.', 'grayfox' ) );
		}

		$site_builder = GrayFox_SiteBuilder::get_instance();
		$estimate     = $site_builder->estimate_tokens( $sitemap );

		wp_send_json_success( $estimate );
	}

	/**
	 * AJAX: Start the site generation AS background job.
	 */
	public function handle_start_site_generation(): void {
		check_ajax_referer( 'grayfox_start_site_generation' );

		if ( ! current_user_can( 'publish_pages' ) ) {
			wp_send_json_error( __( 'You do not have permission to publish pages.', 'grayfox' ), 403 );
		}

		if ( get_transient( GrayFox_SiteBuilder::LOCK_TRANSIENT ) ) {
			wp_send_json_error( __( 'Site generation is already running.', 'grayfox' ) );
		}

		$sitemap = get_option( GrayFox_SiteBuilder::SITEMAP_OPTION, array() );
		$format  = get_option( GrayFox_SiteBuilder::FORMAT_OPTION, 'blocks' );

		if ( empty( $sitemap ) ) {
			wp_send_json_error( __( 'No sitemap saved. Complete steps 1-3 first.', 'grayfox' ) );
		}

		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			wp_send_json_error( __( 'Action Scheduler is not available. Please ensure WooCommerce or a compatible plugin is active.', 'grayfox' ) );
		}

		$page_count = $this->count_sitemap_pages( $sitemap );

		set_transient( GrayFox_SiteBuilder::LOCK_TRANSIENT, 1, 1800 );

		update_option( GrayFox_SiteBuilder::BUILD_OPTION, array(
			'status'    => 'running',
			'total'     => $page_count,
			'completed' => 0,
			'pages'     => array(),
		) );

		as_enqueue_async_action( GrayFox_SiteBuilder::AS_HOOK_GENERATE, array( $sitemap, $format ), 'grayfox' );

		wp_send_json_success( array( 'started' => true ) );
	}

	/**
	 * Count total pages (including nested children) in a sitemap array.
	 *
	 * @param array $pages Sitemap pages.
	 * @return int
	 */
	private function count_sitemap_pages( array $pages ): int {
		$count = 0;
		foreach ( $pages as $page ) {
			$count++;
			if ( ! empty( $page['children'] ) && is_array( $page['children'] ) ) {
				$count += $this->count_sitemap_pages( $page['children'] );
			}
		}
		return $count;
	}

	/**
	 * AJAX: Get the current site build progress.
	 */
	public function handle_get_build_progress(): void {
		check_ajax_referer( 'grayfox_get_build_progress' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'grayfox' ), 403 );
		}

		wp_send_json_success( get_option( GrayFox_SiteBuilder::BUILD_OPTION, array( 'status' => 'idle' ) ) );
	}

	/**
	 * AJAX: Trash all GrayFox-generated pages.
	 */
	public function handle_undo_site_build(): void {
		check_ajax_referer( 'grayfox_undo_site_build' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'grayfox' ), 403 );
		}

		$confirmed = isset( $_POST['confirmed'] ) && '1' === $_POST['confirmed'];

		if ( ! $confirmed ) {
			wp_send_json_error( __( 'Confirmation required.', 'grayfox' ) );
		}

		$post_ids = get_posts( array(
			'post_type'      => 'page',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'meta_key'       => GrayFox_SiteBuilder::META_GENERATED, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => '1',                                  // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'fields'         => 'ids',
		) );

		$trashed = 0;
		foreach ( $post_ids as $post_id ) {
			if ( wp_trash_post( $post_id ) ) {
				$trashed++;
			}
		}

		update_option( GrayFox_SiteBuilder::BUILD_OPTION, array( 'status' => 'idle' ) );

		wp_send_json_success( array( 'trashed_count' => $trashed ) );
	}

	/**
	 * AJAX: Save the Unsplash API key (encrypted).
	 */
	public function handle_save_unsplash_key(): void {
		check_ajax_referer( 'grayfox_save_unsplash_key' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'grayfox' ), 403 );
		}

		$key = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';

		if ( empty( $key ) || preg_match( '/^\*+$/', $key ) ) {
			wp_send_json_error( __( 'Invalid key.', 'grayfox' ) );
		}

		update_option( GrayFox_SiteBuilder::UNSPLASH_OPTION, grayfox_encrypt( $key ) );

		wp_send_json_success( array( 'saved' => true ) );
	}
}
