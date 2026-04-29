<?php
/**
 * Theme Builder — generates a standalone WordPress block theme from brand profile.
 *
 * The generated theme lives in wp-content/themes/grayfox-theme/ and appears in
 * Appearance → Themes exactly like any other parent theme. The user activates /
 * deactivates it there.
 *
 * @package GrayFox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GrayFox_ThemeBuilder
 */
class GrayFox_ThemeBuilder {

	/** WP option key for the saved brand profile. */
	const BRAND_PROFILE_OPTION = 'grayfox_brand_profile';

	/** Transient key for logo analysis results (1-hour TTL). */
	const LOGO_ANALYSIS_TRANSIENT = 'grayfox_logo_analysis';

	/** WP option key for the registry of all generated themes. */
	const GENERATED_THEMES_OPTION = 'grayfox_generated_themes';

	/** Maximum number of GrayFox themes that can exist at once. */
	const MAX_THEMES = 3;

	/** Slug prefix used for all generated themes. */
	const THEME_SLUG_PREFIX = 'grayfox-theme-';

	/**
	 * @var GrayFox_ThemeBuilder|null
	 */
	private static ?GrayFox_ThemeBuilder $instance = null;

	public static function get_instance(): GrayFox_ThemeBuilder {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register hooks.
	 *
	 * @param GrayFox_Loader $loader
	 */
	public function register( GrayFox_Loader $loader ): void {
		$loader->add_action( 'wp_ajax_grayfox_tb_analyze_logo',       $this, 'handle_analyze_logo' );
		$loader->add_action( 'wp_ajax_grayfox_tb_generate_theme',     $this, 'handle_generate_theme' );
		$loader->add_action( 'wp_ajax_grayfox_tb_save_brand_profile', $this, 'handle_save_brand_profile' );
		$loader->add_action( 'wp_ajax_grayfox_tb_apply_theme',        $this, 'handle_apply_theme' );
		$loader->add_action( 'wp_ajax_grayfox_tb_delete_theme',       $this, 'handle_delete_theme' );
	}

	/* ------------------------------------------------------------------
	 * AJAX Handlers
	 * ------------------------------------------------------------------ */

	public function handle_analyze_logo(): void {
		check_ajax_referer( 'grayfox_tb_analyze_logo' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'grayfox' ), 403 );
		}
		$attachment_id = absint( $_POST['logo_attachment_id'] ?? 0 );
		if ( ! $attachment_id ) {
			wp_send_json_error( __( 'No attachment ID provided.', 'grayfox' ) );
		}
		$analysis = $this->analyze_logo_image( $attachment_id );

		// Surface skip reasons as a top-level warning so the frontend can
		// inform the user that logo colors were not extracted.
		$warning = null;
		if ( ! empty( $analysis['skipped'] ) ) {
			$warning = match ( $analysis['reason'] ?? '' ) {
				'provider_no_vision'  => 'Your configured LLM provider does not support image analysis. Logo colors were not extracted.',
				'llm_not_configured'  => 'LLM API key or model is not configured. Logo colors were not extracted.',
				'unsupported_mime_type' => 'Logo file type is not supported for analysis (use JPG, PNG, or WebP).',
				'attachment_not_found', 'download_failed' => 'Could not retrieve the logo image. Logo colors were not extracted.',
				default               => 'Logo analysis was skipped. Brand colors will be inferred from your industry and guidelines instead.',
			};
		}

