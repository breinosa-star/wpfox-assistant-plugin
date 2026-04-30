<?php
/**
 * Pattern builder — manifest-driven WordPress block pattern generator.
 *
 * Pattern group files (patterns/class-tb-patterns-*.php) register their
 * renderers via GrayFox_TB_PatternBuilder::register_renderers() at load time.
 * This class orchestrates: iterates manifest.patterns, dispatches to the
 * correct renderer, and returns the full filename => content map.
 *
 * Ported from wp-theme-builder/src/pattern_builder.py
 *
 * @package GrayFox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GrayFox_TB_PatternBuilder
 */
class GrayFox_TB_PatternBuilder {

	/**
	 * Dispatch table: layout_type => callable.
	 * Populated by pattern group files via register_renderers().
	 *
	 * @var array<string,callable>
	 */
	private static array $renderers = [];

	/**
	 * Register one or more layout renderers.
	 *
	 * Called at require_once time by each pattern group file.
	 *
	 * @param array<string,callable> $map layout_slug => callable.
	 */
	public static function register_renderers( array $map ): void {
		self::$renderers = array_merge( self::$renderers, $map );
	}

	/**
	 * Return all registered layout slugs (for validation and error messages).
	 *
	 * @return string[]
	 */
	public static function get_registered_layouts(): array {
		return array_keys( self::$renderers );
	}

	/**
	 * Default CSS classes per layout, applied when patterns are pre-registered
	 * without manifest-level css_classes.  Tinted layouts receive gf-section-tint.
	 *
	 * @var array<string, string[]>
	 */
	private static array $default_css_classes = [
		'three-column-cards'    => [ 'gf-section-tint' ],
		'six-icon-grid'         => [ 'gf-section-tint' ],
		'image-checklist-split' => [ 'gf-section-tint' ],
		'numbered-steps'        => [ 'gf-section-tint' ],
		'testimonials-grid'     => [ 'gf-section-tint' ],
		'review-stars-row'      => [ 'gf-section-tint' ],
		'three-tier-pricing'    => [ 'gf-section-tint' ],
		'two-tier-pricing'      => [ 'gf-section-tint' ],
		'comparison-table'      => [ 'gf-section-tint' ],
		'feature-matrix'        => [ 'gf-section-tint' ],
		'pricing-toggle'        => [ 'gf-section-tint' ],
		'usage-based-pricing'   => [ 'gf-section-tint' ],
		'accordion-faq'         => [ 'gf-section-tint' ],
		'team-grid'             => [ 'gf-section-tint' ],
		'team-list'             => [ 'gf-section-tint' ],
		'advisor-grid'          => [ 'gf-section-tint' ],
		'vertical-timeline'     => [ 'gf-section-tint' ],
		'mission-values'        => [ 'gf-section-tint' ],
		'case-study-grid'       => [ 'gf-section-tint' ],
		'event-list-cards'      => [ 'gf-section-tint' ],
		'speaker-grid'          => [ 'gf-section-tint' ],
		'product-grid'          => [ 'gf-section-tint' ],
		'job-list-cards'        => [ 'gf-section-tint' ],
		'job-board'             => [ 'gf-section-tint' ],
		'metrics-grid'          => [ 'gf-section-tint' ],
		'chart-embed-section'   => [ 'gf-section-tint' ],
		'membership-plans'      => [ 'gf-section-tint' ],
		'course-catalog-grid'   => [ 'gf-section-tint' ],
		'donor-wall'            => [ 'gf-section-tint' ],
		'campaign-progress'     => [ 'gf-section-tint' ],
		'masonry-gallery'       => [ 'gf-section-tint' ],
		'video-grid'            => [ 'gf-section-tint' ],
		'album-index'           => [ 'gf-section-tint' ],
	];

