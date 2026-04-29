<?php
/**
 * Core layout renderers — Wave 1.
 *
 * Covers: three-column-cards, six-icon-grid, image-checklist-split,
 *         numbered-steps, testimonials-grid, logo-strip, stats-row,
 *         gradient-cta-band, light-dual-cta, newsletter-form,
 *         three-tier-pricing, contact-form-info, accordion-faq,
 *         image-text-split, mission-values, announcement-banner,
 *         pull-quote-block, callout-box, section-divider-block.
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
 * Class GrayFox_TB_Patterns_Core
 */
class GrayFox_TB_Patterns_Core {

	// -------------------------------------------------------------------------
	// three-column-cards (features)
	// -------------------------------------------------------------------------

	public static function render_three_column_cards( array $spec, array $theme ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];

		$section_class   = esc_attr( implode( ' ', $classes ) ?: 'gf-section-tint' );
		$section_heading = esc_html( $copy['section_heading'] ?? 'What We Offer' );
		$section_subtext = esc_html( $copy['section_subtext'] ?? '' );

		$cards = '';
		for ( $i = 1; $i <= 3; $i++ ) {
			$title = esc_html( $copy[ "card_{$i}_title" ] ?? "Feature {$i}" );
			$body  = esc_html( $copy[ "card_{$i}_body" ]  ?? 'Describe this feature here.' );
			$icon  = esc_attr( $copy[ "card_{$i}_icon" ]  ?? 'bi-star' );
			ob_start(); ?>

    <!-- wp:column -->
    <div class="wp-block-column">
      <!-- wp:group {"className":"gf-feature-card card h-100","style":{"spacing":{"padding":{"top":"2rem","bottom":"2rem","left":"1.75rem","right":"1.75rem"}}}} -->
      <div class="wp-block-group gf-feature-card card h-100">
        <!-- wp:group {"className":"gf-feature-icon","style":{"spacing":{"margin":{"bottom":"1.25rem"}}}} -->
        <div class="wp-block-group gf-feature-icon">
          <!-- wp:html -->
          <i class="<?php echo $icon; ?> gf-bi-icon" style="font-size:1.5rem;color:#ffffff;" aria-hidden="true"></i>
          <!-- /wp:html -->
        </div>
        <!-- /wp:group -->
        <!-- wp:heading {"level":3,"className":"gf-card-title"} -->
        <h3 class="wp-block-heading gf-card-title"><?php echo $title; ?></h3>
        <!-- /wp:heading -->
        <!-- wp:paragraph {"textColor":"muted"} -->
        <p class="has-muted-color has-text-color"><?php echo $body; ?></p>
        <!-- /wp:paragraph -->
      </div>
      <!-- /wp:group -->
    </div>
    <!-- /wp:column -->
			<?php $cards .= ob_get_clean();
		}