		wp_send_json_success( array( 'analysis' => $analysis, 'warning' => $warning ) );
	}

	public function handle_generate_theme(): void {
		check_ajax_referer( 'grayfox_tb_generate_theme' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'grayfox' ), 403 );
		}

		$logo_analysis_raw = isset( $_POST['logo_analysis'] ) ? wp_unslash( $_POST['logo_analysis'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$logo_analysis     = json_decode( $logo_analysis_raw, true );
		$logo_analysis     = is_array( $logo_analysis ) ? $logo_analysis : array();

		$brand_guidelines = isset( $_POST['brand_guidelines'] )
			? sanitize_textarea_field( wp_unslash( $_POST['brand_guidelines'] ) )
			: '';

		$profile = $this->generate_theme_profile( $logo_analysis, $brand_guidelines );
		if ( empty( $profile ) ) {
			wp_send_json_error( __( 'Theme generation failed. Please try again.', 'grayfox' ) );
		}
		wp_send_json_success( array( 'profile' => $profile ) );
	}

	public function handle_save_brand_profile(): void {
		check_ajax_referer( 'grayfox_tb_save_brand_profile' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'grayfox' ), 403 );
		}

		$raw     = isset( $_POST['profile'] ) ? wp_unslash( $_POST['profile'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$profile = json_decode( $raw, true );
		if ( ! is_array( $profile ) ) {
			wp_send_json_error( __( 'Invalid profile data.', 'grayfox' ) );
		}

		$sanitized               = $this->sanitize_brand_profile( $profile );
		$sanitized['generated_at'] = time();
		update_option( self::BRAND_PROFILE_OPTION, $sanitized );
		wp_send_json_success( array( 'saved' => true ) );
	}

	/**
	 * AJAX: Generate a new WordPress theme on disk (multi-theme, max 3).
	 *
	 * Each call creates a uniquely-slugged theme in wp-content/themes/.
	 * Returns an error if the 3-theme cap has been reached.
	 */
	public function handle_apply_theme(): void {
		check_ajax_referer( 'grayfox_tb_apply_theme' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'grayfox' ), 403 );
		}

		$profile = get_option( self::BRAND_PROFILE_OPTION, array() );
		if ( empty( $profile ) ) {
			wp_send_json_error( __( 'No brand profile saved. Complete Steps 1 and 2 first.', 'grayfox' ) );
		}

		// Enforce 3-theme cap.
		$registry = get_option( self::GENERATED_THEMES_OPTION, array() );
		if ( count( $registry ) >= self::MAX_THEMES ) {
			wp_send_json_error( array(
				'code'    => 'theme_limit_reached',
				'message' => __( 'You have reached the maximum of 3 GrayFox themes. Delete one before generating a new one.', 'grayfox' ),
			) );
		}

		// Build a unique slug and display name for this theme.
		$slug        = self::THEME_SLUG_PREFIX . gmdate( 'YmdHis' );
		$business    = get_option( 'grayfox_business_profile', array() );
		$biz_name    = ! empty( $business['name'] ) ? $business['name'] : 'GrayFox';
		// Support both manifest format (theme.name) and legacy flat format (visual_style).
		$theme_name   = $profile['theme']['name'] ?? '';
		$style_label  = ! empty( $theme_name ) ? $theme_name : ucfirst( $profile['visual_style'] ?? 'Theme' );
		$display_name = $style_label;

		$result = $this->generate_theme_files( $profile, $slug, $display_name );
		if ( ! $result['success'] ) {
			wp_send_json_error( $result['error'] );
		}

		// Optionally also update Elementor Global Kit if active.
		$apply_elementor = ! empty( $_POST['apply_elementor'] ) && '1' === $_POST['apply_elementor'];
		if ( $apply_elementor && defined( 'ELEMENTOR_VERSION' ) ) {
			$this->apply_elementor_kit( $profile );
		}

		// Register the new theme in the option registry.
		$visual_style = $profile['theme']['style_archetype'] ?? $profile['visual_style'] ?? 'clean';
		$registry[]   = array(
			'slug'         => $slug,
			'display_name' => $display_name,
			'visual_style' => $visual_style,
			'generated_at' => time(),
		);
		update_option( self::GENERATED_THEMES_OPTION, $registry );

		// Mark the profile with the most recently generated slug.
		$profile['theme_generated_at'] = time();
		$profile['theme_slug']         = $slug;
		update_option( self::BRAND_PROFILE_OPTION, $profile );

		wp_send_json_success( array(
			'slug'           => $slug,
			'display_name'   => $display_name,
			'visual_style'   => $profile['visual_style'] ?? 'clean',
			'generated_at'   => time(),
			'activate_url'   => wp_nonce_url( admin_url( 'themes.php?action=activate&stylesheet=' . rawurlencode( $slug ) ), 'switch-theme_' . $slug ),
			'themes_url'     => admin_url( 'themes.php' ),
			'remaining_slots' => self::MAX_THEMES - count( $registry ),
		) );
	}

	/**
	 * AJAX: Delete a GrayFox-generated theme from disk and the registry.
	 */
	public function handle_delete_theme(): void {
		check_ajax_referer( 'grayfox_tb_delete_theme' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'grayfox' ), 403 );
		}

		$slug = sanitize_key( wp_unslash( $_POST['theme_slug'] ?? '' ) );

		// Security: only GrayFox-generated themes.
		if ( empty( $slug ) || strpos( $slug, self::THEME_SLUG_PREFIX ) !== 0 ) {
			wp_send_json_error( __( 'Invalid theme slug.', 'grayfox' ) );
		}

		// Cannot delete the currently active theme.
		if ( get_stylesheet() === $slug ) {
			wp_send_json_error( __( 'This theme is currently active. Switch to a different theme in Appearance → Themes before deleting it here.', 'grayfox' ) );
		}

		// Delete the theme directory.
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;

		$theme_dir = get_theme_root() . '/' . $slug;
		if ( $wp_filesystem && $wp_filesystem->is_dir( $theme_dir ) ) {
			$wp_filesystem->delete( $theme_dir, true );
		}

		// Remove from registry.
		$registry = get_option( self::GENERATED_THEMES_OPTION, array() );
		$registry = array_values( array_filter( $registry, static function ( $t ) use ( $slug ) {
			return $t['slug'] !== $slug;
		} ) );
		update_option( self::GENERATED_THEMES_OPTION, $registry );

		wp_clean_themes_cache();

		wp_send_json_success( array(
			'deleted'         => $slug,
			'remaining_count' => count( $registry ),
			'can_create'      => count( $registry ) < self::MAX_THEMES,
		) );
	}

	/* ------------------------------------------------------------------
	 * Theme File Generation
	 * ------------------------------------------------------------------ */

	/**
	 * Write all theme files to wp-content/themes/{$slug}/.
	 *
	 * @param array  $profile      Brand profile.
	 * @param string $slug         Theme directory slug (unique per generation).
	 * @param string $display_name Human-readable theme name for style.css header.
	 * @return array{ success: bool, error: string, already_active: bool }
	 */
	private function generate_theme_files( array $profile, string $slug, string $display_name ): array {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			return array( 'success' => false, 'error' => 'Could not initialize WP_Filesystem.' );
		}

		$theme_dir = get_theme_root() . '/' . $slug;

		// Create directories.
		foreach ( array( $theme_dir, $theme_dir . '/templates', $theme_dir . '/parts', $theme_dir . '/patterns', $theme_dir . '/assets', $theme_dir . '/assets/js', $theme_dir . '/assets/css' ) as $dir ) {
			if ( ! $wp_filesystem->is_dir( $dir ) ) {
				wp_mkdir_p( $dir );
			}
		}

		// ── Resolve full manifest ──────────────────────────────────────────────
		// LLM produces a manifest with manifest['theme']['colors']. If the profile
		// is a full manifest, validate it; otherwise build a default manifest from
		// the flat legacy profile so all paths below use the same manifest shape.
		$has_llm_manifest = isset( $profile['theme']['colors'] );
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[TB] generate_theme_files: has_llm_manifest=' . ( $has_llm_manifest ? 'true' : 'false' ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		if ( $has_llm_manifest ) {
			// Stamp the slug so pattern headers reference the correct namespace.
			$profile['theme']['slug'] = $slug;
			$manifest = $profile;
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[TB] generate_theme_files: using LLM manifest path, front-page patterns=' . json_encode( $manifest['templates']['front-page.html']['patterns'] ?? 'NOT SET' ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}

			// Hard stop — do not write files from a broken manifest.
			if ( ! empty( $manifest['_validation_errors'] ) ) {
				$error_summary = implode( '; ', array_slice( $manifest['_validation_errors'], 0, 3 ) );
				return array(
					'success' => false,
					'error'   => 'Manifest validation failed after retries: ' . $error_summary,
				);
			}

			$fresh_errors = GrayFox_TB_ManifestValidator::validate( $manifest );
			if ( ! empty( $fresh_errors ) ) {
				$error_summary = implode( '; ', array_slice( $fresh_errors, 0, 3 ) );
				return array(
					'success' => false,
					'error'   => 'Saved manifest has validation errors: ' . $error_summary,
				);
			}
		} else {
			// Legacy flat profile — wrap into a manifest so builders get a consistent shape.
			$manifest = GrayFox_TB_ManifestBuilder::ensure_manifest( array_merge( $profile, array( 'slug' => $slug ) ) );
		}

		$theme  = $manifest['theme'];
		$assets = $manifest['assets'] ?? array();

		// ── Build root theme files ─────────────────────────────────────────────
		$files = array(
			$theme_dir . '/style.css'            => GrayFox_TB_StyleCSSBuilder::build( $theme, $assets ),
			$theme_dir . '/theme.json'           => GrayFox_TB_ThemeJsonBuilder::build( $theme ),
			$theme_dir . '/functions.php'        => GrayFox_TB_FunctionsPHPBuilder::build( $theme, $assets ),
			$theme_dir . '/assets/js/gf-theme.js' => GrayFox_TB_JSBuilder::build(),
		);

		// ── Template parts (header, footer, variants) ─────────────────────────
		foreach ( GrayFox_TB_PartBuilder::get_all_parts( $theme ) as $part_file => $part_content ) {
			$files[ $theme_dir . '/parts/' . $part_file ] = $part_content;
		}

		// ── Templates (index, front-page, page, single, 404, …) ───────────────
		foreach ( GrayFox_TB_TemplateBuilder::get_all_templates( $manifest ) as $tpl_file => $tpl_content ) {
			$files[ $theme_dir . '/templates/' . $tpl_file ] = $tpl_content;
		}

		// ── Patterns ──────────────────────────────────────────────────────────
		foreach ( GrayFox_TB_PatternBuilder::get_all_registered_patterns( $manifest ) as $pattern_file => $pattern_content ) {
			$files[ $theme_dir . '/patterns/' . $pattern_file ] = $pattern_content;
		}

		foreach ( $files as $path => $content ) {
			if ( false === $wp_filesystem->put_contents( $path, $content, FS_CHMOD_FILE ) ) {
				return array( 'success' => false, 'error' => 'Failed to write: ' . basename( $path ) );
			}
		}

		// Download Bootstrap CSS/JS into the theme's assets/ dir so the generated
		// theme serves them locally (WP Guideline 8 — no third-party CDN for JS/CSS).
		GrayFox_TB_FunctionsPHPBuilder::copy_bootstrap_assets(
			$theme_dir,
			! empty( $assets['load_bootstrap_icons'] )
		);

		// Write screenshot.png (binary) separately — GD required.
		if ( function_exists( 'imagecreatetruecolor' ) ) {
			$png = $this->build_screenshot_png( $theme );
			if ( $png ) {
				$wp_filesystem->put_contents( $theme_dir . '/screenshot.png', $png, FS_CHMOD_FILE );
			}
		}

		// Clear theme cache so WordPress picks up the new theme.
		wp_clean_themes_cache();

		// If the GrayFox theme is currently active, also clear the compiled
		// theme.json CSS cache so visitors immediately see the updated styles.
		if ( get_stylesheet() === $slug ) {
			// WP 6.0+ provides this resolver method; fall back to manual deletion.
			if ( class_exists( 'WP_Theme_JSON_Resolver' ) && method_exists( 'WP_Theme_JSON_Resolver', 'clean_cached_data' ) ) {
				WP_Theme_JSON_Resolver::clean_cached_data();
			} else {
				delete_transient( 'global_styles' );
				delete_transient( 'global_styles_user' );
				wp_cache_delete( 'wp_global_styles_id', 'options' );
			}
			// Clear FSE template/part caches.
			if ( function_exists( 'wp_cache_delete_group' ) ) {
				wp_cache_delete_group( 'block_templates' );
				wp_cache_delete_group( 'block_template_fallback' );
			}
		}

		return array(
			'success'      => true,
			'error'        => '',
			'already_active' => ( get_stylesheet() === $slug ),
		);
	}

	// build_style_css() and build_theme_json() removed — replaced by static
	// GrayFox_TB_StyleCSSBuilder and GrayFox_TB_ThemeJsonBuilder classes.

	/**
	 * Generate an 880×660 PNG screenshot via PHP GD so WP shows a real preview
	 * in Appearance → Themes. Returns raw PNG binary string (or empty on failure).
	 */
	private function build_screenshot_png( array $profile ): string {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			return '';
		}

		$colors     = $profile['colors'] ?? array();
		$primary    = $colors['primary']    ?? '#1a2e4a';
		$secondary  = $colors['secondary']  ?? '#2d6a8f';
		$accent     = $colors['accent']     ?? '#f4a723';
		$background = $colors['background'] ?? '#ffffff';
		$text_col   = $colors['text']       ?? '#1e1e1e';
		$muted      = $colors['muted']      ?? '#6b7280';

		$w  = 880;
		$h  = 660;
		$im = imagecreatetruecolor( $w, $h );
		imagealphablending( $im, true );
		imagesavealpha( $im, true );

		// Helper: solid GD colour from hex.
		$gc = function ( string $hex ) use ( $im ): int {
			$c = $this->hex_to_rgb( $hex );
			return imagecolorallocate( $im, $c[0], $c[1], $c[2] );
		};
		// Helper: hex + GD alpha (0=opaque … 127=transparent).
		$ga = function ( string $hex, int $alpha ) use ( $im ): int {
			$c = $this->hex_to_rgb( $hex );
			return imagecolorallocatealpha( $im, $c[0], $c[1], $c[2], $alpha );
		};
		$white_a = function ( int $alpha ) use ( $im ): int {
			return imagecolorallocatealpha( $im, 255, 255, 255, $alpha );
		};

		$c_bg     = $gc( $background );
		$c_text   = $gc( $text_col );
		$c_muted  = $gc( $muted );
		$c_accent = $gc( $accent );
		$c_pri    = $gc( $primary );

		// ── Canvas ──────────────────────────────────────────────────────────
		imagefilledrectangle( $im, 0, 0, $w, $h, $c_bg );

		// ── Nav bar ─────────────────────────────────────────────────────────
		imagefilledrectangle( $im, 0, 0, $w, 64, $c_pri );
		imagefilledrectangle( $im, 28, 18, 148, 46, $white_a( 80 ) ); // logo placeholder
		foreach ( array( 600, 654, 708, 768 ) as $nx ) {
			imagefilledrectangle( $im, $nx, 27, $nx + 38, 37, $white_a( 90 ) );
		}

		// ── Hero: vertical gradient primary → secondary ──────────────────────
		$rgb1 = $this->hex_to_rgb( $primary );
		$rgb2 = $this->hex_to_rgb( $secondary );
		for ( $y = 64; $y <= 290; $y++ ) {
			$t  = ( $y - 64 ) / ( 290 - 64 );
			$lc = imagecolorallocate( $im,
				(int) ( $rgb1[0] + ( $rgb2[0] - $rgb1[0] ) * $t ),
				(int) ( $rgb1[1] + ( $rgb2[1] - $rgb1[1] ) * $t ),
				(int) ( $rgb1[2] + ( $rgb2[2] - $rgb1[2] ) * $t )
			);
			imagefilledrectangle( $im, 0, $y, $w, $y, $lc );
		}
		// Hero text blocks.
		imagefilledrectangle( $im, 80, 106, 560, 136, $white_a( 20 ) ); // headline
		imagefilledrectangle( $im, 80, 146, 480, 159, $white_a( 45 ) ); // subtext 1
		imagefilledrectangle( $im, 80, 166, 420, 178, $white_a( 45 ) ); // subtext 2
		// Hero buttons.
		imagefilledrectangle( $im, 80, 198, 222, 228, $c_accent );           // primary CTA
		imagefilledrectangle( $im, 238, 198, 360, 228, $white_a( 80 ) );     // outline CTA

		// ── Features section ────────────────────────────────────────────────
		$feat_bg = $this->tint_color( $secondary, 0.92 );
		imagefilledrectangle( $im, 0, 290, $w, 462, $gc( $feat_bg ) );

		$card_w = 232;
		$cx0    = (int) ( ( $w - 3 * $card_w - 40 ) / 2 );
		for ( $i = 0; $i < 3; $i++ ) {
			$cx = $cx0 + $i * ( $card_w + 20 );
			$cy = 310;
			// Shadow.
			imagefilledrectangle( $im, $cx + 3, $cy + 3, $cx + $card_w + 3, $cy + 128, $ga( '#000000', 110 ) );
			// Card.
			imagefilledrectangle( $im, $cx, $cy, $cx + $card_w, $cy + 128, $gc( $background ) );
			imagefilledrectangle( $im, $cx, $cy, $cx + $card_w, $cy + 4, $c_accent );       // accent bar
			imagefilledellipse( $im, $cx + 28, $cy + 30, 24, 24, $c_pri );                  // icon
			imagefilledrectangle( $im, $cx + 14, $cy + 54, $cx + $card_w - 14, $cy + 65, $c_text );
			imagefilledrectangle( $im, $cx + 14, $cy + 73, $cx + $card_w - 28, $cy + 82, $c_muted );
			imagefilledrectangle( $im, $cx + 14, $cy + 90, $cx + $card_w - 48, $cy + 98, $c_muted );
		}

		// ── CTA band: horizontal gradient accent → primary ───────────────────
		$acc_rgb = $this->hex_to_rgb( $accent );
		$pri_rgb = $this->hex_to_rgb( $primary );
		for ( $x = 0; $x <= $w; $x++ ) {
			$t  = $x / $w;
			$lc = imagecolorallocate( $im,
				(int) ( $acc_rgb[0] + ( $pri_rgb[0] - $acc_rgb[0] ) * $t ),
				(int) ( $acc_rgb[1] + ( $pri_rgb[1] - $acc_rgb[1] ) * $t ),
				(int) ( $acc_rgb[2] + ( $pri_rgb[2] - $acc_rgb[2] ) * $t )
			);
			imagefilledrectangle( $im, $x, 462, $x, 552, $lc );
		}
		$mid = (int) ( $w / 2 );
		imagefilledrectangle( $im, $mid - 188, 478, $mid + 188, 496, $white_a( 30 ) );
		imagefilledrectangle( $im, $mid - 64,  512, $mid + 64,  536, $white_a( 60 ) );

		// ── Footer ──────────────────────────────────────────────────────────
		$c_foot = $gc( $this->darken_color( $primary, 0.25 ) );
		imagefilledrectangle( $im, 0, 552, $w, $h, $c_foot );
		imagefilledrectangle( $im, 0, 552, $w, 555, $c_accent ); // accent top-border
		foreach ( array( 40, 320, 600 ) as $fx ) {
			imagefilledrectangle( $im, $fx, 572, $fx + 140, 582, $white_a( 70 ) );
			imagefilledrectangle( $im, $fx, 594, $fx + 100, 602, $white_a( 90 ) );
			imagefilledrectangle( $im, $fx, 610, $fx + 120, 618, $white_a( 90 ) );
		}
		imagefilledrectangle( $im, 0, 636, $w, 637, $white_a( 100 ) );
		imagefilledrectangle( $im, $mid - 120, 643, $mid + 120, 652, $white_a( 90 ) );

		ob_start();
		imagepng( $im );
		$data = ob_get_clean();
		imagedestroy( $im );

		return $data ?: '';
	}

	/**
	 * Generate functions.php — theme setup, Google Fonts, style.css enqueue, and pattern category.
	 */
	private function build_functions_php( array $profile ): string {
		$typo         = $profile['typography'] ?? array();
		$heading_font = $typo['heading_font'] ?? 'Inter';
		$body_font    = $typo['body_font']    ?? 'Inter';

		$fonts_url    = $this->build_google_fonts_url( $heading_font, $body_font );

		$business  = get_option( 'grayfox_business_profile', array() );
		$site_name = ! empty( $business['name'] ) ? sanitize_text_field( $business['name'] ) : 'GrayFox Site';
		$generated = gmdate( 'Y-m-d H:i:s' );
		$version   = gmdate( 'Y.m.d' );
		// Cannot write a literal <?php opening tag inside a PHP file — store as string.
		$open_php  = '<?php';

		ob_start(); ?>
<?php echo $open_php; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

/**
 * <?php echo esc_html( $site_name ); ?> — Theme Functions
 * Generated by GrayFox Theme Builder on <?php echo esc_html( $generated ); ?>
 *
 * @package grayfox-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Theme setup.
 */
function grayfox_theme_setup(): void {
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'core-block-patterns' );
	register_nav_menus( array(
		'primary' => __( 'Primary Navigation', 'grayfox-theme' ),
		'footer'  => __( 'Footer Navigation',  'grayfox-theme' ),
	) );
}
add_action( 'after_setup_theme', 'grayfox_theme_setup' );

