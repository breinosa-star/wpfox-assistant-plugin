<?php
/**
 * Interactive pattern renderers: accordion-tabs, tab-panels, toggle-group, calculator-widget.
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
 * Class GrayFox_TB_Patterns_Interactive
 */
class GrayFox_TB_Patterns_Interactive {

	// -------------------------------------------------------------------------
	// accordion-tabs — Bootstrap accordion with 5 items
	// -------------------------------------------------------------------------

	public static function render_accordion_tabs( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-accordion-tabs gf-section-tint';

		$heading = esc_html( $copy['section_heading'] ?? 'Everything You Need to Know' );
		$q1 = esc_html( $copy['q1'] ?? 'Topic 1' ); $a1 = esc_html( $copy['a1'] ?? 'Detailed content for topic 1 goes here.' );
		$q2 = esc_html( $copy['q2'] ?? 'Topic 2' ); $a2 = esc_html( $copy['a2'] ?? 'Detailed content for topic 2 goes here.' );
		$q3 = esc_html( $copy['q3'] ?? 'Topic 3' ); $a3 = esc_html( $copy['a3'] ?? 'Detailed content for topic 3 goes here.' );
		$q4 = esc_html( $copy['q4'] ?? 'Topic 4' ); $a4 = esc_html( $copy['a4'] ?? 'Detailed content for topic 4 goes here.' );
		$q5 = esc_html( $copy['q5'] ?? 'Topic 5' ); $a5 = esc_html( $copy['a5'] ?? 'Detailed content for topic 5 goes here.' );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?>","align":"full"} -->
<section class="wp-block-group <?php echo $css; ?> alignfull py-5">

<!-- wp:heading {"textAlign":"center","textColor":"primary"} -->
<h2 class="wp-block-heading has-text-align-center has-primary-color has-text-color"><?php echo $heading; ?></h2>
<!-- /wp:heading -->

<!-- wp:html -->
<div class="container" style="margin-top:2.5rem">
<div class="accordion" id="accordionTabs">
  <div class="accordion-item">
    <h2 class="accordion-header" id="atHead1">
      <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#atBody1" aria-expanded="true" aria-controls="atBody1"><?php echo $q1; ?></button>
    </h2>
    <div id="atBody1" class="accordion-collapse collapse show" aria-labelledby="atHead1" data-bs-parent="#accordionTabs">
      <div class="accordion-body"><?php echo $a1; ?></div>
    </div>
  </div>
  <div class="accordion-item">
    <h2 class="accordion-header" id="atHead2">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#atBody2" aria-expanded="false" aria-controls="atBody2"><?php echo $q2; ?></button>
    </h2>
    <div id="atBody2" class="accordion-collapse collapse" aria-labelledby="atHead2" data-bs-parent="#accordionTabs">
      <div class="accordion-body"><?php echo $a2; ?></div>
    </div>
  </div>
  <div class="accordion-item">
    <h2 class="accordion-header" id="atHead3">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#atBody3" aria-expanded="false" aria-controls="atBody3"><?php echo $q3; ?></button>
    </h2>
    <div id="atBody3" class="accordion-collapse collapse" aria-labelledby="atHead3" data-bs-parent="#accordionTabs">
      <div class="accordion-body"><?php echo $a3; ?></div>
    </div>
  </div>
  <div class="accordion-item">
    <h2 class="accordion-header" id="atHead4">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#atBody4" aria-expanded="false" aria-controls="atBody4"><?php echo $q4; ?></button>
    </h2>
    <div id="atBody4" class="accordion-collapse collapse" aria-labelledby="atHead4" data-bs-parent="#accordionTabs">
      <div class="accordion-body"><?php echo $a4; ?></div>
    </div>
  </div>
  <div class="accordion-item">
    <h2 class="accordion-header" id="atHead5">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#atBody5" aria-expanded="false" aria-controls="atBody5"><?php echo $q5; ?></button>
    </h2>
    <div id="atBody5" class="accordion-collapse collapse" aria-labelledby="atHead5" data-bs-parent="#accordionTabs">
      <div class="accordion-body"><?php echo $a5; ?></div>
    </div>
  </div>
</div>
</div>
<!-- /wp:html -->

</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// tab-panels — Bootstrap nav-tabs with 4 panels
	// -------------------------------------------------------------------------

	public static function render_tab_panels( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-tab-panels';

		$heading = esc_html( $copy['section_heading'] ?? 'Explore by Topic' );
		$t1  = esc_html( $copy['tab1_label']   ?? 'Overview' );
		$t1c = esc_html( $copy['tab1_content'] ?? 'This is the overview panel. Replace with your content for this tab.' );
		$t2  = esc_html( $copy['tab2_label']   ?? 'Features' );
		$t2c = esc_html( $copy['tab2_content'] ?? 'Detailed feature breakdown goes here. Use lists, images, or sub-sections.' );
		$t3  = esc_html( $copy['tab3_label']   ?? 'Integrations' );
		$t3c = esc_html( $copy['tab3_content'] ?? 'Show which tools connect to this feature or product.' );
		$t4  = esc_html( $copy['tab4_label']   ?? 'Pricing' );
		$t4c = esc_html( $copy['tab4_content'] ?? 'Pricing details, tier comparison, or plan-specific information.' );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?>","backgroundColor":"background","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> has-background-background-color has-background py-5">

<!-- wp:heading {"textAlign":"center","textColor":"primary"} -->
<h2 class="wp-block-heading has-text-align-center has-primary-color has-text-color"><?php echo $heading; ?></h2>
<!-- /wp:heading -->

<!-- wp:html -->
<div style="margin-top:2.5rem">
<ul class="nav nav-tabs mb-4" id="tabPanels" role="tablist">
  <li class="nav-item" role="presentation"><button class="nav-link active" id="tp1-tab" data-bs-toggle="tab" data-bs-target="#tp1" type="button" role="tab" aria-controls="tp1" aria-selected="true"><?php echo $t1; ?></button></li>
  <li class="nav-item" role="presentation"><button class="nav-link" id="tp2-tab" data-bs-toggle="tab" data-bs-target="#tp2" type="button" role="tab" aria-controls="tp2" aria-selected="false"><?php echo $t2; ?></button></li>
  <li class="nav-item" role="presentation"><button class="nav-link" id="tp3-tab" data-bs-toggle="tab" data-bs-target="#tp3" type="button" role="tab" aria-controls="tp3" aria-selected="false"><?php echo $t3; ?></button></li>
  <li class="nav-item" role="presentation"><button class="nav-link" id="tp4-tab" data-bs-toggle="tab" data-bs-target="#tp4" type="button" role="tab" aria-controls="tp4" aria-selected="false"><?php echo $t4; ?></button></li>
</ul>
<div class="tab-content" id="tabPanelsContent">
  <div class="tab-pane fade show active" id="tp1" role="tabpanel" aria-labelledby="tp1-tab"><p><?php echo $t1c; ?></p></div>
  <div class="tab-pane fade" id="tp2" role="tabpanel" aria-labelledby="tp2-tab"><p><?php echo $t2c; ?></p></div>
  <div class="tab-pane fade" id="tp3" role="tabpanel" aria-labelledby="tp3-tab"><p><?php echo $t3c; ?></p></div>
  <div class="tab-pane fade" id="tp4" role="tabpanel" aria-labelledby="tp4-tab"><p><?php echo $t4c; ?></p></div>
</div>
</div>
<!-- /wp:html -->

</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// toggle-group — flush accordion for spec/tech details
	// -------------------------------------------------------------------------

	public static function render_toggle_group( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-toggle-group';

		$heading = esc_html( $copy['section_heading'] ?? 'Technical Specifications' );
		$t1  = esc_html( $copy['t1_label']   ?? 'Dimensions' );
		$t1c = esc_html( $copy['t1_content'] ?? 'Width: 280mm · Height: 180mm · Depth: 12mm · Weight: 1.4kg' );
		$t2  = esc_html( $copy['t2_label']   ?? 'Compatibility' );
		$t2c = esc_html( $copy['t2_content'] ?? 'Windows 10+, macOS 12+, Ubuntu 20.04+. Requires USB-C port.' );
		$t3  = esc_html( $copy['t3_label']   ?? 'In the Box' );
		$t3c = esc_html( $copy['t3_content'] ?? 'Device, USB-C cable, quick-start guide, 1-year warranty card.' );
		$t4  = esc_html( $copy['t4_label']   ?? 'Certifications' );
		$t4c = esc_html( $copy['t4_content'] ?? 'FCC, CE, RoHS, Energy Star compliant.' );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?>","backgroundColor":"background","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> has-background-background-color has-background py-5">

<!-- wp:heading {"textColor":"primary"} -->
<h2 class="wp-block-heading has-primary-color has-text-color"><?php echo $heading; ?></h2>
<!-- /wp:heading -->

<!-- wp:html -->
<div class="accordion accordion-flush" id="toggleGroup" style="margin-top:2rem">
  <div class="accordion-item" style="border-bottom:1px solid var(--wp--preset--color--muted)">
    <h3 class="accordion-header" id="tgH1"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#tgC1" aria-expanded="false" aria-controls="tgC1" style="background:transparent;font-weight:600"><?php echo $t1; ?></button></h3>
    <div id="tgC1" class="accordion-collapse collapse" aria-labelledby="tgH1" data-bs-parent="#toggleGroup"><div class="accordion-body" style="color:var(--wp--preset--color--muted)"><?php echo $t1c; ?></div></div>
  </div>
  <div class="accordion-item" style="border-bottom:1px solid var(--wp--preset--color--muted)">
    <h3 class="accordion-header" id="tgH2"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#tgC2" aria-expanded="false" aria-controls="tgC2" style="background:transparent;font-weight:600"><?php echo $t2; ?></button></h3>
    <div id="tgC2" class="accordion-collapse collapse" aria-labelledby="tgH2" data-bs-parent="#toggleGroup"><div class="accordion-body" style="color:var(--wp--preset--color--muted)"><?php echo $t2c; ?></div></div>
  </div>
  <div class="accordion-item" style="border-bottom:1px solid var(--wp--preset--color--muted)">
    <h3 class="accordion-header" id="tgH3"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#tgC3" aria-expanded="false" aria-controls="tgC3" style="background:transparent;font-weight:600"><?php echo $t3; ?></button></h3>
    <div id="tgC3" class="accordion-collapse collapse" aria-labelledby="tgH3" data-bs-parent="#toggleGroup"><div class="accordion-body" style="color:var(--wp--preset--color--muted)"><?php echo $t3c; ?></div></div>
  </div>
  <div class="accordion-item">
    <h3 class="accordion-header" id="tgH4"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#tgC4" aria-expanded="false" aria-controls="tgC4" style="background:transparent;font-weight:600"><?php echo $t4; ?></button></h3>
    <div id="tgC4" class="accordion-collapse collapse" aria-labelledby="tgH4" data-bs-parent="#toggleGroup"><div class="accordion-body" style="color:var(--wp--preset--color--muted)"><?php echo $t4c; ?></div></div>
  </div>
</div>
<!-- /wp:html -->

</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// calculator-widget — ROI calculator with range sliders
	// -------------------------------------------------------------------------

	public static function render_calculator_widget( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-calculator';

		$heading      = esc_html( $copy['section_heading'] ?? 'Calculate Your ROI' );
		$subtext      = esc_html( $copy['subtext']         ?? 'See how much time and money you could save.' );
		$label1       = esc_html( $copy['input1_label']    ?? 'Number of team members' );
		$label2       = esc_html( $copy['input2_label']    ?? 'Hours spent on manual work per week' );
		$label3       = esc_html( $copy['input3_label']    ?? 'Average hourly rate ($)' );
		$result_label = esc_html( $copy['result_label']    ?? 'Estimated annual savings' );
		$disclaimer   = esc_html( $copy['disclaimer']      ?? 'Estimates based on average customer data. Actual savings may vary.' );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?>","backgroundColor":"background","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> has-background-background-color has-background py-5">

<!-- wp:heading {"textAlign":"center","textColor":"primary"} -->
<h2 class="wp-block-heading has-text-align-center has-primary-color has-text-color"><?php echo $heading; ?></h2>
<!-- /wp:heading -->
<!-- wp:paragraph {"align":"center","textColor":"muted"} -->
<p class="has-text-align-center has-muted-color has-text-color"><?php echo $subtext; ?></p>
<!-- /wp:paragraph -->

<!-- wp:html -->
<div style="border:1px solid var(--wp--preset--color--muted);border-radius:12px;padding:2.5rem;margin-top:2.5rem;max-width:560px;margin-left:auto;margin-right:auto;background:var(--wp--preset--color--background)">

  <div class="mb-4">
    <label class="form-label fw-semibold"><?php echo $label1; ?></label>
    <input type="range" class="form-range" min="1" max="500" value="10" id="calcTeam" oninput="calcROI()">
    <div class="d-flex justify-content-between"><small>1</small><strong id="teamVal">10</strong><small>500</small></div>
  </div>

  <div class="mb-4">
    <label class="form-label fw-semibold"><?php echo $label2; ?></label>
    <input type="range" class="form-range" min="1" max="40" value="10" id="calcHours" oninput="calcROI()">
    <div class="d-flex justify-content-between"><small>1 hr</small><strong id="hoursVal">10 hrs</strong><small>40 hrs</small></div>
  </div>

  <div class="mb-4">
    <label class="form-label fw-semibold"><?php echo $label3; ?></label>
    <input type="number" class="form-control" id="calcRate" value="50" min="10" max="500" onchange="calcROI()">
  </div>

  <div style="background:var(--wp--preset--color--primary);color:var(--wp--preset--color--contrast,#fff);border-radius:8px;padding:1.5rem;text-align:center;margin-top:1.5rem">
    <p style="margin:0;font-size:0.9rem;opacity:0.85"><?php echo $result_label; ?></p>
    <p style="margin:0.25rem 0 0;font-size:2.5rem;font-weight:700" id="calcResult">$26,000/yr</p>
  </div>

  <p style="font-size:0.75rem;color:var(--wp--preset--color--muted);margin-top:1rem;text-align:center"><?php echo $disclaimer; ?></p>

  <script>
  function calcROI(){
    var t=parseInt(document.getElementById('calcTeam').value)||10;
    var h=parseInt(document.getElementById('calcHours').value)||10;
    var r=parseFloat(document.getElementById('calcRate').value)||50;
    document.getElementById('teamVal').textContent=t;
    document.getElementById('hoursVal').textContent=h+' hrs';
    var savings=Math.round(t*h*r*52*0.5);
    document.getElementById('calcResult').textContent='$'+savings.toLocaleString()+'/yr';
  }
  </script>
</div>
<!-- /wp:html -->

</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}
}

GrayFox_TB_PatternBuilder::register_renderers( [
	'accordion-tabs'     => [ GrayFox_TB_Patterns_Interactive::class, 'render_accordion_tabs' ],
	'tab-panels'         => [ GrayFox_TB_Patterns_Interactive::class, 'render_tab_panels' ],
	'toggle-group'       => [ GrayFox_TB_Patterns_Interactive::class, 'render_toggle_group' ],
	'calculator-widget'  => [ GrayFox_TB_Patterns_Interactive::class, 'render_calculator_widget' ],
] );
