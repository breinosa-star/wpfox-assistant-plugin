<?php
/**
 * Lead capture / form renderers: multi-step-form, webinar-registration, demo-request,
 * quiz-lead-form, inline-signup-form.
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
 * Class GrayFox_TB_Patterns_Forms
 */
class GrayFox_TB_Patterns_Forms {

	// -------------------------------------------------------------------------
	// multi-step-form — 3-step Bootstrap form with step indicator
	// -------------------------------------------------------------------------

	public static function render_multi_step_form( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-multi-step-form';

		$heading  = esc_html( $copy['section_heading'] ?? 'Get Started in 3 Easy Steps' );
		$subtext  = esc_html( $copy['section_subtext'] ?? '' );
		$step1    = esc_html( $copy['step_1_title']    ?? 'About You' );
		$step2    = esc_html( $copy['step_2_title']    ?? 'Your Business' );
		$step3    = esc_html( $copy['step_3_title']    ?? 'Confirm' );
		$cta      = esc_html( $copy['cta_label']       ?? 'Submit' );

		$subtext_html = $subtext ? '<p class="text-muted text-center mt-2">' . $subtext . '</p>' : '';

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?> py-5","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5">

<!-- wp:heading {"level":2,"textAlign":"center","textColor":"primary","className":"gf-section-heading"} -->
<h2 class="wp-block-heading has-text-align-center gf-section-heading has-primary-color has-text-color"><?php echo $heading; ?></h2>
<!-- /wp:heading -->

<!-- wp:html -->
<div class="gf-multi-step-form__wrap">
  <?php echo $subtext_html; ?>

  <div class="gf-step-track" aria-label="Form progress">
    <div class="gf-step-item">
      <div class="gf-step-num gf-step-num--done">1</div>
      <span class="gf-step-label gf-step-label--done"><?php echo $step1; ?></span>
    </div>
    <div class="gf-step-sep gf-step-sep--done"></div>
    <div class="gf-step-item">
      <div class="gf-step-num">2</div>
      <span class="gf-step-label"><?php echo $step2; ?></span>
    </div>
    <div class="gf-step-sep"></div>
    <div class="gf-step-item">
      <div class="gf-step-num">3</div>
      <span class="gf-step-label"><?php echo $step3; ?></span>
    </div>
  </div>

  <div class="mt-4">
    <div class="mb-3">
      <label class="form-label fw-semibold">Full Name</label>
      <input type="text" class="form-control" placeholder="Jane Smith"/>
    </div>
    <div class="mb-3">
      <label class="form-label fw-semibold">Work Email</label>
      <input type="email" class="form-control" placeholder="jane@company.com"/>
    </div>
    <button type="button" class="btn btn-primary w-100 fw-bold py-2 mt-2"><?php echo $cta; ?></button>
  </div>
</div>
<!-- /wp:html -->

</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// webinar-registration — date + bullets + form
	// -------------------------------------------------------------------------

	public static function render_webinar_registration( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-webinar-reg';

		$heading   = esc_html( $copy['section_heading'] ?? 'Join Our Free Live Webinar' );
		$date_line = esc_html( $copy['date']            ?? 'Thursday, May 15 · 11:00 AM ET' );
		$host      = esc_html( $copy['host']            ?? 'Hosted by the product team' );
		$cta       = esc_html( $copy['cta_label']       ?? 'Reserve My Spot' );

		$bullet_defaults = [
			'Live product demo — see the full workflow',
			'Q&A with our founders',
			'Recording sent to all registrants',
		];
		$bullets = [];
		for ( $i = 1; $i <= 4; $i++ ) {
			$b = $copy[ "bullet_{$i}" ] ?? '';
			if ( $b ) {
				$bullets[] = esc_html( $b );
			}
		}
		if ( empty( $bullets ) ) {
			$bullets = $bullet_defaults;
		}

		$bullet_html = '';
		foreach ( $bullets as $b ) {
			$bullet_html .= '<li style="padding:0.3rem 0;color:var(--wp--preset--color--muted);display:flex;gap:0.6rem"><i class="bi-check-circle-fill" style="color:var(--wp--preset--color--accent);flex-shrink:0;margin-top:0.2rem"></i>' . $b . '</li>';
		}

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?> py-5","align":"full","backgroundColor":"background","layout":{"type":"constrained"}} -->
<section class="wp-block-group <?php echo $css; ?> py-5 alignfull has-background-background-color has-background">

<!-- wp:columns {"isStackedOnMobile":true} -->
<div class="wp-block-columns is-stacked-on-mobile">

<!-- wp:column {"width":"50%"} -->
<div class="wp-block-column" style="flex-basis:50%">
<!-- wp:heading {"level":2,"textColor":"primary","className":"gf-section-heading"} -->
<h2 class="wp-block-heading gf-section-heading has-primary-color has-text-color"><?php echo $heading; ?></h2>
<!-- /wp:heading -->
<!-- wp:html -->
<p style="color:var(--wp--preset--color--accent);font-weight:600;margin-bottom:0.25rem"><?php echo $date_line; ?></p>
<p style="color:var(--wp--preset--color--muted);font-size:0.9rem;margin-bottom:1.5rem"><?php echo $host; ?></p>
<ul style="list-style:none;padding:0;margin:0"><?php echo $bullet_html; ?></ul>
<!-- /wp:html -->
</div>
<!-- /wp:column -->

<!-- wp:column {"width":"50%"} -->
<div class="wp-block-column" style="flex-basis:50%">
<!-- wp:html -->
<div style="background:var(--wp--preset--color--background);border:1px solid var(--wp--preset--color--muted,#e5e7eb);border-radius:12px;padding:2rem">
  <div class="mb-3">
    <label class="form-label" style="color:var(--wp--preset--color--primary)">Full Name</label>
    <input type="text" class="form-control" placeholder="Jane Smith"/>
  </div>
  <div class="mb-3">
    <label class="form-label" style="color:var(--wp--preset--color--primary)">Work Email</label>
    <input type="email" class="form-control" placeholder="jane@company.com"/>
  </div>
  <div class="mb-3">
    <label class="form-label" style="color:var(--wp--preset--color--primary)">Job Title</label>
    <input type="text" class="form-control" placeholder="Finance Manager"/>
  </div>
  <button class="wp-block-button__link" style="width:100%;padding:0.9rem;background:var(--wp--preset--color--primary);color:var(--wp--preset--color--contrast);border:none;border-radius:6px;font-weight:600;font-size:1rem;cursor:pointer"><?php echo $cta; ?></button>
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
	// demo-request — scheduling form with benefit bullets
	// -------------------------------------------------------------------------

	public static function render_demo_request( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-demo-request';

		$heading = esc_html( $copy['section_heading'] ?? 'See It in Action' );
		$subtext = esc_html( $copy['section_subtext'] ?? 'Book a personalized 30-minute demo with our team.' );
		$cta     = esc_html( $copy['cta_label']       ?? 'Book My Demo' );

		$bullet_defaults = [
			'Live walkthrough tailored to your workflow',
			'Answer your specific questions',
			'Get a custom pricing quote',
			'No hard sell — just a real conversation',
		];
		$bullets = [];
		for ( $i = 1; $i <= 4; $i++ ) {
			$b = $copy[ "bullet_{$i}" ] ?? '';
			if ( $b ) {
				$bullets[] = esc_html( $b );
			}
		}
		if ( empty( $bullets ) ) {
			$bullets = $bullet_defaults;
		}

		$bullet_html = '';
		foreach ( $bullets as $b ) {
			$bullet_html .= '<li style="padding:0.35rem 0;display:flex;gap:0.6rem;align-items:flex-start"><i class="bi bi-check-circle-fill" style="color:var(--wp--preset--color--accent);flex-shrink:0;margin-top:0.2rem"></i><span style="color:var(--wp--preset--color--muted)">' . $b . '</span></li>';
		}

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?> py-5","align":"full","backgroundColor":"background"} -->
<section class="wp-block-group <?php echo $css; ?> py-5 alignfull has-background-background-color has-background">

<!-- wp:heading {"level":2,"textAlign":"center","textColor":"primary","className":"gf-section-heading"} -->
<h2 class="wp-block-heading has-text-align-center gf-section-heading has-primary-color has-text-color"><?php echo $heading; ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","textColor":"muted","className":"gf-section-subtext"} -->
<p class="has-text-align-center gf-section-subtext has-muted-color has-text-color"><?php echo $subtext; ?></p>
<!-- /wp:paragraph -->

<!-- wp:columns {"isStackedOnMobile":true} -->
<div class="wp-block-columns is-stacked-on-mobile">

<!-- wp:column {"width":"45%"} -->
<div class="wp-block-column" style="flex-basis:45%">
<!-- wp:html -->
<div style="max-width:320px;margin:0 auto">
  <ul style="list-style:none;padding:0;margin:0"><?php echo $bullet_html; ?></ul>
</div>
<!-- /wp:html -->
</div>
<!-- /wp:column -->

<!-- wp:column {"width":"55%"} -->
<div class="wp-block-column" style="flex-basis:55%">
<!-- wp:html -->
<div class="gf-form-card">
  <div class="mb-3">
    <label class="gf-form-label">Full Name</label>
    <input type="text" class="form-control gf-form-input" placeholder="Jane Smith"/>
  </div>
  <div class="mb-3">
    <label class="gf-form-label">Work Email</label>
    <input type="email" class="form-control gf-form-input" placeholder="jane@company.com"/>
  </div>
  <div class="mb-3">
    <label class="gf-form-label">Company Size</label>
    <select class="form-select gf-form-input">
      <option>1–10</option><option>11–50</option><option>51–200</option><option>200+</option>
    </select>
  </div>
  <div class="mb-3">
    <label class="gf-form-label">Message (optional)</label>
    <textarea class="form-control gf-form-input" rows="3" placeholder="Tell us what you want to focus on..."></textarea>
  </div>
  <button class="wp-block-button__link wp-element-button gf-form-submit has-primary-background-color has-contrast-color has-background has-text-color" style="width:100%"><?php echo $cta; ?></button>
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
	// quiz-lead-form — 'Find your plan' quiz + email capture
	// -------------------------------------------------------------------------

	public static function render_quiz_lead_form( array $spec, array $manifest ): string {
		$copy        = $spec['copy']        ?? [];
		$classes     = $spec['css_classes'] ?? [];
		$section_css = implode( ' ', $classes ) ?: 'gf-section-tint';

		$heading  = esc_html( $copy['section_heading'] ?? 'Find the Right Plan for You' );
		$question = esc_html( $copy['question']        ?? 'How many people are on your finance team?' );
		$cta      = esc_html( $copy['cta_label']       ?? 'See My Recommendation' );

		$option_defaults = [ 'Just me', '2–5 people', '6–20 people', '20+ people' ];
		$options = [];
		for ( $i = 1; $i <= 4; $i++ ) {
			$o = $copy[ "option_{$i}" ] ?? '';
			if ( $o ) {
				$options[] = esc_html( $o );
			}
		}
		if ( empty( $options ) ) {
			$options = $option_defaults;
		}

		$option_html = '';
		foreach ( $options as $opt ) {
			$option_html .= '<label class="gf-quiz-option"><input class="form-check-input" type="radio" name="gf_quiz_option" value="' . esc_attr( $opt ) . '"> ' . $opt . '</label>' . "\n";
		}

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $section_css; ?> py-5","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $section_css; ?> py-5">

<!-- wp:heading {"level":2,"textAlign":"center","textColor":"primary","className":"gf-section-heading"} -->
<h2 class="wp-block-heading has-text-align-center gf-section-heading has-primary-color has-text-color"><?php echo $heading; ?></h2>
<!-- /wp:heading -->

<!-- wp:html -->
<div class="gf-quiz-form">
  <form class="mt-4">
    <p class="gf-quiz-question"><?php echo $question; ?></p>
    <div class="gf-quiz-options mb-3">
      <?php echo $option_html; ?>
    </div>
    <div class="mt-4">
      <input type="email" class="form-control mb-3" placeholder="Enter your work email to see results" aria-label="Email address"/>
      <button type="submit" class="btn btn-primary w-100"><?php echo $cta; ?></button>
    </div>
  </form>
</div>
<!-- /wp:html -->

</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// inline-signup-form — simple single-field email capture row
	// -------------------------------------------------------------------------

	public static function render_inline_signup_form( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-inline-signup';

		$heading    = esc_html( $copy['heading']    ?? 'Stay in the Loop' );
		$subtext    = esc_html( $copy['subtext']    ?? 'Get product updates, tips, and resources delivered to your inbox.' );
		$cta        = esc_html( $copy['cta']        ?? 'Subscribe Now' );
		$trust_note = esc_html( $copy['trust_note'] ?? 'No spam. Unsubscribe anytime.' );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?> py-5","align":"full","backgroundColor":"background"} -->
<section class="wp-block-group <?php echo $css; ?> py-5 alignfull has-background-background-color has-background">

<!-- wp:heading {"level":2,"textAlign":"center","textColor":"primary","className":"gf-section-heading"} -->
<h2 class="wp-block-heading has-text-align-center gf-section-heading has-primary-color has-text-color"><?php echo $heading; ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","textColor":"muted"} -->
<p class="has-text-align-center has-muted-color has-text-color"><?php echo $subtext; ?></p>
<!-- /wp:paragraph -->

<!-- wp:html -->
<div style="display:flex;gap:0.75rem;flex-wrap:wrap;margin-top:2rem;max-width:560px;margin-left:auto;margin-right:auto;">
  <input type="email" class="gf-form-input" placeholder="you@company.com" aria-label="Email address" style="flex:1;min-width:220px;"/>
  <button type="submit" class="gf-form-submit" style="white-space:nowrap;"><?php echo $cta; ?></button>
</div>
<p class="small text-center opacity-75 mt-2"><?php echo $trust_note; ?></p>
<!-- /wp:html -->

</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}
}

GrayFox_TB_PatternBuilder::register_renderers( [
	'multi-step-form'       => [ GrayFox_TB_Patterns_Forms::class, 'render_multi_step_form' ],
	'webinar-registration'  => [ GrayFox_TB_Patterns_Forms::class, 'render_webinar_registration' ],
	'demo-request'          => [ GrayFox_TB_Patterns_Forms::class, 'render_demo_request' ],
	'quiz-lead-form'        => [ GrayFox_TB_Patterns_Forms::class, 'render_quiz_lead_form' ],
	'inline-signup-form'    => [ GrayFox_TB_Patterns_Forms::class, 'render_inline_signup_form' ],
] );