/**
 * Enqueue Google Fonts and theme stylesheet.
 */
function grayfox_theme_enqueue_assets(): void {
	wp_enqueue_style(
		'grayfox-theme-fonts',
		'<?php echo esc_url( $fonts_url ); ?>',
		array(),
		null
	);
	wp_enqueue_style(
		'grayfox-theme-style',
		get_stylesheet_uri(),
		array(),
		'<?php echo esc_html( $version ); ?>'
	);
}
add_action( 'wp_enqueue_scripts',    'grayfox_theme_enqueue_assets' );
add_action( 'enqueue_block_assets',  'grayfox_theme_enqueue_assets' );

/**
 * Register custom pattern category.
 */
function grayfox_theme_register_patterns(): void {
	register_block_pattern_category(
		'grayfox-sections',
		array( 'label' => __( 'GrayFox Sections', 'grayfox-theme' ) )
	);
}
add_action( 'init', 'grayfox_theme_register_patterns' );

/**
 * Seed a distinct "Footer Menu" navigation post on theme activation.
 *
 * In block themes, wp:navigation blocks reference wp_navigation posts by slug.
 * The footer template uses slug="footer" — this creates that post if it doesn't
 * exist yet, pre-populated with common footer links so it's immediately usable.
 * Users can edit it via Appearance → Menus or the Site Editor → Navigation.
 */
