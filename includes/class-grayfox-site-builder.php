<?php
/**
 * Site Builder — generate WordPress pages from the knowledge base.
 *
 * @package GrayFox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GrayFox_SiteBuilder
 *
 * Generates a full page structure from the active knowledge base using an
 * LLM to produce either Gutenberg block markup or Elementor JSON data.
 */
class GrayFox_SiteBuilder {

	/** Action Scheduler hook for the generation job. */
	const AS_HOOK_GENERATE = 'grayfox_generate_site_pages';

	/** Transient key used as a generation lock. */
	const LOCK_TRANSIENT = 'grayfox_site_generation_lock';

	/** WP option that stores build progress. */
	const BUILD_OPTION = 'grayfox_site_build';

	/** WP option that stores the approved sitemap draft. */
	const SITEMAP_OPTION = 'grayfox_sitemap_draft';

	/** WP option that stores the chosen build format. */
	const FORMAT_OPTION = 'grayfox_site_build_format';

	/** WP option that stores the encrypted Unsplash API key. */
	const UNSPLASH_OPTION = 'grayfox_unsplash_api_key';

	/** Post meta key that marks GrayFox-generated pages. */
	const META_GENERATED = '_grayfox_generated';

	/** Elementor widget types allowed in generated content. */
	const ELEMENTOR_WIDGET_WHITELIST = array( 'heading', 'text-editor', 'image' );

