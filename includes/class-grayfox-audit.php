<?php
/**
 * Site Audit — scans and fixes for generated pages.
 *
 * Sections:
 *  1. Accessibility (ADA/WCAG)
 *  2. Broken / Empty Links
 *  3. Content Quality + Publish
 *  4. WordPress Health
 *  5. SEO Basics (read-only on Free tier)
 *
 * Step 6: Footer Configuration (classic nav, FSE block themes, Elementor)
 *
 * @package GrayFox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GrayFox_Audit
 */
class GrayFox_Audit {

	const AUDIT_OPTION = 'grayfox_site_audit';

	/** @var GrayFox_Audit|null */
	private static ?GrayFox_Audit $instance = null;

	public static function get_instance(): GrayFox_Audit {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	/**
	 * Register AJAX hooks.
	 */
	public function register( GrayFox_Loader $loader ): void {
		// Audit
		$loader->add_action( 'wp_ajax_grayfox_run_site_audit',           $this, 'handle_run_audit' );
		$loader->add_action( 'wp_ajax_grayfox_fix_audit_section',        $this, 'handle_fix_section' );
		$loader->add_action( 'wp_ajax_grayfox_get_audit_results',        $this, 'handle_get_results' );
		// LLM-assisted audit fixes
		$loader->add_action( 'wp_ajax_grayfox_llm_generate_alt_text',    $this, 'handle_llm_generate_alt_text' );
		$loader->add_action( 'wp_ajax_grayfox_llm_suggest_link_targets', $this, 'handle_llm_suggest_link_targets' );
		$loader->add_action( 'wp_ajax_grayfox_llm_replace_placeholders', $this, 'handle_llm_replace_placeholders' );
		$loader->add_action( 'wp_ajax_grayfox_apply_llm_fixes',          $this, 'handle_apply_llm_fixes' );
		$loader->add_action( 'wp_ajax_grayfox_undo_audit_fix',           $this, 'handle_undo_fix' );
		// Footer (Step 6)
		$loader->add_action( 'wp_ajax_grayfox_get_footer_config',        $this, 'handle_get_footer' );
		$loader->add_action( 'wp_ajax_grayfox_save_footer_config',       $this, 'handle_save_footer' );
		$loader->add_action( 'wp_ajax_grayfox_reset_footer',             $this, 'handle_reset_footer' );
		$loader->add_action( 'wp_ajax_grayfox_suggest_footer_links',     $this, 'handle_suggest_footer_links' );
	}

	/* -----------------------------------------------------------------------
	 * AJAX Handlers — Audit
	 * --------------------------------------------------------------------- */

	public function handle_run_audit(): void {
		check_ajax_referer( 'grayfox_run_site_audit' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		$post_ids = $this->get_generated_post_ids();

		$sections = array(
			'accessibility'   => $this->run_section( 'accessibility', $post_ids ),
			'broken_links'    => $this->run_section( 'broken_links', $post_ids ),
			'content_quality' => $this->run_section( 'content_quality', $post_ids ),
			'wp_health'       => $this->run_section( 'wp_health', $post_ids ),
			'seo'             => $this->run_section( 'seo', $post_ids ),
		);

		$pages_list = $this->get_all_pages_list();

		$audit = array(
			'status'    => 'complete',
			'run_at'    => time(),
			'sections'  => $sections,
			'all_pages' => $pages_list,
		);

		update_option( self::AUDIT_OPTION, $audit );
		wp_send_json_success( $audit );
	}

	public function handle_fix_section(): void {
		check_ajax_referer( 'grayfox_fix_audit_section' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		$section  = sanitize_key( $_POST['section'] ?? '' );
		$post_ids = $this->get_generated_post_ids();

		switch ( $section ) {
			case 'accessibility':
				$this->fix_accessibility( $post_ids );
				break;
			case 'broken_links':
				$this->fix_broken_links( $post_ids );
				break;
			case 'content_quality':
				$this->fix_content_quality( $post_ids );
				break;
			case 'wp_health':
				$this->fix_wp_health( $post_ids );
				break;
			default:
				wp_send_json_error( 'Unknown section.' );
				return;
		}

		$updated = $this->run_section( $section, $post_ids );

		$audit = get_option( self::AUDIT_OPTION, array() );
		if ( isset( $audit['sections'] ) ) {
			$audit['sections'][ $section ] = $updated;
			update_option( self::AUDIT_OPTION, $audit );
		}

		wp_send_json_success( array( 'section' => $section, 'result' => $updated ) );
	}

	public function handle_get_results(): void {
		check_ajax_referer( 'grayfox_get_audit_results' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}
		wp_send_json_success( get_option( self::AUDIT_OPTION, array( 'status' => 'idle' ) ) );
	}

	/* -----------------------------------------------------------------------
	 * AJAX Handlers — LLM-Assisted Audit Fixes
	 * --------------------------------------------------------------------- */

	/**
	 * Generate alt text suggestions for images missing alt text.
	 * Returns suggestions for frontend review before applying.
	 */
	public function handle_llm_generate_alt_text(): void {
		check_ajax_referer( 'grayfox_llm_generate_alt_text' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		$post_ids    = $this->get_generated_post_ids();
		$suggestions = array();

		foreach ( $post_ids as $post_id ) {
			$content = get_post_field( 'post_content', $post_id );
			$blocks  = parse_blocks( $content );
			$flat    = $this->flatten_blocks( $blocks );

			// Collect images without alt text.
			$images = array();
			foreach ( $flat as $idx => $block ) {
				if ( 'core/image' !== ( $block['blockName'] ?? '' ) ) {
					continue;
				}
				$attrs = $block['attrs'] ?? array();
				if ( '' !== trim( $attrs['alt'] ?? '' ) ) {
					continue;
				}
				$att_id    = (int) ( $attrs['id'] ?? 0 );
				$att_title = $att_id ? get_the_title( $att_id ) : '';
				$filename  = '';
				if ( $att_id ) {
					$file = get_attached_file( $att_id );
					if ( $file ) {
						$filename = basename( $file );
					}
				}
				// Skip if attachment title is already good (auto-fix handles that).
				if ( $att_title && strpbrk( $att_title, '0123456789' ) === false && strlen( $att_title ) > 4 ) {
					continue; // has a real title — auto-fix will handle it
				}
				$caption     = wp_strip_all_tags( $attrs['caption'] ?? '' );
				$images[]    = array(
					'index'            => $idx,
					'post_id'          => $post_id,
					'filename'         => $filename,
					'caption'          => $caption,
					'attachment_title' => $att_title,
				);
			}

			if ( empty( $images ) ) {
				continue;
			}

			// Build page context from first 400 chars of plain text.
			$page_context = mb_substr( wp_strip_all_tags( $content ), 0, 400 );
			$page_title   = get_the_title( $post_id );

			$user_content = strtr( GRAYFOX_PROMPT_AUDIT_ALT_TEXT_USER, array(
				'{{PAGE_TITLE}}'   => $page_title,
				'{{PAGE_CONTEXT}}' => $page_context,
				'{{IMAGES_JSON}}'  => wp_json_encode( $images ),
			) );

			$messages = array(
				array( 'role' => 'system', 'content' => GRAYFOX_PROMPT_AUDIT_ALT_TEXT_SYSTEM ),
				array( 'role' => 'user',   'content' => $user_content ),
			);

			$llm  = $this->get_llm_client();
			$raw  = $llm['client']->request_json( $llm['provider'], $llm['key'], $llm['model'], $messages, 0.3 );
			$rows = json_decode( $raw, true );

			if ( ! is_array( $rows ) ) {
				continue;
			}

			foreach ( $rows as $row ) {
				if ( ! isset( $row['index'], $row['alt'] ) ) {
					continue;
				}
				// Find the matching image by its flat index.
				$img = null;
				foreach ( $images as $im ) {
					if ( $im['index'] === $row['index'] ) {
						$img = $im;
						break;
					}
				}
				if ( ! $img ) {
					continue;
				}
				$suggestions[] = array(
					'post_id'   => $post_id,
					'page_title'=> $page_title,
					'block_idx' => $row['index'],
					'filename'  => $img['filename'],
					'alt'       => sanitize_text_field( $row['alt'] ),
				);
			}
		}

		// --- Empty button labels (LLM path: no internal URL to auto-resolve) ---
		foreach ( $post_ids as $post_id ) {
			$content  = get_post_field( 'post_content', $post_id );
			$blocks   = parse_blocks( $content );
			$flat     = $this->flatten_blocks( $blocks );
			$buttons  = array();

			foreach ( $flat as $idx => $block ) {
				if ( 'core/button' !== ( $block['blockName'] ?? '' ) ) {
					continue;
				}
				$inner    = implode( '', $block['innerContent'] ?? array() );
				$stripped = trim( wp_strip_all_tags( $inner ) );
				if ( '' !== $stripped ) {
					continue; // has a label already
				}
				$attrs     = $block['attrs'] ?? array();
				$url       = trim( $attrs['url'] ?? '' );
				$linked_id = ( ! empty( $url ) && '#' !== $url ) ? url_to_postid( $url ) : 0;
				$title_val = $linked_id ? get_the_title( $linked_id ) : '';
				if ( $linked_id && $title_val ) {
					continue; // auto-fix handles this one
				}
				$buttons[] = array(
					'index' => $idx,
					'url'   => $url,
				);
			}

			if ( empty( $buttons ) ) {
				continue;
			}

			$page_title   = get_the_title( $post_id );
			$page_context = mb_substr( wp_strip_all_tags( $content ), 0, 400 );

			$user_content = strtr( GRAYFOX_PROMPT_AUDIT_BUTTON_LABEL_USER, array(
				'{{PAGE_TITLE}}'   => $page_title,
				'{{PAGE_CONTEXT}}' => $page_context,
				'{{BUTTONS_JSON}}' => wp_json_encode( $buttons ),
			) );

			$messages = array(
				array( 'role' => 'system', 'content' => GRAYFOX_PROMPT_AUDIT_BUTTON_LABEL_SYSTEM ),
				array( 'role' => 'user',   'content' => $user_content ),
			);

			$llm  = $this->get_llm_client();
			$raw  = $llm['client']->request_json( $llm['provider'], $llm['key'], $llm['model'], $messages, 0.3 );
			$rows = json_decode( $raw, true );

			if ( ! is_array( $rows ) ) {
				continue;
			}
			// LLM sometimes returns a single object instead of an array — normalize it.
			if ( isset( $rows['index'] ) ) {
				$rows = array( $rows );
			}

			foreach ( $rows as $row ) {
				if ( ! isset( $row['index'], $row['label'] ) ) {
					continue;
				}
				// Find the original button entry to get its url.
				$btn_url = '';
				foreach ( $buttons as $btn ) {
					if ( $btn['index'] === $row['index'] ) {
						$btn_url = $btn['url'];
						break;
					}
				}
				$suggestions[] = array(
					'post_id'          => $post_id,
					'page_title'       => $page_title,
					'button_block_idx' => $row['index'],
					'button_label'     => '',
					'replacement'      => sanitize_text_field( $row['label'] ),
					'confidence'       => $row['confidence'] ?? 'medium',
					'reason'           => $row['reason'] ?? '',
				);
			}
		}

		wp_send_json_success( array( 'suggestions' => $suggestions ) );
	}

	/**
	 * Suggest link targets for buttons with no URL.
	 * Returns suggestions with confidence for frontend review.
	 */
	public function handle_llm_suggest_link_targets(): void {
		check_ajax_referer( 'grayfox_llm_suggest_link_targets' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		$post_ids = $this->get_generated_post_ids();
		$buttons  = array();

		foreach ( $post_ids as $post_id ) {
			$content = get_post_field( 'post_content', $post_id );
			$flat    = $this->flatten_blocks( parse_blocks( $content ) );
			$title   = get_the_title( $post_id );

			foreach ( $flat as $block ) {
				if ( 'core/button' !== ( $block['blockName'] ?? '' ) ) {
					continue;
				}
				$attrs    = $block['attrs'] ?? array();
				$btn_url  = trim( $attrs['url'] ?? '' );
				$inner    = implode( '', $block['innerContent'] ?? array() );
				preg_match( '/href="([^"]*)"/i', $inner, $hm );
				$btn_href = trim( $hm[1] ?? '' );

				if ( ( ! empty( $btn_url ) && '#' !== $btn_url )
					|| ( ! empty( $btn_href ) && '#' !== $btn_href )
				) {
					continue; // already has a URL
				}

				preg_match( '/<a[^>]*>(.*?)<\/a>/s', $inner, $lm );
				$label = trim( wp_strip_all_tags( $lm[1] ?? '' ) );
				if ( empty( $label ) ) {
					continue;
				}
				$buttons[] = array(
					'post_id'      => $post_id,
					'page_title'   => $title,
					'button_label' => $label,
				);
			}
		}

		if ( empty( $buttons ) ) {
			wp_send_json_success( array( 'suggestions' => array() ) );
			return;
		}

		$pages = $this->get_all_pages_list();

		$user_content = strtr( GRAYFOX_PROMPT_AUDIT_LINK_SUGGEST_USER, array(
			'{{PAGES_JSON}}'   => wp_json_encode( $pages ),
			'{{BUTTONS_JSON}}' => wp_json_encode( $buttons ),
		) );

		$messages = array(
			array( 'role' => 'system', 'content' => GRAYFOX_PROMPT_AUDIT_LINK_SUGGEST_SYSTEM ),
			array( 'role' => 'user',   'content' => $user_content ),
		);

		$llm  = $this->get_llm_client();
		$raw  = $llm['client']->request_json( $llm['provider'], $llm['key'], $llm['model'], $messages, 0.0 );
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'GrayFox suggest-links LLM raw: ' . mb_substr( (string) $raw, 0, 400 ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		$rows = json_decode( $raw, true );

		if ( ! is_array( $rows ) ) {
			wp_send_json_error( 'LLM did not return valid suggestions.' );
			return;
		}
		// LLM sometimes returns a single object instead of an array — normalize it.
		if ( isset( $rows['button_label'] ) ) {
			$rows = array( $rows );
		}

		// Merge post_id back into suggestions by matching button_label.
		$label_to_post = array();
		foreach ( $buttons as $btn ) {
			$label_to_post[ strtolower( $btn['button_label'] ) ] = $btn['post_id'];
		}

		$suggestions = array();
		foreach ( $rows as $row ) {
			$label   = $row['button_label'] ?? '';
			$post_id = $label_to_post[ strtolower( $label ) ] ?? 0;
			$url     = $row['suggested_url'] ?? null;
			if ( empty( $url ) || null === $url ) {
				continue;
			}
			$suggestions[] = array(
				'post_id'      => $post_id,
				'button_label' => $label,
				'suggested_url'=> esc_url_raw( $url ),
				'confidence'   => sanitize_key( $row['confidence'] ?? 'low' ),
				'reason'       => sanitize_text_field( $row['reason'] ?? '' ),
			);
		}

		wp_send_json_success( array( 'suggestions' => $suggestions ) );
	}

	/**
	 * Generate placeholder replacement suggestions using KB + LLM.
	 */
	public function handle_llm_replace_placeholders(): void {
		check_ajax_referer( 'grayfox_llm_replace_placeholders' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		$post_ids    = $this->get_generated_post_ids();
		$suggestions = array();

		foreach ( $post_ids as $post_id ) {
			$content    = get_post_field( 'post_content', $post_id );
			$plain      = wp_strip_all_tags( $content );
			$page_title = get_the_title( $post_id );

			// Find placeholder instances with surrounding context.
			$pattern      = '/(\{\{[^}]+\}\}|\[Company[^\]]*\]|\[Your[^\]]*\]|Lorem ipsum[^.]*\.?)/i';
			$placeholders = array();
			$offset       = 0;
			$idx          = 0;
			while ( preg_match( $pattern, $plain, $m, PREG_OFFSET_CAPTURE, $offset ) ) {
				$start   = $m[0][1];
				$context = mb_substr( $plain, max( 0, $start - 80 ), 200 );
				$placeholders[] = array(
					'index'              => $idx++,
					'original'           => $m[0][0],
					'surrounding_context'=> $context,
				);
				$offset = $start + strlen( $m[0][0] );
			}

			if ( empty( $placeholders ) ) {
				continue;
			}

			// Get KB excerpt for this page's topic.
			$kb_excerpt = '';
			if ( class_exists( 'GrayFox_RAG' ) ) {
				$kb_excerpt = GrayFox_RAG::get_consolidated_knowledge( $page_title );
				if ( strlen( $kb_excerpt ) > 3000 ) {
					$kb_excerpt = mb_substr( $kb_excerpt, 0, 3000 );
				}
			}

			$user_content = strtr( GRAYFOX_PROMPT_AUDIT_PLACEHOLDER_FIX_USER, array(
				'{{KB_EXCERPT}}'      => $kb_excerpt ?: '(no knowledge base available)',
				'{{PAGE_TITLE}}'      => $page_title,
				'{{PLACEHOLDERS_JSON}}' => wp_json_encode( $placeholders ),
			) );

			$messages = array(
				array( 'role' => 'system', 'content' => GRAYFOX_PROMPT_AUDIT_PLACEHOLDER_FIX_SYSTEM ),
				array( 'role' => 'user',   'content' => $user_content ),
			);

			$llm  = $this->get_llm_client();
			$raw  = $llm['client']->request_json( $llm['provider'], $llm['key'], $llm['model'], $messages, 0.3 );
			$rows = json_decode( $raw, true );

			if ( ! is_array( $rows ) ) {
				continue;
			}
			// Normalize single-object response.
			if ( isset( $rows['original'] ) ) {
				$rows = array( $rows );
			}

			foreach ( $rows as $row ) {
				if ( ! isset( $row['original'], $row['replacement'] ) ) {
					continue;
				}
				$suggestions[] = array(
					'post_id'     => $post_id,
					'page_title'  => $page_title,
					'original'    => $row['original'],
					'replacement' => sanitize_text_field( $row['replacement'] ),
				);
			}
		}

		wp_send_json_success( array( 'suggestions' => $suggestions ) );
	}

	/**
	 * Apply accepted LLM fix suggestions.
	 * Receives: section (string), fixes (JSON array of accepted items).
	 */
	public function handle_apply_llm_fixes(): void {
		check_ajax_referer( 'grayfox_apply_llm_fixes' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		$section   = sanitize_key( $_POST['section'] ?? '' );
		$fixes_raw = wp_unslash( $_POST['fixes'] ?? '[]' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON payload; sanitize_text_field() would corrupt it; structure validated by json_decode + is_array below
		$fixes     = json_decode( $fixes_raw, true );

		if ( ! is_array( $fixes ) || empty( $fixes ) ) {
			wp_send_json_error( 'No fixes provided.' );
			return;
		}

		$post_ids = $this->get_generated_post_ids();

		switch ( $section ) {
			case 'accessibility':
				$this->apply_llm_accessibility_fixes( $fixes );
				break;
			case 'broken_links':
				$this->apply_llm_link_fixes( $post_ids, $fixes );
				break;
			case 'content_quality':
				$this->apply_llm_placeholder_fixes( $fixes );
				break;
			default:
				wp_send_json_error( 'Unknown section.' );
				return;
		}

		$updated = $this->run_section( $section, $post_ids );

		$audit = get_option( self::AUDIT_OPTION, array() );
		if ( isset( $audit['sections'] ) ) {
			$audit['sections'][ $section ] = $updated;
			update_option( self::AUDIT_OPTION, $audit );
		}

		wp_send_json_success( array( 'section' => $section, 'result' => $updated ) );
	}

	/**
	 * Restore post content from pre-fix backup for a given section.
	 */
	public function handle_undo_fix(): void {
		check_ajax_referer( 'grayfox_undo_audit_fix' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		$section  = sanitize_key( $_POST['section'] ?? '' );
		$post_ids = $this->get_generated_post_ids();
		$restored = 0;

		foreach ( $post_ids as $id ) {
			if ( $this->restore_post_content( $id, $section ) ) {
				$restored++;
			}
		}

		$updated = $this->run_section( $section, $post_ids );

		$audit = get_option( self::AUDIT_OPTION, array() );
		if ( isset( $audit['sections'] ) ) {
			$audit['sections'][ $section ] = $updated;
			update_option( self::AUDIT_OPTION, $audit );
		}

		wp_send_json_success( array( 'restored' => $restored, 'result' => $updated ) );
	}

	/* -----------------------------------------------------------------------
	 * AJAX Handlers — Step 6: Menus
	 * --------------------------------------------------------------------- */

	public function handle_get_footer(): void {
		check_ajax_referer( 'grayfox_get_footer_config' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		$generated_ids = $this->get_generated_post_ids();

		$header_menu = $this->get_grayfox_menu( self::HEADER_MENU_NAME );
		$footer_menu = $this->get_grayfox_menu( self::FOOTER_MENU_NAME );

		wp_send_json_success( array(
			'header_menu_exists' => (bool) $header_menu,
			'header_items'       => $this->get_menu_items_list( $header_menu, $generated_ids ),
			'footer_menu_exists' => (bool) $footer_menu,
			'footer_items'       => $this->get_menu_items_list( $footer_menu, $generated_ids ),
			'is_block_theme'     => wp_is_block_theme(),
			'all_pages'          => $this->get_all_pages_list(),
		) );
	}

	public function handle_save_footer(): void {
		check_ajax_referer( 'grayfox_save_footer_config' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		$config = json_decode( wp_unslash( $_POST['config'] ?? '{}' ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON payload; sanitize_text_field() would corrupt it; structure validated by json_decode + is_array below
		if ( ! is_array( $config ) ) {
			wp_send_json_error( 'Invalid config.' );
			return;
		}

		$header_summary = $this->save_nav_menu( self::HEADER_MENU_NAME, $config['header_items'] ?? array() );
		$footer_summary = $this->save_nav_menu( self::FOOTER_MENU_NAME, $config['footer_items'] ?? array() );

		// Block theme only: wire menus into template parts.
		if ( wp_is_block_theme() ) {
			if ( ! empty( $header_summary['menu_id'] ) ) {
				$this->wire_block_theme_nav( (int) $header_summary['menu_id'], 'header' );
			}
			if ( ! empty( $footer_summary['menu_id'] ) ) {
				$this->wire_block_theme_nav( (int) $footer_summary['menu_id'], 'footer' );
			}
		}

		wp_send_json_success( array(
			'saved'          => true,
			'header_summary' => $header_summary,
			'footer_summary' => $footer_summary,
			'verify_url'     => admin_url( 'nav-menus.php' ),
		) );
	}

	/**
	 * Reset menus: delete both GrayFox nav menus.
	 */
	public function handle_reset_footer(): void {
		check_ajax_referer( 'grayfox_reset_footer' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}
		foreach ( array( self::HEADER_MENU_NAME, self::FOOTER_MENU_NAME ) as $name ) {
			$menu = $this->get_grayfox_menu( $name );
			if ( $menu ) {
				wp_delete_nav_menu( $menu->term_id );
			}
		}
		wp_send_json_success( array( 'reset' => true ) );
	}

	/**
	 * LLM-powered categorization: returns which pages belong in header vs footer areas.
	 */
	public function handle_suggest_footer_links(): void {
		check_ajax_referer( 'grayfox_suggest_footer_links' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		$post_ids = $this->get_generated_post_ids();

		if ( empty( $post_ids ) ) {
			wp_send_json_error( 'No generated pages found.' );
			return;
		}

		// Build slug → {post_id, url} lookup for the whole site.
		$slug_to_info = array();
		foreach ( $post_ids as $id ) {
			$slug                  = get_post_field( 'post_name', $id );
			$slug_to_info[ $slug ] = array(
				'post_id' => $id,
				'url'     => get_permalink( $id ),
			);
		}

		$pages = array_map( function ( $id ) {
			return array(
				'title'     => get_the_title( $id ),
				'slug'      => get_post_field( 'post_name', $id ),
				'page_type' => get_post_meta( $id, '_grayfox_page_type', true ) ?: '',
			);
		}, $post_ids );

		$sitemap = get_option( GrayFox_SiteBuilder::SITEMAP_OPTION, array() );

		$user_content = strtr( GRAYFOX_PROMPT_FOOTER_SUGGEST_USER, array(
			'{{SITEMAP_JSON}}' => wp_json_encode( $sitemap ),
			'{{PAGES_JSON}}'   => wp_json_encode( $pages ),
		) );

		$messages = array(
			array( 'role' => 'system', 'content' => GRAYFOX_PROMPT_FOOTER_SUGGEST_SYSTEM ),
			array( 'role' => 'user',   'content' => $user_content ),
		);

		$llm    = $this->get_llm_client();
		$raw    = $llm['client']->request_json( $llm['provider'], $llm['key'], $llm['model'], $messages, 0.0 );
		$result = json_decode( $raw, true );

		if ( ! is_array( $result ) ) {
			wp_send_json_error( 'LLM did not return valid suggestions.' );
			return;
		}

		// Recursively attach post_id + url to every item (including children).
		array_walk_recursive( $result, function ( &$val, $key ) use ( $slug_to_info ) {
			// Only process leaf strings — actual attachment happens at the item level below.
		} );

		$attach_info = function ( array &$items ) use ( &$attach_info, $slug_to_info ): void {
			foreach ( $items as &$item ) {
				$info            = $slug_to_info[ $item['slug'] ?? '' ] ?? array();
				$item['post_id'] = $info['post_id'] ?? 0;
				$item['url']     = $info['url']     ?? '';
				if ( ! empty( $item['children'] ) && is_array( $item['children'] ) ) {
					$attach_info( $item['children'] );
				}
			}
		};

		foreach ( array( 'header_nav', 'footer_nav' ) as $group ) {
			if ( ! isset( $result[ $group ] ) || ! is_array( $result[ $group ] ) ) {
				$result[ $group ] = array();
				continue;
			}
			$attach_info( $result[ $group ] );
		}

		wp_send_json_success( $result );
	}

	/* -----------------------------------------------------------------------
	 * Section dispatcher
	 * --------------------------------------------------------------------- */

	private function run_section( string $section, array $post_ids ): array {
		$issues = match ( $section ) {
			'accessibility'   => $this->scan_accessibility( $post_ids ),
			'broken_links'    => $this->scan_broken_links( $post_ids ),
			'content_quality' => $this->scan_content_quality( $post_ids ),
			'wp_health'       => $this->scan_wp_health( $post_ids ),
			'seo'             => $this->scan_seo( $post_ids ),
			default           => array(),
		};

		return array(
			'status' => empty( $issues ) ? 'pass' : 'issues',
			'issues' => $issues,
		);
	}

	/* -----------------------------------------------------------------------
	 * Step 6: Menus — private methods
	 * --------------------------------------------------------------------- */

	const HEADER_MENU_NAME = 'GrayFox Header Menu';
	const FOOTER_MENU_NAME = 'GrayFox Footer Menu';

	/**
	 * Find a GrayFox nav menu by name, or return null.
	 */
	private function get_grayfox_menu( string $name ): ?\WP_Term {
		$menu = get_term_by( 'name', $name, 'nav_menu' );
		return ( $menu instanceof \WP_Term ) ? $menu : null;
	}

	/**
	 * Return the items of a nav menu as a nested array for the UI.
	 * Children are nested under their parent via a `children` key.
	 */
	private function get_menu_items_list( ?\WP_Term $menu, array $generated_ids ): array {
		if ( ! $menu ) {
			return array();
		}

		$flat = array();
		foreach ( wp_get_nav_menu_items( $menu->term_id ) ?: array() as $item ) {
			$flat[ (int) $item->ID ] = array(
				'menu_item_id' => (int) $item->ID,
				'parent_id'    => (int) $item->menu_item_parent,
				'post_id'      => (int) $item->object_id,
				'title'        => $item->title,
				'url'          => $item->url,
				'is_generated' => in_array( (int) $item->object_id, $generated_ids, true ),
				'children'     => array(),
			);
		}

		$tree = array();
		foreach ( $flat as $id => &$entry ) {
			$parent_id = $entry['parent_id'];
			if ( $parent_id && isset( $flat[ $parent_id ] ) ) {
				$flat[ $parent_id ]['children'][] = &$entry;
			} else {
				$tree[] = &$entry;
			}
		}
		unset( $entry );

		return $tree;
	}

	/**
	 * Create or update a named GrayFox nav menu with the given items.
	 * Supports one level of nesting via a `children` key on each item.
	 * No location assignment — block theme wiring is handled separately.
	 */
	private function save_nav_menu( string $menu_name, array $items ): array {
		$menu = $this->get_grayfox_menu( $menu_name );
		if ( $menu ) {
			$menu_id = $menu->term_id;
		} else {
			$menu_id = wp_create_nav_menu( $menu_name );
			if ( is_wp_error( $menu_id ) ) {
				return array( 'error' => $menu_id->get_error_message() );
			}
		}

		// Wipe all existing items — simpler than diffing when structure may have changed.
		foreach ( wp_get_nav_menu_items( $menu_id ) ?: array() as $existing ) {
			wp_delete_nav_menu_item( (int) $existing->ID );
		}

		$saved   = 0;
		$position = 1;

		foreach ( $items as $item ) {
			$parent_menu_item_id = $this->insert_nav_menu_item( $menu_id, $item, 0, $position );
			if ( $parent_menu_item_id ) {
				$saved++;
				$position++;
				foreach ( $item['children'] ?? array() as $child ) {
					if ( $this->insert_nav_menu_item( $menu_id, $child, $parent_menu_item_id, $position ) ) {
						$saved++;
						$position++;
					}
				}
			}
		}

		return array(
			'menu_id'    => $menu_id,
			'menu_name'  => $menu_name,
			'item_count' => $saved,
		);
	}

	/**
	 * Insert a single nav menu item and return its new menu item ID, or 0 on failure.
	 */
	private function insert_nav_menu_item( int $menu_id, array $item, int $parent_menu_item_id, int $position ): int {
		$post_id = (int) ( $item['post_id'] ?? 0 );
		$title   = sanitize_text_field( $item['title'] ?? '' );
		$url     = $post_id ? get_permalink( $post_id ) : esc_url_raw( $item['url'] ?? '' );

		if ( empty( $title ) || empty( $url ) ) {
			return 0;
		}

		$item_data = array(
			'menu-item-title'     => $title,
			'menu-item-url'       => $url,
			'menu-item-status'    => 'publish',
			'menu-item-position'  => $position,
			'menu-item-parent-id' => $parent_menu_item_id,
		);

		if ( $post_id ) {
			$item_data['menu-item-object-id'] = $post_id;
			$item_data['menu-item-object']    = 'page';
			$item_data['menu-item-type']      = 'post_type';
		}

		$result = wp_update_nav_menu_item( $menu_id, 0, $item_data );
		return is_wp_error( $result ) ? 0 : (int) $result;
	}

	/**
	 * For block themes: find the template part (header or footer) and patch any
	 * core/navigation block to reference our menu by ID.
	 *
	 * @param int    $menu_id       WP nav menu term ID.
	 * @param string $part          'header' or 'footer'.
	 */
	private function wire_block_theme_nav( int $menu_id, string $part ): void {
		// Resolve the template part post — check customizations first, then theme file.
		$part_post = get_posts( array(
			'post_type'      => 'wp_template_part',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'name'           => $part,
			'tax_query'      => array( array(
				'taxonomy' => 'wp_theme',
				'field'    => 'slug',
				'terms'    => get_stylesheet(),
			) ),
		) );

		if ( ! empty( $part_post ) ) {
			$post_id  = $part_post[0]->ID;
			$content  = $part_post[0]->post_content;
			$patched  = $this->patch_navigation_block( $content, $menu_id );
			if ( $patched !== $content ) {
				wp_update_post( array( 'ID' => $post_id, 'post_content' => $patched ) );
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( sprintf( 'GrayFox Menus: wired menu %d into %s template part (post %d)', $menu_id, $part, $post_id ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
			}
			return;
		}

		// No customized post — read from theme file and create a template part override.
		$theme_dir  = get_stylesheet_directory();
		$candidates = array(
			$theme_dir . '/parts/' . $part . '.html',
			$theme_dir . '/block-templates/parts/' . $part . '.html',
		);
		$file = '';
		foreach ( $candidates as $c ) {
			if ( file_exists( $c ) ) {
				$file = $c;
				break;
			}
		}

		if ( ! $file ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( 'GrayFox Menus: no %s template part found for block-theme wiring.', $part ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			return;
		}

		$content = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$patched = $this->patch_navigation_block( $content, $menu_id );

		if ( $patched === $content ) {
			// No core/navigation block found in the file — nothing to wire.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( 'GrayFox Menus: no core/navigation block in %s template part.', $part ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			return;
		}

		// Create a template part post so the patched version is used.
		wp_insert_post( array(
			'post_name'    => $part,
			'post_type'    => 'wp_template_part',
			'post_status'  => 'publish',
			'post_content' => $patched,
			'tax_input'    => array(
				'wp_theme' => array( get_stylesheet() ),
				'wp_template_part_area' => array( 'header' === $part ? 'header' : 'uncategorized' ),
			),
		) );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'GrayFox Menus: created %s template part override with menu %d', $part, $menu_id ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Replace the ref attribute (or inject one) into a core/navigation block comment.
	 * Handles both classic {"ref":N} navigation and {"navigationMenuId":N} variants.
	 */
	private function patch_navigation_block( string $content, int $menu_id ): string {
		// Match the opening comment of any core/navigation block.
		return preg_replace_callback(
			'/<!-- wp:navigation(\s+(\{[^}]*\}))? -->/',
			function ( $matches ) use ( $menu_id ) {
				$attrs = array();
				if ( ! empty( $matches[2] ) ) {
					$attrs = json_decode( $matches[2], true ) ?: array();
				}
				$attrs['ref'] = $menu_id;
				return '<!-- wp:navigation ' . wp_json_encode( $attrs ) . ' -->';
			},
			$content
		);
	}


	/* -----------------------------------------------------------------------
	 * Step 7: Architectural helpers
	 * --------------------------------------------------------------------- */

	/**
	 * Store a pre-fix backup of post_content in post meta.
	 */
	private function backup_post_content( int $post_id, string $section ): void {
		update_post_meta( $post_id, "_grayfox_pre_fix_{$section}", get_post_field( 'post_content', $post_id ) );
	}

	/**
	 * Restore post_content from pre-fix backup. Returns true on success.
	 */
	private function restore_post_content( int $post_id, string $section ): bool {
		$backup = get_post_meta( $post_id, "_grayfox_pre_fix_{$section}", true );
		if ( empty( $backup ) ) {
			return false;
		}
		wp_update_post( array( 'ID' => $post_id, 'post_content' => $backup ) );
		return true;
	}

	/**
	 * Extract all visible text from Elementor page data.
	 */
	private function get_elementor_text( int $post_id ): string {
		$raw  = get_post_meta( $post_id, '_elementor_data', true );
		$data = json_decode( $raw ?: '[]', true );
		return trim( $this->extract_elementor_text_recursive( is_array( $data ) ? $data : array() ) );
	}

	private function extract_elementor_text_recursive( array $elements ): string {
		$text = '';
		foreach ( $elements as $el ) {
			$s     = $el['settings'] ?? array();
			$text .= ' ' . ( $s['title'] ?? '' );
			$text .= ' ' . wp_strip_all_tags( $s['editor'] ?? '' );
			$text .= ' ' . ( $s['text'] ?? '' );
			$text .= ' ' . ( $s['description_text'] ?? '' );
			if ( ! empty( $el['elements'] ) ) {
				$text .= $this->extract_elementor_text_recursive( $el['elements'] );
			}
		}
		return $text;
	}

	/**
	 * Return a shared LLM client + credentials array.
	 */
	private function get_llm_client(): array {
		return array(
			'client'   => new GrayFox_LLM(),
			'provider' => get_option( 'grayfox_llm_provider', 'openai' ),
			'key'      => grayfox_decrypt( get_option( 'grayfox_llm_api_key', '' ) ),
			'model'    => get_option( 'grayfox_llm_model', '' ),
		);
	}

	/* -----------------------------------------------------------------------
	 * Scan methods
	 * --------------------------------------------------------------------- */

	private function scan_accessibility( array $post_ids ): array {
		$issues = array();

		foreach ( $post_ids as $post_id ) {
			$title    = get_the_title( $post_id );
			$is_elems = (bool) get_post_meta( $post_id, '_elementor_edit_mode', true );

			if ( $is_elems ) {
				// Scan Elementor data.
				$el_data = json_decode( get_post_meta( $post_id, '_elementor_data', true ) ?: '[]', true );
				$issues  = array_merge( $issues, $this->scan_elementor_accessibility( $el_data, $post_id, $title ) );
				continue;
			}

			$content = get_post_field( 'post_content', $post_id );
			$blocks  = parse_blocks( $content );
			$flat    = $this->flatten_blocks( $blocks );

			foreach ( $flat as $block ) {
				$name  = $block['blockName'] ?? '';
				$attrs = $block['attrs'] ?? array();
				$inner = implode( '', $block['innerContent'] ?? array() );

				// Missing alt text on images.
				if ( 'core/image' === $name && '' === trim( $attrs['alt'] ?? '' ) ) {
					$att_id     = (int) ( $attrs['id'] ?? 0 );
					$has_attach = $att_id && get_the_title( $att_id );
					$issues[]   = array(
						'post_id'  => $post_id,
						'title'    => $title,
						'issue'    => 'Image missing alt text',
						'severity' => 'error',
						'fixable'  => true,
						'fix_type' => $has_attach ? 'auto' : 'llm',
					);
				}

				// Empty button label.
				if ( 'core/button' === $name && '' === trim( wp_strip_all_tags( $inner ) ) ) {
					$btn_url    = trim( $attrs['url'] ?? '' );
					$linked_id  = ( ! empty( $btn_url ) && '#' !== $btn_url ) ? url_to_postid( $btn_url ) : 0;
					$auto_label = $linked_id ? get_the_title( $linked_id ) : '';
					$issues[]   = array(
						'post_id'  => $post_id,
						'title'    => $title,
						'issue'    => 'Button block has no label text',
						'severity' => 'error',
						'fixable'  => true,
						'fix_type' => $auto_label ? 'auto' : 'llm',
					);
				}

				// Generic link text.
				if ( in_array( $name, array( 'core/paragraph', 'core/button', 'core/heading' ), true ) ) {
					if ( preg_match( '/<a[^>]*>\s*(click here|read more|here|learn more)\s*<\/a>/i', $inner ) ) {
						$btn_url  = trim( $attrs['url'] ?? '' );
						$fixable  = 'core/button' === $name && ! empty( $btn_url ) && '#' !== $btn_url;
						$issues[] = array(
							'post_id'  => $post_id,
							'title'    => $title,
							'issue'    => 'Generic link text: "click here", "read more", etc.' . ( $fixable ? ' (will use linked page title)' : ' — edit manually' ),
							'severity' => 'warning',
							'fixable'  => $fixable,
							'fix_type' => $fixable ? 'auto' : 'none',
						);
					}
				}
			}

			// Heading hierarchy — walk flat list tracking sequence.
			$h1_count = 0;
			$prev     = 0;
			$gap_reported = false;
			foreach ( $flat as $block ) {
				if ( 'core/heading' !== ( $block['blockName'] ?? '' ) ) {
					continue;
				}
				$level = (int) ( $block['attrs']['level'] ?? 2 );
				if ( 1 === $level ) {
					$h1_count++;
				}
				if ( $prev > 0 && $level > $prev + 1 && ! $gap_reported ) {
					$issues[]    = array(
						'post_id'  => $post_id,
						'title'    => $title,
						'issue'    => "Heading level jumps from H{$prev} to H{$level}",
						'severity' => 'warning',
						'fixable'  => true,
						'fix_type' => 'auto',
					);
					$gap_reported = true;
				}
				$prev = $level;
			}

			if ( $h1_count > 1 ) {
				$issues[] = array(
					'post_id'  => $post_id,
					'title'    => $title,
					'issue'    => "Page has {$h1_count} H1 headings (should have exactly 1)",
					'severity' => 'warning',
					'fixable'  => false,
					'fix_type' => 'none',
				);
			}
		}

		return $issues;
	}

	/**
	 * Scan Elementor elements for accessibility issues.
	 */
	private function scan_elementor_accessibility( array $elements, int $post_id, string $title ): array {
		$issues = array();
		foreach ( $elements as $el ) {
			if ( 'widget' === ( $el['elType'] ?? '' ) ) {
				$wtype = $el['widgetType'] ?? '';
				$s     = $el['settings'] ?? array();
				if ( 'image' === $wtype && empty( $s['image']['alt'] ) ) {
					$issues[] = array(
						'post_id'  => $post_id,
						'title'    => $title,
						'issue'    => 'Elementor image widget missing alt text',
						'severity' => 'error',
						'fixable'  => true,
						'fix_type' => 'llm',
					);
				}
				if ( 'button' === $wtype && empty( trim( wp_strip_all_tags( $s['text'] ?? '' ) ) ) ) {
					$issues[] = array(
						'post_id'  => $post_id,
						'title'    => $title,
						'issue'    => 'Elementor button widget has no label text',
						'severity' => 'error',
						'fixable'  => false,
						'fix_type' => 'none',
					);
				}
			}
			if ( ! empty( $el['elements'] ) ) {
				$issues = array_merge( $issues, $this->scan_elementor_accessibility( $el['elements'], $post_id, $title ) );
			}
		}
		return $issues;
	}

	private function scan_broken_links( array $post_ids ): array {
		$issues   = array();
		$site_url = home_url();

		foreach ( $post_ids as $post_id ) {
			$content    = get_post_field( 'post_content', $post_id );
			$title      = get_the_title( $post_id );
			$top_blocks = parse_blocks( $content );
			$flat       = $this->flatten_blocks( $top_blocks );

			// Validate block nesting.
			$nesting_issues = $this->scan_block_nesting( $top_blocks, null, $post_id, $title );
			$issues         = array_merge( $issues, $nesting_issues );

			foreach ( $flat as $block ) {
				$name  = $block['blockName'] ?? '';
				$attrs = $block['attrs'] ?? array();
				$inner = implode( '', $block['innerContent'] ?? array() );

				if ( 'core/button' === $name ) {
					$btn_url = trim( $attrs['url'] ?? '' );
					preg_match( '/href="([^"]*)"/i', $inner, $href_m );
					$btn_href = trim( $href_m[1] ?? '' );

					$no_real_url  = empty( $btn_url ) || '#' === $btn_url;
					$no_real_href = empty( $btn_href ) || '#' === $btn_href;

					if ( $no_real_url && $no_real_href ) {
						preg_match( '/<a[^>]*>(.*?)<\/a>/s', $inner, $lm );
						$btn_label = wp_strip_all_tags( $lm[1] ?? '' );
						$issues[]  = array(
							'post_id'      => $post_id,
							'title'        => $title,
							'issue'        => ( $btn_label ? '"' . $btn_label . '" button' : 'Button' ) . ' has no link',
							'severity'     => 'error',
							'fixable'      => true,
							'fix_type'     => 'llm',
							'button_label' => $btn_label,
						);
					}
				}

				// Check internal links in paragraphs/headings.
				if ( in_array( $name, array( 'core/paragraph', 'core/heading' ), true ) ) {
					preg_match_all( '/href="([^"]+)"/i', $inner, $m );
					foreach ( $m[1] as $href ) {
						if ( str_starts_with( $href, $site_url ) && 0 === url_to_postid( $href ) ) {
							$issues[] = array(
								'post_id'  => $post_id,
								'title'    => $title,
								'issue'    => 'Possible broken internal link: ' . esc_url( $href ),
								'severity' => 'warning',
								'fixable'  => false,
								'fix_type' => 'none',
							);
						}
					}
				}

				// Check external links via HEAD request (cached).
				if ( in_array( $name, array( 'core/paragraph', 'core/heading', 'core/button' ), true ) ) {
					preg_match_all( '/href="(https?:\/\/[^"]+)"/i', $inner, $ext_m );
					foreach ( $ext_m[1] as $ext_url ) {
						if ( str_starts_with( $ext_url, $site_url ) ) {
							continue; // internal — already checked above
						}
						$cache_key  = 'grayfox_ext_link_' . md5( $ext_url );
						$cached     = get_transient( $cache_key );
						if ( false === $cached ) {
							$response = wp_remote_head( $ext_url, array( 'timeout' => 5, 'redirection' => 3 ) );
							$code     = is_wp_error( $response ) ? 0 : wp_remote_retrieve_response_code( $response );
							set_transient( $cache_key, (string) $code, HOUR_IN_SECONDS );
							$cached = (string) $code;
						}
						$code = (int) $cached;
						if ( $code >= 400 || 0 === $code ) {
							$issues[] = array(
								'post_id'  => $post_id,
								'title'    => $title,
								'issue'    => 'External link appears broken (HTTP ' . ( $code ?: 'unreachable' ) . '): ' . esc_url( $ext_url ),
								'severity' => 'warning',
								'fixable'  => false,
								'fix_type' => 'none',
							);
						}
					}
				}
			}
		}

		return $issues;
	}

	private function scan_content_quality( array $post_ids ): array {
		$issues = array();

		foreach ( $post_ids as $post_id ) {
			$content  = get_post_field( 'post_content', $post_id );
			$title    = get_the_title( $post_id );
			$status   = get_post_status( $post_id );
			$is_elems = (bool) get_post_meta( $post_id, '_elementor_edit_mode', true );

			// Get plain text — prefer Elementor extraction for Elementor pages.
			$plain = $is_elems
				? $this->get_elementor_text( $post_id )
				: wp_strip_all_tags( $content );

			// Placeholder text detection.
			if ( preg_match( '/\{\{[^}]+\}\}|\[Company[^\]]*\]|\[Your[^\]]*\]|Lorem ipsum/i', $is_elems ? $plain : $content ) ) {
				$issues[] = array(
					'post_id'  => $post_id,
					'title'    => $title,
					'issue'    => 'Placeholder text still present — use AI Fix to replace with real content',
					'severity' => 'error',
					'fixable'  => true,
					'fix_type' => 'llm',
				);
			}

			// Low word count.
			$words = str_word_count( $plain );
			if ( $words < 100 ) {
				$issues[] = array(
					'post_id'  => $post_id,
					'title'    => $title,
					'issue'    => "Low word count ({$words} words) — consider adding more content",
					'severity' => 'warning',
					'fixable'  => false,
					'fix_type' => 'none',
				);
			}

			// Draft status.
			if ( 'draft' === $status ) {
				$issues[] = array(
					'post_id'  => $post_id,
					'title'    => $title,
					'issue'    => 'Page is still a Draft — not publicly visible',
					'severity' => 'warning',
					'fixable'  => true,
					'fix_type' => 'auto',
				);
			}

			// No images on the page.
			$has_images = false;
			if ( $is_elems ) {
				$el_data    = json_decode( get_post_meta( $post_id, '_elementor_data', true ) ?: '[]', true );
				$has_images = $this->elementor_has_images( $el_data );
			} else {
				foreach ( $this->flatten_blocks( parse_blocks( $content ) ) as $b ) {
					$bn = $b['blockName'] ?? '';
					if ( in_array( $bn, array( 'core/image', 'core/cover', 'core/media-text', 'core/gallery' ), true ) ) {
						$has_images = true;
						break;
					}
				}
			}
			if ( ! $has_images ) {
				$issues[] = array(
					'post_id'  => $post_id,
					'title'    => $title,
					'issue'    => 'Page has no images — consider adding visuals',
					'severity' => 'warning',
					'fixable'  => false,
					'fix_type' => 'none',
				);
			}
		}

		return $issues;
	}

	/**
	 * Check whether Elementor data contains any image widgets.
	 */
	private function elementor_has_images( array $elements ): bool {
		foreach ( $elements as $el ) {
			if ( 'widget' === ( $el['elType'] ?? '' )
				&& in_array( $el['widgetType'] ?? '', array( 'image', 'image-carousel', 'image-gallery' ), true )
			) {
				return true;
			}
			if ( ! empty( $el['elements'] ) && $this->elementor_has_images( $el['elements'] ) ) {
				return true;
			}
		}
		return false;
	}

	private function scan_wp_health( array $post_ids ): array {
		$issues = array();

		// --- Front page setting ---
		$front_page_id = (int) get_option( 'page_on_front' );
		if ( 0 === $front_page_id ) {
			$issues[] = array(
				'post_id'  => 0,
				'title'    => 'Site',
				'issue'    => 'No static front page set — a "Home" page should be designated in Settings → Reading',
				'severity' => 'warning',
				'fixable'  => true,
				'fix_type' => 'auto',
			);
		}

		// --- Permalink structure ---
		$permalink = get_option( 'permalink_structure', '' );
		if ( '' === $permalink ) {
			$issues[] = array(
				'post_id'  => 0,
				'title'    => 'Site',
				'issue'    => 'Permalink structure is set to "Plain" — this hurts SEO and is not recommended',
				'severity' => 'warning',
				'fixable'  => true,
				'fix_type' => 'auto',
			);
		}

// --- Elementor Pro: check Theme Builder templates ---
		$format = get_option( GrayFox_SiteBuilder::FORMAT_OPTION, 'blocks' );
		if ( 'elementor' === $format && class_exists( 'ElementorPro\Plugin' ) ) {
			$footer_templates = get_posts( array(
				'post_type'      => 'elementor_library',
				'posts_per_page' => 1,
				'meta_query'     => array(
					array( 'key' => '_elementor_template_type', 'value' => 'footer' ),
				),
			) );
			if ( empty( $footer_templates ) ) {
				$issues[] = array(
					'post_id'  => 0,
					'title'    => 'Site',
					'issue'    => 'No Elementor footer template found — run Step 6 to create one',
					'severity' => 'warning',
					'fixable'  => false,
					'fix_type' => 'none',
				);
			}
		}

		// --- WordPress built-in Site Health checks ---
		$health_file = ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
		if ( file_exists( $health_file ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			require_once $health_file;
		}

		if ( class_exists( 'WP_Site_Health' ) ) {
			$site_health = WP_Site_Health::get_instance();
			$tests       = WP_Site_Health::get_tests();

			foreach ( $tests['direct'] as $test_key => $test ) {
				try {
					$callback = $test['test'] ?? null;
					if ( is_string( $callback ) ) {
						$method = 'get_test_' . $callback;
						if ( ! method_exists( $site_health, $method ) ) {
							continue;
						}
						$result = $site_health->$method();
					} elseif ( is_callable( $callback ) ) {
						$result = call_user_func( $callback );
					} else {
						continue;
					}

					$status = $result['status'] ?? 'good';
					if ( 'good' === $status ) {
						continue;
					}

					$auto_fixable = in_array( $test_key, array( 'rest_availability', 'php_errors_severity' ), true );
					$issues[] = array(
						'post_id'  => 0,
						'title'    => 'Site',
						'issue'    => wp_strip_all_tags( $result['label'] ?? $test_key ),
						'severity' => 'critical' === $status ? 'error' : 'warning',
						'fixable'  => $auto_fixable,
						'fix_type' => $auto_fixable ? 'auto' : 'none',
					);
				} catch ( \Throwable $e ) {
					// Skip any test that throws.
				}
			}
		}

		return $issues;
	}

	private function scan_seo( array $post_ids ): array {
		$issues    = array();
		$site_name = get_bloginfo( 'name' );

		foreach ( $post_ids as $post_id ) {
			$title = get_the_title( $post_id );

			$yoast     = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
			$rank_math = get_post_meta( $post_id, 'rank_math_description', true );
			$aioseo    = get_post_meta( $post_id, '_aioseo_description', true );
			if ( empty( $yoast ) && empty( $rank_math ) && empty( $aioseo ) ) {
				$issues[] = array(
					'post_id'  => $post_id,
					'title'    => $title,
					'issue'    => 'Missing SEO meta description',
					'severity' => 'warning',
					'fixable'  => false,
					'fix_type' => 'none',
				);
			}

			$content  = get_post_field( 'post_content', $post_id );
			$h1_count = 0;
			foreach ( $this->flatten_blocks( parse_blocks( $content ) ) as $block ) {
				if ( 'core/heading' === ( $block['blockName'] ?? '' )
					&& 1 === (int) ( $block['attrs']['level'] ?? 2 )
				) {
					$h1_count++;
				}
			}
			if ( $h1_count > 1 ) {
				$issues[] = array(
					'post_id'  => $post_id,
					'title'    => $title,
					'issue'    => "Multiple H1 headings ({$h1_count}) on page",
					'severity' => 'warning',
					'fixable'  => false,
					'fix_type' => 'none',
				);
			}

			if ( $title === $site_name ) {
				$issues[] = array(
					'post_id'  => $post_id,
					'title'    => $title,
					'issue'    => 'Page title identical to site name',
					'severity' => 'warning',
					'fixable'  => false,
					'fix_type' => 'none',
				);
			}
		}

		return $issues;
	}

	/* -----------------------------------------------------------------------
	 * Fix methods
	 * --------------------------------------------------------------------- */

	/**
	 * Auto-fix accessibility issues using parse_blocks / serialize_blocks.
	 * Fixes: missing alt text (from attachment title) + heading hierarchy gaps.
	 */
	private function fix_accessibility( array $post_ids ): void {
		foreach ( $post_ids as $post_id ) {
			// Skip Elementor pages — programmatic alt text requires the JS editor.
			if ( get_post_meta( $post_id, '_elementor_edit_mode', true ) ) {
				continue;
			}

			$this->backup_post_content( $post_id, 'accessibility' );

			$blocks  = parse_blocks( get_post_field( 'post_content', $post_id ) );
			$changed = false;
			$blocks  = $this->fix_accessibility_in_blocks( $blocks, $changed );

			if ( $changed ) {
				wp_update_post( array( 'ID' => $post_id, 'post_content' => serialize_blocks( $blocks ) ) );
			}
		}
	}

	/**
	 * Recursively walk block tree applying accessibility fixes.
	 */
	private function fix_accessibility_in_blocks( array $blocks, bool &$changed ): array {
		foreach ( $blocks as &$block ) {
			$name  = $block['blockName'] ?? '';
			$attrs = $block['attrs'] ?? array();

			// Fix missing alt text on images.
			if ( 'core/image' === $name && '' === trim( $attrs['alt'] ?? '' ) ) {
				$att_id = (int) ( $attrs['id'] ?? 0 );
				if ( $att_id ) {
					$alt = get_the_title( $att_id );
					if ( $alt ) {
						$block['attrs']['alt'] = $alt;
						// Also patch the img tag in innerContent.
						$block['innerContent'] = array_map( function ( $chunk ) use ( $alt ) {
							if ( ! is_string( $chunk ) ) {
								return $chunk;
							}
							// If alt="" exists, replace it; otherwise inject before >.
							if ( preg_match( '/\balt=""/i', $chunk ) ) {
								return preg_replace( '/\balt=""/i', 'alt="' . esc_attr( $alt ) . '"', $chunk );
							}
							if ( ! preg_match( '/\balt=/i', $chunk ) ) {
								return preg_replace( '/<img\b/', '<img alt="' . esc_attr( $alt ) . '"', $chunk, 1 );
							}
							return $chunk;
						}, $block['innerContent'] ?? array() );
						$changed = true;
					}
				}
			}

			// Fix generic button text — replace with linked page title.
			if ( 'core/button' === $name ) {
				$url = trim( $attrs['url'] ?? '' );
				if ( ! empty( $url ) && '#' !== $url ) {
					$inner_html = implode( '', $block['innerContent'] ?? array() );

					// Case 1: completely empty label — inject the linked page title.
					if ( '' === trim( wp_strip_all_tags( $inner_html ) ) ) {
						$linked_id = url_to_postid( $url );
						$new_text  = $linked_id ? get_the_title( $linked_id ) : '';
						if ( $new_text ) {
							$block['innerContent'] = array_map( function ( $chunk ) use ( $new_text ) {
								if ( ! is_string( $chunk ) ) {
									return $chunk;
								}
								return preg_replace(
									'/(<a[^>]*>)\s*(<\/a>)/i',
									'$1' . esc_html( $new_text ) . '$2',
									$chunk,
									1
								);
							}, $block['innerContent'] ?? array() );
							$changed = true;
						}
					} elseif ( preg_match( '/<a[^>]*>\s*(click here|read more|here|learn more)\s*<\/a>/i', $inner_html ) ) {
						// Case 2: generic link text — replace with linked page title.
						$linked_id = url_to_postid( $url );
						$new_text  = $linked_id ? get_the_title( $linked_id ) : '';
						if ( $new_text ) {
							$block['innerContent'] = array_map( function ( $chunk ) use ( $new_text ) {
								if ( ! is_string( $chunk ) ) {
									return $chunk;
								}
								return preg_replace(
									'/(<a[^>]*>)\s*(?:click here|read more|here|learn more)\s*(<\/a>)/i',
									'$1' . esc_html( $new_text ) . '$2',
									$chunk,
									1
								);
							}, $block['innerContent'] ?? array() );
							$changed = true;
						}
					}
				}
			}

			// Recurse into inner blocks.
			if ( ! empty( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = $this->fix_accessibility_in_blocks( $block['innerBlocks'], $changed );
			}
		}
		unset( $block );

		// Fix heading hierarchy across the current block list.
		$blocks = $this->fix_heading_hierarchy( $blocks, $changed );

		return $blocks;
	}

	/**
	 * Walk a block list and close heading level gaps (e.g. H1→H3 becomes H1→H2).
	 */
	private function fix_heading_hierarchy( array $blocks, bool &$changed ): array {
		$prev = 0;
		foreach ( $blocks as &$block ) {
			if ( 'core/heading' !== ( $block['blockName'] ?? '' ) ) {
				continue;
			}
			$level = (int) ( $block['attrs']['level'] ?? 2 );
			if ( $prev > 0 && $level > $prev + 1 ) {
				$new_level             = $prev + 1;
				$block['attrs']['level'] = $new_level;
				// Patch the HTML tag in innerContent.
				$block['innerContent'] = array_map( function ( $chunk ) use ( $level, $new_level ) {
					if ( ! is_string( $chunk ) ) {
						return $chunk;
					}
					$chunk = preg_replace( '/<h' . $level . '(\b[^>]*)>/', '<h' . $new_level . '$1>', $chunk );
					$chunk = preg_replace( '/<\/h' . $level . '>/', '</h' . $new_level . '>', $chunk );
					return $chunk;
				}, $block['innerContent'] ?? array() );
				$changed = true;
				$level   = $new_level;
			}
			$prev = $level;
		}
		unset( $block );
		return $blocks;
	}

	/**
	 * Auto-fix broken link structure (orphaned buttons).
	 * URL assignment for unlinked buttons is handled separately via handle_apply_llm_fixes.
	 */
	private function fix_broken_links( array $post_ids ): void {
		foreach ( $post_ids as $post_id ) {
			$this->backup_post_content( $post_id, 'broken_links' );
			$this->repair_orphaned_buttons( $post_id );
		}
	}

	/**
	 * Apply LLM-suggested URLs to buttons using parse_blocks / serialize_blocks.
	 *
	 * @param array $post_ids All generated post IDs.
	 * @param array $fixes    Accepted suggestions: [{post_id, button_label, suggested_url}].
	 */
	private function apply_llm_link_fixes( array $post_ids, array $fixes ): void {
		// Build lookup: post_id → [ lowercased_label → url ].
		$assignments = array();
		foreach ( $fixes as $fix ) {
			$pid   = (int) ( $fix['post_id'] ?? 0 );
			$label = strtolower( trim( $fix['button_label'] ?? '' ) );
			$url   = esc_url_raw( $fix['suggested_url'] ?? '' );
			if ( $pid && $label && $url ) {
				$assignments[ $pid ][ $label ] = $url;
			}
		}

		foreach ( $post_ids as $post_id ) {
			if ( empty( $assignments[ $post_id ] ) ) {
				continue;
			}
			$blocks  = parse_blocks( get_post_field( 'post_content', $post_id ) );
			$changed = false;
			$blocks  = $this->apply_link_assignments_in_blocks( $blocks, $assignments[ $post_id ], $changed );
			if ( $changed ) {
				wp_update_post( array( 'ID' => $post_id, 'post_content' => serialize_blocks( $blocks ) ) );
			}
		}
	}

	/**
	 * Recursively apply link URL assignments to button blocks.
	 */
	private function apply_link_assignments_in_blocks( array $blocks, array $label_to_url, bool &$changed ): array {
		foreach ( $blocks as &$block ) {
			if ( 'core/button' === ( $block['blockName'] ?? '' ) ) {
				$attrs    = $block['attrs'] ?? array();
				$cur_url  = trim( $attrs['url'] ?? '' );
				$inner    = implode( '', $block['innerContent'] ?? array() );
				preg_match( '/href="([^"]*)"/i', $inner, $hm );
				$cur_href = trim( $hm[1] ?? '' );

				// Only patch buttons that are missing a real URL.
				if ( ( ! empty( $cur_url ) && '#' !== $cur_url )
					|| ( ! empty( $cur_href ) && '#' !== $cur_href )
				) {
					continue;
				}

				preg_match( '/<a[^>]*>(.*?)<\/a>/s', $inner, $lm );
				$label = strtolower( trim( wp_strip_all_tags( $lm[1] ?? '' ) ) );
				$url   = $label_to_url[ $label ] ?? '';

				if ( ! $url ) {
					continue;
				}

				$block['attrs']['url'] = $url;
				$escaped               = esc_url( $url );
				$block['innerContent'] = array_map( function ( $chunk ) use ( $escaped ) {
					if ( ! is_string( $chunk ) ) {
						return $chunk;
					}
					if ( preg_match( '/href="[^"]*"/i', $chunk ) ) {
						return preg_replace( '/href="[^"]*"/i', 'href="' . $escaped . '"', $chunk, 1 );
					}
					return preg_replace( '/<a\b/', '<a href="' . $escaped . '"', $chunk, 1 );
				}, $block['innerContent'] ?? array() );
				$changed = true;
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = $this->apply_link_assignments_in_blocks( $block['innerBlocks'], $label_to_url, $changed );
			}
		}
		unset( $block );
		return $blocks;
	}

	private function fix_content_quality( array $post_ids ): void {
		foreach ( $post_ids as $post_id ) {
			if ( 'draft' === get_post_status( $post_id ) ) {
				$this->backup_post_content( $post_id, 'content_quality' );
				wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
			}
		}
	}

	private function fix_wp_health( array $post_ids ): void {
		// Auto-set static front page if not set and a "Home" page exists.
		if ( 0 === (int) get_option( 'page_on_front' ) ) {
			$home = get_posts( array(
				'post_type'      => 'page',
				'posts_per_page' => 1,
				'post_status'    => array( 'publish', 'draft' ),
				'title'          => 'Home',
			) );
			if ( empty( $home ) ) {
				$home = get_posts( array(
					'post_type'      => 'page',
					'posts_per_page' => 1,
					'post_status'    => array( 'publish', 'draft' ),
					'title'          => 'Homepage',
				) );
			}
			if ( ! empty( $home ) ) {
				update_option( 'show_on_front', 'page' );
				update_option( 'page_on_front', $home[0]->ID );
			}
		}

		// --- Fix plain permalink structure → set to /%postname%/ and write .htaccess ---
		if ( '' === get_option( 'permalink_structure', '' ) ) {
			update_option( 'permalink_structure', '/%postname%/' );
		}

		// Always ensure .htaccess has WordPress rewrite rules (flush_rewrite_rules()
		// is unreliable in AJAX context — write the standard rules directly instead).
		$htaccess = ABSPATH . '.htaccess';
		$rules     = array(
			'<IfModule mod_rewrite.c>',
			'RewriteEngine On',
			'RewriteBase /',
			'RewriteRule ^index\.php$ - [L]',
			'RewriteCond %{REQUEST_FILENAME} !-f',
			'RewriteCond %{REQUEST_FILENAME} !-d',
			'RewriteRule . /index.php [L]',
			'</IfModule>',
		);
		insert_with_markers( $htaccess, 'WordPress', $rules );

		// --- Fix debug.log publicly accessible → block via wp-content/.htaccess ---
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			$htaccess_file = WP_CONTENT_DIR . '/.htaccess';
			$marker        = 'GrayFox debug.log protection';
			$rules         = array(
				'<Files "debug.log">',
				'  <IfModule mod_authz_core.c>',
				'    Require all denied',
				'  </IfModule>',
				'  <IfModule !mod_authz_core.c>',
				'    Order allow,deny',
				'    Deny from all',
				'  </IfModule>',
				'</Files>',
			);
			insert_with_markers( $htaccess_file, $marker, $rules );
		}

	}

	/* -----------------------------------------------------------------------
	 * LLM-fix apply helpers
	 * --------------------------------------------------------------------- */

	/**
	 * Apply accepted LLM accessibility suggestions: alt text and/or button labels.
	 *
	 * @param array $fixes [{post_id, block_idx, alt}] and/or [{post_id, button_block_idx, replacement}]
	 */
	private function apply_llm_accessibility_fixes( array $fixes ): void {
		// Group by post_id.
		$by_post = array();
		foreach ( $fixes as $fix ) {
			$pid = (int) ( $fix['post_id'] ?? 0 );
			if ( $pid ) {
				$by_post[ $pid ][] = $fix;
			}
		}

		foreach ( $by_post as $post_id => $post_fixes ) {
			$blocks  = parse_blocks( get_post_field( 'post_content', $post_id ) );
			$changed = false;

			// Build lookups by fix type.
			$idx_to_alt   = array();
			$idx_to_label = array();
			foreach ( $post_fixes as $fix ) {
				if ( isset( $fix['block_idx'], $fix['alt'] ) ) {
					$idx_to_alt[ (int) $fix['block_idx'] ] = sanitize_text_field( $fix['alt'] );
				}
				if ( isset( $fix['button_block_idx'], $fix['replacement'] ) ) {
					$idx_to_label[ (int) $fix['button_block_idx'] ] = sanitize_text_field( $fix['replacement'] );
				}
			}

			if ( ! empty( $idx_to_alt ) ) {
				$counter = 0;
				$blocks  = $this->patch_image_alt_in_blocks( $blocks, $idx_to_alt, $counter, $changed );
			}

			if ( ! empty( $idx_to_label ) ) {
				$counter = 0;
				$blocks  = $this->patch_button_label_in_blocks( $blocks, $idx_to_label, $counter, $changed );
			}

			if ( $changed ) {
				wp_update_post( array( 'ID' => $post_id, 'post_content' => serialize_blocks( $blocks ) ) );
			}
		}
	}

	/**
	 * Recursively walk block tree and patch label text for empty button blocks at specific flat indices.
	 */
	private function patch_button_label_in_blocks( array $blocks, array $idx_to_label, int &$counter, bool &$changed ): array {
		foreach ( $blocks as &$block ) {
			if ( 'core/button' === ( $block['blockName'] ?? '' ) ) {
				if ( isset( $idx_to_label[ $counter ] ) ) {
					$label                 = $idx_to_label[ $counter ];
					$block['innerContent'] = array_map( function ( $chunk ) use ( $label ) {
						if ( ! is_string( $chunk ) ) {
							return $chunk;
						}
						// Inject label between empty <a> tags.
						return preg_replace(
							'/(<a[^>]*>)\s*(<\/a>)/i',
							'$1' . esc_html( $label ) . '$2',
							$chunk,
							1
						);
					}, $block['innerContent'] ?? array() );
					$changed = true;
				}
				$counter++;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = $this->patch_button_label_in_blocks( $block['innerBlocks'], $idx_to_label, $counter, $changed );
			}
		}
		unset( $block );
		return $blocks;
	}

	/**
	 * Recursively walk block tree and patch alt text for image blocks at specific flat indices.
	 */
	private function patch_image_alt_in_blocks( array $blocks, array $idx_to_alt, int &$counter, bool &$changed ): array {
		foreach ( $blocks as &$block ) {
			if ( 'core/image' === ( $block['blockName'] ?? '' ) ) {
				if ( isset( $idx_to_alt[ $counter ] ) ) {
					$alt                  = $idx_to_alt[ $counter ];
					$block['attrs']['alt'] = $alt;
					$block['innerContent'] = array_map( function ( $chunk ) use ( $alt ) {
						if ( ! is_string( $chunk ) ) return $chunk;
						if ( preg_match( '/\balt=""/i', $chunk ) ) {
							return preg_replace( '/\balt=""/i', 'alt="' . esc_attr( $alt ) . '"', $chunk );
						}
						if ( ! preg_match( '/\balt=/i', $chunk ) ) {
							return preg_replace( '/<img\b/', '<img alt="' . esc_attr( $alt ) . '"', $chunk, 1 );
						}
						return $chunk;
					}, $block['innerContent'] ?? array() );
					$changed = true;
				}
				$counter++;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = $this->patch_image_alt_in_blocks( $block['innerBlocks'], $idx_to_alt, $counter, $changed );
			}
		}
		unset( $block );
		return $blocks;
	}

	/**
	 * Apply accepted LLM placeholder replacement suggestions.
	 *
	 * @param array $fixes [{post_id, original, replacement}]
	 */
	private function apply_llm_placeholder_fixes( array $fixes ): void {
		$by_post = array();
		foreach ( $fixes as $fix ) {
			$pid = (int) ( $fix['post_id'] ?? 0 );
			if ( $pid && ! empty( $fix['original'] ) && ! empty( $fix['replacement'] ) ) {
				$by_post[ $pid ][] = $fix;
			}
		}

		foreach ( $by_post as $post_id => $post_fixes ) {
			$this->backup_post_content( $post_id, 'content_quality' );
			$content = get_post_field( 'post_content', $post_id );
			$changed = false;

			foreach ( $post_fixes as $fix ) {
				$original    = $fix['original'];
				$replacement = sanitize_text_field( $fix['replacement'] );
				if ( str_contains( $content, $original ) ) {
					$content = str_replace( $original, $replacement, $content );
					$changed = true;
				}
			}

			if ( $changed ) {
				wp_update_post( array( 'ID' => $post_id, 'post_content' => $content ) );
			}
		}
	}

	/* -----------------------------------------------------------------------
	 * Content repair helpers (block structure)
	 * --------------------------------------------------------------------- */

	/**
	 * Recursively collect all leaf core/button blocks from a mixed block array.
	 */
	private function collect_leaf_buttons( array $blocks ): array {
		$result = array();
		foreach ( $blocks as $block ) {
			$name = $block['blockName'] ?? '';

			if ( 'core/button' === $name ) {
				if ( empty( $block['innerBlocks'] ) ) {
					$block['innerContent'] = array_map(
						function ( $chunk ) {
							if ( ! is_string( $chunk ) ) {
								return $chunk;
							}
							return preg_replace_callback(
								'/<a\b[^>]*>/i',
								function ( $m ) {
									$tag   = $m[0];
									$count = preg_match_all( '/\bhref="[^"]*"/i', $tag );
									if ( $count > 1 ) {
										$first = true;
										$tag   = preg_replace_callback(
											'/\bhref="[^"]*"/i',
											function ( $h ) use ( &$first ) {
												if ( $first ) {
													$first = false;
													return $h[0];
												}
												return '';
											},
											$tag
										);
										$tag = preg_replace( '/\s{2,}/', ' ', $tag );
									}
									return $tag;
								},
								$chunk
							);
						},
						$block['innerContent'] ?? array()
					);
					$result[] = $block;
				} else {
					$result = array_merge( $result, $this->collect_leaf_buttons( $block['innerBlocks'] ) );
				}
			} elseif ( 'core/buttons' === $name ) {
				$result = array_merge( $result, $this->collect_leaf_buttons( $block['innerBlocks'] ?? array() ) );
			}
		}
		return $result;
	}

	private function buttons_children_are_valid( array $inner_blocks ): bool {
		foreach ( $inner_blocks as $block ) {
			if ( 'core/button' !== ( $block['blockName'] ?? '' ) ) {
				return false;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				return false;
			}
		}
		return true;
	}

	private function make_buttons_block( array $leaf_buttons ): array {
		$inner_content = array( '<div class="wp-block-buttons">' );
		foreach ( $leaf_buttons as $_ ) {
			$inner_content[] = null;
		}
		$inner_content[] = '</div>';
		return array(
			'blockName'    => 'core/buttons',
			'attrs'        => array(),
			'innerBlocks'  => $leaf_buttons,
			'innerHTML'    => '<div class="wp-block-buttons"></div>',
			'innerContent' => $inner_content,
		);
	}

	private function repair_orphaned_buttons_in_blocks( array $blocks ): array {
		$result = array();
		$i      = 0;
		$count  = count( $blocks );

		while ( $i < $count ) {
			$name = $blocks[ $i ]['blockName'] ?? '';

			if ( 'core/button' === $name ) {
				$group = array();
				while ( $i < $count && 'core/button' === ( $blocks[ $i ]['blockName'] ?? '' ) ) {
					$group[] = $blocks[ $i ];
					$i++;
				}
				$leaf_buttons = $this->collect_leaf_buttons( $group );
				$result[]     = $this->make_buttons_block( $leaf_buttons );

			} elseif ( 'core/buttons' === $name ) {
				$block = $blocks[ $i ];
				$inner = $block['innerBlocks'] ?? array();

				if ( ! $this->buttons_children_are_valid( $inner ) ) {
					$leaf_buttons = $this->collect_leaf_buttons( $inner );
					$block        = $this->make_buttons_block( $leaf_buttons );
				}
				$result[] = $block;
				$i++;

			} else {
				$block = $blocks[ $i ];
				if ( ! empty( $block['innerBlocks'] ) ) {
					$block['innerBlocks'] = $this->repair_orphaned_buttons_in_blocks( $block['innerBlocks'] );
				}
				$result[] = $block;
				$i++;
			}
		}

		return $result;
	}

	private function repair_orphaned_buttons( int $post_id ): bool {
		$content = get_post_field( 'post_content', $post_id );

		$content = preg_replace( '/\s*<\/div>\s*<!-- \/wp:buttons -->/m', '', $content );

		$blocks  = parse_blocks( $content );
		$fixed   = $this->repair_orphaned_buttons_in_blocks( $blocks );
		$new_con = serialize_blocks( $fixed );

		if ( $new_con === serialize_blocks( $blocks ) ) {
			return false;
		}

		wp_update_post( array( 'ID' => $post_id, 'post_content' => $new_con ) );
		return true;
	}

	/* -----------------------------------------------------------------------
	 * Private helpers
	 * --------------------------------------------------------------------- */

	private function get_generated_post_ids(): array {
		$build = get_option( GrayFox_SiteBuilder::BUILD_OPTION, array() );
		$ids   = array();
		foreach ( $build['pages'] ?? array() as $p ) {
			if ( ! empty( $p['post_id'] ) && 'complete' === ( $p['status'] ?? '' ) ) {
				$ids[] = (int) $p['post_id'];
			}
		}
		return $ids;
	}

	/**
	 * Return a flat list of all pages for dropdowns and LLM context.
	 */
	private function get_all_pages_list(): array {
		$all_pages = get_posts( array(
			'post_type'      => 'page',
			'posts_per_page' => -1,
			'post_status'    => array( 'publish', 'draft' ),
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );
		return array_map( fn( $p ) => array(
			'post_id' => $p->ID,
			'title'   => $p->post_title,
			'url'     => get_permalink( $p->ID ),
		), $all_pages );
	}

	private const BLOCK_NESTING_RULES = array(
		'core/buttons'    => array( 'allowed_children' => array( 'core/button' ),    'required_parent' => null ),
		'core/button'     => array( 'allowed_children' => array(),                   'required_parent' => array( 'core/buttons' ) ),
		'core/columns'    => array( 'allowed_children' => array( 'core/column' ),    'required_parent' => null ),
		'core/column'     => array( 'allowed_children' => null,                      'required_parent' => array( 'core/columns' ) ),
		'core/list'       => array( 'allowed_children' => array( 'core/list-item' ), 'required_parent' => null ),
		'core/list-item'  => array( 'allowed_children' => array( 'core/list' ),      'required_parent' => array( 'core/list' ) ),
	);

	private function scan_block_nesting( array $blocks, ?string $parent, int $post_id, string $title, array &$seen = array() ): array {
		$issues = array();

		foreach ( $blocks as $block ) {
			$name  = $block['blockName'] ?? '';
			$rules = self::BLOCK_NESTING_RULES[ $name ] ?? null;

			if ( $rules && ! empty( $rules['required_parent'] ) ) {
				if ( ! in_array( $parent, $rules['required_parent'], true ) ) {
					$key = $name . ':parent';
					if ( ! isset( $seen[ $key ] ) ) {
						$seen[ $key ] = true;
						$issues[]     = array(
							'post_id'  => $post_id,
							'title'    => $title,
							'issue'    => sprintf(
								'%s block found outside its required parent (%s) — layout will break',
								$name,
								implode( ' or ', $rules['required_parent'] )
							),
							'severity' => 'error',
							'fixable'  => true,
							'fix_type' => 'auto',
						);
					}
				}
			}

			if ( $rules && is_array( $rules['allowed_children'] ) && ! empty( $block['innerBlocks'] ) ) {
				foreach ( $block['innerBlocks'] as $child ) {
					$child_name = $child['blockName'] ?? '';
					if ( ! in_array( $child_name, $rules['allowed_children'], true ) ) {
						$key = $name . ':child:' . $child_name;
						if ( ! isset( $seen[ $key ] ) ) {
							$seen[ $key ] = true;
							$issues[]     = array(
								'post_id'  => $post_id,
								'title'    => $title,
								'issue'    => sprintf(
									'%s block contains %s, which is not allowed as a child — block structure is invalid',
									$name,
									$child_name
								),
								'severity' => 'error',
								'fixable'  => true,
								'fix_type' => 'auto',
							);
						}
					}
				}
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$child_issues = $this->scan_block_nesting( $block['innerBlocks'], $name, $post_id, $title, $seen );
				$issues       = array_merge( $issues, $child_issues );
			}
		}

		return $issues;
	}

	private function flatten_blocks( array $blocks ): array {
		$flat = array();
		foreach ( $blocks as $block ) {
			$flat[] = $block;
			if ( ! empty( $block['innerBlocks'] ) ) {
				$flat = array_merge( $flat, $this->flatten_blocks( $block['innerBlocks'] ) );
			}
		}
		return $flat;
	}

	/**
	 * Return the footer template part post for the active block theme, or null.
	 */
	private function get_fse_footer_template(): ?\WP_Post {
		$posts = get_posts( array(
			'post_type'      => 'wp_template_part',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'name'           => 'footer',
			'tax_query'      => array( array(
				'taxonomy' => 'wp_theme',
				'field'    => 'slug',
				'terms'    => get_stylesheet(),
			) ),
		) );

		if ( ! empty( $posts ) ) {
			return $posts[0];
		}

		// Fall back to reading the theme file directly.
		$file = get_stylesheet_directory() . '/parts/footer.html';
		if ( file_exists( $file ) ) {
			$post              = new \WP_Post( (object) array() );
			$post->post_content = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			return $post;
		}

		return null;
	}

	private function get_primary_menu_id(): int {
		$locations = get_nav_menu_locations();
		foreach ( array( 'primary', 'main', 'header', 'main-menu', 'primary-menu' ) as $loc ) {
			if ( ! empty( $locations[ $loc ] ) ) {
				return (int) $locations[ $loc ];
			}
		}
		return 0;
	}

	private function build_page_url_map(): array {
		$build = get_option( GrayFox_SiteBuilder::BUILD_OPTION, array() );
		$map   = array();
		foreach ( $build['pages'] ?? array() as $p ) {
			if ( empty( $p['post_id'] ) || 'complete' !== ( $p['status'] ?? '' ) ) {
				continue;
			}
			$pid   = (int) $p['post_id'];
			$slug  = get_post_field( 'post_name', $pid );
			$title = strtolower( get_the_title( $pid ) );
			$url   = get_permalink( $pid );
			if ( $slug ) $map[ $slug ] = $url;
			if ( $title ) $map[ $title ] = $url;
		}
		return $map;
	}

	private function match_button_to_url( string $label, array $page_url_map ): string {
		if ( empty( $label ) ) return '';
		$lower = strtolower( trim( $label ) );

		if ( isset( $page_url_map[ $lower ] ) ) {
			return $page_url_map[ $lower ];
		}

		$keyword_map = array(
			'contact'  => array( 'contact', 'reach us', 'get in touch', 'talk to us', 'demo', 'request a demo', 'book a demo', 'get a demo', 'schedule', 'request demo', 'book demo' ),
			'pricing'  => array( 'pricing', 'plans', 'price', 'get started', 'buy', 'subscribe', 'sign up', 'get a quote', 'quote' ),
			'features' => array( 'features', 'what we do', 'capabilities', 'learn more', 'explore', 'see features', 'view features' ),
			'about'    => array( 'about', 'about us', 'our story', 'who we are', 'our team', 'meet the team' ),
			'home'     => array( 'home', 'homepage', 'back to home', 'go home' ),
			'services' => array( 'services', 'our services', 'view services', 'see services', 'what we offer' ),
		);

		foreach ( $keyword_map as $group_term => $button_keywords ) {
			foreach ( $button_keywords as $kw ) {
				if ( str_contains( $lower, $kw ) ) {
					foreach ( $page_url_map as $page_key => $candidate_url ) {
						if ( str_contains( $page_key, $group_term ) ) {
							return $candidate_url;
						}
					}
				}
			}
		}

		$label_words = preg_split( '/[\s\-_]+/', $lower, -1, PREG_SPLIT_NO_EMPTY );
		foreach ( $page_url_map as $key => $url ) {
			$key_words = preg_split( '/[\s\-_]+/', $key, -1, PREG_SPLIT_NO_EMPTY );
			foreach ( $label_words as $lw ) {
				if ( strlen( $lw ) > 3 && in_array( $lw, $key_words, true ) ) {
					return $url;
				}
			}
		}

		return '';
	}
}
