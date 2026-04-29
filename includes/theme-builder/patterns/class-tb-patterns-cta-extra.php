<?php
/**
 * Extended CTA renderers: sticky-cta-bar, exit-intent-cta, inline-cta-strip, sidebar-cta.
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
 * Class GrayFox_TB_Patterns_CTA_Extra
 */
class GrayFox_TB_Patterns_CTA_Extra {

	// -------------------------------------------------------------------------
	// sticky-cta-bar — fixed bottom bar
	// -------------------------------------------------------------------------

	public static function render_sticky_cta_bar( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-sticky-cta';

		$message = esc_html( $copy['message'] ?? 'Ready to get started?' );
		$cta     = esc_html( $copy['cta']     ?? 'Try It Free' );
		$sub     = esc_html( $copy['subtext'] ?? 'No credit card required.' );

		ob_start(); ?>
<!-- wp:html -->
<div class="<?php echo $css; ?>" style="position:fixed;bottom:0;left:0;right:0;z-index:999;background:var(--wp--preset--color--primary);padding:0.85rem 1.5rem;display:flex;align-items:center;justify-content:center;gap:2rem;flex-wrap:wrap;box-shadow:0 -2px 12px rgba(0,0,0,0.15)">
  <div>
    <strong style="color:var(--wp--preset--color--contrast)"><?php echo $message; ?></strong>
    <span style="color:var(--wp--preset--color--contrast);opacity:0.7;font-size:0.875rem;margin-left:0.75rem"><?php echo $sub; ?></span>
  </div>
  <a href="#" style="background:var(--wp--preset--color--accent);color:var(--wp--preset--color--contrast);padding:0.6rem 1.75rem;border-radius:6px;font-weight:600;text-decoration:none;white-space:nowrap"><?php echo $cta; ?></a>
</div>
<!-- /wp:html -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// exit-intent-cta — full-width bold accent section
	// -------------------------------------------------------------------------

	public static function render_exit_intent_cta( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-exit-cta';

		$heading    = esc_html( $copy['heading']    ?? "Wait — Don't Leave Empty-Handed" );
		$subtext    = esc_html( $copy['subtext']    ?? 'Start your free trial in 60 seconds. No credit card. Cancel anytime.' );
		$cta        = esc_html( $copy['cta']        ?? 'Claim Your Free Trial' );
		$trust_note = esc_html( $copy['trust_note'] ?? 'Join 500+ teams already using it' );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?>","align":"full","backgroundColor":"accent","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"layout":{"type":"constrained","contentSize":"720px"}} -->
<section class="wp-block-group <?php echo $css; ?> alignfull has-accent-background-color has-background py-5">

<!-- wp:heading {"level":2,"textAlign":"center","textColor":"contrast","className":"gf-section-heading"} -->
<h2 class="wp-block-heading has-text-align-center gf-section-heading has-contrast-color has-text-color"><?php echo $heading; ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","textColor":"contrast"} -->
<p class="has-text-align-center has-contrast-color has-text-color"><?php echo $subtext; ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"className":"justify-content-center mt-4"} -->
<div class="wp-block-buttons justify-content-center mt-4">
<!-- wp:button {"backgroundColor":"primary","textColor":"contrast"} -->
<div class="wp-block-button"><a class="wp-block-button__link has-contrast-color has-primary-background-color has-text-color has-background wp-element-button"><?php echo $cta; ?></a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->

<!-- wp:paragraph {"align":"center","textColor":"contrast","className":"small opacity-75"} -->
<p class="has-text-align-center has-contrast-color has-text-color small opacity-75"><?php echo $trust_note; ?></p>
<!-- /wp:paragraph -->

</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// inline-cta-strip — narrow accent band between content sections
	// -------------------------------------------------------------------------

	public static function render_inline_cta_strip( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-inline-strip';

		$message = esc_html( $copy['message'] ?? 'Enjoying this content? See how it works in practice.' );
		$cta     = esc_html( $copy['cta']     ?? 'Get a Free Demo' );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?> py-5","align":"full","backgroundColor":"secondary"} -->
<section class="wp-block-group <?php echo $css; ?> py-5 alignfull has-secondary-background-color has-background">

<!-- wp:group {"className":"d-flex flex-wrap align-items-center justify-content-between gap-3"} -->
<div class="wp-block-group d-flex flex-wrap align-items-center justify-content-between gap-3">
<!-- wp:paragraph {"textColor":"contrast","className":"fw-medium"} -->
<p class="has-contrast-color has-text-color fw-medium"><?php echo $message; ?></p>
<!-- /wp:paragraph -->
<!-- wp:buttons -->
<div class="wp-block-buttons">
<!-- wp:button {"backgroundColor":"accent","textColor":"contrast"} -->
<div class="wp-block-button"><a class="wp-block-button__link has-contrast-color has-accent-background-color has-text-color has-background wp-element-button"><?php echo $cta; ?></a></div>
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
	// sidebar-cta — compact CTA card for sidebars / inline content
	// -------------------------------------------------------------------------

	public static function render_sidebar_cta( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-sidebar-cta';

		$heading = esc_html( $copy['heading'] ?? 'Ready to Try It?' );
		$body    = esc_html( $copy['body']    ?? 'Start your free trial today — no credit card required.' );
		$cta     = esc_html( $copy['cta']     ?? 'Get Started Free' );

		ob_start(); ?>
<!-- wp:group {"className":"<?php echo $css; ?>","backgroundColor":"primary","style":{"spacing":{"padding":{"top":"2rem","bottom":"2rem","left":"1.5rem","right":"1.5rem"},"blockGap":"1rem"},"border":{"radius":"10px"}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group <?php echo $css; ?> has-primary-background-color has-background">

<!-- wp:heading {"level":3,"textColor":"contrast"} -->
<h3 class="wp-block-heading has-contrast-color has-text-color"><?php echo $heading; ?></h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"textColor":"contrast","style":{"typography":{"fontSize":"0.9rem"}}} -->
<p class="has-contrast-color has-text-color"><?php echo $body; ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons -->
<div class="wp-block-buttons">
<!-- wp:button {"backgroundColor":"accent","textColor":"contrast","width":100} -->
<div class="wp-block-button has-custom-width wp-block-button__width-100"><a class="wp-block-button__link has-contrast-color has-accent-background-color has-text-color has-background wp-element-button"><?php echo $cta; ?></a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->

</div>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}
}

GrayFox_TB_PatternBuilder::register_renderers( [
	'sticky-cta-bar'  => [ GrayFox_TB_Patterns_CTA_Extra::class, 'render_sticky_cta_bar' ],
	'exit-intent-cta' => [ GrayFox_TB_Patterns_CTA_Extra::class, 'render_exit_intent_cta' ],
	'inline-cta-strip'=> [ GrayFox_TB_Patterns_CTA_Extra::class, 'render_inline_cta_strip' ],
	'sidebar-cta'     => [ GrayFox_TB_Patterns_CTA_Extra::class, 'render_sidebar_cta' ],
] );