	/**
	 * Default placeholder dimensions (width × height) keyed by GrayFox image CSS class.
	 * Used by replace_placeholder_images() to pick an appropriately-sized picsum URL.
	 *
	 * @var array<string, array{0: int, 1: int}>
	 */
	private static array $image_class_dimensions = [
		'gf-hero-image'           => [ 1200, 800  ],
		'gf-team-photo'           => [ 120,  120  ],
		'gf-speaker-photo'        => [ 100,  100  ],
		'gf-testimonial-avatar'   => [ 56,   56   ],
		'gf-portfolio-img'        => [ 800,  600  ],
		'gf-masonry-img'          => [ 800,  600  ],
		'gf-slide-img'            => [ 1200, 800  ],
		'gf-lightbox-thumb-img'   => [ 400,  300  ],
		'gf-lightbox-img'         => [ 1200, 800  ],
		'gf-album-img'            => [ 600,  400  ],
		'gf-video-thumb-img'      => [ 640,  360  ],
		'gf-video-featured-thumb' => [ 1280, 720  ],
		'gf-channel-banner-img'   => [ 1280, 400  ],
		'gf-channel-avatar'       => [ 120,  120  ],
		'gf-logo-item'            => [ 240,  80   ],
		'gf-logo-grid-item'       => [ 240,  80   ],
		'gf-founder-photo'        => [ 600,  800  ],
		'gf-ba-before'            => [ 800,  600  ],
		'gf-ba-after'             => [ 800,  600  ],
		'gf-product-img'          => [ 600,  200  ],
		'gf-collection-img'       => [ 800,  420  ],
		'gf-collection-card-img'  => [ 800,  220  ],
		'gf-mini-product-img'     => [ 300,  120  ],
		'gf-social-post-img'      => [ 800,  180  ],
		'gf-user-avatar'          => [ 36,   36   ],
		'gf-featured-post-img'    => [ 800,  450  ],
		'gf-post-thumbnail'       => [ 80,   80   ],
		'gf-screenshot-img'       => [ 800,  500  ],
		'gf-feature-img'          => [ 800,  600  ],
		'gf-split-img'            => [ 800,  600  ],
		'gf-partner-logo'         => [ 120,  80   ],
		'gf-security-img'         => [ 800,  600  ],
		'gf-gallery-img'          => [ 800,  600  ],
		'gf-screenshot-slide'     => [ 1200, 800  ],
	];

	/**
	 * Generate one pattern file for every registered layout renderer.
	 *
	 * Pre-registration path: all layouts are always written to the generated
	 * theme so they appear in the WP block pattern picker regardless of what
	 * the LLM chose for the homepage composition.
	 *
	 * The LLM still controls which layout slugs appear in templates (page
	 * composition / sequencing) — it no longer controls which patterns exist.
	 *
	 * @param array $manifest Full manifest array.
	 * @return array<string,string> filename => PHP pattern content.
	 */
	public static function get_all_registered_patterns( array $manifest ): array {
		$result       = [];
		$default_copy = GrayFox_TB_ManifestBuilder::default_copy_by_layout();

		foreach ( array_keys( self::$renderers ) as $layout_slug ) {
			$spec = [
				'title'       => self::title_for_layout( $layout_slug ),
				'layout'      => $layout_slug,
				'css_classes' => self::$default_css_classes[ $layout_slug ] ?? [],
				'copy'        => $default_copy[ $layout_slug ] ?? [],
			];

			$filename            = $layout_slug . '.php';
			$result[ $filename ] = self::render_pattern( $layout_slug, $spec, $manifest );
		}

		return $result;
	}

	/**
	 * Generate all patterns specified in the manifest (legacy path).
	 *
	 * @deprecated Use get_all_registered_patterns() for new themes.
	 * @param array $manifest Full manifest array.
	 * @return array<string,string> filename => PHP pattern content.
	 */
	public static function get_all_patterns( array $manifest ): array {
		$result   = [];
		$patterns = $manifest['patterns'] ?? [];

		foreach ( $patterns as $slug => $spec ) {
			$filename          = $slug . '.php';
			$result[ $filename ] = self::render_pattern( $slug, $spec, $manifest );
		}

		return $result;
	}

