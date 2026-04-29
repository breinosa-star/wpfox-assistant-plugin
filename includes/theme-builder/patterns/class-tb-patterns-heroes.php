<?php
/**
 * Hero layout renderers — Wave 1.
 *
 * Covers: hero-gradient, hero-split, hero-centered, hero-video,
 *         hero-fullscreen, hero-with-form, hero-minimal, hero-animated.
 *
 * Ported from wp-theme-builder/src/pattern_builder.py
 *
 * @package GrayFox
 */

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
// -- This file is a PHP code generator (ob_start/ob_get_clean). Its output is
// -- written to on-disk theme files, not echoed to the browser. Variables are
// -- pre-sanitized (esc_html/esc_attr/intval) at the point of user input.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GrayFox_TB_Patterns_Heroes
 */
class GrayFox_TB_Patterns_Heroes {

	// -------------------------------------------------------------------------
	// hero-gradient
	// -------------------------------------------------------------------------

	public static function render_hero_gradient( array $spec, array $theme ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];

		$section_class  = esc_attr( implode( ' ', array_merge( [ 'gf-hero-section' ], $classes ) ) );
		$headline       = esc_html( $copy['headline']      ?? 'Transform Your Business' );
		$subtext        = esc_html( $copy['subtext']       ?? 'Powerful solutions built for your industry.' );
		$cta_primary    = esc_html( $copy['cta_primary']   ?? 'Get Started' );
		$cta_secondary  = esc_html( $copy['cta_secondary'] ?? 'Learn More' );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $section_class; ?> py-5","align":"full"} -->
<section class="wp-block-group <?php echo $section_class; ?> py-5 alignfull">
  <!-- wp:group {"className":"text-center"} -->
  <div class="wp-block-group text-center">
    <!-- wp:heading {"level":1,"textAlign":"center","className":"gf-hero-heading","textColor":"contrast"} -->
    <h1 class="wp-block-heading has-text-align-center gf-hero-heading has-contrast-color has-text-color"><?php echo $headline; ?></h1>
    <!-- /wp:heading -->
    <!-- wp:paragraph {"align":"center","className":"gf-hero-subtext","textColor":"contrast"} -->
    <p class="has-text-align-center gf-hero-subtext has-contrast-color has-text-color"><?php echo $subtext; ?></p>
    <!-- /wp:paragraph -->
    <!-- wp:buttons {"className":"justify-content-center gap-3 mt-4"} -->
    <div class="wp-block-buttons justify-content-center gap-3 mt-4">
      <!-- wp:button {"backgroundColor":"accent","textColor":"contrast","className":"is-style-fill"} -->
      <div class="wp-block-button is-style-fill"><a class="wp-block-button__link has-contrast-color has-accent-background-color has-text-color has-background wp-element-button"><?php echo $cta_primary; ?></a></div>
      <!-- /wp:button -->
      <!-- wp:button {"className":"is-style-outline","textColor":"contrast"} -->
      <div class="wp-block-button is-style-outline"><a class="wp-block-button__link has-contrast-color has-text-color wp-element-button"><?php echo $cta_secondary; ?></a></div>
      <!-- /wp:button -->
    </div>
    <!-- /wp:buttons -->
  </div>
  <!-- /wp:group -->
</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// hero-split
	// -------------------------------------------------------------------------

	public static function render_hero_split( array $spec, array $theme ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];

		$section_class = esc_attr( implode( ' ', array_merge( [ 'gf-hero-split' ], $classes ) ) );
		$headline      = esc_html( $copy['headline']      ?? 'Ship Features Customers Love' );
		$subtext       = esc_html( $copy['subtext']       ?? 'The platform that connects your team to real user insights.' );
		$cta_primary   = esc_html( $copy['cta_primary']   ?? 'Get Started Free' );
		$cta_secondary = esc_html( $copy['cta_secondary'] ?? 'See a Demo' );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $section_class; ?> py-5","align":"full"} -->
<section class="wp-block-group <?php echo $section_class; ?> py-5 alignfull">
  <!-- wp:columns {"isStackedOnMobile":true,"verticalAlignment":"center"} -->
  <div class="wp-block-columns is-stacked-on-mobile are-vertically-aligned-center">