	/**
	 * Singleton instance.
	 *
	 * @var GrayFox_SiteBuilder|null
	 */
	private static ?GrayFox_SiteBuilder $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return GrayFox_SiteBuilder
	 */
	public static function get_instance(): GrayFox_SiteBuilder {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** Private constructor for singleton. */
	private function __construct() {}

	/**
	 * Register hooks with the loader.
	 *
	 * @param GrayFox_Loader $loader Loader instance.
	 */
	public function register( GrayFox_Loader $loader ): void {
		$loader->add_action( 'init', $this, 'register_as_callback', 5 );
		$loader->add_filter( 'wp_revisions_to_keep', $this, 'limit_revisions_for_generated_pages', 10, 2 );
	}

	/**
	 * Register the Action Scheduler callback on the init hook.
	 *
	 * Called at priority 5 to ensure AS has loaded before WordPress fires the hook.
	 */
	public function register_as_callback(): void {
		add_action( self::AS_HOOK_GENERATE, array( $this, 'generate_site_pages' ), 10, 2 );
	}

	/**
	 * Limit revision history to 3 for GrayFox-generated pages.
	 *
	 * @param int     $num  Default revision count.
	 * @param WP_Post $post Post object.
	 * @return int
	 */
	public function limit_revisions_for_generated_pages( int $num, WP_Post $post ): int {
		if ( get_post_meta( $post->ID, self::META_GENERATED, true ) ) {
			return 3;
		}
		return $num;
	}

	/**
	 * Detect the active page builder and theme environment.
	 *
	 * @return array{
	 *   has_elementor: bool,
	 *   elementor_version_ok: bool,
	 *   elementor_version: string,
	 *   has_other_builder: bool,
	 *   other_builder_name: string,
	 *   is_block_theme: bool
	 * }
	 */
	public static function detect_environment(): array {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$has_elementor        = defined( 'ELEMENTOR_VERSION' ) || is_plugin_active( 'elementor/elementor.php' );
		$elementor_version    = defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '';
		$elementor_version_ok = $has_elementor && version_compare( $elementor_version, '3.0.0', '>=' );

		$other_builders = array(
			'divi'         => 'et-builder/et-builder.php',
			'Beaver Builder' => 'beaver-builder-lite-version/fl-builder.php',
			'WPBakery'     => 'js_composer/js_composer.php',
		);
		$has_other_builder  = false;
		$other_builder_name = '';
		foreach ( $other_builders as $name => $plugin_file ) {
			if ( is_plugin_active( $plugin_file ) ) {
				$has_other_builder  = true;
				$other_builder_name = $name;
				break;
			}
		}

		return array(
			'has_elementor'        => $has_elementor,
			'elementor_version_ok' => $elementor_version_ok,
			'elementor_version'    => $elementor_version,
			'has_other_builder'    => $has_other_builder,
			'other_builder_name'   => $other_builder_name,
			'is_block_theme'       => function_exists( 'wp_is_block_theme' ) && wp_is_block_theme(),
		);
	}

	/**
	 * Action Scheduler callback: generate all pages in the sitemap.
	 *
	 * @param array  $sitemap Page hierarchy array from SITEMAP_OPTION.
	 * @param string $format  'blocks' or 'elementor'.
	 */
	public function generate_site_pages( array $sitemap, string $format ): void {
		$build       = get_option( self::BUILD_OPTION, array() );
		$created_map = array(); // title => post_id for parent resolution.

		$this->process_pages_recursive( $sitemap, $format, 0, $build, $created_map );

		$build['status'] = 'complete';
		update_option( self::BUILD_OPTION, $build );
		delete_transient( self::LOCK_TRANSIENT );
	}

	/**
	 * Recursively generate pages, tracking parent IDs.
	 *
	 * @param array  $pages       Page defs at this level.
	 * @param string $format      Build format.
	 * @param int    $parent_id   WordPress post parent ID (0 = top-level).
	 * @param array  $build       Build progress option (passed by reference).
	 * @param array  $created_map title → post_id map (passed by reference).
	 */
	private function process_pages_recursive( array $pages, string $format, int $parent_id, array &$build, array &$created_map ): void {
		foreach ( $pages as $page_def ) {
			$result = $this->generate_page( $page_def, $format, $parent_id );

			$build['completed'] = ( $build['completed'] ?? 0 ) + 1;
			$build['pages'][]   = $result;
			update_option( self::BUILD_OPTION, $build );

			if ( ! empty( $result['post_id'] ) ) {
				$created_map[ $page_def['title'] ] = $result['post_id'];

				if ( ! empty( $page_def['children'] ) && is_array( $page_def['children'] ) ) {
					$this->process_pages_recursive( $page_def['children'], $format, $result['post_id'], $build, $created_map );
				}
			}
		}
	}

	/**
	 * Generate a single page and insert it into WordPress.
	 *
	 * @param array  $page_def   Page definition array (title, children).
	 * @param string $format     'blocks' or 'elementor'.
	 * @param int    $parent_id  WordPress post parent ID.
	 * @return array{post_id: int, status: string, title: string, edit_url: string}
	 */
	public function generate_page( array $page_def, string $format, int $parent_id ): array {
		$title  = sanitize_text_field( $page_def['title'] ?? 'Untitled' );
		$result = array(
			'post_id'  => 0,
			'status'   => 'failed',
			'title'    => $title,
			'edit_url' => '',
		);

		try {
			// 1. Retrieve relevant KB context.
			global $wpdb;
			$kb_table = GrayFox_DB::get_table( 'knowledge_base' );
			$all_rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
				"SELECT id, source_name, content_json, topic_index FROM `{$kb_table}` WHERE status = 'active'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
				ARRAY_A
			);
			$relevant  = GrayFox_RAG::retrieve_relevant_sections( $title, $all_rows );
			$kb_ctx    = '';
			foreach ( $relevant as $row ) {
				$kb_ctx .= ( $row['source_name'] ?? '' ) . ': ' . ( $row['content_json'] ?? '' ) . "\n\n";
			}
			$kb_ctx = mb_substr( $kb_ctx, 0, 8000 );

			// 2. Call LLM to generate page blocks.
			$provider = get_option( 'grayfox_llm_provider', 'openai' );
			$enc_key  = get_option( 'grayfox_llm_api_key', '' );
			$api_key  = grayfox_decrypt( $enc_key );
			$model    = get_option( 'grayfox_llm_model', '' );

			if ( empty( $api_key ) || empty( $model ) ) {
				return $result;
			}

			$llm      = new GrayFox_LLM();
			$messages = array(
				array(
					'role'    => 'user',
					'content' => sprintf(
						'Generate content for a WordPress page titled "%s". Use this business knowledge:\n\n%s\n\nReturn JSON only: {"title":"%s","blocks":[{"type":"heading","level":2,"content":"..."},{"type":"paragraph","content":"..."},{"type":"image","keyword":"..."}]}. Include 3-6 blocks relevant to the page title. Use only types: heading, paragraph, image.',
						$title,
						$kb_ctx,
						$title
					),
				),
			);

			$raw    = $llm->request_json( $provider, $api_key, $model, $messages, 0.3 );
			$parsed = json_decode( $raw, true );

			if ( ! is_array( $parsed ) || empty( $parsed['blocks'] ) ) {
				return $result;
			}

			// 3. Sanitize all text through wp_kses_post.
			foreach ( $parsed['blocks'] as &$block ) {
				if ( isset( $block['content'] ) ) {
					$block['content'] = wp_kses_post( $block['content'] );
				}
				if ( isset( $block['keyword'] ) ) {
					$block['keyword'] = sanitize_text_field( $block['keyword'] );
				}
			}
			unset( $block );

			// 4. Build content in chosen format.
			$post_content   = '';
			$elementor_data = null;

			if ( 'elementor' === $format ) {
				$elementor_data = $this->build_elementor_data( $parsed['blocks'] );
			} else {
				$post_content = $this->build_wp_blocks( $parsed['blocks'] );
			}

			// 5. Check for slug collision.
			$slug = sanitize_title( $title );
			if ( get_page_by_path( $slug ) ) {
				$slug .= '-v2';
			}

			// 6. Insert the page.
			$post_id = wp_insert_post( array(
				'post_title'   => wp_strip_all_tags( $title ),
				'post_content' => $post_content,
				'post_status'  => 'draft',
				'post_type'    => 'page',
				'post_name'    => $slug,
				'post_parent'  => $parent_id,
			) );

			if ( is_wp_error( $post_id ) || ! $post_id ) {
				return $result;
			}

			// 7. Tag as GrayFox-generated.
			add_post_meta( $post_id, self::META_GENERATED, '1', true );

			// 8. Elementor data.
			if ( 'elementor' === $format && $elementor_data !== null ) {
				update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $elementor_data ) ) );
				update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
			}

			// 9. Featured image from Unsplash.
			$image_keyword = '';
			foreach ( $parsed['blocks'] as $block ) {
				if ( 'image' === $block['type'] && ! empty( $block['keyword'] ) ) {
					$image_keyword = $block['keyword'];
					break;
				}
			}
			if ( empty( $image_keyword ) ) {
				$image_keyword = $title;
			}
			$attachment_id = $this->fetch_unsplash_image( $image_keyword );
			if ( $attachment_id ) {
				set_post_thumbnail( $post_id, $attachment_id );
			}

			$result['post_id']  = $post_id;
			$result['status']   = 'complete';
			$result['edit_url'] = get_edit_post_link( $post_id, 'raw' );

		} catch ( Throwable $e ) {
			error_log( 'GrayFox generate_page error for "' . $title . '": ' . $e->getMessage() );
		}

		return $result;
	}

	/**
	 * Build WordPress block markup from a blocks array.
	 *
	 * @param array $blocks LLM-generated blocks array.
	 * @return string Serialized block content.
	 */
	public function build_wp_blocks( array $blocks ): string {
		$output = '';
		foreach ( $blocks as $block ) {
			$type    = $block['type'] ?? '';
			$content = $block['content'] ?? '';
			switch ( $type ) {
				case 'heading':
					$level   = max( 2, min( 6, (int) ( $block['level'] ?? 2 ) ) );
					$output .= "<!-- wp:heading {\"level\":{$level}} -->\n";
					$output .= "<h{$level} class=\"wp-block-heading\">" . wp_kses_post( $content ) . "</h{$level}>\n";
					$output .= "<!-- /wp:heading -->\n\n";
					break;
				case 'paragraph':
					$output .= "<!-- wp:paragraph -->\n";
					$output .= '<p>' . wp_kses_post( $content ) . "</p>\n";
					$output .= "<!-- /wp:paragraph -->\n\n";
					break;
				case 'image':
					// Image block without a fixed attachment ID — can be filled in manually.
					$output .= "<!-- wp:image -->\n";
					$output .= "<figure class=\"wp-block-image\"><img alt=\"" . esc_attr( $block['keyword'] ?? '' ) . "\" /></figure>\n";
					$output .= "<!-- /wp:image -->\n\n";
					break;
			}
		}
		return $output;
	}

	/**
	 * Build Elementor page data JSON structure from a blocks array.
	 *
	 * @param array $blocks LLM-generated blocks array.
	 * @return array Elementor data ready for _elementor_data meta.
	 */
	public function build_elementor_data( array $blocks ): array {
		$widgets    = array();
		foreach ( $blocks as $block ) {
			$type = $block['type'] ?? '';
			$content = $block['content'] ?? '';

			if ( 'heading' === $type && $this->validate_elementor_widget( 'heading' ) ) {
				$widgets[] = array(
					'id'         => wp_generate_password( 7, false ),
					'elType'     => 'widget',
					'widgetType' => 'heading',
					'settings'   => array(
						'title'   => wp_strip_all_tags( $content ),
						'size'    => 'h' . max( 2, min( 6, (int) ( $block['level'] ?? 2 ) ) ),
					),
				);
			} elseif ( 'paragraph' === $type && $this->validate_elementor_widget( 'text-editor' ) ) {
				$widgets[] = array(
					'id'         => wp_generate_password( 7, false ),
					'elType'     => 'widget',
					'widgetType' => 'text-editor',
					'settings'   => array(
						'editor' => wp_kses_post( $content ),
					),
				);
			} elseif ( 'image' === $type && $this->validate_elementor_widget( 'image' ) ) {
				$widgets[] = array(
					'id'         => wp_generate_password( 7, false ),
					'elType'     => 'widget',
					'widgetType' => 'image',
					'settings'   => array(
						'image' => array(
							'url' => '',
							'alt' => sanitize_text_field( $block['keyword'] ?? '' ),
						),
					),
				);
			}
		}

		$column_id  = wp_generate_password( 7, false );
		$section_id = wp_generate_password( 7, false );

		return array(
			array(
				'id'       => $section_id,
				'elType'   => 'section',
				'settings' => array(),
				'elements' => array(
					array(
						'id'       => $column_id,
						'elType'   => 'column',
						'settings' => array( '_column_size' => 100 ),
						'elements' => $widgets,
					),
				),
			),
		);
	}

	/**
	 * Validate that an Elementor widget type is in the whitelist.
	 *
	 * @param string $type Widget type slug.
	 * @return bool
	 */
	public function validate_elementor_widget( string $type ): bool {
		return in_array( $type, self::ELEMENTOR_WIDGET_WHITELIST, true );
	}

	/**
	 * Fetch an image from Unsplash and sideload it into the WP media library.
	 *
	 * @param string $keyword Search keyword.
	 * @return int Attachment ID, or 0 on failure.
	 */
	public function fetch_unsplash_image( string $keyword ): int {
		$enc_key = get_option( self::UNSPLASH_OPTION, '' );
		if ( empty( $enc_key ) ) {
			return 0;
		}
		$api_key = grayfox_decrypt( $enc_key );
		if ( empty( $api_key ) ) {
			return 0;
		}

		// Respect Unsplash rate limit: 50 requests/hour (~72s between requests).
		// A 1.2s delay is a courtesy; production should use a proper queue.
		usleep( 1200000 );

		$url      = 'https://api.unsplash.com/search/photos?' . http_build_query( array(
			'query'    => $keyword,
			'per_page' => 1,
		) );
		$response = wp_remote_get( $url, array(
			'headers' => array( 'Authorization' => 'Client-ID ' . $api_key ),
			'timeout' => 15,
		) );

		if ( is_wp_error( $response ) ) {
			return 0;
		}

		$body    = json_decode( wp_remote_retrieve_body( $response ), true );
		$img_url = $body['results'][0]['urls']['regular'] ?? '';

		if ( empty( $img_url ) ) {
			return 0;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$attachment_id = media_sideload_image( $img_url, 0, $keyword, 'id' );

		if ( is_wp_error( $attachment_id ) ) {
			return 0;
		}

		return (int) $attachment_id;
	}

	/**
	 * Estimate token usage and cost for generating the sitemap pages.
	 *
	 * @param array $sitemap Sitemap pages array.
	 * @return array{input_tokens: int, output_tokens: int, total_tokens: int, estimated_cost: string, page_count: int}
	 */
	public function estimate_tokens( array $sitemap ): array {
		global $wpdb;
		$kb_table = GrayFox_DB::get_table( 'knowledge_base' );

		$kb_size = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT COALESCE(SUM(token_estimate), 0) FROM `{$kb_table}` WHERE status = 'active'" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		);

		$page_count    = $this->count_pages_recursive( $sitemap );
		$input_tokens  = (int) ( $kb_size * $page_count * 0.3 ); // ~30% of KB per page call.
		$output_tokens = 500 * $page_count;
		$total_tokens  = $input_tokens + $output_tokens;

		$provider = get_option( 'grayfox_llm_provider', 'openai' );
		$model    = get_option( 'grayfox_llm_model', '' );
		$pricing  = GrayFox_Settings::get_model_pricing( $model );

		$cost_str = 'unknown';
		if ( $pricing ) {
			$cost = ( $input_tokens / 1000000 ) * $pricing['input_per_1m']
				  + ( $output_tokens / 1000000 ) * $pricing['output_per_1m'];
			$cost_str = '$' . number_format( $cost, 4 );
		}

		return array(
			'input_tokens'   => $input_tokens,
			'output_tokens'  => $output_tokens,
			'total_tokens'   => $total_tokens,
			'estimated_cost' => $cost_str,
			'page_count'     => $page_count,
		);
	}

	/**
	 * Count total pages recursively (including children).
	 *
	 * @param array $pages Sitemap pages array.
	 * @return int
	 */
	private function count_pages_recursive( array $pages ): int {
		$count = 0;
		foreach ( $pages as $page ) {
			$count++;
			if ( ! empty( $page['children'] ) && is_array( $page['children'] ) ) {
				$count += $this->count_pages_recursive( $page['children'] );
			}
		}
		return $count;
	}
}