	// -------------------------------------------------------------------------
	// Internal rendering
	// -------------------------------------------------------------------------

	/**
	 * Render a single pattern.
	 *
	 * @param string $slug    Pattern slug (used as filename stem and WP slug).
	 * @param array  $spec    Pattern spec from manifest.patterns[slug].
	 * @param array  $manifest Full manifest.
	 * @return string PHP file content.
	 */
	private static function render_pattern( string $slug, array $spec, array $manifest ): string {
		$theme_slug = $manifest['theme']['slug'] ?? 'theme';
		$title      = $spec['title'] ?? ucwords( str_replace( '-', ' ', $slug ) );
		$layout     = $spec['layout'] ?? '';
		$category   = $theme_slug . '-sections';
		$keywords   = str_replace( '-', ' ', $slug );

		$header = self::php_header( $title, "{$theme_slug}/{$slug}", $category, $keywords );

		$renderer = self::$renderers[ $layout ] ?? null;

		if ( null === $renderer ) {
			// Validator should have caught this before generation runs.
			// If we reach here anyway, throw so the error surfaces clearly.
			$valid = implode( ', ', self::get_registered_layouts() );
			throw new \InvalidArgumentException(
				'Unknown layout type "' . esc_html( $layout ) . '" in pattern "' . esc_html( $slug ) . '". Valid layouts: ' . esc_html( $valid )
			);
		}

		$body = call_user_func( $renderer, $spec, $manifest['theme'] );
		$body = self::fix_block_comment_attrs( $body );
		$body = self::replace_placeholder_images( $body );

		return $header . $body;
	}

