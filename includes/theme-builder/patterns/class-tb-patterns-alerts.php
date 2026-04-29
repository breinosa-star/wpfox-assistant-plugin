<?php
/**
 * Alert / announcement pattern renderers.
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
 * Class GrayFox_TB_Patterns_Alerts
 */
class GrayFox_TB_Patterns_Alerts {

	// -------------------------------------------------------------------------
	// promo-ribbon — slim top-of-page promotional banner
	// -------------------------------------------------------------------------

	public static function render_promo_ribbon( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-promo-ribbon';

		$message   = isset( $copy['message'] ) ? esc_html( $copy['message'] ) : 'Limited time offer: 30% off all annual plans. Use code ANNUAL30';
		$cta_label = esc_html( $copy['cta_label'] ?? 'Claim offer' );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?>","backgroundColor":"primary","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> has-primary-background-color has-background">
<!-- wp:group {"className":"gf-promo-ribbon-inner"} -->
<div class="wp-block-group gf-promo-ribbon-inner">
<!-- wp:paragraph {"textColor":"contrast"} -->
<p class="has-contrast-color has-text-color"><?php echo $message; ?></p>
<!-- /wp:paragraph -->
<!-- wp:buttons -->
<div class="wp-block-buttons">
<!-- wp:button {"backgroundColor":"contrast","textColor":"primary","className":"gf-promo-ribbon-btn"} -->
<div class="wp-block-button gf-promo-ribbon-btn"><a class="wp-block-button__link has-primary-color has-contrast-background-color has-text-color has-background wp-element-button"><?php echo $cta_label; ?> <i class="bi bi-arrow-right"></i></a></div>
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
	// notification-bar — info/success/warning/error status bar
	// -------------------------------------------------------------------------

	public static function render_notification_bar( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-notification-bar';

		$variant   = $copy['variant']   ?? 'info';
		$message   = esc_html( $copy['message']   ?? 'System maintenance scheduled for Sunday April 20 from 2–4am UTC. Expect brief downtime.' );
		$cta_label = esc_html( $copy['cta_label'] ?? 'Learn more' );

		$color_map = [ 'info' => 'secondary', 'success' => 'primary', 'warning' => 'accent', 'error' => 'accent' ];
		$bg        = $color_map[ $variant ] ?? 'secondary';
		$icon_map  = [
			'info'    => 'bi-info-circle-fill',
			'success' => 'bi-check-circle-fill',
			'warning' => 'bi-exclamation-triangle-fill',
			'error'   => 'bi-x-circle-fill',
		];
		$icon_class = $icon_map[ $variant ] ?? 'bi-info-circle-fill';

		ob_start(); ?>
<!-- wp:group {"className":"<?php echo $css; ?> gf-notification-bar--<?php echo $variant; ?>","backgroundColor":"<?php echo $bg; ?>","align":"full"} -->
<div class="wp-block-group alignfull <?php echo $css; ?> gf-notification-bar--<?php echo $variant; ?> has-<?php echo $bg; ?>-background-color has-background">
<!-- wp:html -->
<div class="d-flex align-items-center justify-content-center gap-3 py-2 px-3 flex-wrap">
  <i class="bi <?php echo $icon_class; ?> gf-notification-bar__icon" aria-hidden="true"></i>
  <span class="gf-notification-bar__message"><?php echo $message; ?></span>
  <a href="#" class="gf-notification-bar__link"><?php echo $cta_label; ?></a>
</div>
<!-- /wp:html -->
</div>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// coming-soon-section — countdown + email capture on primary background
	// -------------------------------------------------------------------------

	public static function render_coming_soon_section( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-coming-soon-section';

		$heading      = esc_html( $copy['heading']       ?? 'Something Big Is Coming' );
		$subtext      = esc_html( $copy['subtext']        ?? "We're putting the finishing touches on something you're going to love." );
		$launch_date  = esc_html( $copy['launch_date']    ?? 'April 30, 2025' );
		$email_label  = esc_html( $copy['email_label']    ?? 'Get notified when we launch' );
		$placeholder  = esc_attr( $copy['placeholder']    ?? 'Enter your email address' );
		$btn_label    = esc_html( $copy['btn_label']      ?? 'Notify me' );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?> py-5","align":"full","backgroundColor":"primary","layout":{"type":"constrained"}} -->
<section class="wp-block-group <?php echo $css; ?> py-5 alignfull has-primary-background-color has-background">

<!-- wp:heading {"textAlign":"center","textColor":"contrast"} -->
<h2 class="wp-block-heading has-text-align-center has-contrast-color has-text-color"><?php echo $heading; ?></h2>
<!-- /wp:heading -->
<!-- wp:paragraph {"align":"center","textColor":"contrast"} -->
<p class="has-text-align-center has-contrast-color has-text-color"><?php echo $subtext; ?></p>
<!-- /wp:paragraph -->

<!-- wp:html -->
<div class="gf-countdown-boxes">
  <div class="gf-countdown-box">
    <div class="gf-countdown-box__number" id="csDays">14</div>
    <div class="gf-countdown-box__label">Days</div>
  </div>
  <div class="gf-countdown-box">
    <div class="gf-countdown-box__number" id="csHours">08</div>
    <div class="gf-countdown-box__label">Hours</div>
  </div>
  <div class="gf-countdown-box">
    <div class="gf-countdown-box__number" id="csMins">22</div>
    <div class="gf-countdown-box__label">Minutes</div>
  </div>
</div>
<p class="gf-coming-soon-launch">Launching <?php echo $launch_date; ?></p>
<div class="gf-coming-soon-form">
  <p class="gf-coming-soon-form__label"><?php echo $email_label; ?></p>
  <div class="gf-coming-soon-form__row">
    <input type="email" placeholder="<?php echo $placeholder; ?>" class="gf-coming-soon-form__input" />
    <button class="gf-coming-soon-form__btn"><?php echo $btn_label; ?></button>
  </div>
</div>
<!-- /wp:html -->

</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// coming-soon-full — full-screen pre-launch page with email capture
	// -------------------------------------------------------------------------

	public static function render_coming_soon_full( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-coming-soon-full';

		$brand       = esc_html( $copy['brand_name']        ?? 'Brand' );
		$headline    = esc_html( $copy['headline']          ?? "We're building something great" );
		$subtext     = esc_html( $copy['subtext']           ?? "Our new site is on its way. Leave your email and we'll let you know the moment it's live." );
		$placeholder = esc_attr( $copy['email_placeholder'] ?? 'Your email address' );
		$btn_label   = esc_html( $copy['btn_label']         ?? 'Keep me posted' );
		$launch_label = esc_html( $copy['launch_label']     ?? 'Launching soon' );

		ob_start(); ?>
<!-- wp:group {"className":"gf-fullscreen-wrap","backgroundColor":"primary","align":"full"} -->
<div class="wp-block-group alignfull gf-fullscreen-wrap has-primary-background-color has-background">
<!-- wp:html -->
<section class="<?php echo $css; ?>" style="min-height:100vh;background:var(--wp--preset--color--primary);display:flex;align-items:center;justify-content:center;">
  <div style="max-width:560px;padding:4rem 1.5rem;text-align:center;">
    <p style="font-weight:700;font-size:1.5rem;color:var(--wp--preset--color--contrast,#fff);margin-bottom:1.5rem"><?php echo $brand; ?></p>
    <h1 style="color:var(--wp--preset--color--contrast,#fff);font-size:clamp(2rem,5vw,3.5rem);margin-bottom:1rem"><?php echo $headline; ?></h1>
    <p style="color:var(--wp--preset--color--contrast,#fff);opacity:.85;margin-bottom:2.5rem"><?php echo $subtext; ?></p>
    <p style="font-size:.85rem;text-transform:uppercase;letter-spacing:.1em;color:var(--wp--preset--color--contrast,#fff);opacity:.6;margin-bottom:.75rem"><?php echo $launch_label; ?></p>
    <div style="display:flex">
      <input type="email" placeholder="<?php echo $placeholder; ?>" class="form-control" style="border-radius:8px 0 0 8px;border:none;padding:.85rem 1.25rem;font-size:1rem">
      <button class="wp-element-button" style="background:var(--wp--preset--color--contrast,#fff);color:var(--wp--preset--color--primary);border-radius:0 8px 8px 0;border:none;padding:.85rem 1.5rem;font-weight:600;white-space:nowrap"><?php echo $btn_label; ?></button>
    </div>
  </div>
</section>
<!-- /wp:html -->
</div>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// maintenance-mode — full-screen maintenance page
	// -------------------------------------------------------------------------

	public static function render_maintenance_mode( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-maintenance';

		$brand    = esc_html( $copy['brand_name'] ?? 'Brand' );
		$headline = esc_html( $copy['headline']   ?? 'Down for Maintenance' );
		$subtext  = esc_html( $copy['subtext']    ?? "We're performing scheduled maintenance and will be back shortly. Thank you for your patience." );
		$eta      = esc_html( $copy['eta']        ?? 'Expected back online: Sunday, April 20 at 6am UTC' );
		$contact  = esc_html( $copy['contact']    ?? 'Urgent? Email us at support@brand.com' );

		ob_start(); ?>
<!-- wp:group {"align":"full"} -->
<div class="wp-block-group alignfull">
<!-- wp:html -->
<section class="<?php echo $css; ?>" style="min-height:100vh;background:var(--wp--preset--color--background,#fff);display:flex;align-items:center;justify-content:center;">
  <div style="max-width:520px;padding:4rem 1.5rem;text-align:center;">
    <i class="bi bi-tools gf-maintenance-icon" aria-hidden="true"></i>
    <p style="font-weight:700;font-size:1.25rem;color:var(--wp--preset--color--primary);margin-bottom:.5rem"><?php echo $brand; ?></p>
    <h1 style="color:var(--wp--preset--color--foreground,#1e1e1e);margin-bottom:1rem"><?php echo $headline; ?></h1>
    <p style="color:var(--wp--preset--color--muted,#6b7280);margin-bottom:2rem"><?php echo $subtext; ?></p>
    <div style="border:1px solid var(--wp--preset--color--muted,#e5e7eb);border-radius:10px;padding:1.25rem 1.5rem;">
      <p style="font-size:.9rem;color:var(--wp--preset--color--muted,#6b7280);margin:.4rem 0"><i class="bi bi-clock"></i> <?php echo $eta; ?></p>
      <p style="font-size:.9rem;color:var(--wp--preset--color--muted,#6b7280);margin:.4rem 0"><i class="bi bi-envelope"></i> <?php echo $contact; ?></p>
    </div>
  </div>
</section>
<!-- /wp:html -->
</div>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// no-results-section — empty search state with suggestions
	// -------------------------------------------------------------------------

	public static function render_no_results_section( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-no-results';

		$headline    = esc_html( $copy['headline']    ?? 'No results found' );
		$subtext     = esc_html( $copy['subtext']     ?? "We couldn't find anything matching your search. Try different keywords or browse below." );
		$cta1        = esc_html( $copy['cta1']        ?? 'Browse all articles' );
		$cta2        = esc_html( $copy['cta2']        ?? 'Contact support' );
		$suggestion1 = esc_html( $copy['suggestion1'] ?? 'Check your spelling' );
		$suggestion2 = esc_html( $copy['suggestion2'] ?? 'Try more general terms' );
		$suggestion3 = esc_html( $copy['suggestion3'] ?? 'Use fewer words' );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?> py-5 text-center","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5 text-center">

<!-- wp:html -->
<i class="bi bi-search-x gf-no-results-icon" aria-hidden="true"></i>
<!-- /wp:html -->

<!-- wp:heading {"textAlign":"center","textColor":"foreground"} -->
<h2 class="wp-block-heading has-text-align-center has-foreground-color has-text-color"><?php echo $headline; ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","textColor":"muted"} -->
<p class="has-text-align-center has-muted-color has-text-color"><?php echo $subtext; ?></p>
<!-- /wp:paragraph -->

<!-- wp:html -->
<div class="d-flex flex-wrap justify-content-center gap-2 mt-3">
  <span class="gf-suggestion-pill"><?php echo $suggestion1; ?></span>
  <span class="gf-suggestion-pill"><?php echo $suggestion2; ?></span>
  <span class="gf-suggestion-pill"><?php echo $suggestion3; ?></span>
</div>
<!-- /wp:html -->

<!-- wp:buttons {"className":"justify-content-center mt-4"} -->
<div class="wp-block-buttons justify-content-center mt-4">
<!-- wp:button {"backgroundColor":"primary","textColor":"contrast"} -->
<div class="wp-block-button"><a class="wp-block-button__link has-contrast-color has-primary-background-color has-text-color has-background wp-element-button"><?php echo $cta1; ?></a></div>
<!-- /wp:button -->
<!-- wp:button {"className":"is-style-outline","textColor":"primary"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link has-primary-color has-text-color wp-element-button"><?php echo $cta2; ?></a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->

</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}
}

GrayFox_TB_PatternBuilder::register_renderers( [
	'promo-ribbon'          => [ GrayFox_TB_Patterns_Alerts::class, 'render_promo_ribbon' ],
	'notification-bar'      => [ GrayFox_TB_Patterns_Alerts::class, 'render_notification_bar' ],
	'coming-soon-section'   => [ GrayFox_TB_Patterns_Alerts::class, 'render_coming_soon_section' ],
	'coming-soon-full'      => [ GrayFox_TB_Patterns_Alerts::class, 'render_coming_soon_full' ],
	'maintenance-mode'      => [ GrayFox_TB_Patterns_Alerts::class, 'render_maintenance_mode' ],
	'no-results-section'    => [ GrayFox_TB_Patterns_Alerts::class, 'render_no_results_section' ],
] );
