<?php
/**
 * Site Builder harness tools.
 *
 * These tools are used exclusively inside the Site Builder's agentic harness
 * loop. They are NOT registered in the global GrayFox_Tools registry and will
 * never appear in the chat widget.
 *
 * Each tool extends GrayFox_Tool so it shares the same interface contract.
 * The site builder instantiates them directly via GrayFox_SiteBuilder::build_harness_tool_instances().
 *
 * Tool sequence the LLM is expected to follow per page:
 *   sb_query_templates → sb_create_page → sb_query_patterns → sb_append_pattern (×N) → sb_page_complete
 *
 * @package GrayFox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared block-schema logic used by both QueryPatterns and AppendPattern.
 *
 * extract_schema() and inject_content() must traverse blocks in identical order
 * so that slot indices align. Keeping both in one trait enforces that contract.
 */
trait GrayFox_SB_PatternSchema {

	/**
	 * Recursively walk parsed blocks and build a flat, indexed schema of
	 * content slots. Structural containers are traversed but not indexed.
	 *
	 * @param array $blocks Parsed block array from parse_blocks().
	 * @param int   $idx    Running index, passed by reference.
	 * @return array Flat schema entries.
	 */
	private function extract_schema( array $blocks, int &$idx ): array {
		$schema = array();

		foreach ( $blocks as $block ) {
			$name = $block['blockName'] ?? '';

			if ( in_array( $name, array( 'core/group', 'core/columns', 'core/column', 'core/cover', 'core/media-text' ), true ) ) {
				$schema = array_merge( $schema, $this->extract_schema( $block['innerBlocks'] ?? array(), $idx ) );
				continue;
			}

			switch ( $name ) {
				case 'core/heading':
					$level    = (int) ( $block['attrs']['level'] ?? 2 );
					$hint     = trim( wp_strip_all_tags( $block['innerHTML'] ?? '' ) );
					$schema[] = array(
						'index' => $idx++,
						'type'  => 'heading',
						'level' => $level,
						'hint'  => $hint ?: "Heading (h{$level})",
					);
					break;

				case 'core/paragraph':
					$inner_html = $block['innerHTML'] ?? '';
					if ( '' === $inner_html ) {
						$inner_html = implode( '', array_filter( $block['innerContent'] ?? array(), 'is_string' ) );
					}
					$hint = trim( wp_strip_all_tags( $inner_html ) );
					if ( empty( $hint ) ) {
						break;
					}
					$schema[] = array(
						'index' => $idx++,
						'type'  => 'paragraph',
						'hint'  => $hint,
					);
					break;

				case 'core/buttons':
					$buttons = array();
					foreach ( $block['innerBlocks'] ?? array() as $btn ) {
						if ( ( $btn['blockName'] ?? '' ) !== 'core/button' ) {
							continue;
						}
						$btn_text  = trim( wp_strip_all_tags( $btn['innerHTML'] ?? '' ) );
						$buttons[] = array(
							'index' => count( $buttons ),
							'hint'  => $btn_text ?: 'Button label',
						);
					}
					if ( ! empty( $buttons ) ) {
						$schema[] = array(
							'index'   => $idx++,
							'type'    => 'buttons',
							'buttons' => $buttons,
						);
					}
					break;

				case 'core/image':
					$schema[] = array(
						'index' => $idx++,
						'type'  => 'image',
						'hint'  => '2-4 word image search keyword describing the ideal photo for this section',
					);
					break;

				case 'core/html':
					$raw_html = trim( $block['innerHTML'] ?? '' );
					if ( ! empty( $raw_html ) ) {
						$schema[] = array(
							'index' => $idx++,
							'type'  => 'html',
							'hint'  => $raw_html,
						);
					}
					break;

				default:
					if ( ! empty( $name ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( sprintf( 'GrayFox SB extract_schema: unhandled block "%s" — not recursed or indexed. If this block contains content, add it to the container or slot handler list.', $name ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					}
					break;
			}
		}

		return $schema;
	}

	/**
	 * Resolve a pattern's real block HTML content.
	 *
	 * Some themes (e.g. Twenty Twenty-Five) register patterns whose `content`
	 * field is itself a `<!-- wp:pattern {"slug":"..."} /-->` shorthand reference
	 * rather than expanded block HTML. Strips PHP headers and resolves one level
	 * of pattern references so extract_schema and inject_content receive actual blocks.
	 *
	 * @param array                      $pattern  Registered pattern data.
	 * @param WP_Block_Patterns_Registry $registry Pattern registry instance.
	 * @return string Expanded block HTML, or empty string if unresolvable.
	 */
	private function resolve_pattern_content( array $pattern, WP_Block_Patterns_Registry $registry ): string {
		$pattern_name = $pattern['name'] ?? 'unknown';
		$raw          = $pattern['content'] ?? '';

		if ( empty( $raw ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( 'GrayFox SB pattern_resolve [%s]: pattern has no content field — skipping.', $pattern_name ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			return '';
		}

		// Strip PHP file header.
		$html = preg_replace( '/<\?php[\s\S]*?\?>\s*/i', '', $raw );

		// Check if the content is purely core/pattern reference shorthand.
		$blocks = parse_blocks( $html );
		$real   = array_filter( $blocks, static function ( array $b ): bool {
			return ! empty( $b['blockName'] ) && 'core/pattern' !== $b['blockName'];
		} );

		if ( ! empty( $real ) ) {
			return $html;
		}

		// All blocks are core/pattern references — expand the first resolvable one.
		foreach ( $blocks as $block ) {
			if ( ( $block['blockName'] ?? '' ) !== 'core/pattern' ) {
				continue;
			}
			$slug = $block['attrs']['slug'] ?? '';
			if ( ! $slug || ! $registry->is_registered( $slug ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( sprintf( 'GrayFox SB pattern_resolve [%s]: core/pattern reference "%s" is not registered — cannot expand.', $pattern_name, $slug ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
				continue;
			}
			$ref     = $registry->get_registered( $slug );
			$ref_raw = $ref['content'] ?? '';
			if ( ! empty( $ref_raw ) ) {
				return preg_replace( '/<\?php[\s\S]*?\?>\s*/i', '', $ref_raw );
			}
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( 'GrayFox SB pattern_resolve [%s]: referenced pattern "%s" resolved but has empty content.', $pattern_name, $slug ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'GrayFox SB pattern_resolve [%s]: fell through — returning potentially unresolved shorthand HTML.', $pattern_name ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		return $html;
	}
}

/**
 * Tool: sb_query_templates
 *
 * Returns FSE block templates available in the active theme so the LLM can
 * choose the most appropriate one for the page being built.
 */
class GrayFox_SB_Tool_QueryTemplates extends GrayFox_Tool {

	public function get_name(): string {
		return 'sb_query_templates';
	}

	public function get_tier(): string {
		return 'all';
	}

	public function get_definition(): array {
		return array(
			'type'     => 'function',
			'function' => array(
				'name'        => 'sb_query_templates',
				'description' => 'Returns the list of FSE page templates available in the active WordPress theme. '
					. 'Call this first to see which templates exist before calling sb_create_page.',
				'parameters'  => array(
					'type'                 => 'object',
					'properties'           => array(
						'area' => array(
							'type'        => 'string',
							'description' => 'Optional template area filter, e.g. "page" or "single". Leave empty to get all templates.',
						),
					),
					'required'             => array(),
					'additionalProperties' => false,
				),
			),
		);
	}

	public function execute( array $args ): string {
		$theme_slug = get_stylesheet();
		$templates  = get_block_templates( array( 'theme' => $theme_slug, 'post_type' => 'page' ) );

		if ( empty( $templates ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					'GrayFox SB sb_query_templates: no FSE templates found for theme "%s". Is this a block theme? wp_is_block_theme=%s',
					get_stylesheet(),
					( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) ? 'true' : 'false'
				) );
			}
			return wp_json_encode( array() );
		}

		$result = array();
		foreach ( $templates as $template ) {
			$result[] = array(
				'slug'        => sanitize_key( $template->slug ),
				'title'       => wp_strip_all_tags( $template->title ),
				'description' => wp_strip_all_tags( $template->description ?? '' ),
				'outline'     => $this->extract_template_outline( $template->content ?? '' ),
			);
		}

		return wp_json_encode( $result );
	}

	/**
	 * Extract a readable structural outline from a template's block content.
	 * Returns section entries with class names and first heading found — enough
	 * for the LLM to understand the theme designer's intent for this template.
	 *
	 * @param string $content Raw template block HTML.
	 * @return array[]
	 */
	private function extract_template_outline( string $content ): array {
		if ( empty( $content ) ) {
			return array();
		}

		$blocks  = parse_blocks( $content );
		$outline = array();

		foreach ( $blocks as $block ) {
			$name = $block['blockName'] ?? '';

			// Skip template parts (header/footer) — they're chrome, not content.
			if ( 'core/template-part' === $name ) {
				continue;
			}

			$classes = trim( $block['attrs']['className'] ?? '' );
			$heading = $this->find_first_heading( $block );

			$outline[] = array_filter( array(
				'section' => $classes ?: $name,
				'heading' => $heading,
			) );
		}

		return $outline;
	}

	/**
	 * Recursively find the first heading text in a block tree.
	 *
	 * @param array $block Parsed block.
	 * @return string Heading text, or empty string if none found.
	 */
	private function find_first_heading( array $block ): string {
		if ( 'core/heading' === ( $block['blockName'] ?? '' ) ) {
			return trim( wp_strip_all_tags( $block['innerHTML'] ?? '' ) );
		}
		foreach ( $block['innerBlocks'] ?? array() as $inner ) {
			$found = $this->find_first_heading( $inner );
			if ( '' !== $found ) {
				return $found;
			}
		}
		return '';
	}
}

/**
 * Tool: sb_query_patterns
 *
 * Returns registered block patterns from the active theme with a parsed
 * content_schema — a flat, indexed list of fillable slots (headings,
 * paragraphs, buttons) derived from the pattern's actual block structure.
 *
 * The LLM uses the schema to know exactly what content to generate for each
 * slot. Raw block HTML is not returned — only the schema and metadata.
 */
class GrayFox_SB_Tool_QueryPatterns extends GrayFox_Tool {

	use GrayFox_SB_PatternSchema;

	public function get_name(): string {
		return 'sb_query_patterns';
	}

	public function get_tier(): string {
		return 'all';
	}

	public function get_definition(): array {
		return array(
			'type'     => 'function',
			'function' => array(
				'name'        => 'sb_query_patterns',
				'description' => 'Returns block patterns available in the active WordPress theme. '
					. 'Each pattern includes its name, title, description, and a content_schema — '
					. 'a flat indexed list of the fillable content slots (headings, paragraphs, buttons) '
					. 'inside the pattern, each with its current placeholder text as a hint. '
					. 'Use the schema to generate targeted content for each slot when calling sb_append_pattern.',
				'parameters'  => array(
					'type'                 => 'object',
					'properties'           => array(
						'category' => array(
							'type'        => 'string',
							'description' => 'Optional category slug to filter patterns (e.g. "grayfox-sections"). Leave empty for all.',
						),
					),
					'required'             => array(),
					'additionalProperties' => false,
				),
			),
		);
	}

	public function execute( array $args ): string {
		$category = isset( $args['category'] ) ? sanitize_key( $args['category'] ) : '';
		$registry = WP_Block_Patterns_Registry::get_instance();
		$all      = $registry->get_all_registered();
		$result   = array();

		foreach ( $all as $pattern ) {
			$name = $pattern['name'] ?? '';

			// Exclude WordPress core patterns — include everything from any theme namespace.
			// We intentionally do NOT restrict to the active theme slug: the active theme
			// may register patterns under a different namespace (e.g. grayfox-theme while
			// the user previews twentytwentyfive, or vice versa).
			$namespace = strstr( $name, '/', true );
			if ( 'core' === $namespace ) {
				continue;
			}

			if ( $category ) {
				$cats = $pattern['categories'] ?? array();
				if ( ! in_array( $category, $cats, true ) ) {
					continue;
				}
			}

			// Resolve pattern content — some themes store patterns as `<!-- wp:pattern /-->`
			// shorthand references rather than expanded block HTML. Expand them recursively.
			$raw = $this->resolve_pattern_content( $pattern, $registry );

			// Parse block structure to extract the content schema.
			$schema = array();
			if ( ! empty( $raw ) ) {
				$blocks = parse_blocks( $raw );
				$idx    = 0;
				$schema = $this->extract_schema( $blocks, $idx );
				if ( empty( $schema ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( sprintf( 'GrayFox SB sb_query_patterns: pattern "%s" has content but extract_schema returned 0 slots. Block structure may use unhandled container types.', $name ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
			}

			$result[] = array(
				'name'           => $name,
				'title'          => $pattern['title']       ?? $name,
				'description'    => $pattern['description'] ?? '',
				'categories'     => $pattern['categories']  ?? array(),
				'keywords'       => $pattern['keywords']    ?? array(),
				'content_schema' => $schema,
			);
		}

		if ( empty( $result ) ) {
			return wp_json_encode( array(
				'error'      => 'No non-core patterns are registered. Call sb_page_complete with a note.',
				'patterns'   => array(),
			) );
		}

		return wp_json_encode( $result );
	}

}


/**
 * Tool: sb_append_pattern
 *
 * Fetches a registered block pattern, injects the LLM-provided content into
 * its block slots by index (using WordPress parse_blocks / serialize_blocks),
 * and appends the result to the page's post_content.
 *
 * The content object uses the same slot indices as returned by sb_query_patterns
 * content_schema. Structural styling (classes, colors, spacing) is preserved
 * from the original pattern; only text content is replaced.
 */
class GrayFox_SB_Tool_AppendPattern extends GrayFox_Tool {

	use GrayFox_SB_PatternSchema;

	public function get_name(): string {
		return 'sb_append_pattern';
	}

	public function get_tier(): string {
		return 'all';
	}

	public function get_definition(): array {
		return array(
			'type'     => 'function',
			'function' => array(
				'name'        => 'sb_append_pattern',
				'description' => 'Fetches a registered block pattern by name, injects your content into its slots '
					. 'using the indices from sb_query_patterns content_schema, and appends the styled result '
					. 'to the specified page. All styling from the pattern is preserved — only text content changes. '
					. 'Call this once per pattern you want to add to the page.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'      => array(
							'type'        => 'integer',
							'description' => 'The post_id returned by sb_create_page.',
						),
						'pattern_name' => array(
							'type'        => 'string',
							'description' => 'Full pattern name as returned by sb_query_patterns (e.g. "grayfox-theme/hero").',
						),
						'slots'        => array(
							'type'        => 'array',
							'description' => 'Content for each slot, matched by the index from content_schema. '
								. 'For heading/paragraph slots: {"index": 0, "content": "Your text here"}. '
								. 'For button slots: {"index": 2, "buttons": [{"index": 0, "text": "CTA Label"}, {"index": 1, "text": "Secondary"}]}. '
								. 'For image slots: {"index": 3, "keyword": "travel agency beach"}.',
							'items'       => array( 'type' => 'object' ),
						),
					),
					'required'             => array( 'post_id', 'pattern_name', 'slots' ),
					'additionalProperties' => false,
				),
			),
		);
	}

	public function execute( array $args ): string {
		$post_id      = absint( $args['post_id']      ?? 0 );
		$pattern_name = sanitize_text_field( $args['pattern_name'] ?? '' );
		$slots        = is_array( $args['slots'] ) ? $args['slots'] : array();

		if ( ! $post_id || ! $pattern_name ) {
			return wp_json_encode( array( 'error' => 'post_id and pattern_name are required.' ) );
		}

		$registry = WP_Block_Patterns_Registry::get_instance();
		if ( ! $registry->is_registered( $pattern_name ) ) {
			// Return the registered names so the LLM can pick a valid alternative.
			$available = array_column( $registry->get_all_registered(), 'name' );
			return wp_json_encode( array(
				'error'               => 'Pattern not found: "' . $pattern_name . '". Check the name — it must match exactly as returned by sb_query_patterns.',
				'available_patterns'  => $available,
			) );
		}

		$pattern = $registry->get_registered( $pattern_name );
		$html    = $this->resolve_pattern_content( $pattern, $registry );

		if ( empty( $html ) ) {
			return wp_json_encode( array( 'error' => 'Pattern "' . $pattern_name . '" has no resolvable content. Choose a different pattern.' ) );
		}

		// Parse into WP block data structures.
		$blocks = parse_blocks( $html );

		// Build the expected schema so we can report coverage.
		$schema_idx    = 0;
		$expected_schema = $this->extract_schema( $blocks, $schema_idx );
		$expected_count  = count( $expected_schema );

		// Reject empty slots — return the schema so the LLM can retry with real content.
		if ( empty( $slots ) && $expected_count > 0 ) {
			return wp_json_encode( array(
				'error'          => 'slots is required and was not provided. Pattern "' . $pattern_name . '" has ' . $expected_count . ' fillable slots. Call sb_append_pattern again with a slots array that covers all indices listed in content_schema below.',
				'content_schema' => $expected_schema,
			) );
		}

		// Build a slot map keyed by index for fast lookup.
		$slot_map = array();
		foreach ( $slots as $slot ) {
			$slot_map[ (int) ( $slot['index'] ?? -1 ) ] = $slot;
		}

		// Detect missing or malformed slots before injecting.
		// Check both index presence AND that the required key for the slot type is non-empty.
		$missing_slots = array();
		foreach ( $expected_schema as $entry ) {
			$sidx  = $entry['index'];
			$stype = $entry['type'];
			$slot  = $slot_map[ $sidx ] ?? null;

			$filled = false;
			if ( $slot ) {
				switch ( $stype ) {
					case 'heading':
					case 'paragraph':
						$filled = ! empty( $slot['content'] );
						break;
					case 'html':
						// Accept either 'html' or 'content' — LLMs routinely use 'content' for html slots.
						$filled = ! empty( $slot['html'] ) || ! empty( $slot['content'] );
						break;
					case 'buttons':
						$filled = ! empty( $slot['buttons'] );
						break;
					case 'image':
						$filled = ! empty( $slot['keyword'] );
						break;
					default:
						$filled = true;
				}
			}

			if ( ! $filled ) {
				$missing_slots[] = array(
					'index' => $sidx,
					'type'  => $stype,
					'hint'  => $entry['hint'] ?? '',
				);
			}
		}

		// Inject content into blocks, tracking running index.
		$idx    = 0;
		$blocks = $this->inject_content( $blocks, $slot_map, $idx );

		// Serialize back to block HTML.
		$new_content = serialize_blocks( $blocks );

		// Append to existing post_content.
		$existing = get_post_field( 'post_content', $post_id );
		$updated  = trim( $existing ) . "\n\n" . trim( $new_content );

		$update = wp_update_post( array(
			'ID'           => $post_id,
			'post_content' => $updated,
		) );

		if ( is_wp_error( $update ) ) {
			return wp_json_encode( array( 'error' => 'Failed to write pattern "' . $pattern_name . '" to post ' . $post_id . ': ' . $update->get_error_message() ) );
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				'GrayFox SB harness: appended pattern "%s" to post %d (%d/%d slots filled)',
				$pattern_name,
				$post_id,
				$expected_count - count( $missing_slots ),
				$expected_count
			) );
		}

		$current_content = get_post_field( 'post_content', $post_id );

		$response = array(
			'success'        => true,
			'pattern_name'   => $pattern_name,
			'post_id'        => $post_id,
			'slots_expected' => $expected_count,
			'slots_filled'   => $expected_count - count( $missing_slots ),
			'post_content'   => $current_content,
		);

		if ( ! empty( $missing_slots ) ) {
			$response['warning']       = 'Some slots were not filled — placeholder text remains in those positions. Review post_content above to decide if you need to call sb_append_pattern again or if the result is acceptable.';
			$response['missing_slots'] = $missing_slots;
		}

		return wp_json_encode( $response );
	}

	/**
	 * Recursively walk parsed blocks, injecting slot content by index.
	 * Mirrors the traversal order of GrayFox_SB_Tool_QueryPatterns::extract_schema()
	 * exactly so indices align.
	 *
	 * @param array $blocks   Parsed blocks.
	 * @param array $slot_map Content map keyed by slot index.
	 * @param int   $idx      Running index, passed by reference.
	 * @return array Modified blocks.
	 */
	private function inject_content( array $blocks, array $slot_map, int &$idx ): array {
		foreach ( $blocks as &$block ) {
			$name = $block['blockName'] ?? '';

			// Structural containers — recurse without consuming an index.
			if ( in_array( $name, array( 'core/group', 'core/columns', 'core/column', 'core/cover', 'core/media-text' ), true ) ) {
				$block['innerBlocks'] = $this->inject_content(
					$block['innerBlocks'] ?? array(),
					$slot_map,
					$idx
				);
				continue;
			}

			switch ( $name ) {
				case 'core/heading':
					$slot = $slot_map[ $idx ] ?? null;
					if ( $slot && ! empty( $slot['content'] ) ) {
						$block = $this->replace_text_in_block( $block, wp_kses_post( $slot['content'] ) );
					} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( sprintf( 'GrayFox SB inject_content: heading slot %d skipped — %s', $idx, $slot ? 'content key missing or empty' : 'no slot provided' ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					}
					$idx++;
					break;

				case 'core/paragraph':
					// Normalize innerHTML — same fallback as extract_schema to guarantee index alignment.
					$inner_html = $block['innerHTML'] ?? '';
					if ( '' === $inner_html ) {
						$inner_html = implode( '', array_filter( $block['innerContent'] ?? array(), 'is_string' ) );
					}
					$current = trim( wp_strip_all_tags( $inner_html ) );
					// Only consume an index for non-empty paragraphs (matches extract_schema).
					if ( ! empty( $current ) ) {
						$slot = $slot_map[ $idx ] ?? null;
						if ( $slot && ! empty( $slot['content'] ) ) {
							$block = $this->replace_text_in_block( $block, wp_kses_post( $slot['content'] ) );
						} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( sprintf( 'GrayFox SB inject_content: paragraph slot %d skipped — %s', $idx, $slot ? 'content key missing or empty' : 'no slot provided' ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
						}
						$idx++;
					}
					break;

				case 'core/buttons':
					$slot = $slot_map[ $idx ] ?? null;
					if ( $slot && ! empty( $slot['buttons'] ) ) {
						$btn_map = array();
						foreach ( $slot['buttons'] as $b ) {
							$btn_map[ (int) ( $b['index'] ?? 0 ) ] = sanitize_text_field( $b['text'] ?? '' );
						}
						foreach ( $block['innerBlocks'] as $bi => &$btn_block ) {
							if ( ( $btn_block['blockName'] ?? '' ) === 'core/button' && isset( $btn_map[ $bi ] ) ) {
								$btn_block = $this->replace_text_in_block( $btn_block, $btn_map[ $bi ] );
							}
						}
						unset( $btn_block );
					}
					$idx++;
					break;

				case 'core/image':
					// Image blocks are filled by the post-harness image pass.
					// Store the keyword as a data attribute so fill_page_images() can find it.
					$slot = $slot_map[ $idx ] ?? null;
					if ( $slot && ! empty( $slot['keyword'] ) ) {
						$kw = sanitize_text_field( $slot['keyword'] );
						$block['attrs']['alt'] = $kw;
						// Embed keyword as alt text so the post-harness pass can query it.
						$block['innerHTML']    = preg_replace(
							'/alt="[^"]*"/',
							'alt="' . esc_attr( $kw ) . '"',
							$block['innerHTML'] ?? ''
						);
					}
					$idx++;
					break;

				case 'core/html':
					$slot     = $slot_map[ $idx ] ?? null;
					// Accept 'html' or 'content' — LLMs frequently use 'content' for html-type slots.
					$html_val = $slot['html'] ?? $slot['content'] ?? '';
					if ( $slot && ! empty( $html_val ) ) {
						// core/html is WordPress's raw-HTML block. Patterns legitimately
						// embed <style> tags for scoped component CSS, but wp_kses_post
						// strips them. Extend the allowlist so those styles are preserved.
						$allowed               = wp_kses_allowed_html( 'post' );
						$allowed['style']      = array();
						$new_html              = wp_kses( $html_val, $allowed );
						$block['innerHTML']    = $new_html;
						$block['innerContent'] = array( $new_html );
					} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( sprintf( 'GrayFox SB inject_content: html slot %d skipped — %s', $idx, $slot ? 'html/content key missing or empty' : 'no slot provided' ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					}
					$idx++;
					break;
			}
		}
		unset( $block );

		return $blocks;
	}

	/**
	 * Replace the visible text inside a leaf block's innerHTML while preserving
	 * all HTML attributes, classes, and inline styles.
	 *
	 * Handles: core/heading (h1-h6), core/paragraph (p), core/button (a).
	 *
	 * @param array  $block Parsed block.
	 * @param string $text  New content (may contain basic HTML from wp_kses_post).
	 * @return array Modified block.
	 */
	private function replace_text_in_block( array $block, string $text ): array {
		$html = $block['innerHTML'] ?? '';
		$name = $block['blockName'] ?? '';

		switch ( $name ) {
			case 'core/heading':
				$new_html = preg_replace(
					'/(<h[1-6][^>]*>).*?(<\/h[1-6]>)/s',
					'$1' . $text . '$2',
					$html,
					1
				);
				break;

			case 'core/paragraph':
				$new_html = preg_replace(
					'/(<p[^>]*>).*?(<\/p>)/s',
					'$1' . $text . '$2',
					$html,
					1
				);
				break;

			case 'core/button':
				// Button wraps an <a> inside a <div>.
				$new_html = preg_replace(
					'/(<a[^>]*>).*?(<\/a>)/s',
					'$1' . $text . '$2',
					$html,
					1
				);
				break;

			default:
				return $block;
		}

		if ( $new_html && $new_html !== $html ) {
			$block['innerHTML'] = $new_html;
			// Only overwrite innerContent when the block is a pure leaf (single string item, no
			// null inner-block placeholders). Overwriting mixed arrays would destroy inner block markers.
			$content = $block['innerContent'] ?? array();
			if ( count( $content ) === 1 && is_string( $content[0] ) ) {
				$block['innerContent'] = array( $new_html );
			} else {
				// Replace the matching string item in place, leaving null placeholders intact.
				foreach ( $content as $ci => $item ) {
					if ( is_string( $item ) && str_contains( $item, $html ) ) {
						$content[ $ci ] = str_replace( $html, $new_html, $item );
						break;
					}
				}
				$block['innerContent'] = $content;
			}
		}

		return $block;
	}

	/**
	 * Public helper — extract a flat content schema from serialized post_content.
	 * Used by the site builder's revision handlers to read current page copy.
	 *
	 * @param string $post_content Serialized block HTML from get_post_field().
	 * @return array Flat indexed schema (same format as content_schema in sb_query_patterns).
	 */
	public function get_content_schema( string $post_content ): array {
		$blocks = parse_blocks( $post_content );
		$idx    = 0;
		return $this->extract_schema( $blocks, $idx );
	}

	/**
	 * Public helper — apply a slot map to serialized post_content and return new HTML.
	 * Used by revise_copy to write LLM changes back into existing pattern blocks.
	 *
	 * @param string $post_content Serialized block HTML.
	 * @param array  $slot_map     Map of index => slot data (same format as sb_append_pattern slots).
	 * @return string Updated serialized block HTML.
	 */
	public function apply_slots( string $post_content, array $slot_map ): string {
		$blocks = parse_blocks( $post_content );
		$idx    = 0;
		$blocks = $this->inject_content( $blocks, $slot_map, $idx );
		return serialize_blocks( $blocks );
	}
}

/**
 * Tool: sb_create_page
 *
 * Creates a WordPress draft page and assigns the chosen FSE template to it.
 * Returns the new post_id — the LLM must pass this to subsequent tool calls.
 */
class GrayFox_SB_Tool_CreatePage extends GrayFox_Tool {

	public function get_name(): string {
		return 'sb_create_page';
	}

	public function get_tier(): string {
		return 'all';
	}

	public function get_definition(): array {
		return array(
			'type'     => 'function',
			'function' => array(
				'name'        => 'sb_create_page',
				'description' => 'Creates a WordPress draft page. '
					. 'Returns a post_id you must pass to sb_append_pattern and sb_page_complete. '
					. 'Call this once per page, after sb_query_templates and sb_query_patterns.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'title'     => array(
							'type'        => 'string',
							'description' => 'The page title.',
						),
						'parent_id' => array(
							'type'        => 'integer',
							'description' => 'WordPress parent page ID. Use 0 for a top-level page.',
						),
					),
					'required'             => array( 'title' ),
					'additionalProperties' => false,
				),
			),
		);
	}

	/**
	 * Extra post meta injected by the harness (content_brief, page_type).
	 *
	 * @var array
	 */
	public array $extra_meta = array();

	/**
	 * Default parent ID injected by the harness when the page has a parent.
	 * The LLM may override this by passing parent_id in its tool call args.
	 *
	 * @var int
	 */
	public int $default_parent_id = 0;

	public function execute( array $args ): string {
		$title = sanitize_text_field( $args['title'] ?? 'Untitled' );

		// Prefer explicit LLM-provided parent_id, fall back to harness default.
		$parent_id = isset( $args['parent_id'] )
			? absint( $args['parent_id'] )
			: $this->default_parent_id;

		// Slug collision check — use a time-based suffix to avoid deterministic collisions.
		$slug = sanitize_title( $title );
		if ( get_page_by_path( $slug ) ) {
			$slug .= '-' . substr( (string) time(), -6 );
		}

		$post_id = wp_insert_post( array(
			'post_title'  => wp_strip_all_tags( $title ),
			'post_status' => 'draft',
			'post_type'   => 'page',
			'post_name'   => $slug,
			'post_parent' => $parent_id,
		) );

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return wp_json_encode( array( 'error' => 'Failed to create page.' ) );
		}

		add_post_meta( $post_id, '_grayfox_generated', '1', true );
		if ( ! empty( $this->extra_meta['_wp_page_template'] ) ) {
			$template_slug = sanitize_text_field( $this->extra_meta['_wp_page_template'] );
			update_post_meta( $post_id, '_wp_page_template', $template_slug );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$available_templates = get_block_templates( array( 'theme' => get_stylesheet(), 'post_type' => 'page' ) );
				$available_slugs     = array_map( static fn( $t ) => $t->slug, $available_templates );
				if ( ! in_array( $template_slug, $available_slugs, true ) ) {
					error_log( sprintf( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
						'GrayFox SB sb_create_page: assigned template "%s" to post %d but it is NOT registered in theme "%s". Available templates: %s',
						$template_slug,
						$post_id,
						get_stylesheet(),
						implode( ', ', $available_slugs ) ?: 'none'
					) );
				}
			}
		}
		if ( ! empty( $this->extra_meta['content_brief'] ) ) {
			update_post_meta( $post_id, '_grayfox_content_brief', sanitize_text_field( $this->extra_meta['content_brief'] ) );
		}
		if ( ! empty( $this->extra_meta['page_type'] ) ) {
			update_post_meta( $post_id, '_grayfox_page_type', sanitize_key( $this->extra_meta['page_type'] ) );
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'GrayFox SB harness: created page "%s" (ID %d, slug "%s")', $title, $post_id, $slug ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		return wp_json_encode( array(
			'post_id' => $post_id,
		) );
	}
}

/**
 * Tool: sb_remove_last_pattern
 *
 * Removes the last appended pattern from the page's post_content by dropping
 * the final top-level block group. Gives the LLM a way to undo a bad append —
 * e.g. when post_content shows 0 slots filled, wrong content, or a pattern
 * that doesn't suit the page. After removal the LLM can retry sb_append_pattern
 * with corrected slots or choose a different pattern entirely.
 */
class GrayFox_SB_Tool_RemoveLastPattern extends GrayFox_Tool {

	public function get_name(): string {
		return 'sb_remove_last_pattern';
	}

	public function get_tier(): string {
		return 'all';
	}

	public function get_definition(): array {
		return array(
			'type'     => 'function',
			'function' => array(
				'name'        => 'sb_remove_last_pattern',
				'description' => 'Removes the last pattern appended to the page. '
					. 'Use this when post_content returned by sb_append_pattern shows 0 slots filled, '
					. 'placeholder text remaining, or content that does not match the page goals. '
					. 'After removal you can call sb_append_pattern again with corrected slots, '
					. 'or choose a different pattern. Returns the post_content after removal so you can verify.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array(
							'type'        => 'integer',
							'description' => 'The post_id of the page being built.',
						),
						'reason' => array(
							'type'        => 'string',
							'description' => 'Brief explanation of why you are removing the pattern (e.g. "slots_filled was 0", "wrong content injected").',
						),
					),
					'required'             => array( 'post_id', 'reason' ),
					'additionalProperties' => false,
				),
			),
		);
	}

	public function execute( array $args ): string {
		$post_id = absint( $args['post_id'] ?? 0 );
		$reason  = sanitize_text_field( $args['reason'] ?? '' );

		if ( ! $post_id ) {
			return wp_json_encode( array( 'error' => 'post_id is required.' ) );
		}

		$content = get_post_field( 'post_content', $post_id );

		if ( empty( trim( $content ) ) ) {
			return wp_json_encode( array(
				'error'        => 'No content to remove — post_content is already empty.',
				'post_content' => '',
			) );
		}

		// Parse all top-level blocks, drop the last one, re-serialize.
		$blocks = parse_blocks( $content );

		// Filter out null/empty blocks that parse_blocks inserts between real blocks.
		$real_blocks = array_values( array_filter( $blocks, static function ( array $b ): bool {
			return ! empty( $b['blockName'] );
		} ) );

		if ( empty( $real_blocks ) ) {
			return wp_json_encode( array(
				'error'        => 'No removable block found in post_content.',
				'post_content' => $content,
			) );
		}

		// Remove the last real block.
		array_pop( $real_blocks );

		$new_content = serialize_blocks( $real_blocks );

		$update = wp_update_post( array(
			'ID'           => $post_id,
			'post_content' => $new_content,
		) );

		if ( is_wp_error( $update ) ) {
			return wp_json_encode( array( 'error' => 'Failed to update post: ' . $update->get_error_message() ) );
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'GrayFox SB harness: removed last pattern from post %d. Reason: %s', $post_id, $reason ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		return wp_json_encode( array(
			'success'      => true,
			'removed'      => true,
			'reason'       => $reason,
			'post_content' => get_post_field( 'post_content', $post_id ),
		) );
	}
}

/**
 * Tool: sb_clear_page
 *
 * Clears the post_content of an existing page so the rearrange harness can
 * append fresh patterns. Used instead of sb_create_page in rearrange mode —
 * the post already exists and the approved copy has been extracted before the
 * harness starts.
 */
class GrayFox_SB_Tool_ClearPage extends GrayFox_Tool {

	public function get_name(): string {
		return 'sb_clear_page';
	}

	public function get_tier(): string {
		return 'all';
	}

	public function get_definition(): array {
		return array(
			'type'     => 'function',
			'function' => array(
				'name'        => 'sb_clear_page',
				'description' => 'Clears the post_content of an existing page so you can append new patterns. '
					. 'Use this in rearrange mode — the post_id is already known, do not call sb_create_page.',
				'parameters'  => array(
					'type'                 => 'object',
					'properties'           => array(
						'post_id' => array(
							'type'        => 'integer',
							'description' => 'ID of the page to clear.',
						),
					),
					'required'             => array( 'post_id' ),
					'additionalProperties' => false,
				),
			),
		);
	}

	public function execute( array $args ): string {
		$post_id = absint( $args['post_id'] ?? 0 );
		if ( ! $post_id ) {
			return wp_json_encode( array( 'error' => 'post_id required.' ) );
		}

		$result = wp_update_post( array( 'ID' => $post_id, 'post_content' => '' ) );
		if ( is_wp_error( $result ) ) {
			return wp_json_encode( array( 'error' => 'Failed to clear post: ' . $result->get_error_message() ) );
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'GrayFox SB rearrange: cleared post_content for post %d', $post_id ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		return wp_json_encode( array( 'post_id' => $post_id ) );
	}
}


/**
 * Tool: sb_page_complete
 *
 * Terminal tool. Signals the harness that the LLM has finished building this
 * page. Stores the full decision log as post meta for debugging and revisions.
 * The harness detects "done: true" in the response and exits the loop.
 */
class GrayFox_SB_Tool_PageComplete extends GrayFox_Tool {

	public function get_name(): string {
		return 'sb_page_complete';
	}

	public function get_tier(): string {
		return 'all';
	}

	public function get_definition(): array {
		return array(
			'type'     => 'function',
			'function' => array(
				'name'        => 'sb_page_complete',
				'description' => 'Signals that you have finished building this page. '
					. 'Call this after all sb_append_pattern calls are done. '
					. 'Include a summary of what you built and why each choice was made.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'         => array(
							'type'        => 'integer',
							'description' => 'The post_id returned by sb_create_page.',
						),
						'summary'         => array(
							'type'        => 'string',
							'description' => 'One to two sentences summarising what was built and why the template and patterns were chosen.',
						),
						'template_chosen' => array(
							'type'        => 'string',
							'description' => 'The template slug selected for this page.',
						),
						'patterns_used'   => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => 'List of pattern names appended, in order.',
						),
					),
					'required'             => array( 'post_id', 'summary' ),
					'additionalProperties' => false,
				),
			),
		);
	}

	public function execute( array $args ): string {
		$post_id         = absint( $args['post_id']                   ?? 0 );
		$summary         = sanitize_text_field( $args['summary']       ?? '' );
		$template_chosen = sanitize_key( $args['template_chosen']      ?? '' );
		$patterns_used   = is_array( $args['patterns_used'] )
			? array_map( 'sanitize_text_field', $args['patterns_used'] )
			: array();

		if ( $post_id ) {
			update_post_meta( $post_id, '_grayfox_generation_log', wp_json_encode( array(
				'summary'         => $summary,
				'template_chosen' => $template_chosen,
				'patterns_used'   => $patterns_used,
				'completed_at'    => current_time( 'mysql' ),
			) ) );
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'GrayFox SB harness: page %d complete. Template: %s. Patterns: %s', $post_id, $template_chosen, implode( ', ', $patterns_used ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		return wp_json_encode( array( 'done' => true ) );
	}
}
