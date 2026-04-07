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
		$loader->add_action( 'wp_ajax_grayfox_run_site_audit',     $this, 'handle_run_audit' );
		$loader->add_action( 'wp_ajax_grayfox_fix_audit_section',  $this, 'handle_fix_section' );
		$loader->add_action( 'wp_ajax_grayfox_get_audit_results',  $this, 'handle_get_results' );
		$loader->add_action( 'wp_ajax_grayfox_get_footer_config',  $this, 'handle_get_footer' );
		$loader->add_action( 'wp_ajax_grayfox_save_footer_config', $this, 'handle_save_footer' );
	}

	/* -----------------------------------------------------------------------
	 * AJAX Handlers
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

		$all_pages = get_posts( array(
			'post_type'      => 'page',
			'posts_per_page' => -1,
			'post_status'    => array( 'publish', 'draft' ),
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );
		$pages_list = array_map( fn( $p ) => array(
			'post_id' => $p->ID,
			'title'   => $p->post_title,
			'url'     => get_permalink( $p->ID ),
		), $all_pages );

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
				$assignments_raw = isset( $_POST['assignments'] ) ? wp_unslash( $_POST['assignments'] ) : '[]';
				$assignments     = json_decode( $assignments_raw, true ) ?: array();
				$this->fix_broken_links( $post_ids, $assignments );
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

		// Re-scan this section to return fresh results.
		$updated = $this->run_section( $section, $post_ids );

		// For broken_links: flag whether any button issues remain so the UI
		// can show the URL selector column and Apply button.
		if ( 'broken_links' === $section ) {
			$has_unmatched = false;
			foreach ( $updated['issues'] ?? array() as $issue ) {
				if ( 'url_selector' === ( $issue['input_type'] ?? '' ) ) {
					$has_unmatched = true;
					break;
				}
			}
			$updated['show_url_selectors'] = $has_unmatched;
		}

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

	public function handle_get_footer(): void {
		check_ajax_referer( 'grayfox_get_footer_config' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		// Show ALL registered nav menu locations — the theme may not name them "footer".
		$footer_locations = get_registered_nav_menus();

		if ( empty( $footer_locations ) ) {
			wp_send_json_success( array(
				'footer_locations' => array(),
				'items'            => array(),
				'all_pages'        => array(),
			) );
			return;
		}

		$nav_locations    = get_nav_menu_locations();
		$generated_ids    = $this->get_generated_post_ids();

		$items = array();
		foreach ( $footer_locations as $location_key => $location_label ) {
			$menu_id    = $nav_locations[ $location_key ] ?? 0;
			$menu_items = $menu_id ? ( wp_get_nav_menu_items( $menu_id ) ?: array() ) : array();
			$items[ $location_key ] = array();

			foreach ( $menu_items as $item ) {
				$items[ $location_key ][] = array(
					'menu_item_id' => (int) $item->ID,
					'post_id'      => (int) $item->object_id,
					'title'        => $item->title,
					'url'          => $item->url,
					'is_generated' => in_array( (int) $item->object_id, $generated_ids, true ),
				);
			}
		}

		$pages     = get_posts( array(
			'post_type'      => 'page',
			'posts_per_page' => -1,
			'post_status'    => array( 'publish', 'draft' ),
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );
		$all_pages = array();
		foreach ( $pages as $p ) {
			$all_pages[] = array(
				'post_id' => $p->ID,
				'title'   => $p->post_title,
				'url'     => get_permalink( $p->ID ),
			);
		}

		wp_send_json_success( array(
			'footer_locations' => $footer_locations,
			'items'            => $items,
			'all_pages'        => $all_pages,
		) );
	}

	public function handle_save_footer(): void {
		check_ajax_referer( 'grayfox_save_footer_config' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		$config_json = wp_unslash( $_POST['config'] ?? '' );
		$config      = json_decode( $config_json, true );

		if ( ! is_array( $config ) ) {
			wp_send_json_error( 'Invalid config.' );
			return;
		}

		$nav_locations = get_nav_menu_locations();

		foreach ( $config as $location_key => $new_items ) {
			$location_key = sanitize_key( $location_key );
			$menu_id      = $nav_locations[ $location_key ] ?? 0;

			if ( ! $menu_id ) {
				$menu_name = ucwords( str_replace( '-', ' ', $location_key ) );
				$menu_id   = wp_create_nav_menu( $menu_name );
				if ( is_wp_error( $menu_id ) ) {
					continue;
				}
				$locations                  = get_nav_menu_locations();
				$locations[ $location_key ] = $menu_id;
				set_theme_mod( 'nav_menu_locations', $locations );
			}

			$existing     = wp_get_nav_menu_items( $menu_id ) ?: array();
			$existing_ids = wp_list_pluck( $existing, 'ID' );

			// Which existing item IDs are staying?
			$kept_ids = array();
			foreach ( $new_items as $item ) {
				if ( ! empty( $item['menu_item_id'] ) ) {
					$kept_ids[] = (int) $item['menu_item_id'];
				}
			}

			// Delete removed items.
			foreach ( $existing_ids as $eid ) {
				if ( ! in_array( (int) $eid, $kept_ids, true ) ) {
					wp_delete_nav_menu_item( (int) $eid );
				}
			}

			// Update / create items with correct order.
			foreach ( $new_items as $order => $item ) {
				$menu_item_id = (int) ( $item['menu_item_id'] ?? 0 );
				$post_id      = (int) ( $item['post_id'] ?? 0 );
				$title        = sanitize_text_field( $item['title'] ?? '' );
				$url          = $post_id ? get_permalink( $post_id ) : esc_url_raw( $item['url'] ?? '' );

				$item_data = array(
					'menu-item-title'    => $title,
					'menu-item-url'      => $url,
					'menu-item-status'   => 'publish',
					'menu-item-position' => $order + 1,
				);

				if ( $post_id ) {
					$item_data['menu-item-object-id'] = $post_id;
					$item_data['menu-item-object']    = 'page';
					$item_data['menu-item-type']      = 'post_type';
				}

				wp_update_nav_menu_item( $menu_id, $menu_item_id ?: 0, $item_data );
			}
		}

		wp_send_json_success( array( 'saved' => true ) );
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
	 * Scan methods
	 * --------------------------------------------------------------------- */

	private function scan_accessibility( array $post_ids ): array {
		$issues = array();

		foreach ( $post_ids as $post_id ) {
			$content = get_post_field( 'post_content', $post_id );
			$title   = get_the_title( $post_id );
			$blocks  = parse_blocks( $content );
			$flat    = $this->flatten_blocks( $blocks );

			foreach ( $flat as $block ) {
				$name  = $block['blockName'] ?? '';
				$attrs = $block['attrs'] ?? array();
				$inner = implode( '', $block['innerContent'] ?? array() );

				if ( 'core/image' === $name && '' === trim( $attrs['alt'] ?? '' ) ) {
					$issues[] = array(
						'post_id'  => $post_id,
						'title'    => $title,
						'issue'    => 'Image missing alt text',
						'severity' => 'error',
						'fixable'  => true,
					);
				}

				if ( 'core/button' === $name && '' === trim( wp_strip_all_tags( $inner ) ) ) {
					$issues[] = array(
						'post_id'  => $post_id,
						'title'    => $title,
						'issue'    => 'Button block has no label text',
						'severity' => 'error',
						'fixable'  => false,
					);
				}

				if ( in_array( $name, array( 'core/paragraph', 'core/button', 'core/heading' ), true ) ) {
					if ( preg_match( '/<a[^>]*>\s*(click here|read more|here|learn more)\s*<\/a>/i', $inner ) ) {
						// Buttons with a real URL can be auto-fixed (replace text with linked page title).
						$btn_url   = trim( $attrs['url'] ?? '' );
						$is_fixable = 'core/button' === $name
							&& ! empty( $btn_url )
							&& '#' !== $btn_url;

						$issues[] = array(
							'post_id'  => $post_id,
							'title'    => $title,
							'issue'    => 'Generic link text: "click here", "read more", etc.' . ( $is_fixable ? ' (will use linked page title)' : ' — edit manually' ),
							'severity' => 'warning',
							'fixable'  => $is_fixable,
						);
					}
				}
			}

			// Heading hierarchy per page.
			$prev = 0;
			foreach ( $flat as $block ) {
				if ( 'core/heading' !== ( $block['blockName'] ?? '' ) ) continue;
				$level = (int) ( $block['attrs']['level'] ?? 2 );
				if ( $prev > 0 && $level > $prev + 1 ) {
					$issues[] = array(
						'post_id'  => $post_id,
						'title'    => $title,
						'issue'    => "Heading level jumps from H{$prev} to H{$level}",
						'severity' => 'warning',
						'fixable'  => true,
					);
					break;
				}
				$prev = $level;
			}
		}

		return $issues;
	}

	private function scan_broken_links( array $post_ids ): array {
		$issues   = array();
		$site_url = home_url();

		foreach ( $post_ids as $post_id ) {
			$content       = get_post_field( 'post_content', $post_id );
			$title         = get_the_title( $post_id );
			$top_blocks    = parse_blocks( $content );
			$flat          = $this->flatten_blocks( $top_blocks );

			// Validate block nesting against WordPress block.json rules.
			$nesting_issues = $this->scan_block_nesting( $top_blocks, null, $post_id, $title );
			foreach ( $nesting_issues as $ni ) {
				$issues[] = $ni;
			}

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
							'issue'        => 'Button has no link',
							'severity'     => 'error',
							'fixable'      => true,
							'button_label' => $btn_label,
							'input_type'   => 'url_selector',
						);
					}
				}

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
			$content = get_post_field( 'post_content', $post_id );
			$title   = get_the_title( $post_id );
			$status  = get_post_status( $post_id );
			$plain   = wp_strip_all_tags( $content );

			if ( preg_match( '/\{\{|Lorem ipsum|\[Company/i', $content ) ) {
				$issues[] = array(
					'post_id'  => $post_id,
					'title'    => $title,
					'issue'    => 'Placeholder text still present',
					'severity' => 'error',
					'fixable'  => false,
				);
			}

			$words = str_word_count( $plain );
			if ( $words < 100 ) {
				$issues[] = array(
					'post_id'  => $post_id,
					'title'    => $title,
					'issue'    => "Low word count ({$words} words)",
					'severity' => 'warning',
					'fixable'  => false,
				);
			}

			if ( 'draft' === $status ) {
				$issues[] = array(
					'post_id'  => $post_id,
					'title'    => $title,
					'issue'    => 'Page is still a Draft — not publicly visible',
					'severity' => 'warning',
					'fixable'  => true,
				);
			}
		}

		return $issues;
	}

	private function scan_wp_health( array $post_ids ): array {
		$issues = array();

		// --- Classic theme: check primary nav menu assignment ---
		if ( ! wp_is_block_theme() ) {
			$menu_id = $this->get_primary_menu_id();

			if ( ! $menu_id ) {
				$issues[] = array(
					'post_id'  => 0,
					'title'    => 'Site',
					'issue'    => 'No primary navigation menu assigned',
					'severity' => 'warning',
					'fixable'  => true,
				);
				return $issues;
			}

			$menu_items  = wp_get_nav_menu_items( $menu_id ) ?: array();
			$in_menu_ids = array_map( fn( $i ) => (int) $i->object_id, $menu_items );

			foreach ( $post_ids as $post_id ) {
				if ( ! in_array( $post_id, $in_menu_ids, true ) ) {
					$issues[] = array(
						'post_id'  => $post_id,
						'title'    => get_the_title( $post_id ),
						'issue'    => 'Page not in primary navigation menu',
						'severity' => 'warning',
						'fixable'  => true,
					);
				}
			}

			$home_id = (int) get_option( 'page_on_front' );
			if ( $home_id && ! empty( $menu_items ) ) {
				$first = reset( $menu_items );
				if ( (int) $first->object_id !== $home_id ) {
					$issues[] = array(
						'post_id'  => $home_id,
						'title'    => get_the_title( $home_id ),
						'issue'    => 'Home page is not first in the primary menu',
						'severity' => 'warning',
						'fixable'  => true,
					);
				}
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

					$issues[] = array(
						'post_id'  => 0,
						'title'    => 'Site',
						'issue'    => wp_strip_all_tags( $result['label'] ?? $test_key ),
						'severity' => 'critical' === $status ? 'error' : 'warning',
						'fixable'  => false,
					);
				} catch ( \Throwable $e ) {
					// Skip any test that throws — don't let one bad check break the scan.
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
				);
			}

			if ( $title === $site_name ) {
				$issues[] = array(
					'post_id'  => $post_id,
					'title'    => $title,
					'issue'    => 'Page title identical to site name',
					'severity' => 'warning',
					'fixable'  => false,
				);
			}
		}

		return $issues;
	}

	/* -----------------------------------------------------------------------
	 * Fix methods
	 * --------------------------------------------------------------------- */

	private function fix_accessibility( array $post_ids ): void {
		foreach ( $post_ids as $post_id ) {
			$content = get_post_field( 'post_content', $post_id );
			$changed = false;

			// Fill empty/missing alt on image blocks from attachment title.
			$content = preg_replace_callback(
				'/<!-- wp:image\s+(.*?)\s*-->(.*?)<!-- \/wp:image -->/s',
				function ( $m ) use ( &$changed ) {
					$attrs  = json_decode( $m[1], true ) ?: array();
					$html   = $m[2];
					$att_id = (int) ( $attrs['id'] ?? 0 );

					if ( ! $att_id || '' !== trim( $attrs['alt'] ?? '' ) ) {
						return $m[0]; // no attachment id, or alt already set in block attrs
					}

					$alt = esc_attr( get_the_title( $att_id ) );
					if ( ! $alt ) {
						return $m[0];
					}

					if ( preg_match( '/\balt=""/i', $html ) ) {
						// Empty alt attribute present — replace it.
						$html = preg_replace( '/\balt=""/i', 'alt="' . $alt . '"', $html );
					} elseif ( ! preg_match( '/\balt=/i', $html ) ) {
						// No alt attribute at all — inject into <img> tag.
						$html = preg_replace( '/<img\b/', '<img alt="' . $alt . '"', $html, 1 );
					} else {
						return $m[0]; // alt is set to something non-empty already
					}

					$attrs['alt'] = html_entity_decode( $alt );
					$changed      = true;
					return '<!-- wp:image ' . wp_json_encode( $attrs ) . ' -->' . $html . '<!-- /wp:image -->';
				},
				$content
			);

			// Fix heading hierarchy: close sequential gaps (e.g. h1→h3 becomes h1→h2).
			// Use a plain reference variable — the IIFE pattern cannot capture $changed from the outer scope.
			$heading_prev = 0;
			$content      = preg_replace_callback(
				'/<!-- wp:heading\s*(.*?)\s*-->(.*?)<!-- \/wp:heading -->/s',
				function ( $m ) use ( &$heading_prev, &$changed ) {
					$attrs = json_decode( $m[1], true ) ?: array();
					$level = (int) ( $attrs['level'] ?? 2 );

					if ( $heading_prev > 0 && $level > $heading_prev + 1 ) {
						$new_level      = $heading_prev + 1;
						$html           = preg_replace( '/<h' . $level . '/', '<h' . $new_level, $m[2] );
						$html           = preg_replace( '/<\/h' . $level . '>/', '</h' . $new_level . '>', $html );
						$attrs['level'] = $new_level;
						$changed        = true;
						$heading_prev   = $new_level;
						return '<!-- wp:heading ' . wp_json_encode( $attrs ) . ' -->' . $html . '<!-- /wp:heading -->';
					}
					$heading_prev = $level;
					return $m[0];
				},
				$content
			);

			// Replace generic button text with the linked page's title when URL is known.
			$content = preg_replace_callback(
				'/<!-- wp:button(?!s)\s*(.*?)\s*-->(.*?)<!-- \/wp:button -->/s',
				function ( $m ) use ( &$changed ) {
					$attrs_str = trim( $m[1] ?? '' );
					$attrs     = $attrs_str ? ( json_decode( $attrs_str, true ) ?: array() ) : array();
					$html      = $m[2];
					$url       = trim( $attrs['url'] ?? '' );

					if ( empty( $url ) || '#' === $url ) {
						return $m[0];
					}
					if ( ! preg_match( '/<a[^>]*>\s*(click here|read more|here|learn more)\s*<\/a>/i', $html ) ) {
						return $m[0];
					}

					$linked_id = url_to_postid( $url );
					$new_text  = $linked_id ? get_the_title( $linked_id ) : '';
					if ( empty( $new_text ) ) {
						return $m[0];
					}

					$html    = preg_replace(
						'/(<a[^>]*>)\s*(?:click here|read more|here|learn more)\s*(<\/a>)/i',
						'$1' . esc_html( $new_text ) . '$2',
						$html,
						1
					);
					$changed = true;
					return '<!-- wp:button ' . wp_json_encode( $attrs ) . ' -->' . $html . '<!-- /wp:button -->';
				},
				$content
			);

			if ( $changed ) {
				wp_update_post( array( 'ID' => $post_id, 'post_content' => $content ) );
			}
		}
	}

	private function fix_broken_links( array $post_ids, array $assignments = array() ): void {
		$auto_url_map = $this->build_page_url_map();

		// Build lookup: post_id → [ lowercased_button_label → url ].
		$url_by_label = array();
		foreach ( $assignments as $a ) {
			$pid   = (int) ( $a['post_id'] ?? 0 );
			$label = strtolower( trim( $a['button_label'] ?? '' ) );
			$url   = esc_url_raw( $a['url'] ?? '' );
			if ( $pid && $label && $url ) {
				$url_by_label[ $pid ][ $label ] = $url;
			}
		}

		foreach ( $post_ids as $post_id ) {
			// Always repair block structure first.
			$this->repair_orphaned_buttons( $post_id );

			if ( empty( $url_by_label[ $post_id ] ) ) {
				continue; // no URL assignments for this page
			}

			$content = get_post_field( 'post_content', $post_id );
			$changed = false;

			$content = preg_replace_callback(
				'/<!-- wp:button(?!s)\s*(.*?)\s*-->(.*?)<!-- \/wp:button -->/s',
				function ( $m ) use ( $post_id, $url_by_label, $auto_url_map, &$changed ) {
					$attrs_str = trim( $m[1] ?? '' );
					$attrs     = $attrs_str ? ( json_decode( $attrs_str, true ) ?: array() ) : array();
					$html      = $m[2];

					// Skip if already has a real URL.
					$cur_url = trim( $attrs['url'] ?? '' );
					preg_match( '/href="([^"]*)"/i', $html, $hm );
					$cur_href = trim( $hm[1] ?? '' );
					if ( ( ! empty( $cur_url ) && '#' !== $cur_url )
						|| ( ! empty( $cur_href ) && '#' !== $cur_href )
					) {
						return $m[0];
					}

					// Match by button label.
					preg_match( '/<a[^>]*>(.*?)<\/a>/s', $html, $lm );
					$label = strtolower( wp_strip_all_tags( $lm[1] ?? '' ) );
					// Use user-supplied assignment first, then fall back to auto-matching.
					$url   = $url_by_label[ $post_id ][ $label ] ?? $this->match_button_to_url( $label, $auto_url_map );
					if ( ! $url ) {
						return $m[0];
					}

					$attrs['url'] = $url;
					$changed      = true;
					$escaped_url  = esc_url( $url );

					if ( preg_match( '/href="[^"]*"/i', $html ) ) {
						$html = preg_replace( '/href="[^"]*"/i', 'href="' . $escaped_url . '"', $html, 1 );
					} else {
						$html = preg_replace( '/<a\b/', '<a href="' . $escaped_url . '"', $html, 1 );
					}
					return '<!-- wp:button ' . wp_json_encode( $attrs ) . ' -->' . $html . '<!-- /wp:button -->';
				},
				$content
			);

			if ( $changed ) {
				wp_update_post( array( 'ID' => $post_id, 'post_content' => $content ) );
			}
		}
	}

	private function fix_content_quality( array $post_ids ): void {
		foreach ( $post_ids as $post_id ) {
			if ( 'draft' === get_post_status( $post_id ) ) {
				wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
			}
		}
	}

	private function fix_wp_health( array $post_ids ): void {
		if ( wp_is_block_theme() ) {
			return; // navigation managed via Site Editor — cannot fix programmatically
		}

		$menu_id = $this->get_primary_menu_id();

		if ( ! $menu_id ) {
			$menu_id = wp_create_nav_menu( 'Primary Menu' );
			if ( is_wp_error( $menu_id ) ) {
				return;
			}
			$locations = get_nav_menu_locations();
			foreach ( array_keys( get_registered_nav_menus() ) as $loc ) {
				if ( in_array( strtolower( $loc ), array( 'primary', 'main', 'header', 'main-menu', 'primary-menu' ), true ) ) {
					$locations[ $loc ] = $menu_id;
					set_theme_mod( 'nav_menu_locations', $locations );
					break;
				}
			}
		}

		$existing    = wp_get_nav_menu_items( $menu_id ) ?: array();
		$in_menu_ids = array_map( fn( $i ) => (int) $i->object_id, $existing );
		$max_order   = $existing ? max( array_map( fn( $i ) => (int) $i->menu_order, $existing ) ) : 0;

		// Add missing generated pages in BUILD_OPTION order.
		$build      = get_option( GrayFox_SiteBuilder::BUILD_OPTION, array() );
		$build_pages = array_filter(
			$build['pages'] ?? array(),
			fn( $p ) => 'complete' === ( $p['status'] ?? '' )
		);

		foreach ( $build_pages as $p ) {
			$pid = (int) ( $p['post_id'] ?? 0 );
			if ( ! $pid || in_array( $pid, $in_menu_ids, true ) ) {
				continue;
			}
			$max_order++;
			wp_update_nav_menu_item( $menu_id, 0, array(
				'menu-item-object-id' => $pid,
				'menu-item-object'    => 'page',
				'menu-item-type'      => 'post_type',
				'menu-item-status'    => 'publish',
				'menu-item-position'  => $max_order,
				'menu-item-title'     => get_the_title( $pid ),
			) );
			$in_menu_ids[] = $pid;
		}

		// Ensure Home page is first.
		$home_id = (int) get_option( 'page_on_front' );
		if ( $home_id ) {
			$all_items = wp_get_nav_menu_items( $menu_id ) ?: array();
			usort( $all_items, function ( $a, $b ) use ( $home_id ) {
				if ( (int) $a->object_id === $home_id ) return -1;
				if ( (int) $b->object_id === $home_id ) return 1;
				return $a->menu_order <=> $b->menu_order;
			} );
			foreach ( $all_items as $idx => $item ) {
				wp_update_nav_menu_item( $menu_id, $item->ID, array(
					'menu-item-title'     => $item->title,
					'menu-item-url'       => $item->url,
					'menu-item-status'    => 'publish',
					'menu-item-position'  => $idx + 1,
					'menu-item-object-id' => $item->object_id,
					'menu-item-object'    => $item->object,
					'menu-item-type'      => $item->type,
				) );
			}
		}
	}

	/* -----------------------------------------------------------------------
	 * Content repair helpers
	 * --------------------------------------------------------------------- */

	/**
	 * Recursively collect all leaf core/button blocks from a mixed block array.
	 *
	 * Handles three cases produced by the broken fix_broken_links regex:
	 *  - core/button containing core/button children  → hoist the children (discard the fake outer shell)
	 *  - core/buttons containing core/buttons children → hoist the grandchildren
	 *  - Normal core/button with no children           → keep as-is
	 *
	 * Also strips duplicate href attributes from button HTML left by the link injector.
	 */
	private function collect_leaf_buttons( array $blocks ): array {
		$result = array();
		foreach ( $blocks as $block ) {
			$name = $block['blockName'] ?? '';

			if ( 'core/button' === $name ) {
				if ( empty( $block['innerBlocks'] ) ) {
					// Real leaf button — clean up any duplicate href before keeping.
					$block['innerContent'] = array_map(
						function ( $chunk ) {
							if ( ! is_string( $chunk ) ) {
								return $chunk;
							}
							// If an <a> tag has more than one href, keep only the first.
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
					// core/button wrongly contains children (e.g. the Home page artifact
					// where wp:buttons was consumed as a wp:button by the bad regex).
					// Discard the outer shell and hoist its inner buttons.
					$result = array_merge( $result, $this->collect_leaf_buttons( $block['innerBlocks'] ) );
				}
			} elseif ( 'core/buttons' === $name ) {
				// Nested wrapper — hoist its children.
				$result = array_merge( $result, $this->collect_leaf_buttons( $block['innerBlocks'] ?? array() ) );
			}
			// Any other block type inside core/buttons is silently dropped (invalid per spec).
		}
		return $result;
	}

	/**
	 * Return true if $inner_blocks is already a valid flat list of leaf core/button blocks.
	 */
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

	/**
	 * Build a repaired core/buttons block from a list of leaf buttons.
	 */
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

	/**
	 * Walk a block array and repair all button nesting violations.
	 * Recurses into innerBlocks of non-button container blocks.
	 */
	private function repair_orphaned_buttons_in_blocks( array $blocks ): array {
		$result = array();
		$i      = 0;
		$count  = count( $blocks );

		while ( $i < $count ) {
			$name = $blocks[ $i ]['blockName'] ?? '';

			if ( 'core/button' === $name ) {
				// Orphaned top-level core/button(s) — collect consecutive siblings and wrap.
				$group = array();
				while ( $i < $count && 'core/button' === ( $blocks[ $i ]['blockName'] ?? '' ) ) {
					$group[] = $blocks[ $i ];
					$i++;
				}
				$leaf_buttons = $this->collect_leaf_buttons( $group );
				$result[]     = $this->make_buttons_block( $leaf_buttons );

			} elseif ( 'core/buttons' === $name ) {
				$block        = $blocks[ $i ];
				$inner        = $block['innerBlocks'] ?? array();

				if ( ! $this->buttons_children_are_valid( $inner ) ) {
					// Invalid children — collect all leaf buttons recursively and rebuild.
					$leaf_buttons        = $this->collect_leaf_buttons( $inner );
					$block               = $this->make_buttons_block( $leaf_buttons );
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

	/**
	 * Detect and repair orphaned core/button blocks in a post's content.
	 * Uses parse_blocks + serialize_blocks so no regex touches block wrappers.
	 * Returns true if a repair was made and saved.
	 */
	private function repair_orphaned_buttons( int $post_id ): bool {
		$content = get_post_field( 'post_content', $post_id );

		// Strip orphaned wp:buttons closing fragments left behind by the bad regex.
		$content = preg_replace( '/\s*<\/div>\s*<!-- \/wp:buttons -->/m', '', $content );

		$blocks  = parse_blocks( $content );
		$fixed   = $this->repair_orphaned_buttons_in_blocks( $blocks );
		$new_con = serialize_blocks( $fixed );

		// Compare serialized forms — if nothing changed, skip the DB write.
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
	 * WordPress block nesting rules sourced from block.json in Gutenberg trunk.
	 *
	 * 'allowed_children' — the only block types permitted as direct children (null = any).
	 * 'required_parent'  — block must have one of these as its direct parent (null = no restriction).
	 */
	private const BLOCK_NESTING_RULES = array(
		'core/buttons'    => array( 'allowed_children' => array( 'core/button' ),    'required_parent' => null ),
		'core/button'     => array( 'allowed_children' => array(),                   'required_parent' => array( 'core/buttons' ) ),
		'core/columns'    => array( 'allowed_children' => array( 'core/column' ),    'required_parent' => null ),
		'core/column'     => array( 'allowed_children' => null,                      'required_parent' => array( 'core/columns' ) ),
		'core/list'       => array( 'allowed_children' => array( 'core/list-item' ), 'required_parent' => null ),
		'core/list-item'  => array( 'allowed_children' => array( 'core/list' ),      'required_parent' => array( 'core/list' ) ),
	);

	/**
	 * Walk the block tree and return nesting violations based on WordPress block.json rules.
	 * Reports one issue per violation per page to avoid flooding.
	 *
	 * @param array       $blocks     Blocks at the current level.
	 * @param string|null $parent     Block name of the direct parent, or null at top level.
	 * @param int         $post_id    Post being scanned.
	 * @param string      $title      Page title for issue reporting.
	 * @param array       $seen       Violation keys already reported for this page (passed by ref).
	 * @return array                  Issues array.
	 */
	private function scan_block_nesting( array $blocks, ?string $parent, int $post_id, string $title, array &$seen = array() ): array {
		$issues = array();

		foreach ( $blocks as $block ) {
			$name  = $block['blockName'] ?? '';
			$rules = self::BLOCK_NESTING_RULES[ $name ] ?? null;

			// Check required_parent: this block must be inside a specific parent.
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
						);
					}
				}
			}

			// Check allowed_children: this block's children must be in the allowed list.
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
							);
						}
					}
				}
			}

			// Recurse into inner blocks.
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

		// Keyword groups: if the button label contains a keyword, find a page whose
		// slug or title contains the group term (handles slugs like "plans-pricing").
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
					// Search all page keys for one that contains the group term.
					foreach ( $page_url_map as $page_key => $candidate_url ) {
						if ( str_contains( $page_key, $group_term ) ) {
							return $candidate_url;
						}
					}
				}
			}
		}

		// Word-overlap fallback: any page key word that appears in the button label.
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