function grayfox_theme_seed_footer_nav(): void {
	if ( get_page_by_path( 'footer', OBJECT, 'wp_navigation' ) ) {
		return; // Already exists — respect any customizations the user made.
	}
	wp_insert_post( array(
		'post_type'    => 'wp_navigation',
		'post_title'   => 'Footer Menu',
		'post_name'    => 'footer',
		'post_status'  => 'publish',
		'post_content' =>
			'<!-- wp:navigation-link {"label":"Home","url":"/"} /-->' .
			'<!-- wp:navigation-link {"label":"About","url":"/about"} /-->' .
			'<!-- wp:navigation-link {"label":"Services","url":"/services"} /-->' .
			'<!-- wp:navigation-link {"label":"Contact","url":"/contact"} /-->',
	) );
}
add_action( 'after_switch_theme', 'grayfox_theme_seed_footer_nav' );
		<?php return ob_get_clean();
	}

	/**
	 * Default index template (blog / archive fallback).
	 */
	private function build_template_index(): string {
		ob_start(); ?>
<!-- wp:template-part {"slug":"header","tagName":"header"} /-->

<!-- wp:group {"tagName":"main","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"layout":{"type":"constrained"}} -->
<main class="wp-block-group">

	<!-- wp:query {"queryId":0,"query":{"perPage":10,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true}} -->
	<div class="wp-block-query">

		<!-- wp:post-template -->
		<!-- wp:group {"style":{"spacing":{"padding":{"bottom":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} -->
		<div class="wp-block-group">
			<!-- wp:post-featured-image {"isLink":true} /-->
			<!-- wp:post-title {"isLink":true} /-->
			<!-- wp:post-excerpt /-->
		</div>
		<!-- /wp:group -->
		<!-- /wp:post-template -->

		<!-- wp:query-pagination {"layout":{"type":"flex","justifyContent":"center"}} -->
		<!-- wp:query-pagination-previous /-->
		<!-- wp:query-pagination-numbers /-->
		<!-- wp:query-pagination-next /-->
		<!-- /wp:query-pagination -->

		<!-- wp:query-no-results -->
		<!-- wp:paragraph {"placeholder":"Add text or blocks that will display when a query returns no results."} -->
		<p></p>
		<!-- /wp:paragraph -->
		<!-- /wp:query-no-results -->

	</div>
	<!-- /wp:query -->

</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->
		<?php return ob_get_clean();
	}

	/**
	 * Front-page template — a complete homepage layout with all sections inlined.
	 * Unlike generic page.html this embeds hero, features, CTA and a content
	 * area directly so the homepage looks finished the moment the theme activates.
	 */
	private function build_template_front_page( array $profile ): string {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[TB] build_template_front_page: called — legacy inline method' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		$colors          = $profile['colors'] ?? array();
		$primary         = $colors['primary']    ?? '#1a2e4a';
		$secondary       = $colors['secondary']  ?? '#2d6a8f';
		$accent          = $colors['accent']     ?? '#f4a723';
		$background      = $colors['background'] ?? '#ffffff';
		$text            = $colors['text']       ?? '#1e1e1e';
		$muted           = $colors['muted']      ?? '#6b7280';
		$features_bg     = $this->tint_color( $secondary, 0.92 );
		$contrast        = $this->is_dark_color( $primary )  ? '#ffffff' : '#1a1a1a';
		$contrast_accent = $this->is_dark_color( $accent )   ? '#ffffff' : '#1a1a1a';
		$heading_weight  = $profile['typography']['heading_weight'] ?? '700';
		$letter_spacing  = $profile['heading_letter_spacing'] ?? '-0.02em';
		$business        = get_option( 'grayfox_business_profile', array() );
		$site_name       = ! empty( $business['name'] ) ? esc_html( $business['name'] ) : get_bloginfo( 'name' );
		$year            = gmdate( 'Y' );

		// Helper to render one feature card with a coloured icon box.
		$card = function ( string $title, string $body ) use ( $text, $muted, $primary ): string {
			return
				'<!-- wp:column {"className":"gf-feature-card card h-100"} -->' . "\n" .
				'<div class="wp-block-column gf-feature-card card h-100">' . "\n" .
				'<!-- wp:group {"className":"gf-feature-icon","style":{"color":{"background":"' . $primary . '"},"border":{"radius":"10px"},"dimensions":{"minHeight":"0px"},"spacing":{"padding":{"top":"0.75rem","bottom":"0.75rem","left":"0.75rem","right":"0.75rem"}}}} -->' . "\n" .
				'<div class="wp-block-group gf-feature-icon has-background" style="background-color:' . $primary . ';border-radius:10px;padding-top:0.75rem;padding-right:0.75rem;padding-bottom:0.75rem;padding-left:0.75rem;min-height:0px"></div>' . "\n" .
				'<!-- /wp:group -->' . "\n" .
				'<!-- wp:heading {"level":3,"style":{"typography":{"fontSize":"1.2rem","fontWeight":"700","lineHeight":"1.3"},"color":{"text":"' . $text . '"},"spacing":{"margin":{"top":"1.25rem","bottom":"0.5rem"}}}} -->' . "\n" .
				'<h3 class="wp-block-heading" style="font-size:1.2rem;font-weight:700;line-height:1.3;color:' . $text . ';margin-top:1.25rem;margin-bottom:0.5rem">' . esc_html( $title ) . '</h3>' . "\n" .
				'<!-- /wp:heading -->' . "\n" .
				'<!-- wp:paragraph {"style":{"color":{"text":"' . $muted . '"},"typography":{"fontSize":"0.9375rem","lineHeight":"1.65"}}} -->' . "\n" .
				'<p style="color:' . $muted . ';font-size:0.9375rem;line-height:1.65">' . esc_html( $body ) . '</p>' . "\n" .
				'<!-- /wp:paragraph -->' . "\n" .
				'</div>' . "\n" .
				'<!-- /wp:column -->';
		};

		$card1 = $card( 'Quality Service',    'We deliver excellence in everything we do. Your success is our primary measure of achievement.' );
		$card2 = $card( 'Expert Team',        'Our experienced professionals bring deep knowledge and genuine care to every project we take on.' );
		$card3 = $card( 'Proven Results',     'We have helped businesses like yours achieve their goals. Let us show you what is possible.' );

		ob_start(); ?>
<!-- wp:template-part {"slug":"header","tagName":"header"} /-->
<!-- wp:group {"tagName":"section","className":"gf-hero-section","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|80","bottom":"var:preset|spacing|80"},"blockGap":"0"}},"layout":{"type":"constrained","contentSize":"860px"}} -->
<section class="wp-block-group gf-hero-section alignfull" style="padding-top:var(--wp--preset--spacing--80);padding-bottom:var(--wp--preset--spacing--80)">
<!-- wp:paragraph {"style":{"color":{"text":"<?php echo esc_attr( $contrast ); ?>"},"typography":{"fontWeight":"700","fontSize":"0.75rem","letterSpacing":"0.12em","textTransform":"uppercase"},"spacing":{"margin":{"top":"0","bottom":"0.75rem"}}}} -->
<p style="color:<?php echo esc_attr( $contrast ); ?>;font-weight:700;font-size:0.75rem;letter-spacing:0.12em;text-transform:uppercase;margin-top:0;margin-bottom:0.75rem"><?php echo esc_html( $site_name ); ?></p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":1,"style":{"color":{"text":"<?php echo esc_attr( $contrast ); ?>"},"typography":{"fontSize":"clamp(2.75rem,5vw,4.5rem)","fontWeight":"<?php echo esc_attr( $heading_weight ); ?>","lineHeight":"1.08","letterSpacing":"<?php echo esc_attr( $letter_spacing ); ?>"},"spacing":{"margin":{"top":"0","bottom":"1.25rem"}}}} -->
<h1 class="wp-block-heading" style="color:<?php echo esc_attr( $contrast ); ?>;font-size:clamp(2.75rem,5vw,4.5rem);font-weight:<?php echo esc_attr( $heading_weight ); ?>;line-height:1.08;letter-spacing:<?php echo esc_attr( $letter_spacing ); ?>;margin-top:0;margin-bottom:1.25rem">Your Business Headline Goes Here</h1>
<!-- /wp:heading -->
<!-- wp:paragraph {"style":{"color":{"text":"<?php echo esc_attr( $contrast ); ?>"},"typography":{"fontSize":"1.2rem","lineHeight":"1.65"},"spacing":{"margin":{"top":"0","bottom":"2.25rem"}}}} -->
<p style="color:<?php echo esc_attr( $contrast ); ?>;font-size:1.2rem;line-height:1.65;margin-top:0;margin-bottom:2.25rem">Describe what your business does and the value it provides. Two compelling sentences that speak directly to your ideal customer.</p>
<!-- /wp:paragraph -->
<!-- wp:buttons {"style":{"spacing":{"blockGap":"1rem","margin":{"top":"0"}}}} -->
<div class="wp-block-buttons">
<!-- wp:button {"style":{"color":{"background":"<?php echo esc_attr( $accent ); ?>","text":"<?php echo esc_attr( $contrast_accent ); ?>"},"typography":{"fontWeight":"700"},"spacing":{"padding":{"top":"0.9rem","bottom":"0.9rem","left":"2.25rem","right":"2.25rem"}}}} -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" style="background-color:<?php echo esc_attr( $accent ); ?>;color:<?php echo esc_attr( $contrast_accent ); ?>;padding-top:0.9rem;padding-right:2.25rem;padding-bottom:0.9rem;padding-left:2.25rem;font-weight:700">Get Started →</a></div>
<!-- /wp:button -->
<!-- wp:button {"className":"is-style-outline","style":{"color":{"text":"<?php echo esc_attr( $contrast ); ?>"},"border":{"color":"<?php echo esc_attr( $contrast ); ?>","width":"2px"},"typography":{"fontWeight":"500"},"spacing":{"padding":{"top":"0.9rem","bottom":"0.9rem","left":"2.25rem","right":"2.25rem"}}}} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" style="color:<?php echo esc_attr( $contrast ); ?>;border-color:<?php echo esc_attr( $contrast ); ?>;border-width:2px;padding-top:0.9rem;padding-right:2.25rem;padding-bottom:0.9rem;padding-left:2.25rem;font-weight:500">Learn More</a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->
</section>
<!-- /wp:group -->
<!-- wp:group {"tagName":"section","align":"full","style":{"color":{"background":"<?php echo esc_attr( $features_bg ); ?>"},"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"}}},"layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull has-background" style="background-color:<?php echo esc_attr( $features_bg ); ?>;padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70)">
<!-- wp:group {"style":{"spacing":{"blockGap":"0.75rem","margin":{"bottom":"var:preset|spacing|60"}}},"layout":{"type":"constrained","contentSize":"600px"}} -->
<div class="wp-block-group" style="margin-bottom:var(--wp--preset--spacing--60)">
<!-- wp:heading {"level":2,"textAlign":"center","style":{"typography":{"fontSize":"clamp(2rem,4vw,3rem)","lineHeight":"1.15"},"color":{"text":"<?php echo esc_attr( $text ); ?>"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<h2 class="wp-block-heading has-text-align-center" style="font-size:clamp(2rem,4vw,3rem);line-height:1.15;color:<?php echo esc_attr( $text ); ?>;margin-top:0;margin-bottom:0">What We Offer</h2>
<!-- /wp:heading -->
<!-- wp:paragraph {"align":"center","style":{"color":{"text":"<?php echo esc_attr( $muted ); ?>"},"typography":{"fontSize":"1.05rem","lineHeight":"1.65"},"spacing":{"margin":{"top":"0.75rem","bottom":"0"}}}} -->
<p class="has-text-align-center" style="color:<?php echo esc_attr( $muted ); ?>;font-size:1.05rem;line-height:1.65;margin-top:0.75rem;margin-bottom:0">Our services are designed to help your business succeed and grow.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
<!-- wp:columns {"isStackedOnMobile":true,"style":{"spacing":{"blockGap":{"top":"1.5rem","left":"1.5rem"}}}} -->
<div class="wp-block-columns">
<?php echo $card1; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<?php echo $card2; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<?php echo $card3; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</div>
<!-- /wp:columns -->
</section>
<!-- /wp:group -->
<!-- wp:group {"tagName":"section","align":"full","style":{"color":{"background":"<?php echo esc_attr( $background ); ?>"},"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"}}},"layout":{"type":"constrained","contentSize":"760px"}} -->
<section class="wp-block-group alignfull has-background" style="background-color:<?php echo esc_attr( $background ); ?>;padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70)">
<!-- wp:heading {"level":2,"style":{"color":{"text":"<?php echo esc_attr( $text ); ?>"},"typography":{"fontSize":"clamp(1.875rem,3.5vw,2.75rem)","lineHeight":"1.2"},"spacing":{"margin":{"bottom":"1.25rem"}}}} -->
<h2 class="wp-block-heading" style="color:<?php echo esc_attr( $text ); ?>;font-size:clamp(1.875rem,3.5vw,2.75rem);line-height:1.2;margin-bottom:1.25rem">About <?php echo esc_html( $site_name ); ?></h2>
<!-- /wp:heading -->
<!-- wp:paragraph {"style":{"color":{"text":"<?php echo esc_attr( $muted ); ?>"},"typography":{"fontSize":"1.0625rem","lineHeight":"1.75"}}} -->
<p style="color:<?php echo esc_attr( $muted ); ?>;font-size:1.0625rem;line-height:1.75">Tell your story here. Who are you, what do you stand for, and why should a customer choose you? This section is automatically included on your homepage and can be edited anytime in Appearance → Editor.</p>
<!-- /wp:paragraph -->
<!-- wp:post-content {"layout":{"type":"constrained"}} /-->
</section>
<!-- /wp:group -->
<!-- wp:group {"tagName":"section","className":"gf-cta-band","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"}}},"layout":{"type":"constrained","contentSize":"900px"}} -->
<section class="wp-block-group gf-cta-band alignfull" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70)">
<!-- wp:group {"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","alignItems":"center"},"style":{"spacing":{"blockGap":"2rem"}}} -->
<div class="wp-block-group">
<!-- wp:group {"style":{"spacing":{"blockGap":"0.75rem"}},"layout":{"type":"constrained","contentSize":"520px"}} -->
<div class="wp-block-group">
<!-- wp:heading {"level":2,"style":{"color":{"text":"<?php echo esc_attr( $contrast ); ?>"},"typography":{"fontSize":"clamp(1.875rem,3.5vw,2.75rem)","fontWeight":"700","lineHeight":"1.15"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<h2 class="wp-block-heading" style="color:<?php echo esc_attr( $contrast ); ?>;font-size:clamp(1.875rem,3.5vw,2.75rem);font-weight:700;line-height:1.15;margin-top:0;margin-bottom:0">Ready to Work With <?php echo esc_html( $site_name ); ?>?</h2>
<!-- /wp:heading -->
<!-- wp:paragraph {"style":{"color":{"text":"<?php echo esc_attr( $contrast ); ?>"},"typography":{"fontSize":"1.05rem","lineHeight":"1.65"},"spacing":{"margin":{"top":"0.5rem","bottom":"0"}}}} -->
<p style="color:<?php echo esc_attr( $contrast ); ?>;font-size:1.05rem;line-height:1.65;margin-top:0.5rem;margin-bottom:0">Contact us today and discover how we can help your business grow.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
<!-- wp:buttons -->
<div class="wp-block-buttons">
<!-- wp:button {"style":{"color":{"background":"<?php echo esc_attr( $accent ); ?>","text":"<?php echo esc_attr( $contrast_accent ); ?>"},"typography":{"fontWeight":"700"},"spacing":{"padding":{"top":"1rem","bottom":"1rem","left":"2.5rem","right":"2.5rem"}}}} -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" style="background-color:<?php echo esc_attr( $accent ); ?>;color:<?php echo esc_attr( $contrast_accent ); ?>;padding-top:1rem;padding-right:2.5rem;padding-bottom:1rem;padding-left:2.5rem;font-weight:700">Contact Us →</a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->
</div>
<!-- /wp:group -->
</section>
<!-- /wp:group -->
<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->
		<?php return ob_get_clean();
	}

	/**
	 * Page template — used for all WordPress Pages (including Elementor-edited pages).
	 *
	 * Elementor replaces wp:post-content with its own canvas when a page is
	 * edited with Elementor, so this template is fully compatible with both.
	 */
	private function build_template_page(): string {
		ob_start(); ?>
<!-- wp:template-part {"slug":"header","tagName":"header"} /-->

<!-- wp:group {"tagName":"main","layout":{"type":"default"}} -->
<main class="wp-block-group">
	<!-- wp:post-content {"layout":{"type":"constrained"}} /-->
</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->
		<?php return ob_get_clean();
	}

	/**
	 * Header template part — sticky nav with site title + primary navigation.
	 */
	private function build_part_header( array $profile ): string {
		$primary  = $profile['colors']['primary']  ?? '#1a2e4a';
		$contrast = $this->is_dark_color( $primary ) ? '#ffffff' : '#1a1a1a';

		ob_start(); ?>
<!-- wp:group {"tagName":"header","className":"site-header","backgroundColor":"primary","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"}}},"layout":{"type":"constrained"}} -->
<header class="wp-block-group site-header alignfull has-primary-background-color has-background" style="padding-top:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30)">
  <!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between","alignItems":"center"},"style":{"spacing":{"blockGap":"var:preset|spacing|50"}}} -->
  <div class="wp-block-group">
    <!-- wp:site-title {"level":0,"isLink":true,"style":{"color":{"text":"<?php echo esc_attr( $contrast ); ?>"},"typography":{"textDecoration":"none","fontWeight":"700","fontSize":"1.375rem","letterSpacing":"-0.025em"}}} /-->
    <!-- wp:navigation {"textColor":"contrast","overlayBackgroundColor":"primary","overlayTextColor":"contrast","overlayMenu":"mobile","style":{"typography":{"fontSize":"0.9375rem","fontWeight":"500"},"spacing":{"blockGap":"var:preset|spacing|20"}}} /-->
  </div>
  <!-- /wp:group -->
</header>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	/**
	 * Single post template.
	 */
	private function build_template_single(): string {
		ob_start(); ?>
<!-- wp:template-part {"slug":"header","tagName":"header"} /-->

<!-- wp:group {"tagName":"main","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"layout":{"type":"constrained"}} -->
<main class="wp-block-group" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)">

    <!-- wp:group {"layout":{"type":"constrained","contentSize":"760px"}} -->
    <div class="wp-block-group">

        <!-- wp:post-featured-image {"aspectRatio":"16/9","style":{"spacing":{"margin":{"bottom":"var:preset|spacing|50"}}}} /-->

        <!-- wp:group {"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|40"},"blockGap":"0.5rem"}}} -->
        <div class="wp-block-group">
            <!-- wp:post-terms {"term":"category","style":{"typography":{"fontSize":"0.8rem","fontWeight":"700","letterSpacing":"0.07em","textTransform":"uppercase"},"color":{"text":"var:preset|color|primary"}}} /-->
            <!-- wp:post-title {"level":1,"style":{"typography":{"lineHeight":"1.1"},"spacing":{"margin":{"top":"0.5rem"}}}} /-->
            <!-- wp:post-date {"style":{"typography":{"fontSize":"0.875rem"},"color":{"text":"var:preset|color|muted"},"spacing":{"margin":{"top":"0.5rem"}}}} /-->
        </div>
        <!-- /wp:group -->

        <!-- wp:post-content {"layout":{"type":"constrained"}} /-->

        <!-- wp:post-tags {"style":{"spacing":{"margin":{"top":"var:preset|spacing|50"}}}} /-->

    </div>
    <!-- /wp:group -->

