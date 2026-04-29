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

	/** Action Scheduler hook for single-page revision jobs. */
	const AS_HOOK_REVISE = 'grayfox_revise_page';

	/** Transient key used as a generation lock. */
	const LOCK_TRANSIENT = 'grayfox_site_generation_lock';

	/** WP option that stores build progress. */
	const BUILD_OPTION = 'grayfox_site_build';

	/** WP option that stores the approved sitemap draft. */
	const SITEMAP_OPTION = 'grayfox_sitemap_draft';

	/** WP option that stores the chosen build format. */
	const FORMAT_OPTION = 'grayfox_site_build_format';

	/** Transient key for the business profile extracted during sitemap generation. */
	const BUSINESS_PROFILE_TRANSIENT = 'grayfox_site_business_profile';

	/** WP option that stores the encrypted Unsplash API key. */
	const UNSPLASH_OPTION = 'grayfox_unsplash_api_key';

	/** Post meta key that marks GrayFox-generated pages. */
	const META_GENERATED = '_grayfox_generated';

	/** Post meta key storing the last LLM-generated blocks JSON (for revisions). */
	const META_BLOCKS_JSON = '_grayfox_blocks_json';

	/** Post meta key storing keyword→attachment_id image map (for revisions). */
	const META_IMAGE_MAP = '_grayfox_image_map';

	/** Elementor widget types allowed in generated content. */
	const ELEMENTOR_WIDGET_WHITELIST = array( 'heading', 'text-editor', 'image', 'icon-list', 'button', 'testimonial' );

	/**
	 * Maximum Unsplash API calls per site-generation run.
	 *
	 * Unsplash free tier: 50 requests/hour. We cap at 20 per run so the hourly
	 * budget isn't exhausted in a single generation, leaving headroom for retries
	 * or simultaneous use. Cover blocks are routed to DALL-E regardless, so this
	 * budget is spent only on foreground images (image, media_text) where real
	 * photos noticeably improve quality.
	 */
	const UNSPLASH_PER_RUN_LIMIT = 20;

	/**
	 * Unsplash call counter for the current generation run.
	 * Reset at the start of generate_site_pages() so each run starts clean.
	 *
	 * @var int
	 */
	private int $unsplash_calls = 0;

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
	 * Full pattern catalog.
	 *
	 * Each entry defines:
	 *   label       — human-readable name shown in logs / admin
	 *   description — selection guidance shown to the LLM when choosing a pattern
	 *   sequence    — block sequence passed to the content-generation LLM as a structural guide
	 *   best_for    — page_type values this pattern suits
	 *   avoid_for   — page_type values to exclude during filtered selection
	 *   industries  — industry slugs that strongly prefer this pattern ('*' = all)
	 *   styles      — visual_style values compatible with this pattern ('*' = all)
	 *
	 * @return array<string, array>
	 */
	/**
	 * Derive image style directives from the business profile.
	 *
	 * Returns two values consumed by the image-fetching pipeline:
	 *   dalle_suffix    — appended to the keyword before sending to DALL-E so all
	 *                     generated images share a consistent visual language.
	 *   unsplash_color  — passed as the Unsplash `color` filter parameter to bias
	 *                     results toward the same palette (empty = no filter).
	 *
	 * Both signals are derived from `visual_style` + `industry` in the business
	 * profile extracted during sitemap generation. No extra LLM call needed.
	 *
	 * @param array $profile Business profile from BUSINESS_PROFILE_TRANSIENT.
	 * @return array{ dalle_suffix: string, unsplash_color: string }
	 */
	public static function get_image_style_directive( array $profile ): array {
		// If a brand profile exists, let it override the visual_style and derive unsplash_color.
		$brand_profile = get_option( 'grayfox_brand_profile', array() );
		if ( ! empty( $brand_profile['visual_style'] ) ) {
			$profile['visual_style'] = $brand_profile['visual_style'];
		}

		$visual_style = sanitize_key( $profile['visual_style'] ?? 'clean' );
		$industry     = sanitize_key( $profile['industry']     ?? 'other' );

		// Photography style per visual_style — shared suffix appended to every DALL-E prompt.
		$style_directives = array(
			'clean'     => 'professional commercial photography, neutral tones, soft diffused lighting, clean uncluttered background',
			'bold'      => 'bold dynamic composition, high contrast, vivid colors, dramatic lighting, professional photography',
			'editorial' => 'editorial photography, natural available light, authentic candid atmosphere, journalistic style',
			'minimal'   => 'minimalist composition, muted desaturated palette, abundant negative space, simple clean background',
			'technical' => 'technical precision photography, cool blue-grey tones, sharp focus, studio lighting',
		);

		// Industry context — tells DALL-E what kind of subject treatment is appropriate.
		$industry_modifiers = array(
			'technology'            => 'workspace technology context, modern equipment',
			'professional_services' => 'professional office environment, confident people, trust signals',
			'healthcare'            => 'calm clinical setting, warmth and care, reassuring',
			'nonprofit'             => 'authentic human impact, community, real people',
			'creative'              => 'creative workspace, artistic process, expressive',
			'hospitality'           => 'inviting atmosphere, sensory details, warmth',
			'education'             => 'learning environment, engaged focus, knowledge',
			'legal'                 => 'professional formal setting, precision, authority',
			'finance'               => 'professional precision, growth, stability',
		);

		$style_part    = $style_directives[ $visual_style ] ?? $style_directives['clean'];
		$industry_part = $industry_modifiers[ $industry ]   ?? '';

		$dalle_suffix = $style_part
			. ( $industry_part ? ', ' . $industry_part : '' )
			. ', no text, no logos, no watermarks';

		// Unsplash color filter — biases image results toward the same palette.
		// Only set when visual_style strongly implies a dominant color.
		// Empty string = no filter applied (Unsplash returns best match).
		$color_hints = array(
			'minimal'   => 'white',
			'technical' => 'blue',
			'clean'     => '',
			'bold'      => '',
			'editorial' => '',
		);
		$unsplash_color = $color_hints[ $visual_style ] ?? '';

		return array(
			'dalle_suffix'   => $dalle_suffix,
			'unsplash_color' => $unsplash_color,
		);
	}

	public static function get_pattern_catalog(): array {
		return array(
			// ── Home / Landing ────────────────────────────────────────────────
			'hero-features' => array(
				'label'       => 'Hero + Features',
				'description' => 'Bold full-width cover with tagline → intro paragraph → three-column feature grid → separator → CTA buttons. Classic marketing structure, works for most product or service home pages.',
				'sequence'    => 'cover → paragraph → separator → columns(3) → separator → list → buttons',
				'best_for'    => array( 'home', 'landing', 'product_overview' ),
				'avoid_for'   => array( 'contact', 'pricing', 'technical', 'reference' ),
				'industries'  => array( '*' ),
				'styles'      => array( 'clean', 'bold', 'minimal' ),
			),
			'hero-narrative' => array(
				'label'       => 'Hero + Narrative',
				'description' => 'Cover with tagline, then alternating image/text pairs telling a story, closing with a quote and CTA. More engaging than a feature grid — good when the business has a strong story or differentiator.',
				'sequence'    => 'cover → paragraph → separator → media_text(left) → media_text(right) → quote → buttons',
				'best_for'    => array( 'home', 'services_overview', 'landing' ),
				'avoid_for'   => array( 'contact', 'technical', 'reference', 'pricing' ),
				'industries'  => array( '*' ),
				'styles'      => array( 'editorial', 'bold', 'clean' ),
			),
			'conversion-focused' => array(
				'label'       => 'Conversion',
				'description' => 'Every element drives toward one action: cover → single-benefit paragraph → three reasons columns → social proof quote → prominent CTA. Minimal distraction, maximum intent.',
				'sequence'    => 'cover → paragraph → separator → columns(3) → separator → quote → separator → buttons',
				'best_for'    => array( 'landing', 'product_overview', 'pricing' ),
				'avoid_for'   => array( 'about', 'contact', 'technical', 'team' ),
				'industries'  => array( 'technology', 'saas', 'ecommerce' ),
				'styles'      => array( 'bold', 'clean' ),
			),

			// ── About / Team / Story ──────────────────────────────────────────
			'founder-story' => array(
				'label'       => 'Founder / Team Story',
				'description' => 'Opens with a personal or team photo in media_text (image right), narrative paragraph, values quote, columns for team roles or differentiators, closing paragraph with location or contact. Human and relationship-forward.',
				'sequence'    => 'media_text(right) → paragraph → quote → separator → columns(3) → paragraph',
				'best_for'    => array( 'about', 'team' ),
				'avoid_for'   => array( 'pricing', 'technical', 'contact', 'home', 'reference' ),
				'industries'  => array( '*' ),
				'styles'      => array( 'clean', 'editorial', 'minimal' ),
			),
			'mission-driven' => array(
				'label'       => 'Mission-Driven',
				'description' => 'Cover with mission statement, purpose paragraph, three-column values, impact story in media_text, credibility list. Best for organizations where the why matters as much as the what.',
				'sequence'    => 'cover → paragraph → separator → columns(3) → separator → media_text(left) → list',
				'best_for'    => array( 'about', 'home', 'services_overview' ),
				'avoid_for'   => array( 'pricing', 'technical', 'contact', 'reference' ),
				'industries'  => array( 'nonprofit', 'education', 'healthcare' ),
				'styles'      => array( 'bold', 'clean', 'editorial' ),
			),
			'credentials-forward' => array(
				'label'       => 'Credentials Forward',
				'description' => 'No hero image — opens with a strong heading and credibility paragraph, ordered milestone or achievement list, expertise columns, closing quote. Authoritative tone, text-heavy. Good for professional services where trust comes from track record.',
				'sequence'    => 'heading → paragraph → separator → list(ordered) → separator → columns(3) → quote',
				'best_for'    => array( 'about', 'team', 'services_overview' ),
				'avoid_for'   => array( 'home', 'contact', 'pricing', 'portfolio' ),
				'industries'  => array( 'professional_services', 'legal', 'finance', 'consulting' ),
				'styles'      => array( 'clean', 'minimal', 'technical' ),
			),

			// ── Services / Solutions ──────────────────────────────────────────
			'service-grid' => array(
				'label'       => 'Service Grid',
				'description' => 'Cover with service overview tagline, paragraph summarizing the offering, columns for each service area, separator, ordered process or how-it-works list, CTA.',
				'sequence'    => 'cover → paragraph → separator → columns(3) → separator → list(ordered) → buttons',
				'best_for'    => array( 'services_overview', 'product_overview' ),
				'avoid_for'   => array( 'contact', 'pricing', 'technical', 'reference' ),
				'industries'  => array( '*' ),
				'styles'      => array( 'clean', 'bold' ),
			),
			'problem-solution' => array(
				'label'       => 'Problem / Solution',
				'description' => 'Cover establishes context, paragraph names the challenge, then alternating media_text pairs: problem framed on the left, solution on the right. Closes with CTA. Very persuasive for B2B.',
				'sequence'    => 'cover → paragraph → separator → media_text(left) → media_text(right) → separator → media_text(left) → buttons',
				'best_for'    => array( 'services_overview', 'service_detail', 'product_overview', 'landing' ),
				'avoid_for'   => array( 'contact', 'about', 'reference', 'team' ),
				'industries'  => array( '*' ),
				'styles'      => array( 'clean', 'bold', 'editorial' ),
			),
			'process-steps' => array(
				'label'       => 'Process / How It Works',
				'description' => 'Cover with overview, paragraph, ordered numbered list showing the process or workflow, separator, outcome columns, quote, CTA. Good for service delivery, onboarding, or implementation pages.',
				'sequence'    => 'cover → paragraph → list(ordered) → separator → columns(3) → quote → buttons',
				'best_for'    => array( 'service_detail', 'product_detail', 'services_overview' ),
				'avoid_for'   => array( 'contact', 'pricing', 'about', 'reference' ),
				'industries'  => array( '*' ),
				'styles'      => array( 'clean', 'technical', 'minimal' ),
			),

			// ── Product / Feature ─────────────────────────────────────────────
			'feature-showcase' => array(
				'label'       => 'Feature Showcase',
				'description' => 'Cover with product tagline, three key capability columns, separator, alternating media_text pairs for two deep-dive features, spec or comparison table, CTA. Best when the product has multiple distinct features worth showing.',
				'sequence'    => 'cover → columns(3) → separator → media_text(left) → media_text(right) → separator → table → buttons',
				'best_for'    => array( 'product_overview', 'product_detail', 'service_detail' ),
				'avoid_for'   => array( 'contact', 'about', 'reference', 'team' ),
				'industries'  => array( 'technology', 'saas', 'software' ),
				'styles'      => array( 'clean', 'technical', 'bold' ),
			),
			'deep-feature' => array(
				'label'       => 'Deep Feature / Technical',
				'description' => 'No hero cover — opens with heading and substantive paragraph, media_text with a specific capability detail, feature or spec list, separator, comparison or data table, closing paragraph and CTA. For pages where the reader already knows what this is and wants depth.',
				'sequence'    => 'heading → paragraph → media_text(left) → list → separator → table → paragraph → buttons',
				'best_for'    => array( 'product_detail', 'service_detail', 'technical' ),
				'avoid_for'   => array( 'home', 'contact', 'about', 'landing' ),
				'industries'  => array( '*' ),
				'styles'      => array( 'technical', 'minimal', 'clean' ),
			),

			// ── Pricing / Plans ───────────────────────────────────────────────
			'pricing-table' => array(
				'label'       => 'Pricing / Plans',
				'description' => 'Heading with brief context (no cover — this page should load fast and get to the point), paragraph, full-width comparison table, separator, what-is-included list, social proof or FAQ quote, CTA.',
				'sequence'    => 'heading → paragraph → table → separator → list → quote → buttons',
				'best_for'    => array( 'pricing', 'reference' ),
				'avoid_for'   => array( 'home', 'about', 'contact', 'team', 'portfolio' ),
				'industries'  => array( '*' ),
				'styles'      => array( '*' ),
			),

			// ── Contact ───────────────────────────────────────────────────────
			'contact-direct' => array(
				'label'       => 'Contact Direct',
				'description' => 'No cover, no hero. Heading and inviting paragraph → columns with specific contact methods (phone, email, address, hours) → separator → media_text with office or location image. Gets to the information immediately.',
				'sequence'    => 'heading → paragraph → separator → columns(3) → separator → media_text(right)',
				'best_for'    => array( 'contact' ),
				'avoid_for'   => array( 'home', 'about', 'pricing', 'technical', 'product_overview' ),
				'industries'  => array( '*' ),
				'styles'      => array( '*' ),
			),

			// ── Creative / Portfolio ──────────────────────────────────────────
			'visual-story' => array(
				'label'       => 'Visual Story',
				'description' => 'Cover, then alternating media_text blocks with large images and narrative copy on alternating sides, closing with a strong quote and CTA. Editorial rhythm, image-heavy. Best for creative, design, or experience-focused businesses.',
				'sequence'    => 'cover → media_text(right) → separator → media_text(left) → separator → media_text(right) → quote → buttons',
				'best_for'    => array( 'about', 'portfolio', 'home', 'services_overview' ),
				'avoid_for'   => array( 'pricing', 'technical', 'reference', 'contact' ),
				'industries'  => array( 'creative', 'agency', 'design', 'hospitality', 'arts' ),
				'styles'      => array( 'editorial', 'bold' ),
			),

			// ── Technical / Reference ─────────────────────────────────────────
			'spec-reference' => array(
				'label'       => 'Spec / Reference',
				'description' => 'No cover. Heading and overview paragraph → detailed bullet or numbered list → separator → comparison or data table → separator → closing paragraph. Pure information density. No images, no marketing language.',
				'sequence'    => 'heading → paragraph → list → separator → table → separator → paragraph',
				'best_for'    => array( 'technical', 'reference' ),
				'avoid_for'   => array( 'home', 'about', 'contact', 'landing', 'portfolio' ),
				'industries'  => array( '*' ),
				'styles'      => array( 'technical', 'minimal' ),
			),
		);
	}

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
		add_action( self::AS_HOOK_REVISE,   array( $this, 'handle_revise_page' ),  10, 1 );
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
		// Image sideloading (Imagick resize) can exceed the default 30s PHP time limit.
		// This runs inside an Action Scheduler background job, so lifting the limit is safe.
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		@set_time_limit( 0 );

		// Reset per-run Unsplash budget so each generation starts with a full allowance.
		$this->unsplash_calls = 0;

		$build       = get_option( self::BUILD_OPTION, array() );
		$created_map = array(); // title => post_id for parent resolution.

		try {
			$this->process_pages_recursive( $sitemap, $format, 0, $build, $created_map );
			$build['status'] = 'complete';
		} catch ( \Throwable $e ) {
			$build['status'] = 'failed';
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'GrayFox generate_site_pages fatal: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		} finally {
			update_option( self::BUILD_OPTION, $build );
			delete_transient( self::LOCK_TRANSIENT );
		}
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
	/**
	 * Choose the best layout pattern for a page using a dedicated LLM call.
	 *
	 * Runs a small, cheap request (low temperature, tiny output) before the main
	 * content-generation call so the two concerns — what structure fits this page?
	 * vs what content fills it? — are separated. The LLM cannot anchor on a default
	 * pattern when it has to justify the choice first.
	 *
	 * @param GrayFox_LLM $llm          LLM instance.
	 * @param string      $provider     LLM provider slug.
	 * @param string      $api_key      Decrypted API key.
	 * @param string      $model        Model slug.
	 * @param string      $page_type    Page type slug from sitemap (e.g. 'about', 'pricing').
	 * @param string      $title        Page title.
	 * @param string      $content_brief Page purpose summary.
	 * @param array       $profile      Business profile from sitemap step.
	 * @return array Pattern definition from get_pattern_catalog(), or the 'hero-features' fallback.
	 */
	private function select_layout_pattern(
		GrayFox_LLM $llm,
		string $provider,
		string $api_key,
		string $model,
		string $page_type,
		string $title,
		string $content_brief,
		array $profile
	): array {
		$catalog  = self::get_pattern_catalog();
		$industry = sanitize_key( $profile['industry']      ?? '' );
		$tone     = sanitize_key( $profile['tone']          ?? '' );
		$style    = sanitize_key( $profile['visual_style']  ?? '' );

		// Pre-filter: keep patterns that list this page_type in best_for AND
		// don't list it in avoid_for. Also prefer industry-matched patterns.
		$candidates = array();
		$fallbacks  = array();
		foreach ( $catalog as $key => $pattern ) {
			if ( in_array( $page_type, $pattern['avoid_for'], true ) ) {
				continue;
			}
			$fits_page     = in_array( $page_type, $pattern['best_for'], true );
			$fits_industry = in_array( '*', $pattern['industries'], true )
				|| in_array( $industry, $pattern['industries'], true );

			if ( $fits_page && $fits_industry ) {
				$candidates[ $key ] = $pattern;
			} elseif ( $fits_page ) {
				$fallbacks[ $key ] = $pattern;
			}
		}

		// If industry narrowing left nothing, use the page-fit fallbacks.
		if ( empty( $candidates ) ) {
			$candidates = $fallbacks;
		}

		// If still nothing (unusual page_type or all patterns avoided), use full catalog.
		if ( empty( $candidates ) ) {
			$candidates = $catalog;
		}

		// Build the candidate list for the LLM.
		$pattern_list = '';
		foreach ( $candidates as $key => $p ) {
			$pattern_list .= sprintf( "\n  %s: %s", $key, $p['description'] );
		}

		$messages = array(
			array(
				'role'    => 'system',
				'content' => GRAYFOX_PROMPT_SITE_BUILDER_PATTERN_SELECT_SYSTEM,
			),
			array(
				'role'    => 'user',
				'content' => str_replace(
					array(
						'{{COMPANY_NAME}}',
						'{{INDUSTRY}}',
						'{{TONE}}',
						'{{VISUAL_STYLE}}',
						'{{PAGE_TITLE}}',
						'{{PAGE_TYPE}}',
						'{{CONTENT_BRIEF}}',
						'{{PATTERN_LIST}}',
					),
					array(
						sanitize_text_field( $profile['name'] ?? 'the company' ),
						$industry ?: 'other',
						$tone     ?: 'professional',
						$style    ?: 'clean',
						$title,
						$page_type ?: 'reference',
						$content_brief,
						$pattern_list,
					),
					GRAYFOX_PROMPT_SITE_BUILDER_PATTERN_SELECT_USER
				),
			),
		);

		$raw      = $llm->request_json( $provider, $api_key, self::get_small_model( $provider, $model ), $messages, 0.1 );
		$selected = json_decode( $raw, true );
		$key      = sanitize_key( $selected['pattern'] ?? '' );

		if ( isset( $candidates[ $key ] ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( 'GrayFox site builder: "%s" → pattern "%s" (%s)', $title, $key, $selected['reason'] ?? '' ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			return array_merge( array( '_key' => $key ), $candidates[ $key ] );
		}

		// Fallback: first candidate or hero-features.
		$fallback_key = array_key_first( $candidates ) ?? 'hero-features';
		return array_merge( array( '_key' => $fallback_key ), $catalog[ $fallback_key ] ?? $catalog['hero-features'] );
	}

	public function generate_page( array $page_def, string $format, int $parent_id ): array {
		// Elementor format uses the legacy generation path unchanged.
		if ( 'elementor' === $format ) {
			return $this->generate_page_elementor_legacy( $page_def, $parent_id );
		}

		$title  = sanitize_text_field( $page_def['title'] ?? 'Untitled' );
		$result = array(
			'post_id'  => 0,
			'status'   => 'failed',
			'title'    => $title,
			'edit_url' => '',
		);

		try {
			$provider = get_option( 'grayfox_llm_provider', 'openai' );
			$enc_key  = get_option( 'grayfox_llm_api_key', '' );
			$api_key  = grayfox_decrypt( $enc_key );
			$model    = get_option( 'grayfox_llm_model', '' );

			if ( empty( $api_key ) || empty( $model ) ) {
				return $result;
			}

			$profile       = get_transient( self::BUSINESS_PROFILE_TRANSIENT );
			$profile       = is_array( $profile ) ? $profile : array();
			$company_name  = ! empty( $profile['name'] ) ? $profile['name'] : 'the company';
			$content_brief = ! empty( $page_def['content_brief'] ) ? $page_def['content_brief'] : $title;
			$page_type     = sanitize_key( $page_def['page_type'] ?? '' );

			// Merge brand profile for image directives.
			$brand_profile = get_option( 'grayfox_brand_profile', array() );
			if ( ! empty( $brand_profile['visual_style'] ) ) {
				$profile['visual_style'] = $brand_profile['visual_style'];
			}

			// ── Phase 1: LLM-driven KB gathering ──────────────────────────────
			// The LLM uses search_knowledge_base to make targeted queries rather
			// than relying on a generic title-based pre-fetch.
			$search_tool = array(
				array(
					'type'     => 'function',
					'function' => array(
						'name'        => 'search_knowledge_base',
						'description' => 'Search the business knowledge base for specific information needed to write this web page. Make targeted, specific queries to retrieve the right content.',
						'parameters'  => array(
							'type'       => 'object',
							'properties' => array(
								'query' => array(
									'type'        => 'string',
									'description' => 'Specific search query (e.g. "pricing plans Trial Starter limits" or "company address contact details").',
								),
							),
							'required'   => array( 'query' ),
						),
					),
				),
			);

			$llm         = new GrayFox_LLM();
			$gather_msgs = array(
				array(
					'role'    => 'system',
					'content' => str_replace(
						array( '{{PAGE_TITLE}}', '{{COMPANY_NAME}}' ),
						array( $title, $company_name ),
						GRAYFOX_PROMPT_SITE_BUILDER_GATHER_SYSTEM
					),
				),
				array(
					'role'    => 'user',
					'content' => sprintf(
						'Search the knowledge base to collect all content needed for this page. Page: "%s". Purpose: %s',
						$title,
						$content_brief
					),
				),
			);

			$kb_ctx       = '';
			$max_searches = 3;

			for ( $i = 0; $i < $max_searches; $i++ ) {
				$gather = $llm->request_with_tools( $provider, $api_key, $model, $gather_msgs, $search_tool );

				if ( 'complete' === $gather['status'] ) {
					break;
				}

				if ( ! empty( $gather['assistant_message'] ) ) {
					$gather_msgs[] = $gather['assistant_message'];
				}

				foreach ( $gather['tool_calls'] as $call ) {
					if ( 'search_knowledge_base' === $call['name'] ) {
						$query     = sanitize_text_field( $call['args']['query'] ?? '' );
						$kb_result = GrayFox_RAG::get_consolidated_knowledge( $query );
						$kb_ctx   .= $kb_result . "\n\n";

						$gather_msgs[] = array(
							'role'         => 'tool',
							'tool_call_id' => $call['id'],
							'name'         => 'search_knowledge_base',
							'content'      => ! empty( $kb_result )
								? $kb_result
								: wp_json_encode( array( 'result' => 'No relevant information found.' ) ),
						);
					}
				}
			}

			$kb_ctx = mb_substr( trim( $kb_ctx ), 0, 12000 );

			if ( empty( $kb_ctx ) ) {
				$result['status'] = 'skipped';
				return $result;
			}

			// ── Harness loop ──────────────────────────────────────────────────
			// Build the page context passed into the single agentic harness call.
			$page_ctx = array(
				'page_title'    => $title,
				'page_type'     => $page_type,
				'content_brief' => $content_brief,
				'company_name'  => $company_name,
				'industry'      => $profile['industry'] ?? '',
				'tone'          => $profile['tone']     ?? '',
				'kb_content'    => $kb_ctx,
			);

			$harness = $this->run_page_harness( $llm, $provider, $api_key, $model, $page_ctx, $parent_id );

			if ( empty( $harness['post_id'] ) ) {
				return $result;
			}

			$post_id = $harness['post_id'];

			$img_directive = self::get_image_style_directive( $profile );

			// ── Phase 3a: Fill inline pattern images ─────────────────────────────
			// Replace any core/image blocks that have a keyword in alt but no src.
			$this->fill_page_images( $post_id, $title, $img_directive );

			// ── Phase 3b: Featured image ──────────────────────────────────────────
			// GrayFox theme patterns are text-based (no wp:cover blocks that would
			// duplicate a featured image), so we always attempt to set one.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( 'GrayFox SB Phase 3b [%s]: fetching featured image (unsplash_calls=%d/%d)', $title, $this->unsplash_calls, self::UNSPLASH_PER_RUN_LIMIT ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			$featured_id   = $this->find_cached_image( $title );

			if ( $featured_id ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( sprintf( 'GrayFox SB Phase 3b [%s]: found cached image att=%d', $title, $featured_id ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
			} elseif ( $this->unsplash_calls < self::UNSPLASH_PER_RUN_LIMIT ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( sprintf( 'GrayFox SB Phase 3b [%s]: calling Unsplash for "%s"', $title, $title ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
				$featured_id = $this->fetch_unsplash_image( $title, $img_directive['unsplash_color'] );
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( sprintf( 'GrayFox SB Phase 3b [%s]: Unsplash returned att=%d', $title, $featured_id ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
			}
			if ( ! $featured_id ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( sprintf( 'GrayFox SB Phase 3b [%s]: falling back to DALL-E', $title ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
				$featured_id = $this->fetch_dalle_image( $title . ' hero', $title, $img_directive['dalle_suffix'] );
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( sprintf( 'GrayFox SB Phase 3b [%s]: DALL-E returned att=%d', $title, $featured_id ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
			}
			if ( $featured_id ) {
				set_post_thumbnail( $post_id, $featured_id );
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( sprintf( 'GrayFox SB Phase 3b [%s]: set_post_thumbnail att=%d', $title, $featured_id ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
			}

			// Store a minimal image map and a harness-built marker for the revision system.
			update_post_meta( $post_id, self::META_IMAGE_MAP,   wp_json_encode( array() ) );
			update_post_meta( $post_id, self::META_BLOCKS_JSON, wp_json_encode( array( 'harness_built' => true ) ) );

			$result['post_id']  = $post_id;
			$result['status']   = 'complete';
			$result['edit_url'] = get_edit_post_link( $post_id, 'raw' );

		} catch ( Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'GrayFox generate_page error for "' . $title . '": ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}

		return $result;
	}

	/**
	 * Run the harness-lite agentic loop for a single page (blocks format).
	 *
	 * A single LLM conversation drives the entire page build via tool calls.
	 * Context accumulates across the full loop — the LLM sees template outlines
	 * (guidelines), pattern schemas, its own tool call results, and KB content
	 * all in one coherent conversation. No siloed calls.
	 *
	 * Expected tool call sequence per page:
	 *   sb_query_templates → sb_query_patterns → sb_create_page
	 *   → sb_append_pattern (×2–4) → sb_page_complete
	 *
	 * @param GrayFox_LLM $llm       LLM instance.
	 * @param string      $provider  LLM provider slug.
	 * @param string      $api_key   Decrypted API key.
	 * @param string      $model     Model slug.
	 * @param array       $page_ctx  Page context (title, type, brief, company, kb, tone, industry).
	 * @param int         $parent_id WordPress parent page ID.
	 * @return array{post_id: int}
	 */
	private function run_page_harness(
		GrayFox_LLM $llm,
		string $provider,
		string $api_key,
		string $model,
		array $page_ctx,
		int $parent_id
	): array {
		$tool_defs = $this->build_harness_tool_definitions();
		$system    = $this->get_harness_system_prompt( $page_ctx );

		$messages = array(
			array( 'role' => 'system', 'content' => $system ),
			array( 'role' => 'user',   'content' => "Build the page now." ),
		);

		$max_iters = 14;
		$post_id   = 0;

		for ( $i = 0; $i < $max_iters; $i++ ) {
			$result = $llm->request_with_tools( $provider, $api_key, $model, $messages, $tool_defs );

			if ( ! empty( $result['assistant_message'] ) ) {
				$messages[] = $result['assistant_message'];
			}

			$tool_calls = $result['tool_calls'] ?? array();

			if ( 'complete' === ( $result['status'] ?? '' ) && empty( $tool_calls ) ) {
				$content = trim( $result['content'] ?? '' );
				if ( ! empty( $content ) ) {
					// LLM sent a planning/thinking message — log it and nudge back to tool calls.
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( sprintf( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
							'GrayFox SB harness [%s] LLM text (no tool call): %s',
							$page_ctx['page_title'],
							mb_substr( $content, 0, 300 )
						) );
					}
					$messages[] = array( 'role' => 'user', 'content' => 'Continue building the page. Call the next tool now.' );
					continue;
				}
				// Empty content = API error swallowed upstream — abort this page.
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( sprintf( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
						'GrayFox SB harness [%s]: API returned empty response (rate limit or timeout). Aborting page.',
						$page_ctx['page_title']
					) );
				}
				break;
			}

			foreach ( $tool_calls as $call ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( sprintf( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
						'GrayFox SB harness [%s] tool_call: %s args=%s',
						$page_ctx['page_title'],
						$call['name'],
						wp_json_encode( $call['args'] ?? array() )
					) );
				}

				$tool_result = $this->execute_harness_tool( $call['name'], $call['args'] ?? array(), $page_ctx, $parent_id );

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( sprintf( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
						'GrayFox SB harness [%s] tool_result: %s',
						$page_ctx['page_title'],
						mb_substr( $tool_result, 0, 500 )
					) );
				}

				$messages[] = array(
					'role'         => 'tool',
					'tool_call_id' => $call['id'],
					'name'         => $call['name'],
					'content'      => $tool_result,
				);

				$decoded = json_decode( $tool_result, true ) ?? array();

				if ( 'sb_create_page' === $call['name'] && ! empty( $decoded['post_id'] ) ) {
					$post_id = (int) $decoded['post_id'];
				}

				if ( 'sb_page_complete' === $call['name'] && ! empty( $decoded['done'] ) ) {
					break 2;
				}
			}
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'GrayFox SB harness [%s]: loop complete after %d iteration(s). post_id=%d', $page_ctx['page_title'], $i + 1, $post_id ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		return array( 'post_id' => $post_id );
	}

	/**
	 * Execute a single harness tool call and return its JSON result string.
	 *
	 * @param string $name      Tool name (e.g. "sb_query_templates").
	 * @param array  $args      Decoded tool call arguments.
	 * @param array  $page_ctx  Page context (read-only here; post_id tracked in loop).
	 * @param int    $parent_id WordPress parent page ID.
	 * @return string JSON result string.
	 */
	private function execute_harness_tool( string $name, array $args, array $page_ctx, int $parent_id ): string {
		switch ( $name ) {
			case 'sb_query_templates':
				return ( new GrayFox_SB_Tool_QueryTemplates() )->execute( $args );

			case 'sb_query_patterns':
				return ( new GrayFox_SB_Tool_QueryPatterns() )->execute( $args );

			case 'sb_create_page':
				$tool                    = new GrayFox_SB_Tool_CreatePage();
				$tool->default_parent_id = $parent_id;
				$tool->extra_meta        = array(
					'_wp_page_template' => 'page-no-title',
					'content_brief'     => $page_ctx['content_brief'] ?? '',
					'page_type'         => $page_ctx['page_type']     ?? '',
				);
				return $tool->execute( $args );

			case 'sb_append_pattern':
				return ( new GrayFox_SB_Tool_AppendPattern() )->execute( $args );

			case 'sb_remove_last_pattern':
				return ( new GrayFox_SB_Tool_RemoveLastPattern() )->execute( $args );

			case 'sb_page_complete':
				return ( new GrayFox_SB_Tool_PageComplete() )->execute( $args );

			default:
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'GrayFox SB harness: unknown tool call "' . $name . '"' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
				return wp_json_encode( array( 'error' => 'Unknown tool: ' . $name ) );
		}
	}

	/**
	 * Build the tool definitions array for the harness loop.
	 *
	 * @return array Tool definitions in OpenAI function-calling format.
	 */
	private function build_harness_tool_definitions(): array {
		return array(
			( new GrayFox_SB_Tool_QueryTemplates() )->get_definition(),
			( new GrayFox_SB_Tool_QueryPatterns() )->get_definition(),
			( new GrayFox_SB_Tool_CreatePage() )->get_definition(),
			( new GrayFox_SB_Tool_AppendPattern() )->get_definition(),
			( new GrayFox_SB_Tool_RemoveLastPattern() )->get_definition(),
			( new GrayFox_SB_Tool_PageComplete() )->get_definition(),
		);
	}

	/**
	 * Build the harness system prompt by substituting page context into the template.
	 *
	 * @param array $page_ctx Page context.
	 * @return string Populated system prompt.
	 */
	private function get_harness_system_prompt( array $page_ctx ): string {
		return str_replace(
			array( '{{PAGE_TITLE}}', '{{PAGE_TYPE}}', '{{CONTENT_BRIEF}}', '{{COMPANY_NAME}}', '{{INDUSTRY}}', '{{TONE}}', '{{KB_CONTENT}}' ),
			array(
				$page_ctx['page_title']    ?? '',
				$page_ctx['page_type']     ?? '',
				$page_ctx['content_brief'] ?? '',
				$page_ctx['company_name']  ?? '',
				$page_ctx['industry']      ?? '',
				$page_ctx['tone']          ?? '',
				$page_ctx['kb_content']    ?? '',
			),
			GRAYFOX_PROMPT_SITE_BUILDER_HARNESS_SYSTEM
		);
	}

	/**
	 * Walk the post_content of a harness-built page and replace empty core/image
	 * blocks (those with a keyword in alt but no attachment id) with real images
	 * fetched via Unsplash / DALL-E.
	 *
	 * @param int    $post_id      WordPress post ID.
	 * @param string $page_title   Used as context for DALL-E prompts.
	 * @param array  $img_directive Style directives from get_image_style_directive().
	 */
	/**
	 * @param bool $bypass_cache When true, skip the media library cache and always
	 *                           fetch a fresh image. Used by the new_images revision action.
	 */
	private function fill_page_images( int $post_id, string $page_title, array $img_directive, bool $bypass_cache = false ): void {
		$content = get_post_field( 'post_content', $post_id );
		if ( empty( $content ) ) {
			return;
		}

		$blocks  = parse_blocks( $content );
		$changed = false;

		$this->fill_image_blocks_recursive( $blocks, $page_title, $img_directive, $changed, $bypass_cache );

		if ( $changed ) {
			wp_update_post( array(
				'ID'           => $post_id,
				'post_content' => serialize_blocks( $blocks ),
			) );
		}
	}

	/**
	 * Recursively walk parsed blocks and fill empty core/image slots.
	 *
	 * @param array  $blocks        Parsed block array (passed by reference).
	 * @param string $page_title    Context for DALL-E.
	 * @param array  $img_directive Style directives.
	 * @param bool   $changed       Passed by reference — set to true when any block is modified.
	 * @param bool   $bypass_cache  When true, replace even images that already have an attachment id.
	 */
	private function fill_image_blocks_recursive( array &$blocks, string $page_title, array $img_directive, bool &$changed, bool $bypass_cache = false ): void {
		foreach ( $blocks as &$block ) {
			// Recurse into containers.
			if ( ! empty( $block['innerBlocks'] ) ) {
				$this->fill_image_blocks_recursive( $block['innerBlocks'], $page_title, $img_directive, $changed, $bypass_cache );
			}

			if ( ( $block['blockName'] ?? '' ) !== 'core/image' ) {
				continue;
			}

			// Already has an attachment — skip unless bypassing cache.
			if ( ! $bypass_cache && ! empty( $block['attrs']['id'] ) ) {
				continue;
			}

			// Pull keyword from alt attribute (injected by inject_content).
			$kw = trim( $block['attrs']['alt'] ?? '' );
			if ( empty( $kw ) ) {
				continue;
			}

			// Try cache first (skip when bypassing for new_images action).
			$att_id = ( ! $bypass_cache ) ? $this->find_cached_image( $kw ) : 0;

			// Unsplash.
			if ( ! $att_id && $this->unsplash_calls < self::UNSPLASH_PER_RUN_LIMIT ) {
				$att_id = $this->fetch_unsplash_image( $kw, $img_directive['unsplash_color'] );
			}

			// DALL-E fallback.
			if ( ! $att_id ) {
				$att_id = $this->fetch_dalle_image( $kw, $page_title, $img_directive['dalle_suffix'] );
			}

			if ( ! $att_id ) {
				continue;
			}

			$url = (string) wp_get_attachment_image_url( $att_id, 'full' );

			// Update block attrs.
			$block['attrs']['id']  = $att_id;
			$block['attrs']['url'] = $url;

			// Patch innerHTML: inject src and id into the <img> tag.
			$html = $block['innerHTML'] ?? '';
			if ( ! empty( $html ) ) {
				// Replace or add src attribute.
				if ( strpos( $html, ' src=' ) !== false ) {
					$html = preg_replace( '/\ssrc="[^"]*"/', ' src="' . esc_url( $url ) . '"', $html );
				} else {
					$html = str_replace( '<img ', '<img src="' . esc_url( $url ) . '" ', $html );
				}
				// Patch the wp:image JSON comment with the attachment id.
				$block['innerHTML'] = $html;
			}

			// Patch the serialized comment attrs in innerContent.
			foreach ( $block['innerContent'] as &$chunk ) {
				if ( is_string( $chunk ) && strpos( $chunk, '<img' ) !== false ) {
					if ( strpos( $chunk, ' src=' ) !== false ) {
						$chunk = preg_replace( '/\ssrc="[^"]*"/', ' src="' . esc_url( $url ) . '"', $chunk );
					} else {
						$chunk = str_replace( '<img ', '<img src="' . esc_url( $url ) . '" ', $chunk );
					}
				}
			}
			unset( $chunk );

			$changed = true;

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					'GrayFox SB fill_page_images: placed image (att %d, kw "%s") on post %d',
					$att_id, $kw, $block['attrs']['id'] ?? 0
				) );
			}
		}
		unset( $block );
	}

	// =========================================================================
	// Harness revision handlers (harness-built pages only)
	// =========================================================================

	/**
	 * new_images revision for harness pages.
	 * Re-fetches every image slot on the page, bypassing the media library cache.
	 */
	private function revise_harness_images( int $post_id, string $page_title, array $img_directive ): void {
		$this->unsplash_calls = 0;
		$this->fill_page_images( $post_id, $page_title, $img_directive, true );
	}

	/**
	 * revise_copy for harness pages.
	 * Extracts the current text schema, sends it to the LLM with a directive,
	 * applies the returned changes via inject_content(), and saves.
	 */
	private function revise_harness_copy(
		int $post_id,
		string $page_title,
		string $company_name,
		string $directive,
		GrayFox_LLM $llm,
		string $provider,
		string $api_key,
		string $model
	): void {
		$post_content = get_post_field( 'post_content', $post_id );
		if ( empty( $post_content ) ) {
			return;
		}

		$helper = new GrayFox_SB_Tool_AppendPattern();
		$schema = $helper->get_content_schema( $post_content );

		if ( empty( $schema ) ) {
			return;
		}

		// Pass only text slots — image slots are not rewritten.
		$text_schema = array_values( array_filter( $schema, static function ( array $s ): bool {
			return 'image' !== $s['type'];
		} ) );

		$system = str_replace(
			array( '{{PAGE_TITLE}}', '{{COMPANY_NAME}}', '{{REVISION_DIRECTIVE}}', '{{CURRENT_SCHEMA_JSON}}' ),
			array(
				$page_title,
				$company_name,
				$directive ?: 'Improve clarity and engagement while keeping the same meaning',
				wp_json_encode( $text_schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ),
			),
			GRAYFOX_PROMPT_SITE_BUILDER_REVISE_COPY_HARNESS_SYSTEM
		);

		$messages = array(
			array( 'role' => 'system', 'content' => $system ),
			array( 'role' => 'user',   'content' => 'Return the updated slots JSON now.' ),
		);

		$raw     = $llm->request_json( $provider, $api_key, $model, $messages, 0.4 );
		$changes = json_decode( $raw, true );

		if ( ! is_array( $changes ) || empty( $changes ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'GrayFox revise_copy harness: LLM returned empty or invalid JSON for post ' . $post_id ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			return;
		}

		// Build slot_map keyed by index.
		$slot_map = array();
		foreach ( $changes as $change ) {
			$idx = (int) ( $change['index'] ?? -1 );
			if ( $idx >= 0 ) {
				$slot_map[ $idx ] = $change;
			}
		}

		$new_content = $helper->apply_slots( $post_content, $slot_map );
		wp_update_post( array( 'ID' => $post_id, 'post_content' => $new_content ) );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'GrayFox revise_copy harness: applied %d slot changes to post %d', count( $slot_map ), $post_id ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * rearrange for harness pages.
	 * Extracts approved copy, then runs a targeted harness that picks new patterns
	 * and re-fills them with the approved text. Images are fetched after the loop.
	 */
	private function revise_harness_rearrange(
		int $post_id,
		string $page_title,
		string $company_name,
		string $directive,
		GrayFox_LLM $llm,
		string $provider,
		string $api_key,
		string $model,
		array $img_directive
	): void {
		$post_content = get_post_field( 'post_content', $post_id );
		if ( empty( $post_content ) ) {
			return;
		}

		// Extract approved copy.
		$helper = new GrayFox_SB_Tool_AppendPattern();
		$schema = $helper->get_content_schema( $post_content );

		// Get currently used patterns so the LLM knows what to avoid.
		$gen_log          = json_decode( get_post_meta( $post_id, '_grayfox_generation_log', true ) ?: '{}', true );
		$current_patterns = $gen_log['patterns_used'] ?? array();

		$system = str_replace(
			array( '{{PAGE_TITLE}}', '{{COMPANY_NAME}}', '{{REVISION_DIRECTIVE}}', '{{CURRENT_SCHEMA_JSON}}', '{{CURRENT_PATTERNS}}', '{{POST_ID}}' ),
			array(
				$page_title,
				$company_name,
				$directive ?: 'Choose patterns that present the content more effectively',
				wp_json_encode( $schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ),
				implode( ', ', $current_patterns ) ?: 'none',
				(string) $post_id,
			),
			GRAYFOX_PROMPT_SITE_BUILDER_REARRANGE_HARNESS_SYSTEM
		);

		$this->run_rearrange_harness( $llm, $provider, $api_key, $model, $system, $post_id, $page_title );

		// Fill any image slots the new patterns introduced.
		$this->unsplash_calls = 0;
		$this->fill_page_images( $post_id, $page_title, $img_directive );
	}

	/**
	 * Agentic loop for the rearrange revision.
	 * Uses sb_clear_page instead of sb_create_page — post_id is already known.
	 */
	private function run_rearrange_harness(
		GrayFox_LLM $llm,
		string $provider,
		string $api_key,
		string $model,
		string $system,
		int $post_id,
		string $page_title
	): void {
		$tool_defs = $this->build_rearrange_tool_definitions();
		$messages  = array(
			array( 'role' => 'system', 'content' => $system ),
			array( 'role' => 'user',   'content' => 'Rearrange the page now.' ),
		);
		$max_iters = 14;

		for ( $i = 0; $i < $max_iters; $i++ ) {
			$result = $llm->request_with_tools( $provider, $api_key, $model, $messages, $tool_defs );

			if ( ! empty( $result['assistant_message'] ) ) {
				$messages[] = $result['assistant_message'];
			}

			$tool_calls = $result['tool_calls'] ?? array();

			if ( 'complete' === ( $result['status'] ?? '' ) && empty( $tool_calls ) ) {
				$content = trim( $result['content'] ?? '' );
				if ( ! empty( $content ) ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( sprintf( 'GrayFox SB rearrange [%s] LLM text: %s', $page_title, mb_substr( $content, 0, 200 ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					}
					$messages[] = array( 'role' => 'user', 'content' => 'Continue. Call the next tool now.' );
					continue;
				}
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( sprintf( 'GrayFox SB rearrange [%s]: empty API response, aborting.', $page_title ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
				break;
			}

			foreach ( $tool_calls as $call ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( sprintf( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
						'GrayFox SB rearrange [%s] tool_call: %s args=%s',
						$page_title, $call['name'], wp_json_encode( $call['args'] ?? array() )
					) );
				}

				$tool_result = $this->execute_rearrange_tool( $call['name'], $call['args'] ?? array(), $post_id );

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( sprintf( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
						'GrayFox SB rearrange [%s] tool_result: %s',
						$page_title, mb_substr( $tool_result, 0, 500 )
					) );
				}

				$messages[] = array(
					'role'         => 'tool',
					'tool_call_id' => $call['id'],
					'name'         => $call['name'],
					'content'      => $tool_result,
				);

				if ( 'sb_page_complete' === $call['name'] ) {
					$decoded = json_decode( $tool_result, true ) ?? array();
					if ( ! empty( $decoded['done'] ) ) {
						break 2;
					}
				}
			}
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'GrayFox SB rearrange [%s]: loop complete after %d iteration(s).', $page_title, $i + 1 ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Dispatch a rearrange harness tool call. Uses the known post_id to prevent
	 * the LLM from operating on a different page.
	 */
	private function execute_rearrange_tool( string $name, array $args, int $post_id ): string {
		switch ( $name ) {
			case 'sb_query_patterns':
				return ( new GrayFox_SB_Tool_QueryPatterns() )->execute( $args );

			case 'sb_clear_page':
				// Always use the known post_id — ignore whatever the LLM passed.
				return ( new GrayFox_SB_Tool_ClearPage() )->execute( array( 'post_id' => $post_id ) );

			case 'sb_append_pattern':
				$args['post_id'] = $post_id;
				return ( new GrayFox_SB_Tool_AppendPattern() )->execute( $args );

			case 'sb_remove_last_pattern':
				$args['post_id'] = $post_id;
				return ( new GrayFox_SB_Tool_RemoveLastPattern() )->execute( $args );

			case 'sb_page_complete':
				return ( new GrayFox_SB_Tool_PageComplete() )->execute( $args );

			default:
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'GrayFox SB rearrange: unknown tool "' . $name . '"' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
				return wp_json_encode( array( 'error' => 'Unknown tool: ' . $name ) );
		}
	}

	/**
	 * Tool definitions for the rearrange harness.
	 * Uses sb_clear_page instead of sb_create_page.
	 */
	private function build_rearrange_tool_definitions(): array {
		return array(
			( new GrayFox_SB_Tool_QueryPatterns() )->get_definition(),
			( new GrayFox_SB_Tool_ClearPage() )->get_definition(),
			( new GrayFox_SB_Tool_AppendPattern() )->get_definition(),
			( new GrayFox_SB_Tool_RemoveLastPattern() )->get_definition(),
			( new GrayFox_SB_Tool_PageComplete() )->get_definition(),
		);
	}


	/**
	 * Legacy generation path for Elementor format.
	 *
	 * Uses the static pattern catalog and direct LLM block generation. Kept
	 * intact to avoid breaking existing Elementor users while the blocks path
	 * is migrated to the harness-lite architecture.
	 *
	 * @param array $page_def  Page definition array.
	 * @param int   $parent_id WordPress post parent ID.
	 * @return array{post_id: int, status: string, title: string, edit_url: string}
	 */
	private function generate_page_elementor_legacy( array $page_def, int $parent_id ): array {
		$title  = sanitize_text_field( $page_def['title'] ?? 'Untitled' );
		$result = array(
			'post_id'  => 0,
			'status'   => 'failed',
			'title'    => $title,
			'edit_url' => '',
		);

		try {
			$provider = get_option( 'grayfox_llm_provider', 'openai' );
			$enc_key  = get_option( 'grayfox_llm_api_key', '' );
			$api_key  = grayfox_decrypt( $enc_key );
			$model    = get_option( 'grayfox_llm_model', '' );

			if ( empty( $api_key ) || empty( $model ) ) {
				return $result;
			}

			$profile       = get_transient( self::BUSINESS_PROFILE_TRANSIENT );
			$profile       = is_array( $profile ) ? $profile : array();
			$company_name  = ! empty( $profile['name'] ) ? $profile['name'] : 'the company';
			$content_brief = ! empty( $page_def['content_brief'] ) ? $page_def['content_brief'] : $title;
			$page_type     = sanitize_key( $page_def['page_type'] ?? '' );

			$brand_profile = get_option( 'grayfox_brand_profile', array() );
			if ( ! empty( $brand_profile['visual_style'] ) ) {
				$profile['visual_style'] = $brand_profile['visual_style'];
			}

			$brand_context = '';
			if ( ! empty( $brand_profile['colors']['primary'] ) ) {
				$brand_context  = 'Brand context: ';
				$brand_context .= 'Primary color ' . $brand_profile['colors']['primary'];
				if ( ! empty( $brand_profile['colors']['accent'] ) ) {
					$brand_context .= ', accent ' . $brand_profile['colors']['accent'];
				}
				if ( ! empty( $brand_profile['typography']['heading_font'] ) ) {
					$brand_context .= '. Heading font: ' . $brand_profile['typography']['heading_font'];
				}
				if ( ! empty( $brand_profile['visual_style'] ) ) {
					$brand_context .= '. Visual style: ' . $brand_profile['visual_style'];
				}
				$brand_context .= '. Reflect this brand identity in copy tone.';
			}

			$search_tool = array(
				array(
					'type'     => 'function',
					'function' => array(
						'name'        => 'search_knowledge_base',
						'description' => 'Search the business knowledge base for specific information needed to write this web page. Make targeted, specific queries to retrieve the right content.',
						'parameters'  => array(
							'type'       => 'object',
							'properties' => array(
								'query' => array(
									'type'        => 'string',
									'description' => 'Specific search query (e.g. "pricing plans Trial Starter limits" or "company address contact details").',
								),
							),
							'required'   => array( 'query' ),
						),
					),
				),
			);

			$llm         = new GrayFox_LLM();
			$gather_msgs = array(
				array(
					'role'    => 'system',
					'content' => str_replace(
						array( '{{PAGE_TITLE}}', '{{COMPANY_NAME}}' ),
						array( $title, $company_name ),
						GRAYFOX_PROMPT_SITE_BUILDER_GATHER_SYSTEM
					),
				),
				array(
					'role'    => 'user',
					'content' => sprintf(
						'Search the knowledge base to collect all content needed for this page. Page: "%s". Purpose: %s',
						$title,
						$content_brief
					),
				),
			);

			$kb_ctx       = '';
			$max_searches = 3;

			for ( $i = 0; $i < $max_searches; $i++ ) {
				$gather = $llm->request_with_tools( $provider, $api_key, $model, $gather_msgs, $search_tool );

				if ( 'complete' === $gather['status'] ) {
					break;
				}
				if ( ! empty( $gather['assistant_message'] ) ) {
					$gather_msgs[] = $gather['assistant_message'];
				}
				foreach ( $gather['tool_calls'] as $call ) {
					if ( 'search_knowledge_base' === $call['name'] ) {
						$query     = sanitize_text_field( $call['args']['query'] ?? '' );
						$kb_result = GrayFox_RAG::get_consolidated_knowledge( $query );
						$kb_ctx   .= $kb_result . "\n\n";
						$gather_msgs[] = array(
							'role'         => 'tool',
							'tool_call_id' => $call['id'],
							'name'         => 'search_knowledge_base',
							'content'      => ! empty( $kb_result )
								? $kb_result
								: wp_json_encode( array( 'result' => 'No relevant information found.' ) ),
						);
					}
				}
			}

			$kb_ctx = mb_substr( trim( $kb_ctx ), 0, 12000 );

			if ( empty( $kb_ctx ) ) {
				$result['status'] = 'skipped';
				return $result;
			}

			$pattern = $this->select_layout_pattern(
				$llm, $provider, $api_key, $model,
				$page_type, $title, $content_brief, $profile
			);

			$messages = array(
				array( 'role' => 'system', 'content' => GRAYFOX_PROMPT_SITE_BUILDER_GENERATE_SYSTEM ),
				array(
					'role'    => 'user',
					'content' => str_replace(
						array(
							'{{COMPANY_NAME}}', '{{PAGE_TITLE}}', '{{CONTENT_BRIEF}}',
							'{{BRAND_CONTEXT}}', '{{PATTERN_LABEL}}', '{{PATTERN_SEQUENCE}}',
							'{{KB_CONTENT}}', '{{PATTERN_KEY}}',
						),
						array(
							$company_name, $title, $content_brief, $brand_context,
							$pattern['label'], $pattern['sequence'], $kb_ctx,
							$pattern['_key'] ?? 'unknown',
						),
						GRAYFOX_PROMPT_SITE_BUILDER_GENERATE_USER
					),
				),
			);

			$raw    = $llm->request_json( $provider, $api_key, $model, $messages, 0.3 );
			$parsed = json_decode( $raw, true );

			if ( ! is_array( $parsed ) || empty( $parsed['blocks'] ) ) {
				return $result;
			}

			foreach ( $parsed['blocks'] as &$block ) {
				if ( isset( $block['content'] ) )  $block['content']  = wp_kses_post( $block['content'] );
				if ( isset( $block['heading'] ) )  $block['heading']  = wp_kses_post( $block['heading'] );
				if ( isset( $block['subtext'] ) )  $block['subtext']  = wp_kses_post( $block['subtext'] );
				if ( isset( $block['citation'] ) ) $block['citation'] = sanitize_text_field( $block['citation'] );
				if ( isset( $block['keyword'] ) ) {
					if ( is_array( $block['keyword'] ) ) $block['keyword'] = implode( ' ', $block['keyword'] );
					$block['keyword'] = sanitize_text_field( (string) $block['keyword'] );
				}
				if ( isset( $block['items'] ) && is_array( $block['items'] ) )   $block['items']  = array_map( 'wp_kses_post', $block['items'] );
				if ( isset( $block['header'] ) && is_array( $block['header'] ) ) $block['header'] = array_map( 'sanitize_text_field', $block['header'] );
				if ( isset( $block['columns'] ) && is_array( $block['columns'] ) ) {
					foreach ( $block['columns'] as &$col ) {
						if ( isset( $col['heading'] ) ) $col['heading'] = wp_kses_post( $col['heading'] );
						if ( isset( $col['content'] ) ) $col['content'] = wp_kses_post( $col['content'] );
					}
					unset( $col );
				}
				if ( isset( $block['buttons'] ) && is_array( $block['buttons'] ) ) {
					foreach ( $block['buttons'] as &$btn ) {
						if ( isset( $btn['text'] ) ) $btn['text'] = sanitize_text_field( $btn['text'] );
						$btn['url'] = '#';
					}
					unset( $btn );
				}
				if ( isset( $block['rows'] ) && is_array( $block['rows'] ) ) {
					foreach ( $block['rows'] as &$row ) {
						if ( is_array( $row ) ) $row = array_map( 'wp_kses_post', $row );
					}
					unset( $row );
				}
			}
			unset( $block );

			$img_directive = self::get_image_style_directive( $profile );
			$images        = array();
			$img_types     = array( 'image', 'cover', 'media_text' );

			foreach ( $parsed['blocks'] as $block ) {
				$type = $block['type'] ?? '';
				$kw   = ! empty( $block['keyword'] ) ? $block['keyword'] : '';
				if ( ! in_array( $type, $img_types, true ) || empty( $kw ) || isset( $images[ $kw ] ) ) {
					continue;
				}
				$att_id = $this->find_cached_image( $kw );
				if ( ! $att_id ) {
					if ( 'cover' === $type ) {
						$att_id = $this->fetch_dalle_image( $kw, $title, $img_directive['dalle_suffix'] );
					} else {
						if ( $this->unsplash_calls < self::UNSPLASH_PER_RUN_LIMIT ) {
							$att_id = $this->fetch_unsplash_image( $kw, $img_directive['unsplash_color'] );
						}
						if ( ! $att_id ) {
							$att_id = $this->fetch_dalle_image( $kw, $title, $img_directive['dalle_suffix'] );
						}
					}
				}
				if ( $att_id ) {
					$images[ $kw ] = array(
						'id'  => $att_id,
						'url' => (string) wp_get_attachment_image_url( $att_id, 'full' ),
					);
				}
			}

			$elementor_data = $this->build_elementor_data( $parsed['blocks'], $images );

			$slug = sanitize_title( $title );
			if ( get_page_by_path( $slug ) ) {
				$slug .= '-' . substr( (string) time(), -6 );
			}

			$post_id = wp_insert_post( array(
				'post_title'   => wp_strip_all_tags( $title ),
				'post_content' => '',
				'post_status'  => 'draft',
				'post_type'    => 'page',
				'post_name'    => $slug,
				'post_parent'  => $parent_id,
			) );

			if ( is_wp_error( $post_id ) || ! $post_id ) {
				return $result;
			}

			add_post_meta( $post_id, self::META_GENERATED, '1', true );
			update_post_meta( $post_id, self::META_BLOCKS_JSON, wp_json_encode( $parsed['blocks'] ) );
			update_post_meta( $post_id, '_grayfox_content_brief', sanitize_text_field( $content_brief ) );
			update_post_meta( $post_id, '_grayfox_page_type',     sanitize_key( $page_type ) );

			if ( $elementor_data !== null ) {
				update_post_meta( $post_id, '_elementor_data',      wp_slash( wp_json_encode( $elementor_data ) ) );
				update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
			}

			$first_block_type = ! empty( $parsed['blocks'][0]['type'] ) ? $parsed['blocks'][0]['type'] : '';
			$skip_thumbnail   = in_array( $first_block_type, array( 'cover', 'media_text' ), true );

			if ( ! $skip_thumbnail ) {
				$featured_id = 0;
				if ( ! empty( $images ) ) { $first = reset( $images ); $featured_id = $first['id']; }
				if ( ! $featured_id ) $featured_id = $this->find_cached_image( $title );
				if ( ! $featured_id ) {
					if ( $this->unsplash_calls < self::UNSPLASH_PER_RUN_LIMIT ) {
						$featured_id = $this->fetch_unsplash_image( $title, $img_directive['unsplash_color'] );
					}
					if ( ! $featured_id ) {
						$featured_id = $this->fetch_dalle_image( $title . ' hero', $title, $img_directive['dalle_suffix'] );
					}
				}
				if ( $featured_id ) set_post_thumbnail( $post_id, $featured_id );
			}

			update_post_meta( $post_id, self::META_IMAGE_MAP, wp_json_encode( $images ) );

			$result['post_id']  = $post_id;
			$result['status']   = 'complete';
			$result['edit_url'] = get_edit_post_link( $post_id, 'raw' );

		} catch ( Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'GrayFox generate_page_elementor_legacy error for "' . $title . '": ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}

		return $result;
	}

	/**
	 * Build WordPress block markup from a blocks array.
	 *
	 * @param array $blocks LLM-generated blocks array.
	 * @param array $images Optional pre-fetched image map: keyword → ['id'=>int,'url'=>string].
	 * @return string Serialized block content.
	 */
	public function build_wp_blocks( array $blocks, array $images = array() ): string {
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
					$kw  = $block['keyword'] ?? '';
					$img = $images[ $kw ] ?? null;
					if ( $img ) {
						$attrs   = wp_json_encode( array( 'id' => $img['id'], 'sizeSlug' => 'large' ) );
						$output .= "<!-- wp:image {$attrs} -->\n";
						$output .= '<figure class="wp-block-image size-large">';
						$output .= '<img src="' . esc_url( $img['url'] ) . '" alt="' . esc_attr( $kw ) . '" class="wp-image-' . (int) $img['id'] . '"/>';
						$output .= "</figure>\n";
						$output .= "<!-- /wp:image -->\n\n";
					} else {
						$output .= "<!-- wp:image -->\n";
						$output .= '<figure class="wp-block-image"><img alt="' . esc_attr( $kw ) . '"/></figure>' . "\n";
						$output .= "<!-- /wp:image -->\n\n";
					}
					break;

				case 'cover':
					$heading = wp_kses_post( $block['heading'] ?? '' );
					$subtext = wp_kses_post( $block['subtext'] ?? '' );
					$kw      = $block['keyword'] ?? '';
					$img     = $images[ $kw ] ?? null;
					if ( $img ) {
						$attrs   = wp_json_encode( array(
							'url'          => $img['url'],
							'id'           => $img['id'],
							'dimRatio'     => 50,
							'overlayColor' => 'black',
							'minHeight'    => 400,
						) );
						$output .= "<!-- wp:cover {$attrs} -->\n";
						$output .= '<div class="wp-block-cover" style="min-height:400px">';
						$output .= '<span aria-hidden="true" class="wp-block-cover__background has-black-background-color has-background-dim"></span>';
						$output .= '<img class="wp-block-cover__image-background wp-image-' . (int) $img['id'] . '" alt="' . esc_attr( $kw ) . '" src="' . esc_url( $img['url'] ) . '" data-object-fit="cover"/>';
					} else {
						$output .= "<!-- wp:cover {\"dimRatio\":50,\"overlayColor\":\"vivid-cyan-blue\",\"minHeight\":400} -->\n";
						$output .= '<div class="wp-block-cover" style="min-height:400px">';
						$output .= '<span aria-hidden="true" class="wp-block-cover__background has-vivid-cyan-blue-background-color has-background-dim"></span>';
					}
					$output .= '<div class="wp-block-cover__inner-container">';
					if ( $heading ) {
						$output .= "<!-- wp:heading {\"textColor\":\"white\",\"level\":1} -->\n";
						$output .= '<h1 class="wp-block-heading has-white-color has-text-color">' . $heading . "</h1>\n";
						$output .= "<!-- /wp:heading -->\n";
					}
					if ( $subtext ) {
						$output .= "<!-- wp:paragraph {\"textColor\":\"white\"} -->\n";
						$output .= '<p class="has-white-color has-text-color">' . $subtext . "</p>\n";
						$output .= "<!-- /wp:paragraph -->\n";
					}
					$output .= "</div></div>\n";
					$output .= "<!-- /wp:cover -->\n\n";
					break;

				case 'columns':
					$cols = is_array( $block['columns'] ?? null ) ? $block['columns'] : array();
					if ( empty( $cols ) ) {
						break;
					}
					$col_count = count( $cols );
					$output   .= "<!-- wp:columns {\"align\":\"full\",\"style\":{\"spacing\":{\"padding\":{\"left\":\"5vw\",\"right\":\"5vw\"}}}} -->\n";
					$output   .= '<div class="wp-block-columns alignfull" style="padding-left:5vw;padding-right:5vw;">' . "\n";
					foreach ( $cols as $col ) {
						$col_heading = wp_kses_post( $col['heading'] ?? '' );
						$col_content = wp_kses_post( $col['content'] ?? '' );
						$output     .= "<!-- wp:column -->\n";
						$output     .= '<div class="wp-block-column">' . "\n";
						if ( $col_heading ) {
							$output .= "<!-- wp:heading {\"level\":3} -->\n";
							$output .= '<h3 class="wp-block-heading">' . $col_heading . "</h3>\n";
							$output .= "<!-- /wp:heading -->\n";
						}
						if ( $col_content ) {
							$output .= "<!-- wp:paragraph -->\n";
							$output .= '<p>' . $col_content . "</p>\n";
							$output .= "<!-- /wp:paragraph -->\n";
						}
						$output .= "</div>\n";
						$output .= "<!-- /wp:column -->\n";
					}
					$output .= "</div>\n";
					$output .= "<!-- /wp:columns -->\n\n";
					break;

				case 'list':
					$items   = is_array( $block['items'] ?? null ) ? $block['items'] : array();
					$ordered = ! empty( $block['ordered'] );
					$tag     = $ordered ? 'ol' : 'ul';
					$attrs   = $ordered ? ' {"ordered":true}' : '';
					$output .= "<!-- wp:list{$attrs} -->\n";
					$output .= "<{$tag} class=\"wp-block-list\">\n";
					foreach ( $items as $item ) {
						$output .= "<!-- wp:list-item --><li>" . wp_kses_post( $item ) . "</li><!-- /wp:list-item -->\n";
					}
					$output .= "</{$tag}>\n";
					$output .= "<!-- /wp:list -->\n\n";
					break;

				case 'buttons':
					$btns = is_array( $block['buttons'] ?? null ) ? $block['buttons'] : array();
					if ( empty( $btns ) ) {
						break;
					}
					$output .= "<!-- wp:buttons -->\n";
					$output .= '<div class="wp-block-buttons">' . "\n";
					foreach ( $btns as $btn ) {
						$btn_text = esc_html( $btn['text'] ?? 'Learn More' );
						$btn_url  = '#';
						$output  .= "<!-- wp:button -->\n";
						$output  .= '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="' . esc_url( $btn_url ) . '">' . $btn_text . '</a></div>' . "\n";
						$output  .= "<!-- /wp:button -->\n";
					}
					$output .= "</div>\n";
					$output .= "<!-- /wp:buttons -->\n\n";
					break;

				case 'table':
					$header = is_array( $block['header'] ?? null ) ? $block['header'] : array();
					$rows   = is_array( $block['rows'] ?? null ) ? $block['rows'] : array();
					if ( empty( $header ) && empty( $rows ) ) {
						break;
					}
					$output .= "<!-- wp:table {\"hasFixedLayout\":true,\"align\":\"full\",\"style\":{\"spacing\":{\"padding\":{\"left\":\"5vw\",\"right\":\"5vw\"}}}} -->\n";
					$output .= '<figure class="wp-block-table alignfull" style="padding-left:5vw;padding-right:5vw;"><table class="has-fixed-layout">' . "\n";
					if ( $header ) {
						$output .= '<thead><tr>';
						foreach ( $header as $th ) {
							$output .= '<th>' . esc_html( $th ) . '</th>';
						}
						$output .= '</tr></thead>' . "\n";
					}
					if ( $rows ) {
						$output .= '<tbody>' . "\n";
						foreach ( $rows as $row ) {
							if ( ! is_array( $row ) ) {
								continue;
							}
							$output .= '<tr>';
							foreach ( $row as $td ) {
								$output .= '<td>' . wp_kses_post( $td ) . '</td>';
							}
							$output .= '</tr>' . "\n";
						}
						$output .= '</tbody>' . "\n";
					}
					$output .= '</table></figure>' . "\n";
					$output .= "<!-- /wp:table -->\n\n";
					break;

				case 'quote':
					$citation = sanitize_text_field( $block['citation'] ?? '' );
					$output  .= "<!-- wp:quote -->\n";
					$output  .= '<blockquote class="wp-block-quote">' . "\n";
					$output  .= "<!-- wp:paragraph --><p>" . wp_kses_post( $content ) . "</p><!-- /wp:paragraph -->\n";
					if ( $citation ) {
						$output .= '<cite>' . esc_html( $citation ) . '</cite>' . "\n";
					}
					$output .= '</blockquote>' . "\n";
					$output .= "<!-- /wp:quote -->\n\n";
					break;

				case 'separator':
					$output .= "<!-- wp:separator {\"align\":\"wide\"} -->\n";
					$output .= '<hr class="wp-block-separator has-alpha-channel-opacity alignwide"/>' . "\n";
					$output .= "<!-- /wp:separator -->\n\n";
					break;

				case 'media_text':
					$mt_heading  = wp_kses_post( $block['heading'] ?? '' );
					$mt_content  = wp_kses_post( $block['content'] ?? '' );
					$kw          = $block['keyword'] ?? '';
					$img         = $images[ $kw ] ?? null;
					$media_pos   = ( 'right' === ( $block['media_position'] ?? 'left' ) ) ? 'right' : 'left';
					$media_right = ( 'right' === $media_pos ) ? ',"mediaPosition":"right"' : '';
					if ( $img ) {
						$attrs   = wp_json_encode( array(
							'mediaId'       => $img['id'],
							'mediaType'     => 'image',
							'mediaPosition' => $media_pos,
						) );
						$output .= "<!-- wp:media-text {$attrs} -->\n";
						$output .= '<div class="wp-block-media-text alignwide is-stacked-on-mobile' . ( 'right' === $media_pos ? ' has-media-on-the-right' : '' ) . '">' . "\n";
						$output .= '<figure class="wp-block-media-text__media">';
						$output .= '<img src="' . esc_url( $img['url'] ) . '" alt="' . esc_attr( $kw ) . '" class="wp-image-' . (int) $img['id'] . '"/>';
						$output .= "</figure>\n";
					} else {
						$output .= '<!-- wp:media-text {"mediaType":"image","mediaPosition":"' . esc_attr( $media_pos ) . '"} -->' . "\n";
						$output .= '<div class="wp-block-media-text alignwide is-stacked-on-mobile' . ( 'right' === $media_pos ? ' has-media-on-the-right' : '' ) . '">' . "\n";
						$output .= '<figure class="wp-block-media-text__media"></figure>' . "\n";
					}
					$output .= '<div class="wp-block-media-text__content">' . "\n";
					if ( $mt_heading ) {
						$output .= "<!-- wp:heading {\"level\":3} -->\n";
						$output .= '<h3 class="wp-block-heading">' . $mt_heading . "</h3>\n";
						$output .= "<!-- /wp:heading -->\n";
					}
					if ( $mt_content ) {
						$output .= "<!-- wp:paragraph -->\n";
						$output .= '<p>' . $mt_content . "</p>\n";
						$output .= "<!-- /wp:paragraph -->\n";
					}
					$output .= "</div>\n</div>\n";
					$output .= "<!-- /wp:media-text -->\n\n";
					break;
			}
		}
		return $output;
	}

	/**
	 * Build Elementor page data JSON structure from a blocks array.
	 *
	 * @param array $blocks LLM-generated blocks array.
	 * @param array $images Optional pre-fetched image map: keyword → ['id'=>int,'url'=>string].
	 * @return array Elementor data ready for _elementor_data meta.
	 */
	public function build_elementor_data( array $blocks, array $images = array() ): array {
		$widgets = array();
		foreach ( $blocks as $block ) {
			$type    = $block['type'] ?? '';
			$content = $block['content'] ?? '';

			switch ( $type ) {
				case 'heading':
					$widgets[] = array(
						'id'         => wp_generate_password( 7, false ),
						'elType'     => 'widget',
						'widgetType' => 'heading',
						'settings'   => array(
							'title' => wp_strip_all_tags( $content ),
							'size'  => 'h' . max( 2, min( 6, (int) ( $block['level'] ?? 2 ) ) ),
						),
					);
					break;

				case 'paragraph':
					$widgets[] = array(
						'id'         => wp_generate_password( 7, false ),
						'elType'     => 'widget',
						'widgetType' => 'text-editor',
						'settings'   => array(
							'editor' => wp_kses_post( $content ),
						),
					);
					break;

				case 'image':
				case 'cover':
				case 'media_text':
					$kw  = $block['keyword'] ?? '';
					$img = $images[ $kw ] ?? null;
					$widgets[] = array(
						'id'         => wp_generate_password( 7, false ),
						'elType'     => 'widget',
						'widgetType' => 'image',
						'settings'   => array(
							'image' => array(
								'id'  => $img ? $img['id'] : '',
								'url' => $img ? $img['url'] : '',
								'alt' => esc_attr( $kw ),
							),
						),
					);
					// For cover/media_text also output any accompanying text.
					if ( 'cover' === $type || 'media_text' === $type ) {
						$ht = wp_strip_all_tags( $block['heading'] ?? '' );
						$ct = wp_kses_post( $block['content'] ?? $block['subtext'] ?? '' );
						if ( $ht ) {
							$widgets[] = array(
								'id'         => wp_generate_password( 7, false ),
								'elType'     => 'widget',
								'widgetType' => 'heading',
								'settings'   => array( 'title' => $ht, 'size' => 'h2' ),
							);
						}
						if ( $ct ) {
							$widgets[] = array(
								'id'         => wp_generate_password( 7, false ),
								'elType'     => 'widget',
								'widgetType' => 'text-editor',
								'settings'   => array( 'editor' => $ct ),
							);
						}
					}
					break;

				case 'list':
					$items = is_array( $block['items'] ?? null ) ? $block['items'] : array();
					$list_items = array();
					foreach ( $items as $item ) {
						$list_items[] = array( 'text' => array( 'text' => wp_strip_all_tags( $item ) ) );
					}
					$widgets[] = array(
						'id'         => wp_generate_password( 7, false ),
						'elType'     => 'widget',
						'widgetType' => 'icon-list',
						'settings'   => array( 'icon_list' => $list_items ),
					);
					break;

				case 'buttons':
					$btns = is_array( $block['buttons'] ?? null ) ? $block['buttons'] : array();
					foreach ( $btns as $btn ) {
						$widgets[] = array(
							'id'         => wp_generate_password( 7, false ),
							'elType'     => 'widget',
							'widgetType' => 'button',
							'settings'   => array(
								'text' => sanitize_text_field( $btn['text'] ?? 'Learn More' ),
								'link' => array( 'url' => '#' ),
							),
						);
					}
					break;

				case 'quote':
					$widgets[] = array(
						'id'         => wp_generate_password( 7, false ),
						'elType'     => 'widget',
						'widgetType' => 'testimonial',
						'settings'   => array(
							'testimonial_content' => wp_kses_post( $content ),
							'testimonial_name'    => sanitize_text_field( $block['citation'] ?? '' ),
						),
					);
					break;

				case 'table':
					// Elementor doesn't have a native table widget in the free version;
					// render as text-editor HTML instead.
					$header = is_array( $block['header'] ?? null ) ? $block['header'] : array();
					$rows   = is_array( $block['rows'] ?? null ) ? $block['rows'] : array();
					$html   = '<table style="width:100%;border-collapse:collapse;">';
					if ( $header ) {
						$html .= '<thead><tr>';
						foreach ( $header as $th ) {
							$html .= '<th style="border:1px solid #ddd;padding:8px;">' . esc_html( $th ) . '</th>';
						}
						$html .= '</tr></thead>';
					}
					if ( $rows ) {
						$html .= '<tbody>';
						foreach ( $rows as $row ) {
							if ( ! is_array( $row ) ) {
								continue;
							}
							$html .= '<tr>';
							foreach ( $row as $td ) {
								$html .= '<td style="border:1px solid #ddd;padding:8px;">' . wp_kses_post( $td ) . '</td>';
							}
							$html .= '</tr>';
						}
						$html .= '</tbody>';
					}
					$html     .= '</table>';
					$widgets[] = array(
						'id'         => wp_generate_password( 7, false ),
						'elType'     => 'widget',
						'widgetType' => 'text-editor',
						'settings'   => array( 'editor' => $html ),
					);
					break;

				case 'columns':
					$cols = is_array( $block['columns'] ?? null ) ? $block['columns'] : array();
					foreach ( $cols as $col ) {
						$ch = wp_strip_all_tags( $col['heading'] ?? '' );
						$cc = wp_kses_post( $col['content'] ?? '' );
						if ( $ch ) {
							$widgets[] = array(
								'id'         => wp_generate_password( 7, false ),
								'elType'     => 'widget',
								'widgetType' => 'heading',
								'settings'   => array( 'title' => $ch, 'size' => 'h3' ),
							);
						}
						if ( $cc ) {
							$widgets[] = array(
								'id'         => wp_generate_password( 7, false ),
								'elType'     => 'widget',
								'widgetType' => 'text-editor',
								'settings'   => array( 'editor' => $cc ),
							);
						}
					}
					break;

				case 'separator':
					// No native separator in Elementor free; skip.
					break;
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
	 * @param string $color   Optional Unsplash color filter (black_and_white, black, white,
	 *                        yellow, orange, red, purple, magenta, green, teal, blue).
	 *                        Empty string = no filter. Derived from get_image_style_directive().
	 * @return int Attachment ID, or 0 on failure.
	 */
	public function fetch_unsplash_image( string $keyword, string $color = '' ): int {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'GrayFox Unsplash: fetching "%s" (call %d/%d)', $keyword, $this->unsplash_calls + 1, self::UNSPLASH_PER_RUN_LIMIT ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		$enc_key = get_option( self::UNSPLASH_OPTION, '' );
		if ( empty( $enc_key ) ) {
			return 0;
		}
		$api_key = grayfox_decrypt( $enc_key );
		if ( empty( $api_key ) ) {
			return 0;
		}

		$query_args = array(
			'query'    => $keyword,
			'per_page' => 1,
		);
		// Valid Unsplash color filter values — whitelist before passing to the API.
		$valid_colors = array( 'black_and_white', 'black', 'white', 'yellow', 'orange', 'red', 'purple', 'magenta', 'green', 'teal', 'blue' );
		if ( $color && in_array( $color, $valid_colors, true ) ) {
			$query_args['color'] = $color;
		}
		$url = 'https://api.unsplash.com/search/photos?' . http_build_query( $query_args );
		$response = wp_remote_get( $url, array(
			'headers' => array( 'Authorization' => 'Client-ID ' . $api_key ),
			'timeout' => 15,
		) );

		if ( is_wp_error( $response ) ) {
			return 0;
		}

		// Treat a 429 as a hard signal that the rate limit is exhausted.
		// Set the counter to the cap so subsequent calls skip Unsplash entirely
		// and go straight to DALL-E for the rest of this run.
		if ( 429 === (int) wp_remote_retrieve_response_code( $response ) ) {
			$this->unsplash_calls = self::UNSPLASH_PER_RUN_LIMIT;
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'GrayFox Unsplash: rate limit hit — switching to DALL-E for remainder of run.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			return 0;
		}

		$body    = json_decode( wp_remote_retrieve_body( $response ), true );
		$photo   = $body['results'][0] ?? array();
		$img_url = $photo['urls']['regular'] ?? '';

		if ( empty( $img_url ) ) {
			return 0;
		}

		// Rich metadata from the Unsplash API response.
		$alt_text        = trim( $photo['alt_description'] ?? $keyword );
		$unsplash_desc   = trim( $photo['description']     ?? '' );
		$photographer    = trim( $photo['user']['name']    ?? '' );
		$unsplash_link   = trim( $photo['links']['html']   ?? '' );

		// Caption attributes Unsplash credit (required by their guidelines).
		$caption = $photographer
			? sprintf( 'Photo by %s on Unsplash', $photographer )
			: 'Photo from Unsplash';
		if ( $unsplash_link ) {
			$caption = sprintf( '<a href="%s" target="_blank" rel="noopener">%s</a>', esc_url( $unsplash_link ), $caption );
		}

		// Description: use Unsplash's own description if available, else the keyword.
		$description = $unsplash_desc ?: ucwords( $keyword );

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		// media_sideload_image rejects Unsplash URLs (no .jpg extension in path).
		// Download manually and sideload with an explicit filename so WP accepts it.
		$tmp = download_url( $img_url, 30 );
		if ( is_wp_error( $tmp ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'GrayFox Unsplash download_url error: ' . $tmp->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			return 0;
		}

		$safe_name = sanitize_file_name( $keyword ) . '.jpg';
		$file = array(
			'name'     => $safe_name,
			'type'     => 'image/jpeg',
			'tmp_name' => $tmp,
			'error'    => 0,
			'size'     => filesize( $tmp ),
		);

		$overrides       = array( 'test_form' => false, 'test_size' => true );
		$sideload_result = wp_handle_sideload( $file, $overrides );

		wp_delete_file( $tmp );

		if ( ! empty( $sideload_result['error'] ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'GrayFox Unsplash sideload error: ' . $sideload_result['error'] ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			return 0;
		}

		$attachment = array(
			'post_mime_type' => $sideload_result['type'] ?? 'image/jpeg',
			'post_title'     => $keyword,       // kept as keyword so find_cached_image() can match it
			'post_excerpt'   => $caption,       // caption (Unsplash credit)
			'post_content'   => $description,   // description
			'post_status'    => 'inherit',
		);
		$attachment_id = wp_insert_attachment( $attachment, $sideload_result['file'] );
		if ( is_wp_error( $attachment_id ) ) {
			return 0;
		}
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $sideload_result['file'] ) );
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );

		++$this->unsplash_calls;
		return (int) $attachment_id;
	}

	/**
	 * Generate an image via DALL-E 3 and sideload it into the WP media library.
	 *
	 * Falls back gracefully if no OpenAI key is configured or the API call fails.
	 *
	 * @param string $prompt       Subject/keyword for the image.
	 * @param string $context      Page title used as the WP attachment title.
	 * @param string $style_suffix Optional style directive appended to the prompt
	 *                             (e.g. "professional photography, neutral tones, no logos").
	 *                             Derived from get_image_style_directive().
	 * @return int Attachment ID, or 0 on failure.
	 */
	public function fetch_dalle_image( string $prompt, string $context, string $style_suffix = '' ): int {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'GrayFox DALL-E: generating image for "%s" (context: %s)', $prompt, $context ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		$provider = get_option( 'grayfox_llm_provider', 'openai' );
		if ( 'openai' !== $provider ) {
			return 0;
		}

		$enc_key = get_option( 'grayfox_llm_api_key', '' );
		$api_key = grayfox_decrypt( $enc_key );
		if ( empty( $api_key ) ) {
			return 0;
		}

		$full_prompt = $style_suffix
			? sanitize_text_field( $prompt ) . ', ' . sanitize_text_field( $style_suffix )
			: sanitize_text_field( $prompt );

		$body = wp_json_encode( array(
			'model'   => 'dall-e-3',
			'prompt'  => $full_prompt,
			'n'       => 1,
			'size'    => '1792x1024',
			'quality' => 'standard',
		) );

		$response = wp_remote_post( 'https://api.openai.com/v1/images/generations', array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'body'    => $body,
			'timeout' => 60,
		) );

		if ( is_wp_error( $response ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'GrayFox DALL-E error: ' . $response->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			return 0;
		}

		$data    = json_decode( wp_remote_retrieve_body( $response ), true );
		$img_url = $data['data'][0]['url'] ?? '';

		if ( empty( $img_url ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'GrayFox DALL-E: no image URL in response for prompt: ' . $prompt ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			return 0;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$attachment_id = media_sideload_image( $img_url, 0, sanitize_text_field( $context ), 'id' );

		if ( is_wp_error( $attachment_id ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'GrayFox DALL-E sideload error: ' . $attachment_id->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			return 0;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'GrayFox DALL-E: sideloaded att=%d for "%s"', (int) $attachment_id, $prompt ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		return (int) $attachment_id;
	}

	/**
	 * Search the Media Library for an existing attachment matching a keyword.
	 *
	 * Attachment title is set to the search keyword when images are sideloaded,
	 * so this acts as a persistent cache — returning the same image on repeat runs
	 * without making any external API call.
	 *
	 * @param string $keyword Image search keyword.
	 * @return int Attachment ID, or 0 if not found.
	 */
	private function find_cached_image( string $keyword ): int {
		global $wpdb;
		if ( empty( $keyword ) ) {
			return 0;
		}
		$att_id = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status = 'inherit' AND post_title = %s LIMIT 1",
				$keyword
			)
		);
		return $att_id;
	}

	/**
	 * Return the cheapest/fastest model for a given provider.
	 *
	 * Used for lightweight calls (intent translation, pattern selection) where
	 * speed and cost matter more than maximum reasoning ability.
	 *
	 * @param string $provider Provider slug (openai, anthropic, etc.).
	 * @param string $model    Currently configured primary model.
	 * @return string Small model identifier.
	 */
	public static function get_small_model( string $provider, string $model ): string {
		if ( 'openai' === $provider ) {
			return 'gpt-4o-mini';
		}
		if ( 'anthropic' === $provider ) {
			return 'claude-haiku-4-5-20251001';
		}
		// For other providers, fall back to the configured model.
		return $model;
	}

	/**
	 * Action Scheduler job: revise a single generated page.
	 *
	 * Handles three revision modes dispatched from the Results table:
	 *   revise_copy — rewrites text while preserving block structure and keywords
	 *   rearrange   — re-selects a layout pattern and restructures the page
	 *   new_images  — re-fetches images for all image blocks, bypassing cache
	 *
	 * @param array $args {
	 *   post_id            int    Required. Page to revise.
	 *   action_type        string Required. 'revise_copy' | 'rearrange' | 'new_images'.
	 *   dropdown_selection string Preset direction chosen by the user.
	 *   user_hint          string Optional short additional context (≤50 chars).
	 * }
	 */
	public function handle_revise_page( array $args ): void {
		$post_id   = (int) ( $args['post_id']            ?? 0 );
		$action    = sanitize_key( $args['action_type']  ?? '' );
		$preset    = sanitize_text_field( $args['dropdown_selection'] ?? '' );
		$hint      = sanitize_text_field( mb_substr( $args['user_hint'] ?? '', 0, 50 ) );

		if ( ! $post_id || ! $action ) {
			return;
		}

		$this->update_revision_status( $post_id, 'processing' );

		try {
			$provider = get_option( 'grayfox_llm_provider', 'openai' );
			$enc_key  = get_option( 'grayfox_llm_api_key', '' );
			$api_key  = grayfox_decrypt( $enc_key );
			$model    = get_option( 'grayfox_llm_model', '' );

			if ( empty( $api_key ) || empty( $model ) ) {
				$this->update_revision_status( $post_id, 'error' );
				return;
			}

			$llm          = new GrayFox_LLM();
			$small_model  = self::get_small_model( $provider, $model );
			$profile      = get_transient( self::BUSINESS_PROFILE_TRANSIENT );
			$profile      = is_array( $profile ) ? $profile : array();
			$company_name = ! empty( $profile['name'] ) ? $profile['name'] : get_bloginfo( 'name' );
			$post         = get_post( $post_id );
			$page_title   = $post ? $post->post_title : '';
			$content_brief = get_post_meta( $post_id, '_grayfox_content_brief', true ) ?: $page_title;
			$blocks_json  = get_post_meta( $post_id, self::META_BLOCKS_JSON, true ) ?: '';
			$image_map    = json_decode( get_post_meta( $post_id, self::META_IMAGE_MAP, true ) ?: '{}', true );
			$format       = get_option( self::FORMAT_OPTION, 'blocks' );
			$img_directive = self::get_image_style_directive( $profile );

			// Step 1: Translate user intent into a precise directive (if hint or preset given).
			$revision_directive = $preset;
			if ( ! empty( $preset ) || ! empty( $hint ) ) {
				$translate_msgs = array(
					array(
						'role'    => 'system',
						'content' => GRAYFOX_PROMPT_SITE_BUILDER_INTENT_TRANSLATE_SYSTEM,
					),
					array(
						'role'    => 'user',
						'content' => str_replace(
							array( '{{REVISION_TYPE}}', '{{PRESET_DIRECTION}}', '{{USER_HINT}}' ),
							array(
								$action === 'revise_copy' ? 'Copy revision' : ( $action === 'rearrange' ? 'Layout rearrangement' : 'Image replacement' ),
								$preset,
								$hint ?: 'none',
							),
							GRAYFOX_PROMPT_SITE_BUILDER_INTENT_TRANSLATE_USER
						),
					),
				);
				$translated = trim( $llm->request_text( $provider, $api_key, $small_model, $translate_msgs, 0.3 ) );
				if ( ! empty( $translated ) ) {
					$revision_directive = $translated;
				}
			}

			// Step 2: Route harness-built pages to dedicated handlers.
			$harness_meta = json_decode( $blocks_json, true );
			$is_harness   = is_array( $harness_meta ) && ! empty( $harness_meta['harness_built'] );

			if ( $is_harness ) {
				if ( 'new_images' === $action ) {
					$this->revise_harness_images( $post_id, $page_title, $img_directive );
				} elseif ( 'revise_copy' === $action ) {
					$this->revise_harness_copy( $post_id, $page_title, $company_name, $revision_directive, $llm, $provider, $api_key, $model );
				} elseif ( 'rearrange' === $action ) {
					$this->revise_harness_rearrange( $post_id, $page_title, $company_name, $revision_directive, $llm, $provider, $api_key, $model, $img_directive );
				}
				$this->update_revision_status( $post_id, 'done' );
				return;
			}

			// Step 3: Legacy block-builder revision path.
			if ( 'new_images' === $action ) {
				// Re-fetch all images bypassing Media Library cache — always hits external APIs.
				if ( empty( $blocks_json ) ) {
					$this->update_revision_status( $post_id, 'error' );
					return;
				}
				$blocks       = json_decode( $blocks_json, true );
				$new_images   = array();
				$img_types    = array( 'image', 'cover', 'media_text' );
				$this->unsplash_calls = 0;

				foreach ( $blocks as $block ) {
					$type = $block['type'] ?? '';
					$kw   = $block['keyword'] ?? '';
					if ( ! in_array( $type, $img_types, true ) || empty( $kw ) || isset( $new_images[ $kw ] ) ) {
						continue;
					}
					$att_id = 0;
					if ( 'cover' === $type ) {
						$att_id = $this->fetch_dalle_image( $kw, $page_title, $img_directive['dalle_suffix'] );
					} else {
						if ( $this->unsplash_calls < self::UNSPLASH_PER_RUN_LIMIT ) {
							$att_id = $this->fetch_unsplash_image( $kw, $img_directive['unsplash_color'] );
						}
						if ( ! $att_id ) {
							$att_id = $this->fetch_dalle_image( $kw, $page_title, $img_directive['dalle_suffix'] );
						}
					}
					if ( $att_id ) {
						$new_images[ $kw ] = array(
							'id'  => $att_id,
							'url' => (string) wp_get_attachment_image_url( $att_id, 'full' ),
						);
					}
				}

				// Rebuild page with same blocks + new images.
				$post_content = $this->build_wp_blocks( $blocks, $new_images );
				wp_update_post( array( 'ID' => $post_id, 'post_content' => $post_content ) );
				update_post_meta( $post_id, self::META_IMAGE_MAP, wp_json_encode( $new_images ) );

			} elseif ( 'revise_copy' === $action ) {
				if ( empty( $blocks_json ) ) {
					$this->update_revision_status( $post_id, 'error' );
					return;
				}
				$messages = array(
					array(
						'role'    => 'system',
						'content' => GRAYFOX_PROMPT_SITE_BUILDER_REVISE_COPY_SYSTEM,
					),
					array(
						'role'    => 'user',
						'content' => str_replace(
							array( '{{COMPANY_NAME}}', '{{PAGE_TITLE}}', '{{REVISION_DIRECTIVE}}', '{{BLOCKS_JSON}}' ),
							array( $company_name, $page_title, $revision_directive, $blocks_json ),
							GRAYFOX_PROMPT_SITE_BUILDER_REVISE_COPY_USER
						),
					),
				);
				$raw    = $llm->request_json( $provider, $api_key, $model, $messages, 0.4 );
				$parsed = json_decode( $raw, true );

				if ( ! is_array( $parsed ) || empty( $parsed['blocks'] ) ) {
					$this->update_revision_status( $post_id, 'error' );
					return;
				}

				$new_blocks   = $parsed['blocks'];
				$post_content = $this->build_wp_blocks( $new_blocks, $image_map );
				wp_update_post( array( 'ID' => $post_id, 'post_content' => $post_content ) );
				update_post_meta( $post_id, self::META_BLOCKS_JSON, wp_json_encode( $new_blocks ) );

			} elseif ( 'rearrange' === $action ) {
				// Select a new pattern (different from the current one).
				$page_type = sanitize_key( get_post_meta( $post_id, '_grayfox_page_type', true ) ?: '' );
				$pattern   = $this->select_layout_pattern(
					$llm, $provider, $api_key, $small_model,
					$page_type, $page_title, $content_brief, $profile
				);

				$messages = array(
					array(
						'role'    => 'system',
						'content' => GRAYFOX_PROMPT_SITE_BUILDER_REARRANGE_SYSTEM,
					),
					array(
						'role'    => 'user',
						'content' => str_replace(
							array(
								'{{COMPANY_NAME}}', '{{PAGE_TITLE}}', '{{CONTENT_BRIEF}}',
								'{{REVISION_DIRECTIVE}}', '{{PATTERN_LABEL}}',
								'{{PATTERN_SEQUENCE}}', '{{BLOCKS_JSON}}',
							),
							array(
								$company_name, $page_title, $content_brief,
								$revision_directive, $pattern['label'],
								$pattern['sequence'], $blocks_json,
							),
							GRAYFOX_PROMPT_SITE_BUILDER_REARRANGE_USER
						),
					),
				);
				$raw    = $llm->request_json( $provider, $api_key, $model, $messages, 0.4 );
				$parsed = json_decode( $raw, true );

				if ( ! is_array( $parsed ) || empty( $parsed['blocks'] ) ) {
					$this->update_revision_status( $post_id, 'error' );
					return;
				}

				$new_blocks = $parsed['blocks'];

				// Sanitize new blocks.
				foreach ( $new_blocks as &$block ) {
					if ( isset( $block['keyword'] ) && is_array( $block['keyword'] ) ) {
						$block['keyword'] = implode( ' ', $block['keyword'] );
					}
					if ( isset( $block['keyword'] ) ) {
						$block['keyword'] = sanitize_text_field( (string) $block['keyword'] );
					}
					if ( isset( $block['heading'] ) ) $block['heading'] = wp_kses_post( $block['heading'] );
					if ( isset( $block['content'] ) ) $block['content'] = wp_kses_post( $block['content'] );
					if ( isset( $block['subtext'] ) ) $block['subtext'] = wp_kses_post( $block['subtext'] );
				}
				unset( $block );

				// Reuse existing images by keyword; fetch new ones for any new keywords.
				$new_images   = array();
				$img_types    = array( 'image', 'cover', 'media_text' );
				$this->unsplash_calls = 0;

				foreach ( $new_blocks as $block ) {
					$type = $block['type'] ?? '';
					$kw   = $block['keyword'] ?? '';
					if ( ! in_array( $type, $img_types, true ) || empty( $kw ) || isset( $new_images[ $kw ] ) ) {
						continue;
					}
					// Existing image for this keyword?
					if ( isset( $image_map[ $kw ] ) ) {
						$new_images[ $kw ] = $image_map[ $kw ];
						continue;
					}
					// Media Library cache.
					$att_id = $this->find_cached_image( $kw );
					if ( ! $att_id ) {
						if ( 'cover' === $type ) {
							$att_id = $this->fetch_dalle_image( $kw, $page_title, $img_directive['dalle_suffix'] );
						} else {
							if ( $this->unsplash_calls < self::UNSPLASH_PER_RUN_LIMIT ) {
								$att_id = $this->fetch_unsplash_image( $kw, $img_directive['unsplash_color'] );
							}
							if ( ! $att_id ) {
								$att_id = $this->fetch_dalle_image( $kw, $page_title, $img_directive['dalle_suffix'] );
							}
						}
					}
					if ( $att_id ) {
						$new_images[ $kw ] = array(
							'id'  => $att_id,
							'url' => (string) wp_get_attachment_image_url( $att_id, 'full' ),
						);
					}
				}

				$post_content = $this->build_wp_blocks( $new_blocks, $new_images );
				wp_update_post( array( 'ID' => $post_id, 'post_content' => $post_content ) );
				update_post_meta( $post_id, self::META_BLOCKS_JSON, wp_json_encode( $new_blocks ) );
				update_post_meta( $post_id, self::META_IMAGE_MAP,   wp_json_encode( $new_images ) );
			}

			$this->update_revision_status( $post_id, 'done' );

		} catch ( Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'GrayFox revise_page error for post ' . $post_id . ': ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			$this->update_revision_status( $post_id, 'error' );
		}
	}

	/**
	 * Update the revision status for a page in the build option.
	 *
	 * @param int    $post_id Page ID.
	 * @param string $status  'processing' | 'done' | 'error'.
	 */
	private function update_revision_status( int $post_id, string $status ): void {
		$build = get_option( self::BUILD_OPTION, array() );
		if ( ! isset( $build['pages'] ) || ! is_array( $build['pages'] ) ) {
			return;
		}
		foreach ( $build['pages'] as &$page ) {
			if ( (int) ( $page['post_id'] ?? 0 ) === $post_id ) {
				$page['revision_status'] = $status;
				break;
			}
		}
		unset( $page );
		update_option( self::BUILD_OPTION, $build );
	}

	/**
	 * Estimate token usage and cost for generating the sitemap pages.
	 *
	 * @param array $sitemap Sitemap pages array.
	 * @return array{input_tokens: int, output_tokens: int, total_tokens: int, estimated_cost: string, page_count: int}
	 */
	public function estimate_tokens( array $sitemap ): array {
		global $wpdb;
		$kb_table = esc_sql( GrayFox_DB::get_table( 'knowledge_base' ) );

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