    <!-- wp:column {"width":"55%"} -->
    <div class="wp-block-column" style="flex-basis:55%">
      <!-- wp:heading {"level":1,"className":"gf-hero-heading","textColor":"foreground"} -->
      <h1 class="wp-block-heading gf-hero-heading has-foreground-color has-text-color"><?php echo $headline; ?></h1>
      <!-- /wp:heading -->
      <!-- wp:paragraph {"className":"gf-hero-subtext","textColor":"muted"} -->
      <p class="gf-hero-subtext has-muted-color has-text-color"><?php echo $subtext; ?></p>
      <!-- /wp:paragraph -->
      <!-- wp:buttons {"className":"gap-3 mt-4"} -->
      <div class="wp-block-buttons gap-3 mt-4">
        <!-- wp:button {"backgroundColor":"primary","textColor":"contrast"} -->
        <div class="wp-block-button"><a class="wp-block-button__link has-contrast-color has-primary-background-color has-text-color has-background wp-element-button"><?php echo $cta_primary; ?></a></div>
        <!-- /wp:button -->
        <!-- wp:button {"className":"is-style-outline","textColor":"primary"} -->
        <div class="wp-block-button is-style-outline"><a class="wp-block-button__link has-primary-color has-text-color wp-element-button"><?php echo $cta_secondary; ?></a></div>
        <!-- /wp:button -->
      </div>
      <!-- /wp:buttons -->
    </div>
    <!-- /wp:column -->

    <!-- wp:column {"width":"45%"} -->
    <div class="wp-block-column" style="flex-basis:45%">
      <!-- wp:image {"aspectRatio":"4/3","scale":"cover","sizeSlug":"large","className":"gf-hero-image","style":{"border":{"radius":"12px"}}} -->
      <figure class="wp-block-image size-large gf-hero-image"><img src="" alt="Hero image" style="aspect-ratio:4/3;object-fit:cover"/></figure>
      <!-- /wp:image -->
    </div>
    <!-- /wp:column -->

  </div>
  <!-- /wp:columns -->
</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// hero-centered
	// -------------------------------------------------------------------------

	public static function render_hero_centered( array $spec, array $theme ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];

		$section_class = esc_attr( implode( ' ', array_merge( [ 'gf-hero-centered', 'gf-section-tint' ], $classes ) ) );
		$headline      = esc_html( $copy['headline']      ?? 'The Platform for Modern Teams' );
		$subtext       = esc_html( $copy['subtext']       ?? 'Everything you need, nothing you don\'t.' );
		$cta_primary   = esc_html( $copy['cta_primary']   ?? 'Start for Free' );
		$cta_secondary = esc_html( $copy['cta_secondary'] ?? 'Watch Demo' );
		$eyebrow       = esc_html( $copy['eyebrow']       ?? '' );

