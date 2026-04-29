<?php
/**
 * Nonprofit / donation pattern renderers.
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
 * Class GrayFox_TB_Patterns_Nonprofit
 */
class GrayFox_TB_Patterns_Nonprofit {

	// -------------------------------------------------------------------------
	// donation-form-section — centered donation form with amount presets
	// -------------------------------------------------------------------------

	public static function render_donation_form_section( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-donation-form';

		$heading     = esc_html( $copy['section_heading'] ?? 'Make a Difference Today' );
		$subtext     = esc_html( $copy['subtext']         ?? 'Your contribution directly funds our programs and helps us serve more people.' );
		$amount_1    = esc_html( $copy['amount_1']        ?? '$10' );
		$amount_2    = esc_html( $copy['amount_2']        ?? '$25' );
		$amount_3    = esc_html( $copy['amount_3']        ?? '$50' );
		$amount_4    = esc_html( $copy['amount_4']        ?? '$100' );
		$cta         = esc_html( $copy['cta_donate']      ?? 'Donate Now' );
		$secure_note = esc_html( $copy['secure_note']     ?? 'Secure, encrypted payment. Tax-deductible receipt provided.' );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?> py-5","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5">
<!-- wp:html -->
<div class="container" style="max-width:680px;">
  <div class="text-center mb-4">
    <h2 class="gf-section-heading"><?php echo $heading; ?></h2>
    <p class="text-muted"><?php echo $subtext; ?></p>
  </div>
  <div class="card shadow-sm">
    <div class="card-body p-4">

      <div class="d-flex border rounded-pill overflow-hidden mb-4">
        <button class="btn btn-primary flex-fill rounded-0 fw-semibold">Monthly</button>
        <button class="btn btn-link flex-fill rounded-0 text-body fw-normal text-decoration-none">One-time</button>
      </div>

      <p class="fw-semibold small mb-2">Select an amount</p>
      <div class="row g-2 mb-3">
        <div class="col-3"><button class="btn btn-outline-secondary w-100 fw-bold"><?php echo $amount_1; ?></button></div>
        <div class="col-3"><button class="btn btn-primary w-100 fw-bold"><?php echo $amount_2; ?></button></div>
        <div class="col-3"><button class="btn btn-outline-secondary w-100 fw-bold"><?php echo $amount_3; ?></button></div>
        <div class="col-3"><button class="btn btn-outline-secondary w-100 fw-bold"><?php echo $amount_4; ?></button></div>
      </div>

      <div class="input-group mb-3">
        <span class="input-group-text fw-semibold">$</span>
        <input type="number" class="form-control" placeholder="Custom amount" aria-label="Custom donation amount" min="1" />
      </div>

      <div class="row g-2 mb-2">
        <div class="col-6"><input type="text" class="form-control" placeholder="First name" aria-label="First name" /></div>
        <div class="col-6"><input type="text" class="form-control" placeholder="Last name" aria-label="Last name" /></div>
      </div>
      <input type="email" class="form-control mb-3" placeholder="Email address" aria-label="Email" />

      <button type="submit" class="btn btn-lg w-100 gf-accent-bg text-white fw-bold border-0 d-flex align-items-center justify-content-center gap-2"><i class="bi bi-heart-fill"></i> <?php echo $cta; ?></button>

      <p class="text-center text-muted small mt-3 mb-0"><i class="bi bi-lock-fill"></i> <?php echo $secure_note; ?></p>
    </div>
  </div>
</div>
<!-- /wp:html -->
</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// campaign-progress — fundraising progress bar card
	// -------------------------------------------------------------------------

	public static function render_campaign_progress( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-campaign-progress';

		$heading       = esc_html( $copy['heading']       ?? 'Help Us Reach Our Goal' );
		$description   = esc_html( $copy['description']   ?? 'Every dollar brings us closer to funding the next generation of community programs.' );
		$goal_amount   = esc_html( $copy['goal_amount']   ?? '$50,000' );
		$raised_amount = esc_html( $copy['raised_amount'] ?? '$34,200' );
		$raised_pct    = min( 100, max( 0, intval( $copy['raised_pct'] ?? 68 ) ) );
		$donor_count   = esc_html( $copy['donor_count']   ?? '412' );
		$days_left     = esc_html( $copy['days_left']     ?? '14' );
		$cta           = esc_html( $copy['cta_donate']    ?? 'Donate Now' );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?> py-5","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5">
<!-- wp:html -->
<div class="container" style="max-width:680px;">
  <div class="card shadow-sm">
    <div class="card-body p-4">

      <h2 class="gf-section-heading mb-2"><?php echo $heading; ?></h2>
      <p class="text-muted mb-4"><?php echo $description; ?></p>

      <div class="mb-3">
        <div class="d-flex justify-content-between align-items-baseline mb-2">
          <strong class="fs-3 text-primary"><?php echo $raised_amount; ?></strong>
          <span class="small text-muted">of <?php echo $goal_amount; ?> goal</span>
        </div>
        <div class="progress" style="height:12px;">
          <div class="progress-bar gf-progress-gradient" role="progressbar"
            style="width:<?php echo $raised_pct; ?>%;" aria-valuenow="<?php echo $raised_pct; ?>" aria-valuemin="0" aria-valuemax="100"></div>
        </div>
        <p class="small text-muted mt-1 mb-0"><?php echo $raised_pct; ?>% funded</p>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-4">
          <div class="text-center p-3 gf-section-tint rounded">
            <p class="fs-5 fw-bold text-primary mb-0"><?php echo $raised_amount; ?></p>
            <p class="small text-muted mb-0">Raised</p>
          </div>
        </div>
        <div class="col-4">
          <div class="text-center p-3 gf-section-tint rounded">
            <p class="fs-5 fw-bold text-primary mb-0"><?php echo $donor_count; ?></p>
            <p class="small text-muted mb-0">Donors</p>
          </div>
        </div>
        <div class="col-4">
          <div class="text-center p-3 gf-section-tint rounded">
            <p class="fs-5 fw-bold text-primary mb-0"><?php echo $days_left; ?></p>
            <p class="small text-muted mb-0">Days left</p>
          </div>
        </div>
      </div>

      <a href="#donate" class="btn btn-lg w-100 gf-accent-bg text-white fw-bold border-0">&#10084;&#65039; <?php echo $cta; ?></a>

    </div>
  </div>
</div>
<!-- /wp:html -->
</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// donor-wall — 3-column grid of donor cards with avatar, name, and message
	// -------------------------------------------------------------------------

	public static function render_donor_wall( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-donor-wall';

		$heading = esc_html( $copy['section_heading'] ?? 'Thank You to Our Donors' );
		$subtext = esc_html( $copy['subtext']         ?? "We're grateful for the generosity of everyone who has contributed to this campaign." );

		$donors = [
			[ esc_html( $copy['donor_1_name'] ?? 'Sarah K.' ),       esc_html( $copy['donor_1_message'] ?? 'Proud to support this mission!' ), '$50' ],
			[ esc_html( $copy['donor_2_name'] ?? 'Marcus T.' ),      esc_html( $copy['donor_2_message'] ?? 'Keep up the incredible work.' ), '$100' ],
			[ esc_html( $copy['donor_3_name'] ?? 'Anonymous' ),      esc_html( $copy['donor_3_message'] ?? '' ), '' ],
			[ esc_html( $copy['donor_4_name'] ?? 'Priya & Raj N.' ), esc_html( $copy['donor_4_message'] ?? 'Donating in memory of our father.' ), '$250' ],
			[ esc_html( $copy['donor_5_name'] ?? 'The Chen Family' ),esc_html( $copy['donor_5_message'] ?? 'Happy to give back to the community.' ), '$75' ],
			[ esc_html( $copy['donor_6_name'] ?? 'Jordan W.' ),      esc_html( $copy['donor_6_message'] ?? 'Every little bit helps!' ), '$25' ],
		];

		$donor_card = function( string $name, string $msg, string $amount ): string {
			$initial  = ( $name && $name !== 'Anonymous' ) ? strtoupper( substr( $name, 0, 1 ) ) : '?';
			$msg_html = $msg ? "<p class=\"small fst-italic text-muted mt-1 mb-0\">&ldquo;{$msg}&rdquo;</p>" : '';
			$amt_html = $amount ? "<span class=\"badge gf-accent-bg text-white ms-1 small\">{$amount}</span>" : '';
			ob_start(); ?>
<div class="card gf-donor-card h-100">
  <div class="card-body p-3 d-flex align-items-start gap-3">
    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold flex-shrink-0"
      style="width:40px;height:40px;"><?php echo $initial; ?></div>
    <div class="min-w-0">
      <p class="fw-semibold small text-primary mb-0"><?php echo $name; ?><?php echo $amt_html; ?></p>
      <?php echo $msg_html; ?>
    </div>
  </div>
</div>
			<?php return ob_get_clean();
		};

		$cards_html = '';
		foreach ( $donors as $donor ) {
			$cards_html .= '<div class="col-md-4">' . $donor_card( $donor[0], $donor[1], $donor[2] ) . '</div>';
		}

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?> py-5","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5">
<!-- wp:html -->
<div class="container" style="max-width:1000px;">
  <div class="text-center mb-5">
    <h2 class="gf-section-heading"><?php echo $heading; ?></h2>
    <p class="text-muted"><?php echo $subtext; ?></p>
  </div>
  <div class="row g-3">
    <?php echo $cards_html; ?>
  </div>
</div>
<!-- /wp:html -->
</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}
}

GrayFox_TB_PatternBuilder::register_renderers( [
	'donation-form-section' => [ GrayFox_TB_Patterns_Nonprofit::class, 'render_donation_form_section' ],
	'campaign-progress'     => [ GrayFox_TB_Patterns_Nonprofit::class, 'render_campaign_progress' ],
	'donor-wall'            => [ GrayFox_TB_Patterns_Nonprofit::class, 'render_donor_wall' ],
] );