		if ( $section_subtext ) {
			ob_start(); ?>
  <!-- wp:paragraph {"align":"center","textColor":"muted","className":"gf-section-subtext","style":{"spacing":{"margin":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|50"}}}} -->
  <p class="has-text-align-center gf-section-subtext has-muted-color has-text-color"><?php echo $section_subtext; ?></p>
  <!-- /wp:paragraph -->
			<?php $subtext_block = ob_get_clean();
		} else {
			$subtext_block = '';
		}

		ob_start(); ?>
<!-- wp:group {"className":"<?php echo $section_class; ?>","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group <?php echo $section_class; ?> alignfull py-5">
  <!-- wp:heading {"textAlign":"center","className":"gf-section-heading"} -->
  <h2 class="wp-block-heading has-text-align-center gf-section-heading"><?php echo $section_heading; ?></h2>
  <!-- /wp:heading -->
<?php echo $subtext_block; ?>
  <!-- wp:columns {"isStackedOnMobile":true,"style":{"spacing":{"blockGap":"2rem"}}} -->
  <div class="wp-block-columns">
<?php echo $cards; ?>
  </div>
  <!-- /wp:columns -->
</div>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// six-icon-grid
	// -------------------------------------------------------------------------

	public static function render_six_icon_grid( array $spec, array $theme ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];

		$section_class   = esc_attr( implode( ' ', $classes ) ?: 'gf-section-tint' );
		$section_heading = esc_html( $copy['section_heading'] ?? 'Everything You Need' );

		$items = '';
		for ( $i = 1; $i <= 6; $i++ ) {
			$title = esc_html( $copy[ "item_{$i}_title" ] ?? "Feature {$i}" );
			$body  = esc_html( $copy[ "item_{$i}_body" ]  ?? 'Short description.' );
			$icon  = esc_attr( $copy[ "item_{$i}_icon" ]  ?? 'bi-check-circle' );
			ob_start(); ?>

    <!-- wp:column -->
    <div class="wp-block-column">
      <!-- wp:group {"style":{"spacing":{"blockGap":"0.75rem","padding":{"top":"1.5rem","bottom":"1.5rem","left":"1.5rem","right":"1.5rem"}}}} -->
      <div class="wp-block-group">
        <!-- wp:html -->
        <i class="<?php echo $icon; ?> gf-bi-icon" style="font-size:2rem;" aria-hidden="true"></i>
        <!-- /wp:html -->
        <!-- wp:heading {"level":4,"className":"gf-card-title"} -->
        <h4 class="wp-block-heading gf-card-title"><?php echo $title; ?></h4>
        <!-- /wp:heading -->
        <!-- wp:paragraph {"textColor":"muted"} -->
        <p class="has-muted-color has-text-color"><?php echo $body; ?></p>
        <!-- /wp:paragraph -->
      </div>
      <!-- /wp:group -->
    </div>
    <!-- /wp:column -->
			<?php $items .= ob_get_clean();
		}

		ob_start(); ?>
<!-- wp:group {"className":"<?php echo $section_class; ?>","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group <?php echo $section_class; ?> alignfull py-5">
  <!-- wp:heading {"textAlign":"center","className":"gf-section-heading"} -->
  <h2 class="wp-block-heading has-text-align-center gf-section-heading"><?php echo $section_heading; ?></h2>
  <!-- /wp:heading -->
  <!-- wp:columns {"isStackedOnMobile":true,"style":{"spacing":{"blockGap":"1.5rem","margin":{"top":"var:preset|spacing|50"}}}} -->
  <div class="wp-block-columns">
<?php echo $items; ?>
  </div>
  <!-- /wp:columns -->
</div>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// image-checklist-split
	// -------------------------------------------------------------------------

	public static function render_image_checklist_split( array $spec, array $theme ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];

		$css             = implode( ' ', $classes ) ?: 'gf-image-checklist-split';
		$section_heading = esc_html( $copy['section_heading'] ?? 'Why Choose Us' );
		$subtext         = esc_html( $copy['subtext']         ?? '' );

		$item_defaults = [
			'Fast onboarding — live within one business day.',
			'No engineering required — zero code setup.',
			'SOC 2 Type II certified — data encrypted end-to-end.',
			'24/7 support included on every plan.',
		];

		$items = '';
		for ( $i = 1; $i <= 4; $i++ ) {
			$item = esc_html( $copy[ "item_{$i}" ] ?? $item_defaults[ $i - 1 ] );
			if ( ! $item ) {
				break;
			}
			ob_start(); ?>
      <!-- wp:group {"className":"gf-icon-list-item"} -->
      <div class="wp-block-group gf-icon-list-item">
        <!-- wp:html --><i class="bi bi-check-circle-fill gf-check-icon" aria-hidden="true"></i><!-- /wp:html -->
        <!-- wp:paragraph -->
        <p><?php echo $item; ?></p>
        <!-- /wp:paragraph -->
      </div>
      <!-- /wp:group -->
			<?php $items .= ob_get_clean();
		}

		if ( $subtext ) {
			ob_start(); ?>
      <!-- wp:paragraph {"textColor":"muted","className":"gf-section-subtext"} -->
      <p class="gf-section-subtext has-muted-color has-text-color"><?php echo $subtext; ?></p>
      <!-- /wp:paragraph -->
			<?php $subtext_block = ob_get_clean();
		} else {
			$subtext_block = '';
		}

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?> py-5","align":"full"} -->
<section class="wp-block-group <?php echo $css; ?> py-5 alignfull">
  <!-- wp:columns {"isStackedOnMobile":true,"verticalAlignment":"center"} -->
  <div class="wp-block-columns is-stacked-on-mobile are-vertically-aligned-center">

    <!-- wp:column {"width":"50%"} -->
    <div class="wp-block-column" style="flex-basis:50%">
      <!-- wp:image {"sizeSlug":"large","className":"gf-split-img"} -->
      <figure class="wp-block-image size-large gf-split-img"><img src="" alt="Feature illustration"/></figure>
      <!-- /wp:image -->
    </div>
    <!-- /wp:column -->

    <!-- wp:column {"width":"50%"} -->
    <div class="wp-block-column" style="flex-basis:50%">
      <!-- wp:heading {"textColor":"primary","className":"gf-section-heading"} -->
      <h2 class="wp-block-heading gf-section-heading has-primary-color has-text-color"><?php echo $section_heading; ?></h2>
      <!-- /wp:heading -->
<?php echo $subtext_block; ?>
      <!-- wp:group {"className":"d-flex flex-column gap-3 mt-4"} -->
      <div class="wp-block-group d-flex flex-column gap-3 mt-4">
<?php echo $items; ?>
      </div>
      <!-- /wp:group -->
    </div>
    <!-- /wp:column -->

  </div>
  <!-- /wp:columns -->
</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// numbered-steps
	// -------------------------------------------------------------------------

	public static function render_numbered_steps( array $spec, array $theme ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];

		$section_class   = esc_attr( implode( ' ', $classes ) ?: 'gf-section-tint' );
		$section_heading = esc_html( $copy['section_heading'] ?? 'How It Works' );

		$steps = '';
		for ( $i = 1; $i <= 4; $i++ ) {
			// Check before assigning so we never render a blank step card.
			if ( empty( $copy[ "step_{$i}_title" ] ) ) {
				break;
			}
			$title = esc_html( $copy[ "step_{$i}_title" ] );
			$body  = esc_html( $copy[ "step_{$i}_body" ] ?? 'Describe this step.' );
			ob_start(); ?>

    <!-- wp:column -->
    <div class="wp-block-column">
      <!-- wp:group {"className":"gf-step"} -->
      <div class="wp-block-group gf-step">
        <!-- wp:paragraph {"className":"gf-step-number","textColor":"primary"} -->
        <p class="gf-step-number has-primary-color has-text-color"><?php echo $i; ?></p>
        <!-- /wp:paragraph -->
        <!-- wp:heading {"level":3,"className":"gf-card-title"} -->
        <h3 class="wp-block-heading gf-card-title"><?php echo $title; ?></h3>
        <!-- /wp:heading -->
        <!-- wp:paragraph {"textColor":"muted"} -->
        <p class="has-muted-color has-text-color"><?php echo $body; ?></p>
        <!-- /wp:paragraph -->
      </div>
      <!-- /wp:group -->
    </div>
    <!-- /wp:column -->
			<?php $steps .= ob_get_clean();
		}

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $section_class; ?> py-5","align":"full"} -->
<section class="wp-block-group <?php echo $section_class; ?> py-5 alignfull">
  <!-- wp:heading {"textAlign":"center","className":"gf-section-heading"} -->
  <h2 class="wp-block-heading has-text-align-center gf-section-heading"><?php echo $section_heading; ?></h2>
  <!-- /wp:heading -->
  <!-- wp:columns {"isStackedOnMobile":true} -->
  <div class="wp-block-columns is-stacked-on-mobile mt-4">
<?php echo $steps; ?>
  </div>
  <!-- /wp:columns -->
</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// testimonials-grid
	// -------------------------------------------------------------------------

	public static function render_testimonials_grid( array $spec, array $theme ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];

		$section_class   = esc_attr( implode( ' ', $classes ) ?: 'gf-section-tint' );
		$section_heading = esc_html( $copy['section_heading'] ?? 'What Our Customers Say' );

		$cards = '';
		for ( $i = 1; $i <= 3; $i++ ) {
			$quote  = esc_html( $copy[ "quote_{$i}" ]  ?? 'This product changed everything for our team.' );
			$author = esc_html( $copy[ "author_{$i}" ] ?? "Customer {$i}" );
			$role   = esc_html( $copy[ "role_{$i}" ]   ?? '' );
			$stars  = '<span class="gf-star-rating" aria-label="5 stars">★★★★★</span>';
			ob_start(); ?>

    <!-- wp:column -->
    <div class="wp-block-column">
      <!-- wp:group {"className":"gf-testimonial-card card h-100"} -->
      <div class="wp-block-group gf-testimonial-card card h-100">
        <!-- wp:html -->
        <?php echo $stars; ?>
        <!-- /wp:html -->
        <!-- wp:paragraph {"className":"gf-testimonial-quote"} -->
        <p class="gf-testimonial-quote">&#8220;<?php echo $quote; ?>&#8221;</p>
        <!-- /wp:paragraph -->
        <!-- wp:group {"className":"gf-testimonial-author-group"} -->
        <div class="wp-block-group gf-testimonial-author-group">
          <!-- wp:paragraph {"className":"gf-testimonial-author","textColor":"foreground"} -->
          <p class="gf-testimonial-author has-foreground-color has-text-color"><strong><?php echo $author; ?></strong><?php echo $role; ?></p>
          <!-- /wp:paragraph -->
        </div>
        <!-- /wp:group -->
      </div>
      <!-- /wp:group -->
    </div>
    <!-- /wp:column -->
			<?php $cards .= ob_get_clean();
		}

		ob_start(); ?>
<!-- wp:group {"className":"<?php echo $section_class; ?> py-5","align":"full","layout":{"type":"constrained"}} -->
<div class="wp-block-group <?php echo $section_class; ?> py-5 alignfull">
  <!-- wp:heading {"textAlign":"center","className":"gf-section-heading"} -->
  <h2 class="wp-block-heading has-text-align-center gf-section-heading"><?php echo $section_heading; ?></h2>
  <!-- /wp:heading -->
  <!-- wp:columns {"isStackedOnMobile":true} -->
  <div class="wp-block-columns is-stacked-on-mobile">
<?php echo $cards; ?>
  </div>
  <!-- /wp:columns -->
</div>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// logo-strip
	// -------------------------------------------------------------------------

	public static function render_logo_strip( array $spec, array $theme ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];

		$section_class = esc_attr( implode( ' ', array_merge( [ 'gf-logo-strip' ], $classes ) ) );
		$heading       = esc_html( $copy['heading'] ?? 'Trusted by teams at' );

		ob_start(); ?>
<!-- wp:group {"className":"<?php echo $section_class; ?>","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group <?php echo $section_class; ?> alignfull py-5">
  <!-- wp:paragraph {"align":"center","textColor":"muted","className":"gf-eyebrow","style":{"spacing":{"margin":{"bottom":"var:preset|spacing|40"}}}} -->
  <p class="has-text-align-center gf-eyebrow has-muted-color has-text-color"><?php echo $heading; ?></p>
  <!-- /wp:paragraph -->
  <!-- wp:group {"className":"gf-logo-grid","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"center","alignItems":"center"},"style":{"spacing":{"blockGap":"2.5rem"}}} -->
  <div class="wp-block-group gf-logo-grid">
    <!-- wp:image {"width":120,"sizeSlug":"full","className":"gf-logo-item"} -->
    <figure class="wp-block-image size-full gf-logo-item is-resized"><img src="" alt="Logo 1" width="120"/></figure>
    <!-- /wp:image -->
    <!-- wp:image {"width":120,"sizeSlug":"full","className":"gf-logo-item"} -->
    <figure class="wp-block-image size-full gf-logo-item is-resized"><img src="" alt="Logo 2" width="120"/></figure>
    <!-- /wp:image -->
    <!-- wp:image {"width":120,"sizeSlug":"full","className":"gf-logo-item"} -->
    <figure class="wp-block-image size-full gf-logo-item is-resized"><img src="" alt="Logo 3" width="120"/></figure>
    <!-- /wp:image -->
    <!-- wp:image {"width":120,"sizeSlug":"full","className":"gf-logo-item"} -->
    <figure class="wp-block-image size-full gf-logo-item is-resized"><img src="" alt="Logo 4" width="120"/></figure>
    <!-- /wp:image -->
    <!-- wp:image {"width":120,"sizeSlug":"full","className":"gf-logo-item"} -->
    <figure class="wp-block-image size-full gf-logo-item is-resized"><img src="" alt="Logo 5" width="120"/></figure>
    <!-- /wp:image -->
  </div>
  <!-- /wp:group -->
</div>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// stats-row
	// -------------------------------------------------------------------------

	public static function render_stats_row( array $spec, array $theme ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];

		$section_class = esc_attr( implode( ' ', $classes ) ?: 'gf-section-tint' );

		$stats = '';
		for ( $i = 1; $i <= 4; $i++ ) {
			$number = esc_html( $copy[ "stat_{$i}_number" ] ?? '' );
			$label  = esc_html( $copy[ "stat_{$i}_label" ]  ?? '' );
			if ( ! $number ) break;
			ob_start(); ?>

    <!-- wp:column -->
    <div class="wp-block-column">
      <!-- wp:group {"className":"gf-stat-item","textAlign":"center","style":{"spacing":{"blockGap":"0.5rem"}}} -->
      <div class="wp-block-group gf-stat-item has-text-align-center">
        <!-- wp:heading {"level":3,"textAlign":"center","className":"gf-stat-number","textColor":"primary"} -->
        <h3 class="wp-block-heading has-text-align-center gf-stat-number has-primary-color has-text-color"><?php echo $number; ?></h3>
        <!-- /wp:heading -->
        <!-- wp:paragraph {"align":"center","className":"gf-stat-label","textColor":"muted"} -->
        <p class="has-text-align-center gf-stat-label has-muted-color has-text-color"><?php echo $label; ?></p>
        <!-- /wp:paragraph -->
      </div>
      <!-- /wp:group -->
    </div>
    <!-- /wp:column -->
			<?php $stats .= ob_get_clean();
		}

		ob_start(); ?>
<!-- wp:group {"className":"<?php echo $section_class; ?>","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group <?php echo $section_class; ?> alignfull py-5">
  <!-- wp:columns {"isStackedOnMobile":true,"style":{"spacing":{"blockGap":"2rem"}}} -->
  <div class="wp-block-columns">
<?php echo $stats; ?>
  </div>
  <!-- /wp:columns -->
</div>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// gradient-cta-band
	// -------------------------------------------------------------------------

	public static function render_gradient_cta_band( array $spec, array $theme ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];

		$section_class = esc_attr( implode( ' ', array_merge( [ 'gf-cta-band' ], $classes ) ) );
		$heading       = esc_html( $copy['heading']     ?? 'Ready to Get Started?' );
		$subtext       = esc_html( $copy['subtext']     ?? 'Join thousands of happy customers today.' );
		$cta_label     = esc_html( $copy['cta_label']   ?? $copy['cta_primary'] ?? 'Start Now' );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $section_class; ?> py-5","align":"full"} -->
<section class="wp-block-group <?php echo $section_class; ?> py-5 alignfull">
  <!-- wp:group {"className":"text-center"} -->
  <div class="wp-block-group text-center">
    <!-- wp:heading {"textAlign":"center","className":"gf-cta-heading","textColor":"contrast"} -->
    <h2 class="wp-block-heading has-text-align-center gf-cta-heading has-contrast-color has-text-color"><?php echo $heading; ?></h2>
    <!-- /wp:heading -->
    <!-- wp:paragraph {"align":"center","className":"gf-cta-subtext","textColor":"contrast"} -->
    <p class="has-text-align-center gf-cta-subtext has-contrast-color has-text-color"><?php echo $subtext; ?></p>
    <!-- /wp:paragraph -->
    <!-- wp:buttons {"className":"justify-content-center mt-4"} -->
    <div class="wp-block-buttons justify-content-center mt-4">
      <!-- wp:button {"backgroundColor":"background","textColor":"primary","className":"is-style-fill"} -->
      <div class="wp-block-button is-style-fill"><a class="wp-block-button__link has-primary-color has-background-background-color has-text-color has-background wp-element-button"><?php echo $cta_label; ?></a></div>
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
	// light-dual-cta
	// -------------------------------------------------------------------------

	public static function render_light_dual_cta( array $spec, array $theme ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];

		$section_class = esc_attr( implode( ' ', array_merge( [ 'gf-cta-two-up', 'gf-section-tint' ], $classes ) ) );
		$heading       = esc_html( $copy['heading']       ?? 'Ready to take the next step?' );
		$cta_primary   = esc_html( $copy['cta_primary']   ?? 'Get Started' );
		$cta_secondary = esc_html( $copy['cta_secondary'] ?? 'Learn More' );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $section_class; ?> py-5","align":"full"} -->
<section class="wp-block-group <?php echo $section_class; ?> py-5 alignfull">
  <!-- wp:group {"className":"d-flex flex-wrap align-items-center justify-content-between gap-4"} -->
  <div class="wp-block-group d-flex flex-wrap align-items-center justify-content-between gap-4">
    <!-- wp:heading {"className":"gf-cta-heading","textColor":"foreground"} -->
    <h2 class="wp-block-heading gf-cta-heading has-foreground-color has-text-color"><?php echo $heading; ?></h2>
    <!-- /wp:heading -->
    <!-- wp:buttons {"className":"gap-3"} -->
    <div class="wp-block-buttons gap-3">
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
	// newsletter-form
	// -------------------------------------------------------------------------

	public static function render_newsletter_form( array $spec, array $theme ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];

		$section_class = esc_attr( implode( ' ', array_merge( [ 'gf-newsletter-section' ], $classes ) ) );
		$heading       = esc_html( $copy['heading']     ?? 'Stay in the Loop' );
		$subtext       = esc_html( $copy['subtext']     ?? 'Get the latest updates delivered to your inbox.' );
		$placeholder   = esc_attr( $copy['placeholder'] ?? 'Enter your email address' );
		$btn_label     = esc_html( $copy['btn_label']   ?? 'Subscribe' );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $section_class; ?> py-5","align":"full"} -->
<section class="wp-block-group <?php echo $section_class; ?> py-5 alignfull">
  <!-- wp:heading {"textAlign":"center","className":"gf-section-heading"} -->
  <h2 class="wp-block-heading has-text-align-center gf-section-heading"><?php echo $heading; ?></h2>
  <!-- /wp:heading -->
  <!-- wp:paragraph {"align":"center","textColor":"muted","className":"gf-section-subtext"} -->
  <p class="has-text-align-center gf-section-subtext has-muted-color has-text-color"><?php echo $subtext; ?></p>
  <!-- /wp:paragraph -->
  <!-- wp:html -->
  <form class="gf-newsletter-form mt-4" action="#" method="post" novalidate>
    <input type="email" class="form-control form-control-lg" placeholder="<?php echo $placeholder; ?>" aria-label="<?php echo $placeholder; ?>" required>
    <button type="submit" class="btn btn-primary fw-bold px-4"><?php echo $btn_label; ?></button>
  </form>
  <p class="text-center text-muted small mt-3">No spam, ever. Unsubscribe anytime.</p>
  <!-- /wp:html -->
</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// three-tier-pricing
	// -------------------------------------------------------------------------

	public static function render_three_tier_pricing( array $spec, array $theme ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];

		$section_class   = esc_attr( implode( ' ', $classes ) ?: 'gf-section-tint' );
		$section_heading = esc_html( $copy['section_heading'] ?? 'Simple, Transparent Pricing' );
		$section_subtext = esc_html( $copy['section_subtext'] ?? 'No hidden fees. Cancel anytime.' );

		$tiers = [];
		for ( $i = 1; $i <= 3; $i++ ) {
			$tiers[] = [
				'name'     => esc_html( $copy[ "tier_{$i}_name" ]  ?? [ 'Starter', 'Pro', 'Scale' ][ $i - 1 ] ),
				'price'    => esc_html( $copy[ "tier_{$i}_price" ] ?? [ '$0', '$49/mo', '$149/mo' ][ $i - 1 ] ),
				'desc'     => esc_html( $copy[ "tier_{$i}_desc" ]  ?? '' ),
				'cta'      => esc_html( $copy[ "tier_{$i}_cta" ]   ?? 'Get Started' ),
				'featured' => ( $i === 2 ),
			];
		}

		$cards = '';
		foreach ( $tiers as $tier ) {
			$card_class = $tier['featured']
				? 'gf-pricing-card gf-pricing-featured'
				: 'gf-pricing-card';
			$bg_color  = $tier['featured'] ? 'primary' : 'background';
			$text_clr  = $tier['featured'] ? 'contrast' : 'foreground';
			$muted_clr = $tier['featured'] ? 'contrast' : 'muted';

			ob_start(); ?>

    <!-- wp:column -->
    <div class="wp-block-column">
      <!-- wp:group {"className":"<?php echo $card_class; ?>","backgroundColor":"<?php echo $bg_color; ?>"} -->
      <div class="wp-block-group <?php echo $card_class; ?> has-<?php echo $bg_color; ?>-background-color has-background">
        <!-- wp:heading {"level":3,"className":"gf-card-title","textColor":"<?php echo $text_clr; ?>"} -->
        <h3 class="wp-block-heading gf-card-title has-<?php echo $text_clr; ?>-color has-text-color"><?php echo $tier['name']; ?></h3>
        <!-- /wp:heading -->
        <!-- wp:heading {"level":2,"className":"gf-price-amount","textColor":"<?php echo $text_clr; ?>"} -->
        <h2 class="wp-block-heading gf-price-amount has-<?php echo $text_clr; ?>-color has-text-color"><?php echo $tier['price']; ?></h2>
        <!-- /wp:heading -->
        <!-- wp:paragraph {"textColor":"<?php echo $muted_clr; ?>"} -->
        <p class="has-<?php echo $muted_clr; ?>-color has-text-color"><?php echo $tier['desc']; ?></p>
        <!-- /wp:paragraph -->
        <!-- wp:buttons -->
        <div class="wp-block-buttons">
          <!-- wp:button {"backgroundColor":"accent","textColor":"contrast","width":100} -->
          <div class="wp-block-button has-custom-width wp-block-button__width-100"><a class="wp-block-button__link has-contrast-color has-accent-background-color has-text-color has-background wp-element-button"><?php echo $tier['cta']; ?></a></div>
          <!-- /wp:button -->
        </div>
        <!-- /wp:buttons -->
      </div>
      <!-- /wp:group -->
    </div>
    <!-- /wp:column -->
			<?php $cards .= ob_get_clean();
		}

		ob_start(); ?>
<!-- wp:group {"className":"<?php echo $section_class; ?> py-5","align":"full","layout":{"type":"constrained"}} -->
<div class="wp-block-group <?php echo $section_class; ?> py-5 alignfull">
  <!-- wp:heading {"textAlign":"center","className":"gf-section-heading"} -->
  <h2 class="wp-block-heading has-text-align-center gf-section-heading"><?php echo $section_heading; ?></h2>
  <!-- /wp:heading -->
  <!-- wp:paragraph {"align":"center","textColor":"muted","className":"gf-section-subtext"} -->
  <p class="has-text-align-center gf-section-subtext has-muted-color has-text-color"><?php echo $section_subtext; ?></p>
  <!-- /wp:paragraph -->
  <!-- wp:columns {"isStackedOnMobile":true} -->
  <div class="wp-block-columns is-stacked-on-mobile">
<?php echo $cards; ?>
  </div>
  <!-- /wp:columns -->
</div>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// contact-form-info
	// -------------------------------------------------------------------------

	public static function render_contact_form_info( array $spec, array $theme ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];

		$section_class   = esc_attr( implode( ' ', $classes ) ?: '' );
		$section_heading = esc_html( $copy['section_heading'] ?? 'Get in Touch' );
		$email           = esc_html( $copy['email']           ?? 'hello@example.com' );
		$phone           = esc_html( $copy['phone']           ?? '' );
		$address         = esc_html( $copy['address']         ?? '' );

		$phone_row   = $phone   ? '<div class="gf-office-contact-row"><i class="bi bi-telephone gf-contact-icon" aria-hidden="true"></i><p class="mb-0">' . $phone . '</p></div>'   : '';
		$address_row = $address ? '<div class="gf-office-contact-row"><i class="bi bi-geo-alt gf-contact-icon" aria-hidden="true"></i><p class="mb-0">' . $address . '</p></div>' : '';

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $section_class; ?> py-5","align":"full","backgroundColor":"background"} -->
<section class="wp-block-group <?php echo $section_class; ?> py-5 alignfull has-background-background-color has-background">

<!-- wp:heading {"textAlign":"center","textColor":"primary","className":"gf-section-heading"} -->
<h2 class="wp-block-heading has-text-align-center gf-section-heading has-primary-color has-text-color"><?php echo $section_heading; ?></h2>
<!-- /wp:heading -->

<!-- wp:columns {"isStackedOnMobile":true} -->
<div class="wp-block-columns is-stacked-on-mobile">

<!-- wp:column {"width":"55%"} -->
<div class="wp-block-column" style="flex-basis:55%">
<!-- wp:heading {"level":3,"className":"gf-card-title"} -->
<h3 class="wp-block-heading gf-card-title">Send us a message</h3>
<!-- /wp:heading -->
<!-- wp:html -->
<form class="gf-contact-form" method="post">
  <div class="form-floating mb-3">
    <input type="text" class="form-control gf-form-input" id="contact-name" placeholder="Your name" required>
    <label for="contact-name" class="gf-form-label">Your Name</label>
  </div>
  <div class="form-floating mb-3">
    <input type="email" class="form-control gf-form-input" id="contact-email" placeholder="Email address" required>
    <label for="contact-email" class="gf-form-label">Email Address</label>
  </div>
  <div class="form-floating mb-3">
    <textarea class="form-control gf-form-input" id="contact-message" placeholder="Your message" style="height:120px" required></textarea>
    <label for="contact-message" class="gf-form-label">Message</label>
  </div>
  <button type="submit" class="wp-block-button__link wp-element-button gf-form-submit has-primary-background-color has-contrast-color has-background has-text-color" style="width:100%">Send Message</button>
</form>
<!-- /wp:html -->
</div>
<!-- /wp:column -->

<!-- wp:column {"width":"45%"} -->
<div class="wp-block-column" style="flex-basis:45%">
<!-- wp:heading {"level":3,"className":"gf-card-title"} -->
<h3 class="wp-block-heading gf-card-title">Contact Info</h3>
<!-- /wp:heading -->
<!-- wp:group {"className":"gf-office-card"} -->
<div class="wp-block-group gf-office-card">
<!-- wp:html -->
<div class="gf-office-contact-list">
  <div class="gf-office-contact-row"><i class="bi bi-envelope gf-contact-icon" aria-hidden="true"></i><p class="mb-0"><a href="mailto:<?php echo $email; ?>"><?php echo $email; ?></a></p></div>
  <?php echo $phone_row; ?>
  <?php echo $address_row; ?>
</div>
<!-- /wp:html -->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:column -->

</div>
<!-- /wp:columns -->

</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// accordion-faq
	// -------------------------------------------------------------------------

	public static function render_accordion_faq( array $spec, array $theme ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];

		$section_class   = esc_attr( implode( ' ', $classes ) ?: 'gf-section-tint' );
		$section_heading = esc_html( $copy['section_heading'] ?? 'Frequently Asked Questions' );

		$items = '';
		for ( $i = 1; $i <= 8; $i++ ) {
			$q = esc_html( $copy[ "q{$i}" ] ?? '' );
			$a = esc_html( $copy[ "a{$i}" ] ?? '' );
			if ( ! $q ) break;
			ob_start(); ?>
  <!-- wp:details {"summary":"<?php echo $q; ?>","className":"gf-faq-item"} -->
  <details class="wp-block-details gf-faq-item">
    <summary><?php echo $q; ?></summary>
    <!-- wp:paragraph {"textColor":"muted"} -->
    <p class="has-muted-color has-text-color"><?php echo $a; ?></p>
    <!-- /wp:paragraph -->
  </details>
  <!-- /wp:details -->
			<?php $items .= ob_get_clean();
		}

		ob_start(); ?>
<!-- wp:group {"className":"<?php echo $section_class; ?>","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"layout":{"type":"constrained","contentSize":"720px"}} -->
<div class="wp-block-group <?php echo $section_class; ?> alignfull py-5">
  <!-- wp:heading {"textAlign":"center","className":"gf-section-heading"} -->
  <h2 class="wp-block-heading has-text-align-center gf-section-heading"><?php echo $section_heading; ?></h2>
  <!-- /wp:heading -->
  <!-- wp:group {"className":"gf-faq-accordion","style":{"spacing":{"blockGap":"0","margin":{"top":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} -->
  <div class="wp-block-group gf-faq-accordion">
<?php echo $items; ?>
  </div>
  <!-- /wp:group -->
</div>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// image-text-split
	// -------------------------------------------------------------------------

	public static function render_image_text_split( array $spec, array $theme ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];

		$css        = implode( ' ', $classes ) ?: 'gf-image-text-split';
		$heading    = esc_html( $copy['heading']    ?? 'Our Story' );
		$p1         = esc_html( $copy['p1']         ?? 'We started with a simple idea: great products should be accessible to everyone, not just companies with deep pockets. That conviction shaped every decision we made from day one.' );
		$p2         = esc_html( $copy['p2']         ?? 'In the early days, the three of us worked from a single room with a whiteboard, a shared laptop, and more caffeine than was probably safe. What kept us going was the steady stream of messages from people whose work had genuinely gotten easier because of what we were building.' );
		$p3         = esc_html( $copy['p3']         ?? 'Today our team has grown across four continents, but the original principle has never wavered. Every feature we ship, every policy we write, and every support ticket we answer comes back to the same question: does this make things meaningfully better for the people using it?' );
		$pull_quote = esc_html( $copy['pull_quote'] ?? '' );
		$image_left = ! empty( $copy['image_left'] );

		if ( $pull_quote ) {
			ob_start(); ?>
      <!-- wp:html -->
      <blockquote style="border-left:4px solid var(--wp--preset--color--accent);padding-left:1.25rem;margin:1.5rem 0;font-style:italic;color:var(--wp--preset--color--primary)">&ldquo;<?php echo $pull_quote; ?>&rdquo;</blockquote>
      <!-- /wp:html -->
			<?php $quote_block = ob_get_clean();
		} else {
			$quote_block = '';
		}

		ob_start(); ?>
    <!-- wp:column {"width":"50%"} -->
    <div class="wp-block-column" style="flex-basis:50%">
      <!-- wp:heading {"textColor":"primary","className":"gf-section-heading"} -->
      <h2 class="wp-block-heading gf-section-heading has-primary-color has-text-color"><?php echo $heading; ?></h2>
      <!-- /wp:heading -->
      <!-- wp:paragraph {"textColor":"foreground"} -->
      <p class="has-foreground-color has-text-color"><?php echo $p1; ?></p>
      <!-- /wp:paragraph -->
      <!-- wp:paragraph {"textColor":"foreground"} -->
      <p class="has-foreground-color has-text-color"><?php echo $p2; ?></p>
      <!-- /wp:paragraph -->
<?php echo $quote_block; ?>      <!-- wp:paragraph {"textColor":"foreground"} -->
      <p class="has-foreground-color has-text-color"><?php echo $p3; ?></p>
      <!-- /wp:paragraph -->
    </div>
    <!-- /wp:column -->
		<?php $text_col = ob_get_clean();

		ob_start(); ?>
    <!-- wp:column {"width":"50%"} -->
    <div class="wp-block-column" style="flex-basis:50%">
      <!-- wp:image {"sizeSlug":"large","className":"gf-split-img"} -->
      <figure class="wp-block-image size-large gf-split-img"><img src="" alt="<?php echo $heading; ?>"/></figure>
      <!-- /wp:image -->
    </div>
    <!-- /wp:column -->
		<?php $img_col = ob_get_clean();

		$cols = $image_left ? $img_col . $text_col : $text_col . $img_col;

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?> py-5","align":"full"} -->
<section class="wp-block-group <?php echo $css; ?> py-5 alignfull">
  <!-- wp:columns {"isStackedOnMobile":true,"verticalAlignment":"center"} -->
  <div class="wp-block-columns is-stacked-on-mobile are-vertically-aligned-center">
<?php echo $cols; ?>
  </div>
  <!-- /wp:columns -->
</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// mission-values
	// -------------------------------------------------------------------------

	public static function render_mission_values( array $spec, array $theme ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];

		$section_class   = esc_attr( implode( ' ', $classes ) ?: 'gf-section-tint' );
		$section_heading = esc_html( $copy['section_heading'] ?? 'Our Mission & Values' );
		$mission_text    = esc_html( $copy['mission_text']    ?? 'We exist to make the world a little bit better every day.' );

		$values = '';
		for ( $i = 1; $i <= 4; $i++ ) {
			$title = esc_html( $copy[ "value_{$i}_title" ] ?? '' );
			$body  = esc_html( $copy[ "value_{$i}_body" ]  ?? '' );
			if ( ! $title ) break;
			ob_start(); ?>

    <!-- wp:column -->
    <div class="wp-block-column">
      <!-- wp:group {"className":"gf-value-card"} -->
      <div class="wp-block-group gf-value-card">
        <!-- wp:heading {"level":4,"className":"gf-card-title"} -->
        <h4 class="wp-block-heading gf-card-title"><?php echo $title; ?></h4>
        <!-- /wp:heading -->
        <!-- wp:paragraph {"textColor":"muted"} -->
        <p class="has-muted-color has-text-color"><?php echo $body; ?></p>
        <!-- /wp:paragraph -->
      </div>
      <!-- /wp:group -->
    </div>
    <!-- /wp:column -->
			<?php $values .= ob_get_clean();
		}

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $section_class; ?> py-5","align":"full"} -->
<section class="wp-block-group <?php echo $section_class; ?> py-5 alignfull">
  <!-- wp:heading {"textAlign":"center","className":"gf-section-heading"} -->
  <h2 class="wp-block-heading has-text-align-center gf-section-heading"><?php echo $section_heading; ?></h2>
  <!-- /wp:heading -->
  <!-- wp:group {"className":"gf-mission-card"} -->
  <div class="wp-block-group gf-mission-card">
    <!-- wp:html -->
    <blockquote class="gf-mission-quote">&#8220;<?php echo $mission_text; ?>&#8221;</blockquote>
    <!-- /wp:html -->
  </div>
  <!-- /wp:group -->
  <!-- wp:columns {"isStackedOnMobile":true} -->
  <div class="wp-block-columns is-stacked-on-mobile">
<?php echo $values; ?>
  </div>
  <!-- /wp:columns -->
</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// announcement-banner
	// -------------------------------------------------------------------------

	public static function render_announcement_banner( array $spec, array $theme ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];

		$section_class = esc_attr( implode( ' ', array_merge( [ 'gf-banner-announcement' ], $classes ) ) );
		$message       = esc_html( $copy['message']   ?? '🎉 New feature just launched — check it out!' );
		$link_text     = esc_html( $copy['link_text'] ?? 'Learn more →' );
		$link_url      = esc_url( $copy['link_url']   ?? '#' );

		ob_start(); ?>
<!-- wp:group {"className":"<?php echo $section_class; ?>","align":"full","backgroundColor":"accent","style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group <?php echo $section_class; ?> alignfull has-accent-background-color has-background">
  <!-- wp:paragraph {"align":"center","textColor":"contrast","style":{"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
  <p class="has-text-align-center has-contrast-color has-text-color"><?php echo $message; ?> <a href="<?php echo $link_url; ?>" style="color:inherit;text-decoration:underline"><?php echo $link_text; ?></a></p>
  <!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// pull-quote-block
	// -------------------------------------------------------------------------

	public static function render_pull_quote_block( array $spec, array $theme ): string {
		$copy  = $spec['copy'] ?? [];
		$quote = esc_html( $copy['quote'] ?? 'A great quote that inspires your visitors.' );
		$attr  = esc_html( $copy['attribution'] ?? '' );

		if ( $attr ) {
			ob_start(); ?>
  <!-- wp:paragraph {"className":"gf-pull-quote__attr","textColor":"muted"} -->
  <p class="gf-pull-quote__attr has-muted-color has-text-color">&#8212; <?php echo $attr; ?></p>
  <!-- /wp:paragraph -->
			<?php $attr_block = ob_get_clean();
		} else {
			$attr_block = '';
		}

		ob_start(); ?>
<!-- wp:group {"className":"gf-pull-quote"} -->
<div class="wp-block-group gf-pull-quote">
  <!-- wp:paragraph {"className":"gf-pull-quote__text","textColor":"foreground"} -->
  <p class="gf-pull-quote__text has-foreground-color has-text-color">&#8220;<?php echo $quote; ?>&#8221;</p>
  <!-- /wp:paragraph -->
<?php echo $attr_block; ?>
</div>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// callout-box
	// -------------------------------------------------------------------------

	public static function render_callout_box( array $spec, array $theme ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];

		$variant = $copy['variant'] ?? 'info';
		$heading = esc_html( $copy['heading'] ?? ucfirst( $variant ) );
		$body    = esc_html( $copy['body']    ?? 'Important information goes here.' );
		$icon    = esc_attr( $copy['icon']    ?? 'bi-info-circle' );

		$type_class = 'gf-callout-' . esc_attr( $variant );
		$section_class = esc_attr( implode( ' ', array_merge( [ 'gf-callout-box', $type_class ], $classes ) ) );

		ob_start(); ?>
<!-- wp:group {"className":"<?php echo $section_class; ?>","align":"full"} -->
<div class="wp-block-group alignfull <?php echo $section_class; ?>">
<!-- wp:html -->
<div class="container">
  <div class="gf-callout-inner">
    <i class="bi <?php echo $icon; ?> gf-callout-icon" aria-hidden="true"></i>
    <div>
      <h5 class="gf-callout-heading"><?php echo $heading; ?></h5>
      <p class="gf-callout-body"><?php echo $body; ?></p>
    </div>
  </div>
</div>
<!-- /wp:html -->
</div>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// section-divider-block
	// -------------------------------------------------------------------------

	public static function render_section_divider_block( array $spec, array $theme ): string {
		ob_start(); ?>
<!-- wp:group {"align":"full"} -->
<div class="wp-block-group alignfull">
  <!-- wp:separator {"className":"gf-section-divider is-style-wide"} -->
  <hr class="wp-block-separator has-alpha-channel-opacity gf-section-divider is-style-wide"/>
  <!-- /wp:separator -->
</div>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}
}

// Register all core renderers.
GrayFox_TB_PatternBuilder::register_renderers( [
	'three-column-cards'     => [ GrayFox_TB_Patterns_Core::class, 'render_three_column_cards' ],
	'six-icon-grid'          => [ GrayFox_TB_Patterns_Core::class, 'render_six_icon_grid' ],
	'image-checklist-split'  => [ GrayFox_TB_Patterns_Core::class, 'render_image_checklist_split' ],
	'numbered-steps'         => [ GrayFox_TB_Patterns_Core::class, 'render_numbered_steps' ],
	'testimonials-grid'      => [ GrayFox_TB_Patterns_Core::class, 'render_testimonials_grid' ],
	'logo-strip'             => [ GrayFox_TB_Patterns_Core::class, 'render_logo_strip' ],
	'stats-row'              => [ GrayFox_TB_Patterns_Core::class, 'render_stats_row' ],
	'gradient-cta-band'      => [ GrayFox_TB_Patterns_Core::class, 'render_gradient_cta_band' ],
	'light-dual-cta'         => [ GrayFox_TB_Patterns_Core::class, 'render_light_dual_cta' ],
	'newsletter-form'        => [ GrayFox_TB_Patterns_Core::class, 'render_newsletter_form' ],
	'three-tier-pricing'     => [ GrayFox_TB_Patterns_Core::class, 'render_three_tier_pricing' ],
	'contact-form-info'      => [ GrayFox_TB_Patterns_Core::class, 'render_contact_form_info' ],
	'accordion-faq'          => [ GrayFox_TB_Patterns_Core::class, 'render_accordion_faq' ],
	'image-text-split'       => [ GrayFox_TB_Patterns_Core::class, 'render_image_text_split' ],
	'mission-values'         => [ GrayFox_TB_Patterns_Core::class, 'render_mission_values' ],
	'announcement-banner'    => [ GrayFox_TB_Patterns_Core::class, 'render_announcement_banner' ],
	'pull-quote-block'       => [ GrayFox_TB_Patterns_Core::class, 'render_pull_quote_block' ],
	'callout-box'            => [ GrayFox_TB_Patterns_Core::class, 'render_callout_box' ],
	'section-divider-block'  => [ GrayFox_TB_Patterns_Core::class, 'render_section_divider_block' ],
] );