	/**
	 * Strip block comment attributes that would cause WordPress block validation
	 * errors because our pattern HTML doesn't include the corresponding generated
	 * CSS classes.
	 *
	 * Stripped:
	 *   layout → WP adds is-layout-* classes we don't emit. Our custom CSS
	 *            classes (gf-logo-grid, gf-hero-section, etc.) handle the same
	 *            layout concerns, so removing layout from the comment is safe
	 *            and eliminates the class mismatch.
	 *
	 * Kept intentionally:
	 *   style.spacing    → WP's PHP render_callback reads this to inject
	 *                       padding/margin inline styles into the rendered wrapper.
	 *                       Without it, sections lose all vertical spacing on the
	 *                       frontend (front-page breaks). Editor validation errors
	 *                       for spacing are acceptable — the frontend takes priority.
	 *   style.typography → Same reasoning: WP injects font-size inline styles at
	 *                       render time; stripping it produces wrong font sizes.
	 *   style.border     → border-radius IS in our HTML inline style.
	 *   style.color      → background-color IS in our HTML inline style.
	 *   All other attrs (className, align, textColor, etc.) are correct.
	 *
	 * Works line-by-line so nested JSON braces are handled correctly
	 * without complex recursive regex.
	 *
	 * @param string $content Raw pattern HTML from a renderer.
	 * @return string Pattern HTML with corrected block comments.
	 */
	private static function fix_block_comment_attrs( string $content ): string {
		$lines  = explode( "\n", $content );
		$result = [];

		foreach ( $lines as $line ) {
			$trimmed = ltrim( $line );

			// Only process wp:* block opener comments that carry JSON attributes.
			// Pattern: <!-- wp:block-name {JSON} --> or <!-- wp:block-name {JSON} /-->
			if ( ! preg_match( '/^<!--\s*wp:([\w\/\-]+)\s+(\{)/', $trimmed, $m ) ) {
				$result[] = $line;
				continue;
			}

			$block_name  = $m[1];
			$brace_start = strpos( $trimmed, '{' );

			// Walk the string counting braces to find the end of the JSON object.
			$depth     = 0;
			$brace_end = -1;
			$len       = strlen( $trimmed );
			for ( $i = $brace_start; $i < $len; $i++ ) {
				if ( '{' === $trimmed[ $i ] ) {
					$depth++;
				} elseif ( '}' === $trimmed[ $i ] ) {
					$depth--;
					if ( 0 === $depth ) {
						$brace_end = $i;
						break;
					}
				}
			}

			if ( $brace_end < 0 ) {
				$result[] = $line; // Malformed — leave unchanged.
				continue;
			}

			$json_str = substr( $trimmed, $brace_start, $brace_end - $brace_start + 1 );
			$attrs    = json_decode( $json_str, true );

			if ( ! is_array( $attrs ) ) {
				$result[] = $line; // Unparseable — leave unchanged.
				continue;
			}

			// Strip attrs whose values WP's save() would turn into inline styles or
			// layout classes that we never emit in the rendered HTML.
			// layout  → is-layout-constrained, is-layout-flex, etc.
			// style   → inline padding/margin/typography styles (handled by Bootstrap/CSS)
			unset( $attrs['layout'] );
			unset( $attrs['style'] );

			// ── Rebuild the comment ────────────────────────────────────────────
			$indent = str_repeat( ' ', strlen( $line ) - strlen( $trimmed ) );
			// Everything after the closing brace (e.g. " /-->" or " -->").
			$suffix = rtrim( substr( $trimmed, $brace_end + 1 ) );

			if ( empty( $attrs ) ) {
				$result[] = $indent . "<!-- wp:{$block_name}{$suffix}";
			} else {
				$encoded  = json_encode( $attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
				$result[] = $indent . "<!-- wp:{$block_name} {$encoded}{$suffix}";
			}
		}

		return implode( "\n", $result );
	}

	/**
	 * Replace empty or missing src attributes on <img> tags with deterministic
	 * placeholder images from picsum.photos.  Called after fix_block_comment_attrs()
	 * so block comment JSON is already normalised before dimension extraction.
	 *
	 * Dimension priority per image:
	 *   1. Block comment JSON {"width":N,"height":N} on the preceding wp:image line
	 *   2. CSS class map ($image_class_dimensions) on <img> or parent <figure>
	 *   3. Global fallback: 800 × 600
	 *
	 * @param string $html Raw pattern HTML.
	 * @return string Pattern HTML with placeholder src attributes injected.
	 */
	private static function replace_placeholder_images( string $html ): string {
		// Avoid mutating $html while iterating offsets — collect replacements first.
		$replacements = [];

		preg_match_all( '/<img\b([^>]*)\/?>/', $html, $matches, PREG_OFFSET_CAPTURE );

		foreach ( $matches[0] as $idx => $match ) {
			[ $tag, $offset ] = $match;
			$attrs            = $matches[1][ $idx ][0];

			// Skip tags that already carry a real src URL.
			if ( preg_match( '/\bsrc\s*=\s*"([^"]+)"/', $attrs, $src_m ) ) {
				continue;
			}

			preg_match( '/\balt\s*=\s*"([^"]*)"/', $attrs, $alt_m );
			$alt = $alt_m[1] ?? '';

			$context_before = substr( $html, max( 0, $offset - 400 ), 400 );

			[ 'width' => $w, 'height' => $h ] = self::resolve_image_dimensions( $attrs, $context_before );

			$url = self::build_picsum_url( $alt, $w, $h );

			if ( str_contains( $attrs, 'src=""' ) ) {
				$new_tag = str_replace( 'src=""', 'src="' . $url . '"', $tag );
			} else {
				// Insert src as first attribute after <img.
				$new_tag = '<img src="' . $url . '"' . $attrs . ( str_ends_with( trim( $tag ), '/>' ) ? '/>' : '>' );
			}

			$replacements[] = [ $offset, strlen( $tag ), $new_tag ];
		}

		// Apply replacements from back to front so offsets stay valid.
		foreach ( array_reverse( $replacements ) as [ $offset, $len, $new_tag ] ) {
			$html = substr_replace( $html, $new_tag, $offset, $len );
		}

		return $html;
	}

	/**
	 * Resolve placeholder image dimensions for a single <img> tag.
	 *
	 * @param string $img_attrs     Full attribute string of the <img> tag.
	 * @param string $context_before ~400 chars of HTML immediately before the tag.
	 * @return array{width: int, height: int}
	 */
	private static function resolve_image_dimensions( string $img_attrs, string $context_before ): array {
		// Priority 1 — block comment JSON on the preceding wp:image line.
		if ( preg_match( '/<!--\s*wp:image\s+(\{[^}]+\})\s*-->/', $context_before, $cm ) ) {
			$json = json_decode( $cm[1], true );
			if ( is_array( $json ) ) {
				$jw = isset( $json['width'] )  ? intval( $json['width'] )  : 0;
				$jh = isset( $json['height'] ) ? intval( $json['height'] ) : 0;
				if ( $jw > 0 && $jh > 0 ) {
					return [ 'width' => $jw, 'height' => $jh ];
				}
			}
		}

		// Priority 2 — CSS class map: check <img class> then parent <figure class>.
		$class_sources = [];
		if ( preg_match( '/\bclass\s*=\s*"([^"]*)"/', $img_attrs, $cm ) ) {
			$class_sources[] = $cm[1];
		}
		if ( preg_match( '/<figure[^>]+class\s*=\s*"([^"]*)"[^>]*>\s*$/', $context_before, $cm ) ) {
			$class_sources[] = $cm[1];
		}
		foreach ( $class_sources as $class_string ) {
			foreach ( self::$image_class_dimensions as $cls => $dims ) {
				if ( str_contains( $class_string, $cls ) ) {
					return [ 'width' => $dims[0], 'height' => $dims[1] ];
				}
			}
		}

		// Priority 3 — global fallback.
		return [ 'width' => 800, 'height' => 600 ];
	}

	/**
	 * Build a deterministic picsum.photos URL from alt text and dimensions.
	 *
	 * @param string $alt Alt text used to derive a stable seed.
	 * @param int    $w   Image width in pixels.
	 * @param int    $h   Image height in pixels.
	 * @return string URL.
	 */
	private static function build_picsum_url( string $alt, int $w, int $h ): string {
		$seed = preg_replace( '/[^a-z0-9]+/', '-', strtolower( trim( $alt ) ) );
		$seed = trim( $seed, '-' );
		$seed = substr( $seed ?: 'placeholder', 0, 40 );
		return "https://picsum.photos/seed/{$seed}/{$w}/{$h}";
	}

	/**
	 * Generate a human-readable title from a layout slug.
	 *
	 * @param string $layout_slug e.g. "hero-gradient"
	 * @return string e.g. "Hero Gradient"
	 */
	private static function title_for_layout( string $layout_slug ): string {
		return ucwords( str_replace( '-', ' ', $layout_slug ) );
	}

	/**
	 * Build the PHP comment header for a pattern file.
	 *
	 * @param string $title      Human-readable pattern title.
	 * @param string $slug       Full pattern slug (theme-slug/pattern-slug).
	 * @param string $categories Category slug string.
	 * @param string $keywords   Keyword string.
	 * @param int    $viewport   Viewport width hint (default 1280).
	 * @return string PHP comment block.
	 */
	private static function php_header(
		string $title,
		string $slug,
		string $categories,
		string $keywords,
		int $viewport = 1280
	): string {
		return "<?php\n/**\n * Title: {$title}\n * Slug: {$slug}\n * Categories: {$categories}\n * Keywords: {$keywords}\n * Viewport Width: {$viewport}\n */\n?>\n";
	}

}