		if ( $eyebrow ) {
			ob_start(); ?>
    <!-- wp:paragraph {"align":"center","className":"gf-eyebrow","textColor":"accent"} -->
    <p class="has-text-align-center gf-eyebrow has-accent-color has-text-color"><?php echo $eyebrow; ?></p>
    <!-- /wp:paragraph -->
			<?php $eyebrow_block = ob_get_clean();
		} else {
			$eyebrow_block = '';
		}

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $section_class; ?> py-5","align":"full"} -->
<section class="wp-block-group <?php echo $section_class; ?> py-5 alignfull">
  <!-- wp:group {"className":"text-center"} -->
  <div class="wp-block-group text-center">
<?php echo $eyebrow_block; ?>
    <!-- wp:heading {"level":1,"textAlign":"center","className":"gf-hero-heading","textColor":"foreground"} -->
    <h1 class="wp-block-heading has-text-align-center gf-hero-heading has-foreground-color has-text-color"><?php echo $headline; ?></h1>
    <!-- /wp:heading -->
    <!-- wp:paragraph {"align":"center","className":"gf-hero-subtext","textColor":"muted"} -->
    <p class="has-text-align-center gf-hero-subtext has-muted-color has-text-color"><?php echo $subtext; ?></p>
    <!-- /wp:paragraph -->
    <!-- wp:buttons {"className":"justify-content-center gap-3 mt-4"} -->
    <div class="wp-block-buttons justify-content-center gap-3 mt-4">
      <!-- wp:button {"backgroundColor":"primary","textColor":"contrast"} -->
      <div class="wp-block-button"><a class="wp-block-button__link has-contrast-color has-primary-background-color has-text-color has-background wp-element-button"><?php echo $cta_primary; ?></a></div>
      <!-- /wp:button -->
      <!-- wp:button {"className":"is-style-outline","textColor":"primary"} -->
      <div class="wp-block-button is-style-outline"><a class="wp-block-button__link has-primary-color has-text-color wp-element-button"><?php echo $cta_secondary; ?></a></div>
      <!-- /wp:button -->
    </div>
    <!-- /wp:buttons -->
  </div>
  <!-- /wp:group -->
</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// hero-video
	// -------------------------------------------------------------------------

	public static function render_hero_video( array $spec, array $theme ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];

		$section_class = esc_attr( implode( ' ', array_merge( [ 'gf-hero-video' ], $classes ) ) );
		$headline      = esc_html( $copy['headline']    ?? 'See What\'s Possible' );
		$subtext       = esc_html( $copy['subtext']     ?? 'Watch how teams achieve more with our platform.' );
		$cta_primary   = esc_html( $copy['cta_primary'] ?? 'Get Started' );
		$video_url     = esc_url( $copy['video_url']    ?? ( defined( 'GRAYFOX_URL' ) ? GRAYFOX_URL . 'assets/video/hero-placeholder.mp4' : '' ) );

		$video_el = $video_url
			? "<video class=\"gf-hero-video__bg\" autoplay muted loop playsinline src=\"{$video_url}\"></video>"
			: '<div class="gf-hero-video__placeholder" aria-hidden="true"><i class="bi bi-play-circle-fill"></i></div>';

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $section_class; ?>","align":"full"} -->
<section class="wp-block-group <?php echo $section_class; ?> alignfull">
<!-- wp:html -->
<?php echo $video_el; ?>
<div class="gf-hero-video__overlay" aria-hidden="true"></div>
<!-- /wp:html -->
<!-- wp:group {"className":"gf-hero-video__content text-center"} -->
<div class="wp-block-group gf-hero-video__content text-center">
  <!-- wp:heading {"level":1,"textAlign":"center","className":"gf-hero-heading","textColor":"contrast"} -->
  <h1 class="wp-block-heading has-text-align-center gf-hero-heading has-contrast-color has-text-color"><?php echo $headline; ?></h1>
  <!-- /wp:heading -->
  <!-- wp:paragraph {"align":"center","className":"gf-hero-subtext","textColor":"contrast"} -->
  <p class="has-text-align-center gf-hero-subtext has-contrast-color has-text-color"><?php echo $subtext; ?></p>
  <!-- /wp:paragraph -->
  <!-- wp:buttons {"className":"justify-content-center mt-4"} -->
  <div class="wp-block-buttons justify-content-center mt-4">
    <!-- wp:button {"backgroundColor":"accent","textColor":"contrast"} -->
    <div class="wp-block-button"><a class="wp-block-button__link has-contrast-color has-accent-background-color has-text-color has-background wp-element-button"><?php echo $cta_primary; ?></a></div>
    <!-- /wp:button -->
  </div>
  <!-- /wp:buttons -->
</div>
<!-- /wp:group -->
</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// hero-minimal
	// -------------------------------------------------------------------------

	public static function render_hero_minimal( array $spec, array $theme ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];

