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
		$loader->add_action( 'wp_ajax_grayfox_submit_page_revisions',    $this, 'handle_submit_page_revisions' );
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

		// Enqueue Site Builder JS only on the grayfox-site-builder page.
		if ( false !== strpos( $hook, 'grayfox-site-builder' ) ) {
			$sb_js_ver = GRAYFOX_VERSION . '.' . filemtime( GRAYFOX_PATH . 'assets/dist/grayfox-site-builder.min.js' );
			wp_enqueue_script(
				'grayfox-site-builder',
				GRAYFOX_URL . 'assets/dist/grayfox-site-builder.min.js',
				array(),
				$sb_js_ver,
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
						'submitPageRevisions'    => wp_create_nonce( 'grayfox_submit_page_revisions' ),
					'runSiteAudit'           => wp_create_nonce( 'grayfox_run_site_audit' ),
					'fixAuditSection'        => wp_create_nonce( 'grayfox_fix_audit_section' ),
					'getAuditResults'        => wp_create_nonce( 'grayfox_get_audit_results' ),
					'getFooterConfig'        => wp_create_nonce( 'grayfox_get_footer_config' ),
					'saveFooterConfig'       => wp_create_nonce( 'grayfox_save_footer_config' ),
					'resetFooter'            => wp_create_nonce( 'grayfox_reset_footer' ),
					'suggestFooterLinks'     => wp_create_nonce( 'grayfox_suggest_footer_links' ),
					'llmGenerateAltText'     => wp_create_nonce( 'grayfox_llm_generate_alt_text' ),
					'llmSuggestLinkTargets'  => wp_create_nonce( 'grayfox_llm_suggest_link_targets' ),
					'llmReplacePlaceholders' => wp_create_nonce( 'grayfox_llm_replace_placeholders' ),
					'applyLlmFixes'          => wp_create_nonce( 'grayfox_apply_llm_fixes' ),
					'undoAuditFix'           => wp_create_nonce( 'grayfox_undo_audit_fix' ),
					),
					'adminUrl'  => admin_url( '' ),
				)
			);
		}

		// Enqueue Theme Builder JS only on the grayfox-theme-builder page.
		if ( false !== strpos( $hook, 'grayfox-theme-builder' ) ) {
			wp_enqueue_media(); // Needed for wp.media() logo picker.
			$tb_js_path = GRAYFOX_PATH . 'assets/dist/grayfox-theme-builder.min.js';
			$tb_js_ver  = GRAYFOX_VERSION . '.' . ( file_exists( $tb_js_path ) ? filemtime( $tb_js_path ) : '0' );
			wp_enqueue_script(
				'grayfox-theme-builder',
				GRAYFOX_URL . 'assets/dist/grayfox-theme-builder.min.js',
				array(),
				$tb_js_ver,
				true
			);
			wp_localize_script(
				'grayfox-theme-builder',
				'GrayFoxThemeBuilderL10n',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonces'  => array(
						'analyzeLogo'      => wp_create_nonce( 'grayfox_tb_analyze_logo' ),
						'generateTheme'    => wp_create_nonce( 'grayfox_tb_generate_theme' ),
						'saveBrandProfile' => wp_create_nonce( 'grayfox_tb_save_brand_profile' ),
						'applyTheme'       => wp_create_nonce( 'grayfox_tb_apply_theme' ),
						'deleteTheme'      => wp_create_nonce( 'grayfox_tb_delete_theme' ),
					),
					'maxThemes' => GrayFox_ThemeBuilder::MAX_THEMES,
					'providerSupportsVision' => GrayFox_ThemeBuilder::get_instance()->provider_supports_vision(
						get_option( 'grayfox_llm_provider', 'openai' )
					),
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

		// Build Site subpage (Free tier and above).
		add_submenu_page(
			'grayfox',
			__( 'Build Site', 'grayfox' ),
			__( 'Build Site', 'grayfox' ),
			'manage_options',
			'grayfox-site-builder',
			array( $this, 'render_site_builder' )
		);

		// Theme Builder subpage.
		add_submenu_page(
			'grayfox',
			__( 'Theme Builder', 'grayfox' ),
			__( 'Theme Builder', 'grayfox' ),
			'manage_options',
			'grayfox-theme-builder',
			array( $this, 'render_theme_builder' )
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

	/**
	 * Render the Site Builder admin page.
	 */
	public function render_site_builder(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		include GRAYFOX_PATH . 'templates/admin/site-builder.php';
	}

	/**
	 * Render the Theme Builder admin page.
	 */
	public function render_theme_builder(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		include GRAYFOX_PATH . 'templates/admin/theme-builder.php';
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
		$kb_table = esc_sql( GrayFox_DB::get_table( 'knowledge_base' ) );

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
		$kb_table = esc_sql( GrayFox_DB::get_table( 'knowledge_base' ) );

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
		$kb_table = esc_sql( GrayFox_DB::get_table( 'knowledge_base' ) );

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
		$kb_table = esc_sql( GrayFox_DB::get_table( 'knowledge_base' ) );

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

		// Read the full KB content — not just topic keywords.
		// get_consolidated_knowledge() returns actual content_json so the LLM
		// can understand what the business is before designing the site.
		$knowledge_json = GrayFox_RAG::get_consolidated_knowledge();
		if ( empty( $knowledge_json ) ) {
			wp_send_json_error( __( 'No active knowledge base documents found. Please add and process documents first.', 'grayfox' ) );
		}
		// Cap at 15k characters — enough to understand the business without burning
		// excessive tokens on a single sitemap-generation call.
		$knowledge_excerpt = mb_substr( $knowledge_json, 0, 15000 );

		$provider = get_option( 'grayfox_llm_provider', 'openai' );
		$enc_key  = get_option( 'grayfox_llm_api_key', '' );
		$api_key  = grayfox_decrypt( $enc_key );
		$model    = get_option( 'grayfox_llm_model', '' );

		if ( empty( $api_key ) || empty( $model ) ) {
			wp_send_json_error( __( 'LLM not configured.', 'grayfox' ) );
		}

		// Valid page_type values — shared between the sitemap prompt and the pattern
		// selection step in generate_page(). Keeping them in one place avoids drift.
		$page_types = 'home, about, team, services_overview, service_detail, product_overview, product_detail, pricing, contact, portfolio, technical, landing, reference';

		$messages = array(
			array(
				'role'    => 'system',
				'content' => GRAYFOX_PROMPT_SITE_BUILDER_SITEMAP_SYSTEM,
			),
			array(
				'role'    => 'user',
				'content' => str_replace(
					array( '{{KNOWLEDGE_EXCERPT}}', '{{PAGE_TYPES}}' ),
					array( $knowledge_excerpt, $page_types ),
					GRAYFOX_PROMPT_SITE_BUILDER_SITEMAP_USER
				),
			),
		);

		$llm    = new GrayFox_LLM();
		$raw    = $llm->request_json( $provider, $api_key, $model, $messages, 0.3 );

		if ( '' === $raw ) {
			wp_send_json_error( __( 'The AI provider did not respond. This may be a temporary issue or an invalid API key. Please check your LLM settings and try again.', 'grayfox' ) );
		}

		$parsed = json_decode( $raw, true );

		if ( ! is_array( $parsed ) ) {
			wp_send_json_error( __( 'The AI returned an unexpected response. Please try again.', 'grayfox' ) );
		}

		if ( empty( $parsed['pages'] ) ) {
			wp_send_json_error( __( 'The AI response was missing the expected sitemap structure. Please try again.', 'grayfox' ) );
		}

		// Persist the business profile so page generation can reference it without
		// re-reading the entire KB on every single page.
		if ( ! empty( $parsed['business_profile'] ) && is_array( $parsed['business_profile'] ) ) {
			set_transient( GrayFox_SiteBuilder::BUSINESS_PROFILE_TRANSIENT, $parsed['business_profile'], DAY_IN_SECONDS );
			// Also save as a durable option so Theme Builder can read it across sessions.
			update_option( 'grayfox_business_profile', $parsed['business_profile'] );
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
		$valid_page_types = array(
			'home', 'about', 'team', 'services_overview', 'service_detail',
			'product_overview', 'product_detail', 'pricing', 'contact',
			'portfolio', 'technical', 'landing', 'reference',
		);

		foreach ( $pages as &$page ) {
			$page['title']         = sanitize_text_field( $page['title'] ?? '' );
			$page['content_brief'] = sanitize_textarea_field( $page['content_brief'] ?? '' );

			// Whitelist page_type — fall back to 'reference' if the LLM returned an
			// unknown value so pattern selection always has a valid type to work with.
			$raw_type        = sanitize_key( $page['page_type'] ?? '' );
			$page['page_type'] = in_array( $raw_type, $valid_page_types, true ) ? $raw_type : 'reference';

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

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'You do not have permission to run site generation.', 'grayfox' ), 403 );
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

		// Action Scheduler's async dispatch fires a loopback HTTP request to start
		// the queue runner. In some environments (Docker, certain hosts) that
		// request fails because the container can't reach itself via its public
		// URL, leaving the job pending until the next WP-Cron tick.
		//
		// Strategy (works universally — no CLI dependency):
		//
		// • With fastcgi_finish_request() (PHP-FPM): close the browser connection
		//   first, then run the AS queue directly in the same process. The AJAX
		//   response is delivered immediately; generation runs in the background.
		//
		// • Without it (mod_php, litespeed, etc.): running AS in the shutdown hook
		//   keeps the browser connection open for the duration of generation,
		//   blocking the AJAX response and preventing the spinner from appearing.
		//   Instead, fire spawn_cron() — a non-blocking background HTTP request to
		//   wp-cron.php that returns instantly. WP-Cron triggers AS within seconds
		//   on standard hosts. In Docker loopback-blocked setups it may still delay,
		//   but the pending-state UI ("Waiting for background worker…") covers that.
		add_action( 'shutdown', static function (): void {
			if ( function_exists( 'fastcgi_finish_request' ) ) {
				fastcgi_finish_request();
				if ( class_exists( 'ActionScheduler_QueueRunner' ) ) {
					ActionScheduler_QueueRunner::instance()->run();
				}
				return;
			}
			// Non-blocking cron spawn — fires and forgets, does not delay response.
			spawn_cron();
		} );

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
	 * AJAX: Enqueue per-page revision jobs from the Results table.
	 *
	 * Expects $_POST['revisions'] as a JSON-encoded array of objects:
	 *   [ { post_id, action_type, dropdown_selection, user_hint }, ... ]
	 */
	public function handle_submit_page_revisions(): void {
		check_ajax_referer( 'grayfox_submit_page_revisions' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'grayfox' ), 403 );
		}

		$raw       = isset( $_POST['revisions'] ) ? wp_unslash( $_POST['revisions'] ) : '[]'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$revisions = json_decode( $raw, true );

		if ( ! is_array( $revisions ) || empty( $revisions ) ) {
			wp_send_json_error( __( 'No revisions submitted.', 'grayfox' ) );
		}

		$valid_actions = array( 'revise_copy', 'rearrange', 'new_images' );
		$queued        = 0;

		foreach ( $revisions as $rev ) {
			$post_id = (int) ( $rev['post_id'] ?? 0 );
			$action  = sanitize_key( $rev['action_type'] ?? '' );

			if ( ! $post_id || ! in_array( $action, $valid_actions, true ) ) {
				continue;
			}

			// Mark pending in build option immediately so UI can reflect queued state.
			$this->mark_revision_pending( $post_id );

			as_enqueue_async_action(
				GrayFox_SiteBuilder::AS_HOOK_REVISE,
				array(
					array(
						'post_id'            => $post_id,
						'action_type'        => $action,
						'dropdown_selection' => sanitize_text_field( $rev['dropdown_selection'] ?? '' ),
						'user_hint'          => sanitize_text_field( mb_substr( $rev['user_hint'] ?? '', 0, 50 ) ),
					),
				),
				'grayfox'
			);
			$queued++;
		}

		if ( ! $queued ) {
			wp_send_json_error( __( 'No valid revision requests found.', 'grayfox' ) );
		}

		// Trigger cron to start jobs promptly (mirrors the main generation flow).
		add_action( 'shutdown', static function (): void {
			if ( function_exists( 'fastcgi_finish_request' ) ) {
				fastcgi_finish_request();
				if ( class_exists( 'ActionScheduler_QueueRunner' ) ) {
					ActionScheduler_QueueRunner::instance()->run();
				}
				return;
			}
			spawn_cron();
		} );

		wp_send_json_success( array( 'queued' => $queued ) );
	}

	/**
	 * Mark a page's revision status as 'pending' in the build option.
	 *
	 * @param int $post_id Page ID.
	 */
	private function mark_revision_pending( int $post_id ): void {
		$build = get_option( GrayFox_SiteBuilder::BUILD_OPTION, array() );
		if ( ! isset( $build['pages'] ) || ! is_array( $build['pages'] ) ) {
			return;
		}
		foreach ( $build['pages'] as &$page ) {
			if ( (int) ( $page['post_id'] ?? 0 ) === $post_id ) {
				$page['revision_status'] = 'pending';
				break;
			}
		}
		unset( $page );
		update_option( GrayFox_SiteBuilder::BUILD_OPTION, $build );
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