</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->
		<?php return ob_get_clean();
	}

	/**
	 * 404 error template.
	 */
	private function build_template_404(): string {
		ob_start(); ?>
<!-- wp:template-part {"slug":"header","tagName":"header"} /-->

<!-- wp:group {"tagName":"main","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"}}},"layout":{"type":"constrained","contentSize":"640px"}} -->
<main class="wp-block-group" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70)">

    <!-- wp:heading {"level":1,"textAlign":"center","style":{"typography":{"fontSize":"clamp(4rem,10vw,8rem)","fontWeight":"800","letterSpacing":"-0.04em"},"color":{"text":"var:preset|color|primary"}}} -->
    <h1 class="wp-block-heading has-text-align-center has-primary-color has-text-color" style="font-size:clamp(4rem,10vw,8rem);font-weight:800;letter-spacing:-0.04em">404</h1>
    <!-- /wp:heading -->

    <!-- wp:heading {"level":2,"textAlign":"center","style":{"typography":{"fontSize":"clamp(1.5rem,3vw,2.25rem)"},"spacing":{"margin":{"top":"0","bottom":"1rem"}}}} -->
    <h2 class="wp-block-heading has-text-align-center" style="font-size:clamp(1.5rem,3vw,2.25rem);margin-top:0;margin-bottom:1rem">Page Not Found</h2>
    <!-- /wp:heading -->

    <!-- wp:paragraph {"align":"center","style":{"color":{"text":"var:preset|color|muted"}}} -->
    <p class="has-text-align-center has-muted-color has-text-color">The page you are looking for doesn't exist or has been moved.</p>
    <!-- /wp:paragraph -->

    <!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"},"style":{"spacing":{"margin":{"top":"2rem"}}}} -->
    <div class="wp-block-buttons" style="margin-top:2rem">
        <!-- wp:button -->
        <div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/">← Back to Home</a></div>
        <!-- /wp:button -->
    </div>
    <!-- /wp:buttons -->

</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->
		<?php return ob_get_clean();
	}

	/**
	 * Hero section block pattern.
	 */
	private function build_pattern_hero( array $profile ): string {
		$contrast        = $this->is_dark_color( $profile['colors']['primary'] ?? '#1a2e4a' ) ? '#ffffff' : '#1a1a1a';
		$accent          = $profile['colors']['accent'] ?? '#f4a723';
		$contrast_accent = $this->is_dark_color( $accent ) ? '#ffffff' : '#1a1a1a';
		$letter_spacing  = $profile['heading_letter_spacing'] ?? '-0.02em';
		$heading_weight  = $profile['typography']['heading_weight'] ?? '700';
		$business        = get_option( 'grayfox_business_profile', array() );
		$site_name       = ! empty( $business['name'] ) ? esc_html( $business['name'] ) : get_bloginfo( 'name' );

		ob_start(); ?>
<?php
/**
 * Title: Hero Section
 * Slug: grayfox-theme/hero
 * Categories: grayfox-sections, featured, banner
 * Keywords: hero, banner, cover, home, intro
 * Block Types: core/post-content
 * Viewport Width: 1280
 */
?>
<!-- wp:group {"tagName":"section","className":"gf-hero-section","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"},"blockGap":"var:preset|spacing|20"}},"layout":{"type":"constrained","contentSize":"900px"}} -->
<section class="wp-block-group gf-hero-section alignfull" style="padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70)">

<!-- wp:paragraph {"style":{"color":{"text":"<?php echo esc_attr( $contrast ); ?>"},"typography":{"fontWeight":"700","fontSize":"0.75rem","letterSpacing":"0.12em","textTransform":"uppercase"},"spacing":{"margin":{"top":"0","bottom":"0.75rem"}}}} -->
<p style="color:<?php echo esc_attr( $contrast ); ?>;font-weight:700;font-size:0.75rem;letter-spacing:0.12em;text-transform:uppercase;margin-top:0;margin-bottom:0.75rem"><?php echo esc_html( $site_name ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":1,"style":{"color":{"text":"<?php echo esc_attr( $contrast ); ?>"},"typography":{"fontSize":"clamp(2.75rem,5vw,4.5rem)","fontWeight":"<?php echo esc_attr( $heading_weight ); ?>","lineHeight":"1.08","letterSpacing":"<?php echo esc_attr( $letter_spacing ); ?>"},"spacing":{"margin":{"top":"0","bottom":"1.25rem"}}}} -->
<h1 class="wp-block-heading" style="color:<?php echo esc_attr( $contrast ); ?>;font-size:clamp(2.75rem,5vw,4.5rem);font-weight:<?php echo esc_attr( $heading_weight ); ?>;line-height:1.08;letter-spacing:<?php echo esc_attr( $letter_spacing ); ?>;margin-top:0;margin-bottom:1.25rem">Your Business Headline Goes Here</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"color":{"text":"<?php echo esc_attr( $contrast ); ?>"},"typography":{"fontSize":"1.2rem","lineHeight":"1.65"},"spacing":{"margin":{"top":"0","bottom":"2.25rem"}}}} -->
<p style="color:<?php echo esc_attr( $contrast ); ?>;font-size:1.2rem;line-height:1.65;margin-top:0;margin-bottom:2.25rem">Describe what your business does and the value it provides. Two compelling sentences that speak directly to your ideal customer.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"style":{"spacing":{"blockGap":"1rem","margin":{"top":"0"}}}} -->
<div class="wp-block-buttons">
<!-- wp:button {"style":{"color":{"background":"<?php echo esc_attr( $accent ); ?>","text":"<?php echo esc_attr( $contrast_accent ); ?>"},"typography":{"fontWeight":"700"},"spacing":{"padding":{"top":"0.9rem","bottom":"0.9rem","left":"2.25rem","right":"2.25rem"}}}} -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" style="background-color:<?php echo esc_attr( $accent ); ?>;color:<?php echo esc_attr( $contrast_accent ); ?>;padding-top:0.9rem;padding-right:2.25rem;padding-bottom:0.9rem;padding-left:2.25rem;font-weight:700">Get Started →</a></div>
<!-- /wp:button -->
<!-- wp:button {"className":"is-style-outline","style":{"color":{"text":"<?php echo esc_attr( $contrast ); ?>"},"border":{"color":"<?php echo esc_attr( $contrast ); ?>","width":"2px"},"typography":{"fontWeight":"500"},"spacing":{"padding":{"top":"0.9rem","bottom":"0.9rem","left":"2.25rem","right":"2.25rem"}}}} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" style="color:<?php echo esc_attr( $contrast ); ?>;border-color:<?php echo esc_attr( $contrast ); ?>;border-width:2px;padding-top:0.9rem;padding-right:2.25rem;padding-bottom:0.9rem;padding-left:2.25rem;font-weight:500">Learn More</a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->

</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	/**
	 * Features grid block pattern.
	 */
	private function build_pattern_features( array $profile ): string {
		$features_bg = $this->tint_color( $profile['colors']['secondary'] ?? '#2d6a8f', 0.92 );
		$primary     = $profile['colors']['primary'] ?? '#1a2e4a';
		$muted       = $profile['colors']['muted']   ?? '#6b7280';
		$text        = $profile['colors']['text']    ?? '#1a1a1a';

		// Build a single feature card with an icon box.
		$card = function ( string $title, string $body ) use ( $text, $muted, $primary ): string {
			ob_start(); ?>
<!-- wp:column {"className":"gf-feature-card card h-100","style":{"spacing":{"blockGap":"0"}}} -->
<div class="wp-block-column gf-feature-card card h-100">

<!-- wp:group {"className":"gf-feature-icon","style":{"color":{"background":"<?php echo esc_attr( $primary ); ?>"},"border":{"radius":"10px"},"dimensions":{"minHeight":"0px"},"spacing":{"padding":{"top":"0.75rem","bottom":"0.75rem","left":"0.75rem","right":"0.75rem"}}}} -->
<div class="wp-block-group gf-feature-icon has-background" style="background-color:<?php echo esc_attr( $primary ); ?>;border-radius:10px;padding-top:0.75rem;padding-right:0.75rem;padding-bottom:0.75rem;padding-left:0.75rem;min-height:0px"></div>
<!-- /wp:group -->

<!-- wp:heading {"level":3,"style":{"typography":{"fontSize":"1.2rem","fontWeight":"700","lineHeight":"1.3"},"color":{"text":"<?php echo esc_attr( $text ); ?>"},"spacing":{"margin":{"top":"1.25rem","bottom":"0.5rem"}}}} -->
<h3 class="wp-block-heading" style="font-size:1.2rem;font-weight:700;line-height:1.3;color:<?php echo esc_attr( $text ); ?>;margin-top:1.25rem;margin-bottom:0.5rem"><?php echo esc_html( $title ); ?></h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"style":{"color":{"text":"<?php echo esc_attr( $muted ); ?>"},"typography":{"fontSize":"0.9375rem","lineHeight":"1.65"},"spacing":{"margin":{"top":"0"}}}} -->
<p style="color:<?php echo esc_attr( $muted ); ?>;font-size:0.9375rem;line-height:1.65;margin-top:0"><?php echo esc_html( $body ); ?></p>
<!-- /wp:paragraph -->

</div>
<!-- /wp:column -->
			<?php return ob_get_clean();
		};

		ob_start(); ?>
<?php
/**
 * Title: Features Grid
 * Slug: grayfox-theme/features
 * Categories: grayfox-sections
 * Keywords: features, services, grid, three columns
 * Viewport Width: 1280
 */
?>
<!-- wp:group {"tagName":"section","align":"full","style":{"color":{"background":"<?php echo esc_attr( $features_bg ); ?>"},"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"}}},"layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull has-background" style="background-color:<?php echo esc_attr( $features_bg ); ?>;padding-top:var(--wp--preset--spacing--70);padding-bottom:var(--wp--preset--spacing--70)">