		$section_class = esc_attr( implode( ' ', $classes ) ?: 'gf-section-tint' );
		$headline      = esc_html( $copy['headline']    ?? 'Simple. Focused. Effective.' );
		$subtext       = esc_html( $copy['subtext']     ?? 'The essentials, done right.' );
		$cta_primary   = esc_html( $copy['cta_primary'] ?? 'Get Started' );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $section_class; ?> py-5","align":"full"} -->
<section class="wp-block-group <?php echo $section_class; ?> py-5 alignfull">
  <!-- wp:group -->
  <div class="wp-block-group">
    <!-- wp:heading {"level":1,"className":"gf-hero-heading","textColor":"foreground"} -->
    <h1 class="wp-block-heading gf-hero-heading has-foreground-color has-text-color"><?php echo $headline; ?></h1>
    <!-- /wp:heading -->
    <!-- wp:paragraph {"className":"gf-hero-subtext","textColor":"muted"} -->
    <p class="gf-hero-subtext has-muted-color has-text-color"><?php echo $subtext; ?></p>
    <!-- /wp:paragraph -->
    <!-- wp:buttons {"className":"mt-4"} -->
    <div class="wp-block-buttons mt-4">
      <!-- wp:button {"backgroundColor":"primary","textColor":"contrast"} -->
      <div class="wp-block-button"><a class="wp-block-button__link has-contrast-color has-primary-background-color has-text-color has-background wp-element-button"><?php echo $cta_primary; ?></a></div>
      <!-- /wp:button -->
    </div>
    <!-- /wp:buttons -->
  </div>
  <!-- /wp:group -->
</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// hero-with-form
	// -------------------------------------------------------------------------

	public static function render_hero_with_form( array $spec, array $theme ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];

		$section_class = esc_attr( implode( ' ', array_merge( [ 'gf-hero-section' ], $classes ) ) );
		$headline      = esc_html( $copy['headline']     ?? 'Get Early Access' );
		$subtext       = esc_html( $copy['subtext']      ?? 'Join thousands of teams already using our platform.' );
		$form_heading  = esc_html( $copy['form_heading'] ?? 'Start your free trial' );
		$form_cta      = esc_html( $copy['form_cta']     ?? 'Create Account' );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $section_class; ?> py-5","align":"full"} -->
<section class="wp-block-group <?php echo $section_class; ?> py-5 alignfull">
  <!-- wp:columns {"isStackedOnMobile":true,"verticalAlignment":"center"} -->
  <div class="wp-block-columns is-stacked-on-mobile are-vertically-aligned-center">

    <!-- wp:column {"width":"50%"} -->
    <div class="wp-block-column" style="flex-basis:50%">
      <!-- wp:heading {"level":1,"className":"gf-hero-heading","textColor":"contrast"} -->
      <h1 class="wp-block-heading gf-hero-heading has-contrast-color has-text-color"><?php echo $headline; ?></h1>
      <!-- /wp:heading -->
      <!-- wp:paragraph {"className":"gf-hero-subtext","textColor":"contrast"} -->
      <p class="gf-hero-subtext has-contrast-color has-text-color"><?php echo $subtext; ?></p>
      <!-- /wp:paragraph -->
    </div>
    <!-- /wp:column -->

    <!-- wp:column {"width":"50%"} -->
    <div class="wp-block-column" style="flex-basis:50%">
<!-- wp:html -->
<div class="gf-form-card">
  <h3 class="gf-card-title mb-1"><?php echo $form_heading; ?></h3>
  <p class="small text-muted mb-4">No credit card required.</p>
  <div class="mb-3">
    <label class="gf-form-label" for="hero-form-name">Full name</label>
    <input id="hero-form-name" type="text" class="gf-form-input" placeholder="Jane Smith" />
  </div>
  <div class="mb-4">
    <label class="gf-form-label" for="hero-form-email">Work email</label>
    <input id="hero-form-email" type="email" class="gf-form-input" placeholder="jane@company.com" />
  </div>
  <button type="submit" class="gf-form-submit w-100"><?php echo $form_cta; ?></button>
</div>
<!-- /wp:html -->
    </div>
    <!-- /wp:column -->

