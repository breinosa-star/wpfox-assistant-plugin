<?php
/**
 * Membership / gating pattern renderers.
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
 * Class GrayFox_TB_Patterns_Membership
 */
class GrayFox_TB_Patterns_Membership {

	// -------------------------------------------------------------------------
	// locked-content-gate — blurred preview with overlay gate
	// -------------------------------------------------------------------------

	public static function render_locked_content_gate( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-content-gate';

		$heading     = esc_html( $copy['heading']      ?? 'Members-Only Content' );
		$subtext     = esc_html( $copy['subtext']      ?? 'This content is available to members only. Join today to get instant access.' );
		$cta_join    = esc_html( $copy['cta_join']     ?? 'Become a Member' );
		$cta_login   = esc_html( $copy['cta_login']   ?? 'Log In' );
		$preview_txt = esc_html( $copy['preview_text'] ?? 'This article explores the key strategies used by top-performing teams to maintain focus and deliver results under pressure. The research draws on interviews with over 200 leaders across...' );

		ob_start(); ?>
<!-- wp:group {"className":"<?php echo $css; ?> position-relative overflow-hidden"} -->
<div class="wp-block-group <?php echo $css; ?> position-relative overflow-hidden">
<!-- wp:html -->
  <div class="p-4 text-muted lh-lg user-select-none pe-none" style="filter:blur(4px);" aria-hidden="true">
    <p><?php echo $preview_txt; ?></p>
    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris.</p>
  </div>

  <div class="position-absolute bottom-0 start-0 end-0 d-flex align-items-end p-4"
    style="background:linear-gradient(to bottom,transparent 0%,rgba(255,255,255,.96) 35%,#fff 60%);">
    <div class="w-100 text-center py-3">
      <i class="bi bi-lock-fill fs-1 mb-2 d-block" aria-hidden="true"></i>
      <h2 class="gf-section-heading mb-2"><?php echo $heading; ?></h2>
      <p class="text-muted mx-auto mb-4" style="max-width:420px;"><?php echo $subtext; ?></p>
      <div class="d-flex justify-content-center gap-3 flex-wrap">
        <a href="/membership" class="btn btn-primary fw-bold px-4"><?php echo $cta_join; ?></a>
        <a href="/login" class="btn btn-outline-primary fw-semibold px-4"><?php echo $cta_login; ?></a>
      </div>
    </div>
  </div>
<!-- /wp:html -->
</div>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// membership-plans — 3-tier membership card grid
	// -------------------------------------------------------------------------

	public static function render_membership_plans( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-membership-plans';

		$heading = esc_html( $copy['section_heading'] ?? 'Choose Your Membership' );
		$subtext = esc_html( $copy['subtext']         ?? 'Unlock exclusive content, community access, and more.' );

		$t1_name  = esc_html( $copy['tier_1_name']   ?? 'Free' );
		$t1_price = esc_html( $copy['tier_1_price']  ?? '$0/mo' );
		$t1_desc  = esc_html( $copy['tier_1_desc']   ?? 'Perfect for getting started.' );
		$t1_feat1 = esc_html( $copy['tier_1_feat_1'] ?? 'Access to free articles' );
		$t1_feat2 = esc_html( $copy['tier_1_feat_2'] ?? 'Weekly newsletter' );
		$t1_cta   = esc_html( $copy['tier_1_cta']   ?? 'Get Started Free' );

		$t2_name  = esc_html( $copy['tier_2_name']   ?? 'Pro' );
		$t2_price = esc_html( $copy['tier_2_price']  ?? '$12/mo' );
		$t2_desc  = esc_html( $copy['tier_2_desc']   ?? 'For serious learners.' );
		$t2_feat1 = esc_html( $copy['tier_2_feat_1'] ?? 'All free content' );
		$t2_feat2 = esc_html( $copy['tier_2_feat_2'] ?? 'Full course library' );
		$t2_feat3 = esc_html( $copy['tier_2_feat_3'] ?? 'Monthly live Q&A' );
		$t2_cta   = esc_html( $copy['tier_2_cta']   ?? 'Join Pro' );

		$t3_name  = esc_html( $copy['tier_3_name']   ?? 'Team' );
		$t3_price = esc_html( $copy['tier_3_price']  ?? '$49/mo' );
		$t3_desc  = esc_html( $copy['tier_3_desc']   ?? 'Collaborate and grow together.' );
		$t3_feat1 = esc_html( $copy['tier_3_feat_1'] ?? 'Everything in Pro' );
		$t3_feat2 = esc_html( $copy['tier_3_feat_2'] ?? 'Up to 10 seats' );
		$t3_feat3 = esc_html( $copy['tier_3_feat_3'] ?? 'Admin dashboard' );
		$t3_cta   = esc_html( $copy['tier_3_cta']   ?? 'Contact Sales' );

		$feat = function( string $text, bool $dark = false ): string {
			$color = $dark ? 'rgba(255,255,255,.85)' : '';
			$style = $color ? " style=\"color:{$color}\"" : '';
			return "<li class=\"d-flex align-items-center gap-2 small mb-1\"{$style}><i class=\"bi bi-check-lg text-success flex-shrink-0\"></i>{$text}</li>";
		};

		ob_start(); ?>
<!-- wp:group {"className":"<?php echo $css; ?> py-5","tagName":"section","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5">
<!-- wp:html -->
  <div class="container text-center" style="max-width:1000px;">
    <h2 class="gf-section-heading mb-2"><?php echo $heading; ?></h2>
    <p class="text-muted mb-5"><?php echo $subtext; ?></p>
    <div class="row g-4 text-start">

      <div class="col-md-4">
        <div class="card h-100 p-4">
          <p class="gf-eyebrow mb-2"><?php echo $t1_name; ?></p>
          <p class="fs-2 fw-bold text-primary mb-1"><?php echo $t1_price; ?></p>
          <p class="small text-muted mb-3"><?php echo $t1_desc; ?></p>
          <ul class="list-unstyled mb-4">
            <?php echo $feat($t1_feat1); ?><?php echo $feat($t1_feat2); ?>
          </ul>
          <a href="#" class="btn btn-outline-primary fw-bold mt-auto"><?php echo $t1_cta; ?></a>
        </div>
      </div>

      <div class="col-md-4">
        <div class="card h-100 p-4 bg-primary text-white position-relative">
          <span class="badge gf-accent-bg position-absolute top-0 start-50 translate-middle px-3 py-1 rounded-pill small fw-bold">Most Popular</span>
          <p class="gf-eyebrow mb-2 opacity-75"><?php echo $t2_name; ?></p>
          <p class="fs-2 fw-bold mb-1"><?php echo $t2_price; ?></p>
          <p class="small mb-3 opacity-75"><?php echo $t2_desc; ?></p>
          <ul class="list-unstyled mb-4">
            <?php echo $feat($t2_feat1, true); ?><?php echo $feat($t2_feat2, true); ?><?php echo $feat($t2_feat3, true); ?>
          </ul>
          <a href="#" class="btn gf-accent-bg text-white fw-bold border-0 mt-auto"><?php echo $t2_cta; ?></a>
        </div>
      </div>

      <div class="col-md-4">
        <div class="card h-100 p-4">
          <p class="gf-eyebrow mb-2"><?php echo $t3_name; ?></p>
          <p class="fs-2 fw-bold text-primary mb-1"><?php echo $t3_price; ?></p>
          <p class="small text-muted mb-3"><?php echo $t3_desc; ?></p>
          <ul class="list-unstyled mb-4">
            <?php echo $feat($t3_feat1); ?><?php echo $feat($t3_feat2); ?><?php echo $feat($t3_feat3); ?>
          </ul>
          <a href="#" class="btn btn-outline-primary fw-bold mt-auto"><?php echo $t3_cta; ?></a>
        </div>
      </div>

    </div>
  </div>
<!-- /wp:html -->
</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}
}

GrayFox_TB_PatternBuilder::register_renderers( [
	'locked-content-gate' => [ GrayFox_TB_Patterns_Membership::class, 'render_locked_content_gate' ],
	'membership-plans'    => [ GrayFox_TB_Patterns_Membership::class, 'render_membership_plans' ],
] );