<!-- wp:group {"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|60"},"blockGap":"0.75rem"}},"layout":{"type":"constrained","contentSize":"600px"}} -->
<div class="wp-block-group" style="margin-bottom:var(--wp--preset--spacing--60)">
<!-- wp:heading {"level":2,"textAlign":"center","style":{"typography":{"fontSize":"clamp(2rem,4vw,3rem)","lineHeight":"1.15"},"color":{"text":"<?php echo esc_attr( $text ); ?>"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<h2 class="wp-block-heading has-text-align-center" style="font-size:clamp(2rem,4vw,3rem);line-height:1.15;color:<?php echo esc_attr( $text ); ?>;margin-top:0;margin-bottom:0">What We Offer</h2>
<!-- /wp:heading -->
<!-- wp:paragraph {"align":"center","style":{"color":{"text":"<?php echo esc_attr( $muted ); ?>"},"typography":{"fontSize":"1.05rem","lineHeight":"1.65"},"spacing":{"margin":{"top":"0.75rem","bottom":"0"}}}} -->
<p class="has-text-align-center" style="color:<?php echo esc_attr( $muted ); ?>;font-size:1.05rem;line-height:1.65;margin-top:0.75rem;margin-bottom:0">Our services are designed to help your business succeed and grow.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->

<!-- wp:columns {"isStackedOnMobile":true,"style":{"spacing":{"blockGap":{"top":"1.5rem","left":"1.5rem"}}}} -->
<div class="wp-block-columns">
<?php echo $card( 'Service One', 'Describe this service or benefit. Keep it focused on the value it delivers to your customers.' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<?php echo $card( 'Service Two', 'Describe this service or benefit. Keep it focused on the value it delivers to your customers.' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<?php echo $card( 'Service Three', 'Describe this service or benefit. Keep it focused on the value it delivers to your customers.' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</div>
<!-- /wp:columns -->

</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	/**
	 * Call-to-action band block pattern.
	 */
	private function build_pattern_cta( array $profile ): string {
		$primary         = $profile['colors']['primary']  ?? '#1a2e4a';
		$accent          = $profile['colors']['accent']   ?? '#f4a723';
		$contrast_accent = $this->is_dark_color( $accent ) ? '#ffffff' : '#1a1a1a';
		$contrast_p      = $this->is_dark_color( $primary ) ? '#ffffff' : '#1a1a1a';
		$business        = get_option( 'grayfox_business_profile', array() );
		$site_name       = ! empty( $business['name'] ) ? esc_html( $business['name'] ) : get_bloginfo( 'name' );

		ob_start(); ?>
<?php
/**
 * Title: Call to Action Band
 * Slug: grayfox-theme/cta
 * Categories: grayfox-sections, call-to-action
 * Keywords: cta, call to action, contact, band
 * Viewport Width: 1280
 */
?>
<!-- wp:group {"tagName":"section","className":"gf-cta-band","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"layout":{"type":"constrained","contentSize":"900px"}} -->
<section class="wp-block-group gf-cta-band alignfull" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--60)">

<!-- wp:group {"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","alignItems":"center"},"style":{"spacing":{"blockGap":"2rem"}}} -->
<div class="wp-block-group">

<!-- wp:group {"layout":{"type":"constrained","contentSize":"520px"},"style":{"spacing":{"blockGap":"0.75rem"}}} -->
<div class="wp-block-group">
<!-- wp:heading {"level":2,"style":{"color":{"text":"<?php echo esc_attr( $contrast_p ); ?>"},"typography":{"fontSize":"clamp(1.875rem,3.5vw,2.75rem)","fontWeight":"700","lineHeight":"1.15"},"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<h2 class="wp-block-heading" style="color:<?php echo esc_attr( $contrast_p ); ?>;font-size:clamp(1.875rem,3.5vw,2.75rem);font-weight:700;line-height:1.15;margin-top:0;margin-bottom:0">Ready to Work With <?php echo esc_html( $site_name ); ?>?</h2>
<!-- /wp:heading -->
<!-- wp:paragraph {"style":{"color":{"text":"<?php echo esc_attr( $contrast_p ); ?>"},"typography":{"fontSize":"1.1rem","lineHeight":"1.6"},"spacing":{"margin":{"top":"0.5rem","bottom":"0"}}}} -->
<p style="color:<?php echo esc_attr( $contrast_p ); ?>;font-size:1.1rem;line-height:1.6;margin-top:0.5rem;margin-bottom:0">Contact us today and discover how we can help your business grow.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->

<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"0"}}}} -->
<div class="wp-block-buttons">
<!-- wp:button {"style":{"color":{"background":"<?php echo esc_attr( $primary ); ?>","text":"<?php echo esc_attr( $contrast_p ); ?>"},"typography":{"fontWeight":"700"},"border":{"width":"2px","color":"<?php echo esc_attr( $contrast_p ); ?>"},"spacing":{"padding":{"top":"1rem","bottom":"1rem","left":"2.5rem","right":"2.5rem"}}}} -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" style="background-color:<?php echo esc_attr( $primary ); ?>;color:<?php echo esc_attr( $contrast_p ); ?>;border-color:<?php echo esc_attr( $contrast_p ); ?>;border-width:2px;padding-top:1rem;padding-right:2.5rem;padding-bottom:1rem;padding-left:2.5rem;font-weight:700">Contact Us →</a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->

</div>
<!-- /wp:group -->

</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	/**
	 * Footer template part — multi-column footer with brand, links, contact, and copyright bar.
	 */
	private function build_part_footer( array $profile ): string {
		$primary  = $profile['colors']['primary']  ?? '#1a2e4a';
		$contrast = $this->is_dark_color( $primary ) ? '#ffffff' : '#1a1a1a';

		$business  = get_option( 'grayfox_business_profile', array() );
		$site_name = ! empty( $business['name'] ) ? esc_html( $business['name'] ) : get_bloginfo( 'name' );
		$year      = gmdate( 'Y' );

		ob_start(); ?>
<!-- wp:group {"tagName":"footer","className":"site-footer","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|40"}}},"layout":{"type":"constrained"}} -->
<footer class="wp-block-group site-footer alignfull" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--40)">

  <!-- wp:columns {"isStackedOnMobile":true,"style":{"spacing":{"padding":{"bottom":"var:preset|spacing|50"},"blockGap":"2.5rem"}}} -->
  <div class="wp-block-columns" style="padding-bottom:var(--wp--preset--spacing--50)">

    <!-- wp:column {"width":"40%"} -->
    <div class="wp-block-column" style="flex-basis:40%">
      <!-- wp:site-title {"level":0,"isLink":true,"style":{"color":{"text":"<?php echo esc_attr( $contrast ); ?>"},"typography":{"textDecoration":"none","fontWeight":"700","fontSize":"1.25rem","letterSpacing":"-0.02em"}}} /-->
      <!-- wp:site-tagline {"style":{"color":{"text":"<?php echo esc_attr( $contrast ); ?>"},"typography":{"fontSize":"0.9rem"},"spacing":{"margin":{"top":"0.5rem"}}}} /-->
    </div>
    <!-- /wp:column -->

    <!-- wp:column {"width":"30%"} -->
    <div class="wp-block-column" style="flex-basis:30%">
      <!-- wp:heading {"level":6,"style":{"color":{"text":"<?php echo esc_attr( $contrast ); ?>"}}} -->
      <h6 class="wp-block-heading" style="color:<?php echo esc_attr( $contrast ); ?>">Quick Links</h6>
      <!-- /wp:heading -->
      <!-- wp:navigation {"slug":"footer","textColor":"contrast","layout":{"type":"flex","orientation":"vertical"},"style":{"spacing":{"blockGap":"0.4rem"}}} /-->
    </div>
    <!-- /wp:column -->

    <!-- wp:column {"width":"30%"} -->
    <div class="wp-block-column" style="flex-basis:30%">
      <!-- wp:heading {"level":6,"style":{"color":{"text":"<?php echo esc_attr( $contrast ); ?>"}}} -->
      <h6 class="wp-block-heading" style="color:<?php echo esc_attr( $contrast ); ?>">Get In Touch</h6>
      <!-- /wp:heading -->
      <!-- wp:paragraph {"style":{"color":{"text":"<?php echo esc_attr( $contrast ); ?>"},"typography":{"fontSize":"0.875rem"},"spacing":{"margin":{"top":"0"}}}} -->
      <p style="color:<?php echo esc_attr( $contrast ); ?>;font-size:0.875rem;margin-top:0">We'd love to hear from you. Reach out to learn how <?php echo esc_html( $site_name ); ?> can help.</p>
      <!-- /wp:paragraph -->
    </div>
    <!-- /wp:column -->

  </div>
  <!-- /wp:columns -->

  <!-- wp:separator {"style":{"color":{"background":"<?php echo esc_attr( $contrast ); ?>"}},"className":"is-style-wide"} -->
  <hr class="wp-block-separator is-style-wide has-background" style="background-color:<?php echo esc_attr( $contrast ); ?>"/>
  <!-- /wp:separator -->

  <!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|30"},"blockGap":"1rem"}},"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","alignItems":"center"}} -->
  <div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--30)">
    <!-- wp:paragraph {"className":"gf-footer-copyright","style":{"color":{"text":"<?php echo esc_attr( $contrast ); ?>"}}} -->
    <p class="gf-footer-copyright" style="color:<?php echo esc_attr( $contrast ); ?>">&copy; <?php echo esc_html( $year ); ?> <?php echo esc_html( $site_name ); ?>. All Rights Reserved.</p>
    <!-- /wp:paragraph -->
    <!-- wp:navigation {"slug":"footer","textColor":"contrast","layout":{"type":"flex","flexWrap":"wrap"},"style":{"typography":{"fontSize":"0.8rem"},"spacing":{"blockGap":"1.25rem"}}} /-->
  </div>
  <!-- /wp:group -->