  </div>
  <!-- /wp:columns -->
</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// hero-fullscreen
	// -------------------------------------------------------------------------

	public static function render_hero_fullscreen( array $spec, array $theme ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];

		$section_class = esc_attr( implode( ' ', array_merge( [ 'gf-hero-section' ], $classes ) ) );
		$headline      = esc_html( $copy['headline']      ?? 'Built for What\'s Next' );
		$subtext       = esc_html( $copy['subtext']       ?? 'The platform that scales with your ambition.' );
		$cta_primary   = esc_html( $copy['cta_primary']   ?? 'Start Building' );
		$cta_secondary = esc_html( $copy['cta_secondary'] ?? 'Explore Features' );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $section_class; ?>","align":"full"} -->
<section class="wp-block-group <?php echo $section_class; ?> alignfull">
  <!-- wp:group {"className":"text-center py-5"} -->
  <div class="wp-block-group text-center py-5">
    <!-- wp:heading {"level":1,"textAlign":"center","className":"gf-hero-heading","textColor":"contrast"} -->
    <h1 class="wp-block-heading has-text-align-center gf-hero-heading has-contrast-color has-text-color"><?php echo $headline; ?></h1>
    <!-- /wp:heading -->
    <!-- wp:paragraph {"align":"center","className":"gf-hero-subtext","textColor":"contrast"} -->
    <p class="has-text-align-center gf-hero-subtext has-contrast-color has-text-color"><?php echo $subtext; ?></p>
    <!-- /wp:paragraph -->
    <!-- wp:buttons {"className":"justify-content-center gap-3 mt-4"} -->
    <div class="wp-block-buttons justify-content-center gap-3 mt-4">
      <!-- wp:button {"backgroundColor":"accent","textColor":"contrast","className":"is-style-fill"} -->
      <div class="wp-block-button is-style-fill"><a class="wp-block-button__link has-contrast-color has-accent-background-color has-text-color has-background wp-element-button"><?php echo $cta_primary; ?></a></div>
      <!-- /wp:button -->
      <!-- wp:button {"className":"is-style-outline","textColor":"contrast"} -->
      <div class="wp-block-button is-style-outline"><a class="wp-block-button__link has-contrast-color has-text-color wp-element-button"><?php echo $cta_secondary; ?></a></div>
      <!-- /wp:button -->
    </div>
    <!-- /wp:buttons -->
  </div>
  <!-- /wp:group -->
</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// hero-animated (CSS animation classes, no JS required)
	// -------------------------------------------------------------------------

	public static function render_hero_animated( array $spec, array $theme ): string {
		// Delegates to gradient with an extra animation class.
		$spec['css_classes'] = array_merge(
			$spec['css_classes'] ?? [],
			[ 'gf-hero-animated' ]
		);
		return self::render_hero_gradient( $spec, $theme );
	}
}

// Register all hero renderers.
GrayFox_TB_PatternBuilder::register_renderers( [
	'hero-gradient'   => [ GrayFox_TB_Patterns_Heroes::class, 'render_hero_gradient' ],
	'hero-split'      => [ GrayFox_TB_Patterns_Heroes::class, 'render_hero_split' ],
	'hero-centered'   => [ GrayFox_TB_Patterns_Heroes::class, 'render_hero_centered' ],
	'hero-video'      => [ GrayFox_TB_Patterns_Heroes::class, 'render_hero_video' ],
	'hero-minimal'    => [ GrayFox_TB_Patterns_Heroes::class, 'render_hero_minimal' ],
	'hero-with-form'  => [ GrayFox_TB_Patterns_Heroes::class, 'render_hero_with_form' ],
	'hero-fullscreen' => [ GrayFox_TB_Patterns_Heroes::class, 'render_hero_fullscreen' ],
	'hero-animated'   => [ GrayFox_TB_Patterns_Heroes::class, 'render_hero_animated' ],
] );