</footer>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	/* ------------------------------------------------------------------
	 * Logo Analysis
	 * ------------------------------------------------------------------ */

	private function analyze_logo_image( int $attachment_id ): array {
		$mime_type = get_post_mime_type( $attachment_id );
		$supported = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );
		if ( ! in_array( $mime_type, $supported, true ) ) {
			return array( 'skipped' => true, 'reason' => 'unsupported_mime_type' );
		}

		$provider = get_option( 'grayfox_llm_provider', 'openai' );
		if ( ! $this->provider_supports_vision( $provider ) ) {
			return array( 'skipped' => true, 'reason' => 'provider_no_vision' );
		}

		$image_path = get_attached_file( $attachment_id );
		if ( ! $image_path || ! file_exists( $image_path ) ) {
			return array( 'skipped' => true, 'reason' => 'attachment_not_found' );
		}

		$image_base64 = base64_encode( file_get_contents( $image_path ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode,WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		$enc_key = get_option( 'grayfox_llm_api_key', '' );
		$api_key = grayfox_decrypt( $enc_key );
		$model   = get_option( 'grayfox_llm_model', '' );

		if ( empty( $api_key ) || empty( $model ) ) {
			return array( 'skipped' => true, 'reason' => 'llm_not_configured' );
		}

		$messages = array(
			array(
				'role'    => 'system',
				'content' => defined( 'GRAYFOX_PROMPT_THEME_BUILDER_LOGO_ANALYZE' )
					? GRAYFOX_PROMPT_THEME_BUILDER_LOGO_ANALYZE
					: 'You are a brand design analyst. Analyze this logo and return JSON with colors, style_keywords, industry_hints, and mood.',
			),
			array( 'role' => 'user', 'content' => 'Analyze this logo image and return the JSON analysis.' ),
		);

		$llm    = new GrayFox_LLM();
		$raw    = $llm->request_vision( $provider, $api_key, $model, $messages, $image_base64, $mime_type, 0.1 );
		$result = json_decode( $raw, true );

		if ( ! is_array( $result ) || empty( $result['colors'] ) ) {
			return array( 'skipped' => true, 'reason' => 'parse_failed' );
		}

		$analysis = array(
			'colors'         => array(
				'primary'   => $this->sanitize_hex( $result['colors']['primary']   ?? '' ),
				'secondary' => $this->sanitize_hex( $result['colors']['secondary'] ?? '' ),
				'accent'    => $this->sanitize_hex( $result['colors']['accent']    ?? '' ),
			),
			'style_keywords' => array_map( 'sanitize_key', (array) ( $result['style_keywords'] ?? array() ) ),
			'industry_hints' => array_map( 'sanitize_key', (array) ( $result['industry_hints'] ?? array() ) ),
			'mood'           => sanitize_text_field( $result['mood'] ?? '' ),
		);

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[TB] analyze_logo_image: colors extracted=' . json_encode( $analysis['colors'] ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		return $analysis;
	}

	/* ------------------------------------------------------------------
	 * Theme Profile Generation (LLM)
	 * ------------------------------------------------------------------ */

	private function generate_theme_profile( array $logo_analysis, string $brand_guidelines ): array {
		$provider = get_option( 'grayfox_llm_provider', 'openai' );
		$enc_key  = get_option( 'grayfox_llm_api_key', '' );
		$api_key  = grayfox_decrypt( $enc_key );
		$model    = get_option( 'grayfox_llm_model', '' );

		if ( empty( $api_key ) || empty( $model ) ) {
			return array();
		}

		$knowledge_json   = GrayFox_RAG::get_consolidated_knowledge( 'brand identity visual style colors typography logo' );
		$business_profile = get_option( 'grayfox_business_profile', array() );

		$prompt_template = defined( 'GRAYFOX_PROMPT_THEME_BUILDER_GENERATE' ) ? GRAYFOX_PROMPT_THEME_BUILDER_GENERATE : '';
		if ( empty( $prompt_template ) ) {
			return array();
		}

		$logo_skipped  = ! empty( $logo_analysis['skipped'] );
		$logo_json     = ( ! empty( $logo_analysis ) && ! $logo_skipped )
			? wp_json_encode( $logo_analysis )
			: '(none provided)';

		// Build a strong seeding hint when the logo analysis succeeded so the LLM
		// cannot ignore the actual extracted colors.
		$logo_color_hint = '';
		if ( ! $logo_skipped && ! empty( $logo_analysis['colors']['primary'] ) ) {
			$lc              = $logo_analysis['colors'];
			$logo_color_hint = "\n\nCRITICAL — LOGO COLOR ANCHORS (you MUST respect these):\n"
				. "  Logo primary color:   " . ( $lc['primary']   ?? '' ) . "\n"
				. "  Logo secondary color: " . ( $lc['secondary'] ?? '' ) . "\n"
				. "  Logo accent color:    " . ( $lc['accent']    ?? '' ) . "\n"
				. "Use the logo primary as the theme primary IF it is dark enough for white text (luminance < 50%).\n"
				. "If the logo primary is too light, use it as the accent instead and derive a darker shade of it for the theme primary.\n"
				. "The accent color MUST match or closely echo the logo accent.\n"
				. "Do not substitute unrelated colors — the logo IS the brand.";
		}

		$bp_json       = ! empty( $business_profile ) ? wp_json_encode( $business_profile ) : '(none available)';
		$guidelines    = ! empty( $brand_guidelines ) ? $brand_guidelines : '(none provided)';
		$knowledge_str = ! empty( $knowledge_json ) ? mb_substr( $knowledge_json, 0, 20000 ) : '(no knowledge base content available)';

		$prompt = str_replace(
			array( '{{KNOWLEDGE_JSON}}', '{{BUSINESS_PROFILE}}', '{{LOGO_ANALYSIS}}', '{{BRAND_GUIDELINES}}' ),
			array( $knowledge_str, $bp_json, $logo_json . $logo_color_hint, $guidelines ),
			$prompt_template
		);

		$messages = array(
			array( 'role' => 'system', 'content' => 'You are a professional brand designer and WordPress theme architect. Return only valid JSON.' ),
			array( 'role' => 'user',   'content' => $prompt ),
		);

		$llm         = new GrayFox_LLM();
		$manifest    = array();
		$max_attempts = 3;

		// Agentic validation loop — up to 3 attempts.
		// On each failure the validator's error list is appended to the conversation
		// so the LLM can self-correct, exactly mirroring the Python skill's retry model.
		for ( $attempt = 1; $attempt <= $max_attempts; $attempt++ ) {

			$raw    = $llm->request_json( $provider, $api_key, $model, $messages, 0.4 );
			$parsed = json_decode( $raw, true );

			// Structural check — must be a manifest object with theme.colors.
			if ( ! is_array( $parsed ) || empty( $parsed['theme']['colors'] ) ) {
				if ( $attempt < $max_attempts ) {
					$messages[] = array(
						'role'    => 'assistant',
						'content' => $raw ?: '(empty response)',
					);
					$messages[] = array(
						'role'    => 'user',
						'content' => 'Your response was not a valid manifest JSON object. '
							. 'It must have a top-level "theme" key containing a "colors" object, '
							. 'plus "assets", "parts", and "templates" keys. '
							. 'Return ONLY the corrected JSON manifest — no markdown, no explanation.',
					);
					continue;
				}
				// All attempts exhausted without a valid structure.
				return array();
			}

			$manifest = $this->sanitize_manifest( $parsed );

			// Semantic validation — catches hallucinated layout slugs, broken pattern
			// references in templates, invalid enum values, etc.
			$errors = GrayFox_TB_ManifestValidator::validate( $manifest );

			if ( empty( $errors ) ) {
				break; // Manifest is valid — exit the retry loop.
			}

			if ( $attempt < $max_attempts ) {
				$error_list = implode( "\n", array_map( static fn( $e ) => "  - {$e}", $errors ) );
				$messages[] = array(
					'role'    => 'assistant',
					'content' => $raw,
				);
				$messages[] = array(
					'role'    => 'user',
					'content' => "The manifest has " . count( $errors ) . " validation error(s). "
						. "Fix every error listed below and return the complete corrected manifest. "
						. "Return ONLY the corrected JSON — no markdown, no explanation.\n\n"
						. "Errors:\n{$error_list}",
				);
			}
			// On the last attempt we keep whatever manifest we have and surface the
			// errors in the return value so the UI can inform the user.
		}

		if ( empty( $manifest ) ) {
			return array();
		}

		// Attach any residual validation errors so the frontend can surface them.
		$residual_errors = GrayFox_TB_ManifestValidator::validate( $manifest );
		if ( ! empty( $residual_errors ) ) {
			$manifest['_validation_errors'] = $residual_errors;
		}

		// Surface logo-skip warnings inside the manifest so the UI can inform the user.
		if ( $logo_skipped && ! empty( $logo_analysis['reason'] ) ) {
			$manifest['logo_warning'] = $logo_analysis['reason'];
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[TB] generate_theme_profile: LLM returned colors=' . json_encode( $manifest['theme']['colors'] ?? 'NOT SET' ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[TB] generate_theme_profile: front-page patterns=' . json_encode( $manifest['templates']['front-page.html']['patterns'] ?? 'NOT SET' ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		return $manifest;
	}

	/* ------------------------------------------------------------------
	 * Elementor Kit (bonus — applied when Elementor is active)
	 * ------------------------------------------------------------------ */

	private function apply_elementor_kit( array $profile ): void {
		$kit_posts = get_posts( array(
			'post_type'   => 'elementor_library',
			'meta_key'    => '_elementor_template_type', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'  => 'kit',                      // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'numberposts' => 1,
			'post_status' => 'publish',
		) );

		if ( empty( $kit_posts ) ) {
			return;
		}

		$kit_id = $kit_posts[0]->ID;
		// Support both manifest format (theme.colors) and legacy flat format (colors).
		$colors = $profile['theme']['colors'] ?? $profile['colors']     ?? array();
		$typo   = $profile['theme']['typography'] ?? $profile['typography'] ?? array();
		$settings = get_post_meta( $kit_id, '_elementor_page_settings', true );
		$settings = is_array( $settings ) ? $settings : array();

		$settings['system_colors'] = array(
			array( '_id' => 'primary',   'title' => 'Primary',   'color' => $colors['primary']   ?? '#000000' ),
			array( '_id' => 'secondary', 'title' => 'Secondary', 'color' => $colors['secondary'] ?? '#000000' ),
			array( '_id' => 'text',      'title' => 'Text',      'color' => $colors['text']       ?? '#333333' ),
			array( '_id' => 'accent',    'title' => 'Accent',    'color' => $colors['accent']     ?? '#000000' ),
		);
		$settings['custom_colors'] = array(
			array( '_id' => 'gf_background', 'title' => 'GF Background', 'color' => $colors['background'] ?? '#ffffff' ),
			array( '_id' => 'gf_muted',      'title' => 'GF Muted',      'color' => $colors['muted']      ?? '#6b7280' ),
		);

		if ( ! empty( $typo['heading_font'] ) || ! empty( $typo['body_font'] ) ) {
			$settings['system_typography'] = array(
				array(
					'_id' => 'primary', 'title' => 'Primary',
					'typography_typography' => 'custom',
					'typography_font_family' => $typo['heading_font']   ?? '',
					'typography_font_weight' => $typo['heading_weight'] ?? '700',
				),
				array(
					'_id' => 'secondary', 'title' => 'Secondary',
					'typography_typography' => 'custom',
					'typography_font_family' => $typo['body_font']   ?? '',
					'typography_font_weight' => $typo['body_weight'] ?? '400',
				),
			);
		}

		update_post_meta( $kit_id, '_elementor_page_settings', $settings );

		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		} else {
			delete_post_meta( $kit_id, '_elementor_css' );
		}
	}

	/* ------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------ */

	public function provider_supports_vision( string $provider ): bool {
		return in_array( $provider, array( 'openai', 'anthropic', 'gemini' ), true );
	}

	/**
	 * Determine if a hex color is perceptually dark (luminance < 50%).
	 * Used to pick white vs dark text on colored backgrounds.
	 */
	private function is_dark_color( string $hex ): bool {
		$hex = ltrim( $hex, '#' );
		if ( strlen( $hex ) !== 6 ) {
			return true; // Assume dark as a safe default.
		}
		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );
		// Relative luminance (sRGB).
		$luminance = ( 0.2126 * $r + 0.7152 * $g + 0.0722 * $b ) / 255;
		return $luminance < 0.5;
	}

	/**
	 * Build a Google Fonts URL for heading and body fonts.
	 */
	private function build_google_fonts_url( string $heading_font, string $body_font ): string {
		$families = array();
		if ( ! empty( $heading_font ) ) {
			$families[] = 'family=' . rawurlencode( $heading_font ) . ':wght@400;600;700';
		}
		if ( ! empty( $body_font ) && $body_font !== $heading_font ) {
			$families[] = 'family=' . rawurlencode( $body_font ) . ':wght@400;500';
		}
		if ( empty( $families ) ) {
			return '';
		}
		return 'https://fonts.googleapis.com/css2?' . implode( '&', $families ) . '&display=swap';
	}

	private function sanitize_hex( string $hex ): string {
		$stripped = ltrim( trim( $hex ), '#' );
		return preg_match( '/^[0-9a-fA-F]{6}$/', $stripped ) ? '#' . strtolower( $stripped ) : '';
	}

	/**
	 * Darken a hex color by a ratio (0.0-1.0).
	 */
	private function darken_color( string $hex, float $ratio ): string {
		$rgb = $this->hex_to_rgb( $hex );
		return sprintf( '#%02x%02x%02x',
			max( 0, (int) round( $rgb[0] * ( 1 - $ratio ) ) ),
			max( 0, (int) round( $rgb[1] * ( 1 - $ratio ) ) ),
			max( 0, (int) round( $rgb[2] * ( 1 - $ratio ) ) )
		);
	}

	/**
	 * Tint a hex color toward white by a ratio (0.0-1.0).
	 */
	private function tint_color( string $hex, float $ratio ): string {
		$rgb = $this->hex_to_rgb( $hex );
		return sprintf( '#%02x%02x%02x',
			min( 255, (int) round( $rgb[0] + ( 255 - $rgb[0] ) * $ratio ) ),
			min( 255, (int) round( $rgb[1] + ( 255 - $rgb[1] ) * $ratio ) ),
			min( 255, (int) round( $rgb[2] + ( 255 - $rgb[2] ) * $ratio ) )
		);
	}

	/**
	 * Convert hex color to RGB array [r, g, b].
	 */
	private function hex_to_rgb( string $hex ): array {
		$hex = ltrim( $hex, '#' );
		if ( strlen( $hex ) !== 6 ) return array( 0, 0, 0 );
		return array(
			hexdec( substr( $hex, 0, 2 ) ),
			hexdec( substr( $hex, 2, 2 ) ),
			hexdec( substr( $hex, 4, 2 ) ),
		);
	}

	/**
	 * Sanitize a brand profile OR a manifest dict.
	 *
	 * Detects manifest format by presence of theme.colors and delegates to
	 * sanitize_manifest() so both the LLM response path and the frontend
	 * save-profile path work with the new manifest structure.
	 *
	 * @param array $profile Either a legacy flat profile or a full manifest.
	 * @return array Sanitized manifest (manifest format) or sanitized flat profile.
	 */
	private function sanitize_brand_profile( array $profile ): array {
		// Manifest format detected — delegate to the manifest sanitizer.
		if ( isset( $profile['theme']['colors'] ) ) {
			return $this->sanitize_manifest( $profile );
		}

		// Legacy flat profile path (kept for backwards compatibility).
		$colors  = $profile['colors']     ?? array();
		$typo    = $profile['typography'] ?? array();

		$valid_spacing = array( 'tight', 'comfortable', 'spacious' );

		$spacing_style = sanitize_key( $profile['spacing_style'] ?? 'comfortable' );
		if ( ! in_array( $spacing_style, $valid_spacing, true ) ) { $spacing_style = 'comfortable'; }

		return array(
			'colors'       => array(
				'primary'    => $this->sanitize_hex( $colors['primary']    ?? '' ) ?: '#1a2e4a',
				'secondary'  => $this->sanitize_hex( $colors['secondary']  ?? '' ) ?: '#2d6a8f',
				'accent'     => $this->sanitize_hex( $colors['accent']     ?? '' ) ?: '#f4a723',
				'background' => $this->sanitize_hex( $colors['background'] ?? '' ) ?: '#ffffff',
				'text'       => $this->sanitize_hex( $colors['text']       ?? '' ) ?: '#1e1e1e',
				'muted'      => $this->sanitize_hex( $colors['muted']      ?? '' ) ?: '#6b7280',
			),
			'typography'   => array(
				'heading_font'   => sanitize_text_field( $typo['heading_font']   ?? 'Inter' ),
				'body_font'      => sanitize_text_field( $typo['body_font']      ?? 'Inter' ),
				'heading_weight' => in_array( (string) ( $typo['heading_weight'] ?? '700' ), array( '400', '500', '600', '700', '800', '900' ), true ) ? (string) $typo['heading_weight'] : '700',
				'body_weight'    => '400',
			),
			'spacing_style'          => $spacing_style,
			'logo_attachment_id'     => absint( $profile['logo_attachment_id'] ?? 0 ),
			'generated_at'           => absint( $profile['generated_at']       ?? 0 ),
			'rationale'              => sanitize_textarea_field( $profile['rationale'] ?? '' ),
			'heading_letter_spacing' => sanitize_text_field( $profile['heading_letter_spacing'] ?? '-0.02em' ),
			'heading_text_transform' => in_array( $profile['heading_text_transform'] ?? 'none', array( 'none', 'uppercase' ), true ) ? $profile['heading_text_transform'] : 'none',
			'logo_analysis'          => is_array( $profile['logo_analysis'] ?? null ) ? $profile['logo_analysis'] : array(),
		);
	}

	/**
	 * Sanitize a full theme manifest produced by the LLM.
	 *
	 * Validates and sanitizes all five top-level blocks: theme, assets, parts,
	 * patterns, and templates. Pattern copy fields are sanitized as text so
	 * LLM-generated content cannot inject markup. The manifest structure is
	 * preserved exactly so the manifest-driven builders can consume it.
	 *
	 * @param array $manifest Raw manifest array from LLM response or frontend POST.
	 * @return array Sanitized manifest.
	 */
	private function sanitize_manifest( array $manifest ): array {

		// ── theme block ────────────────────────────────────────────────────────
		$theme  = $manifest['theme'] ?? array();
		$colors = $theme['colors']   ?? array();
		$typo   = $theme['typography'] ?? array();

		$valid_spacing   = array( 'tight', 'comfortable', 'spacious' );
		$valid_transform = array( 'none', 'uppercase' );
		$valid_weights   = array( '400', '500', '600', '700', '800', '900' );

		$spacing_style = sanitize_key( $theme['spacing_style'] ?? 'comfortable' );
		if ( ! in_array( $spacing_style, $valid_spacing, true ) ) { $spacing_style = 'comfortable'; }

		$heading_transform = $theme['heading_text_transform'] ?? 'none';
		if ( ! in_array( $heading_transform, $valid_transform, true ) ) { $heading_transform = 'none'; }

		$heading_weight = (string) ( $typo['heading_weight'] ?? '700' );
		if ( ! in_array( $heading_weight, $valid_weights, true ) ) { $heading_weight = '700'; }

		$clean_theme = array(
			'name'                   => sanitize_text_field( $theme['name']     ?? 'Custom Theme' ),
			'slug'                   => sanitize_title( $theme['slug']          ?? 'custom-theme' ),
			'industry'               => sanitize_key( $theme['industry']        ?? 'general' ),
			'colors'                 => array(
				'primary'    => $this->sanitize_hex( $colors['primary']    ?? '' ) ?: '#1a2e4a',
				'secondary'  => $this->sanitize_hex( $colors['secondary']  ?? '' ) ?: '#2d6a8f',
				'accent'     => $this->sanitize_hex( $colors['accent']     ?? '' ) ?: '#f4a723',
				'background' => $this->sanitize_hex( $colors['background'] ?? '' ) ?: '#ffffff',
				'text'       => $this->sanitize_hex( $colors['text']       ?? '' ) ?: '#1e1e1e',
				'muted'      => $this->sanitize_hex( $colors['muted']      ?? '' ) ?: '#6b7280',
			),
			'typography'             => array(
				'heading_font'   => sanitize_text_field( $typo['heading_font']   ?? 'Inter' ),
				'body_font'      => sanitize_text_field( $typo['body_font']      ?? 'Inter' ),
				'heading_weight' => $heading_weight,
				'body_weight'    => '400',
			),
			'heading_letter_spacing' => sanitize_text_field( $theme['heading_letter_spacing'] ?? '-0.02em' ),
			'heading_text_transform' => $heading_transform,
			'spacing_style'          => $spacing_style,
			'style_archetype'        => sanitize_key( $theme['style_archetype'] ?? 'soft-modern' ),
		);

		// ── assets block ───────────────────────────────────────────────────────
		$assets      = $manifest['assets'] ?? array();
		$clean_assets = array(
			'bootstrap_components' => array_map( 'sanitize_text_field', (array) ( $assets['bootstrap_components'] ?? array() ) ),
			'icons_used'           => array_map( 'sanitize_text_field', (array) ( $assets['icons_used']           ?? array() ) ),
			'load_bootstrap_js'    => (bool) ( $assets['load_bootstrap_js']    ?? false ),
			'load_bootstrap_icons' => (bool) ( $assets['load_bootstrap_icons'] ?? false ),
			'load_gf_forms'        => (bool) ( $assets['load_gf_forms']        ?? false ),
		);

		// ── parts block ────────────────────────────────────────────────────────
		$parts                = $manifest['parts'] ?? array();
		$valid_header_variant = array( 'header', 'header-minimal', 'header-transparent' );
		$valid_footer_variant = array( 'footer', 'footer-minimal' );

		$header_variant = sanitize_key( $parts['header_variant'] ?? 'header' );
		if ( ! in_array( $header_variant, $valid_header_variant, true ) ) { $header_variant = 'header'; }

		$footer_variant = sanitize_key( $parts['footer_variant'] ?? 'footer' );
		if ( ! in_array( $footer_variant, $valid_footer_variant, true ) ) { $footer_variant = 'footer'; }

		$clean_parts = array(
			'header_variant' => $header_variant,
			'footer_variant' => $footer_variant,
		);

		// ── patterns block ─────────────────────────────────────────────────────
		$raw_patterns  = is_array( $manifest['patterns'] ?? null ) ? $manifest['patterns'] : array();
		$clean_patterns = array();

		foreach ( $raw_patterns as $slug => $spec ) {
			$clean_slug = sanitize_title( (string) $slug );
			if ( empty( $clean_slug ) || ! is_array( $spec ) ) {
				continue;
			}

			// Sanitize copy: every value is user-generated text from the LLM.
			$raw_copy   = is_array( $spec['copy'] ?? null ) ? $spec['copy'] : array();
			$clean_copy = array();
			foreach ( $raw_copy as $key => $value ) {
				$clean_copy[ sanitize_key( (string) $key ) ] = sanitize_textarea_field( (string) $value );
			}

			$entry = array(
				'title'       => sanitize_text_field( $spec['title'] ?? ucwords( str_replace( '-', ' ', $clean_slug ) ) ),
				'layout'      => sanitize_key( $spec['layout'] ?? '' ),
				'css_classes' => array_map( 'sanitize_html_class', (array) ( $spec['css_classes'] ?? array() ) ),
				'copy'        => $clean_copy,
			);

			if ( ! empty( $spec['bootstrap_components'] ) ) {
				$entry['bootstrap_components'] = array_map( 'sanitize_text_field', (array) $spec['bootstrap_components'] );
			}

			$clean_patterns[ $clean_slug ] = $entry;
		}

		// ── templates block ────────────────────────────────────────────────────
		$raw_templates  = is_array( $manifest['templates'] ?? null ) ? $manifest['templates'] : array();
		$clean_templates = array();
		$valid_types     = array( 'content', 'content-single', 'archive', 'error', 'search' );
		$valid_card_styles = array( 'standard', 'minimal', 'portfolio', 'team' );

		foreach ( $raw_templates as $filename => $tspec ) {
			// Filename must be a simple <slug>.html — no path traversal.
			if ( ! is_array( $tspec ) || ! preg_match( '/^[a-z0-9_\-]+\.html$/', (string) $filename ) ) {
				continue;
			}

			$type = sanitize_key( $tspec['type'] ?? 'content' );
			if ( ! in_array( $type, $valid_types, true ) ) {
				$type = 'content';
			}

			$clean_tspec = array(
				'description' => sanitize_text_field( $tspec['description'] ?? '' ),
				'type'        => $type,
			);

			foreach ( array( 'full_width', 'sidebar', 'related_posts', 'inline_cta' ) as $bool_field ) {
				if ( isset( $tspec[ $bool_field ] ) ) {
					$clean_tspec[ $bool_field ] = (bool) $tspec[ $bool_field ];
				}
			}

			if ( isset( $tspec['post_type'] ) ) {
				$clean_tspec['post_type'] = sanitize_key( $tspec['post_type'] );
			}
			if ( isset( $tspec['columns'] ) ) {
				$cols = (int) $tspec['columns'];
				$clean_tspec['columns'] = in_array( $cols, array( 2, 3, 4 ), true ) ? $cols : 3;
			}
			if ( isset( $tspec['card_style'] ) ) {
				$cs = sanitize_key( $tspec['card_style'] );
				$clean_tspec['card_style'] = in_array( $cs, $valid_card_styles, true ) ? $cs : 'standard';
			}

			if ( ! empty( $tspec['patterns'] ) && is_array( $tspec['patterns'] ) ) {
				$clean_tspec['patterns'] = array_values( array_map( 'sanitize_key', $tspec['patterns'] ) );
			}

			$clean_templates[ (string) $filename ] = $clean_tspec;
		}

		// WordPress requires index.html as a fallback template.
		if ( ! isset( $clean_templates['index.html'] ) ) {
			$clean_templates['index.html'] = array(
				'description' => 'Fallback index',
				'type'        => 'archive',
				'post_type'   => 'post',
				'columns'     => 3,
				'card_style'  => 'standard',
			);
		}

		// Patterns are pre-registered at build time from all registered renderers.
		// The patterns block is only present in legacy manifests that still carry it.
		// Omitting it from the sanitized return when empty avoids a latent validator
		// hazard where validate_templates() would incorrectly use manifest.patterns
		// keys (empty) instead of falling through to get_registered_layouts().
		$result = array(
			'theme'     => $clean_theme,
			'assets'    => $clean_assets,
			'parts'     => $clean_parts,
			'templates' => $clean_templates,
		);
		if ( ! empty( $clean_patterns ) ) {
			$result['patterns'] = $clean_patterns;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[TB] sanitize_manifest: saved colors=' . json_encode( $clean_theme['colors'] ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[TB] sanitize_manifest: front-page patterns=' . json_encode( $result['templates']['front-page.html']['patterns'] ?? 'NOT SET' ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		return $result;
	}
}
