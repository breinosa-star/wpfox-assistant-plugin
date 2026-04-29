<?php
/**
 * Pricing patterns — comparison-table, two-tier-pricing, pricing-toggle.
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
 * Class GrayFox_TB_Patterns_Pricing
 */
class GrayFox_TB_Patterns_Pricing {

	/** Dash value used in comparison table cells — checked by string comparison. */
	private const DASH = '✕';

	// -------------------------------------------------------------------------
	// Renderers
	// -------------------------------------------------------------------------

	/**
	 * Feature comparison table — rows × columns grid.
	 */
	public static function render_comparison_table( array $spec, array $theme ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];

		$wrap_class = esc_attr( implode( ' ', $classes ) ?: 'gf-section-tint' );
		$heading    = esc_html( $copy['section_heading'] ?? 'Compare Plans' );
		$subtext    = esc_html( $copy['subtext']         ?? 'Everything you need, nothing you don\'t.' );

		$tiers = [
			esc_html( $copy['tier_1'] ?? 'Starter' ),
			esc_html( $copy['tier_2'] ?? 'Pro' ),
			esc_html( $copy['tier_3'] ?? 'Scale' ),
		];

		$x = self::DASH;
		$feature_defaults = [
			[ 'label' => 'Users',              'vals' => [ '1', '5', 'Unlimited' ] ],
			[ 'label' => 'Projects',           'vals' => [ '3', '25', 'Unlimited' ] ],
			[ 'label' => 'Storage',            'vals' => [ '5 GB', '50 GB', '500 GB' ] ],
			[ 'label' => 'API Access',         'vals' => [ $x,  '✓', '✓' ] ],
			[ 'label' => 'Priority Support',   'vals' => [ $x,   $x,  '✓' ] ],
			[ 'label' => 'Custom Integrations','vals' => [ $x,   $x,  '✓' ] ],
		];

		$features = [];
		for ( $i = 1; $i <= 6; $i++ ) {
			$d = $feature_defaults[ $i - 1 ];
			$features[] = [
				'label' => esc_html( $copy[ "feature_{$i}_label" ] ?? $d['label'] ),
				'v1'    => esc_html( $copy[ "feature_{$i}_v1"    ] ?? $d['vals'][0] ),
				'v2'    => esc_html( $copy[ "feature_{$i}_v2"    ] ?? $d['vals'][1] ),
				'v3'    => esc_html( $copy[ "feature_{$i}_v3"    ] ?? $d['vals'][2] ),
			];
		}

		// Build HTML table rows.
		$header_row = '<tr>'
			. '<th></th>'
			. "<th>{$tiers[0]}</th>"
			. "<th>{$tiers[1]}</th>"
			. "<th>{$tiers[2]}</th>"
			. '</tr>';

		$body_rows = '';
		foreach ( $features as $idx => $f ) {
			$even       = ( $idx % 2 === 0 ) ? '' : ' style="background:rgba(0,0,0,.025)"';
			$v1_class   = ( $f['v1'] === self::DASH ) ? 'gf-pricing-dash' : 'gf-pricing-check';
			$v2_class   = ( $f['v2'] === self::DASH ) ? 'gf-pricing-dash' : 'gf-pricing-check';
			$v3_class   = ( $f['v3'] === self::DASH ) ? 'gf-pricing-dash' : 'gf-pricing-check';
			$body_rows .= "<tr{$even}>"
				. "<td><strong>{$f['label']}</strong></td>"
				. "<td class=\"{$v1_class}\">{$f['v1']}</td>"
				. "<td class=\"{$v2_class} gf-highlight-col\">{$f['v2']}</td>"
				. "<td class=\"{$v3_class}\">{$f['v3']}</td>"
				. '</tr>';
		}

		$table_html = esc_html( '' ); // Reset — we build raw HTML for the table block.

		ob_start(); ?>
<!-- wp:group {"className":"<?php echo $wrap_class; ?>","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group <?php echo $wrap_class; ?> alignfull">
  <!-- wp:heading {"textAlign":"center","level":2,"className":"gf-section-heading"} -->
  <h2 class="wp-block-heading has-text-align-center gf-section-heading"><?php echo $heading; ?></h2>
  <!-- /wp:heading -->
  <!-- wp:paragraph {"align":"center","textColor":"muted","className":"gf-section-subtext"} -->
  <p class="has-text-align-center has-muted-color has-text-color gf-section-subtext"><?php echo $subtext; ?></p>
  <!-- /wp:paragraph -->
  <!-- wp:html -->
  <div class="gf-table-wrapper" style="margin-top:var(--wp--preset--spacing--50)">
    <table class="gf-comparison-table">
      <thead><?php echo $header_row; ?></thead>
      <tbody><?php echo $body_rows; ?></tbody>
    </table>
  </div>
  <!-- /wp:html -->
</div>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	/**
	 * Two-tier pricing — simpler side-by-side without a "featured" middle card.
	 */
	public static function render_two_tier_pricing( array $spec, array $theme ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];

		$wrap_class = esc_attr( implode( ' ', $classes ) ?: '' );
		$heading    = esc_html( $copy['section_heading']  ?? 'Simple pricing' );
		$subtext    = esc_html( $copy['subtext']          ?? 'No hidden fees. Cancel any time.' );

		$tiers = [
			[
				'name'  => esc_html( $copy['tier_1_name']  ?? 'Monthly' ),
				'price' => esc_html( $copy['tier_1_price'] ?? '$49/mo' ),
				'desc'  => esc_html( $copy['tier_1_desc']  ?? 'Billed monthly. Full access.' ),
				'cta'   => esc_html( $copy['tier_1_cta']   ?? 'Get started' ),
			],
			[
				'name'  => esc_html( $copy['tier_2_name']  ?? 'Annual' ),
				'price' => esc_html( $copy['tier_2_price'] ?? '$39/mo' ),
				'desc'  => esc_html( $copy['tier_2_desc']  ?? 'Billed annually. Save 20%.' ),
				'cta'   => esc_html( $copy['tier_2_cta']   ?? 'Get started' ),
			],
		];

		$cards = '';
		foreach ( $tiers as $t ) {
			ob_start(); ?>

    <!-- wp:column -->
    <div class="wp-block-column">
      <!-- wp:group {"className":"gf-pricing-card card h-100","style":{"spacing":{"padding":{"top":"2.5rem","bottom":"2.5rem","left":"2rem","right":"2rem"}}},"layout":{"type":"flex","orientation":"vertical","justifyContent":"left"}} -->
      <div class="wp-block-group gf-pricing-card card h-100">
        <!-- wp:heading {"level":3,"style":{"typography":{"fontSize":"1.1rem","fontWeight":"700"}}} -->
        <h3 class="wp-block-heading"><?php echo $t['name']; ?></h3>
        <!-- /wp:heading -->
        <!-- wp:heading {"level":2,"className":"gf-price-amount","style":{"typography":{"fontSize":"3rem","fontWeight":"800","lineHeight":"1"}}} -->
        <h2 class="wp-block-heading gf-price-amount"><?php echo $t['price']; ?></h2>
        <!-- /wp:heading -->
        <!-- wp:paragraph {"textColor":"muted","style":{"typography":{"fontSize":".875rem"}}} -->
        <p class="has-muted-color has-text-color"><?php echo $t['desc']; ?></p>
        <!-- /wp:paragraph -->
        <!-- wp:buttons {"style":{"spacing":{"margin":{"top":"1.5rem"}}}} -->
        <div class="wp-block-buttons">
          <!-- wp:button {"backgroundColor":"primary","textColor":"contrast","width":100} -->
          <div class="wp-block-button has-custom-width wp-block-button__width-100"><a class="wp-block-button__link has-contrast-color has-primary-background-color has-text-color has-background wp-element-button"><?php echo $t['cta']; ?></a></div>
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
<!-- wp:group {"className":"<?php echo $wrap_class; ?>","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"layout":{"type":"constrained","contentSize":"760px"}} -->
<div class="wp-block-group <?php echo $wrap_class; ?> alignfull">
  <!-- wp:heading {"textAlign":"center","level":2,"className":"gf-section-heading"} -->
  <h2 class="wp-block-heading has-text-align-center gf-section-heading"><?php echo $heading; ?></h2>
  <!-- /wp:heading -->
  <!-- wp:paragraph {"align":"center","textColor":"muted","className":"gf-section-subtext"} -->
  <p class="has-text-align-center has-muted-color has-text-color gf-section-subtext"><?php echo $subtext; ?></p>
  <!-- /wp:paragraph -->
  <!-- wp:columns {"isStackedOnMobile":true,"style":{"spacing":{"blockGap":{"top":"var:preset|spacing|40","left":"var:preset|spacing|40"},"margin":{"top":"var:preset|spacing|50"}}}} -->
  <div class="wp-block-columns is-layout-flex">
<?php echo $cards; ?>
  </div>
  <!-- /wp:columns -->
</div>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}
	/**
	 * Feature matrix — tiers as columns, features as rows, ✓ / — values.
	 */
	public static function render_feature_matrix( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-feature-matrix';

		$heading = esc_html( $copy['section_heading'] ?? 'Everything You Get' );
		$subtext = esc_html( $copy['subtext']         ?? 'A full breakdown of features by plan.' );

		$tier_1 = esc_html( $copy['tier_1'] ?? 'Free' );
		$tier_2 = esc_html( $copy['tier_2'] ?? 'Pro' );
		$tier_3 = esc_html( $copy['tier_3'] ?? 'Enterprise' );

		$feat_defaults = [
			[ 'Unlimited projects',     '✓', '✓', '✓' ],
			[ 'Priority support',       '—', '✓', '✓' ],
			[ 'Custom integrations',    '—', '✓', '✓' ],
			[ 'SSO / SAML login',       '—', '—', '✓' ],
			[ 'Dedicated CSM',          '—', '—', '✓' ],
			[ 'SLA guarantee',          '—', '—', '✓' ],
			[ 'Advanced analytics',     '—', '✓', '✓' ],
			[ 'White-label export',     '—', '—', '✓' ],
		];

		$header = '<thead class="gf-feature-table__head"><tr>'
			. '<th class="text-start fw-semibold small">Feature</th>'
			. "<th class=\"text-center fw-semibold small\">{$tier_1}</th>"
			. "<th class=\"text-center fw-semibold small\">{$tier_2}</th>"
			. "<th class=\"text-center fw-semibold small\">{$tier_3}</th>"
			. '</tr></thead>';

		$body = '<tbody>';
		for ( $i = 1; $i <= 8; $i++ ) {
			$d     = $feat_defaults[ $i - 1 ];
			$label = esc_html( $copy[ "feature_{$i}_label" ] ?? $d[0] );
			$v1    = esc_html( $copy[ "feature_{$i}_v1"    ] ?? $d[1] );
			$v2    = esc_html( $copy[ "feature_{$i}_v2"    ] ?? $d[2] );
			$v3    = esc_html( $copy[ "feature_{$i}_v3"    ] ?? $d[3] );
			$cell  = function( string $v ): string {
				if ( $v === '✓' ) {
					return '<td class="text-center gf-accent-color"><i class="bi bi-check-lg fw-bold"></i></td>';
				}
				return '<td class="text-center text-muted">&#8212;</td>';
			};
			$body .= '<tr>'
				. "<td class=\"small fw-semibold\">{$label}</td>"
				. $cell( $v1 ) . $cell( $v2 ) . $cell( $v3 )
				. '</tr>';
		}
		$body .= '</tbody>';

		ob_start(); ?>
<!-- wp:group {"className":"<?php echo $css; ?> py-5","tagName":"section","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5">
<!-- wp:html -->
  <div class="container text-center" style="max-width:860px;">
    <h2 class="gf-section-heading mb-2"><?php echo $heading; ?></h2>
    <p class="text-muted mb-4"><?php echo $subtext; ?></p>
    <div class="table-responsive">
      <table class="table gf-feature-table align-middle">
        <?php echo $header; ?>
        <?php echo $body; ?>
      </table>
    </div>
  </div>
<!-- /wp:html -->
</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	/**
	 * Pricing toggle — monthly/annual switch with three-card grid.
	 */
	public static function render_pricing_toggle( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-pricing-toggle';

		$heading      = esc_html( $copy['section_heading'] ?? 'Simple, Transparent Pricing' );
		$subtext      = esc_html( $copy['subtext']         ?? 'Switch between monthly and annual billing.' );
		$savings_note = esc_html( $copy['savings_note']    ?? 'Save 20% with annual billing' );

		$tiers = [];
		$tier_defaults = [
			[ 'name' => 'Starter', 'mo_price' => '$19/mo', 'yr_price' => '$15/mo', 'desc' => 'For individuals.',   'feat_1' => '5 projects',   'feat_2' => '10 GB storage',  'feat_3' => 'Email support',    'cta' => 'Get Started'      ],
			[ 'name' => 'Pro',     'mo_price' => '$49/mo', 'yr_price' => '$39/mo', 'desc' => 'For growing teams.', 'feat_1' => '25 projects',  'feat_2' => '100 GB storage', 'feat_3' => 'Priority support', 'cta' => 'Start Free Trial' ],
			[ 'name' => 'Scale',   'mo_price' => '$99/mo', 'yr_price' => '$79/mo', 'desc' => 'For large orgs.',    'feat_1' => 'Unlimited',    'feat_2' => '1 TB storage',   'feat_3' => 'Dedicated CSM',    'cta' => 'Contact Sales'    ],
		];

		for ( $i = 1; $i <= 3; $i++ ) {
			$d = $tier_defaults[ $i - 1 ];
			$tiers[] = [
				'name'     => esc_html( $copy[ "tier_{$i}_name"     ] ?? $d['name']     ),
				'mo_price' => esc_html( $copy[ "tier_{$i}_mo_price" ] ?? $d['mo_price'] ),
				'yr_price' => esc_html( $copy[ "tier_{$i}_yr_price" ] ?? $d['yr_price'] ),
				'desc'     => esc_html( $copy[ "tier_{$i}_desc"     ] ?? $d['desc']     ),
				'feat_1'   => esc_html( $copy[ "tier_{$i}_feat_1"   ] ?? $d['feat_1']   ),
				'feat_2'   => esc_html( $copy[ "tier_{$i}_feat_2"   ] ?? $d['feat_2']   ),
				'feat_3'   => esc_html( $copy[ "tier_{$i}_feat_3"   ] ?? $d['feat_3']   ),
				'cta'      => esc_html( $copy[ "tier_{$i}_cta"      ] ?? $d['cta']      ),
				'popular'  => ( $i === 2 ),
			];
		}

		$cards_html = '';
		foreach ( $tiers as $t ) {
			if ( $t['popular'] ) {
				$popular_badge = '<span class="badge gf-accent-bg text-white position-absolute top-0 start-50 translate-middle rounded-pill px-3 small fw-bold">Most Popular</span>';
				$card_cls      = 'card h-100 p-4 bg-primary text-white position-relative';
				$eyebrow_cls   = 'gf-eyebrow mb-2 opacity-75';
				$price_cls     = 'gf-price-monthly fs-2 fw-bold mb-1';
				$price_cls_yr  = 'gf-price-annual fs-2 fw-bold mb-1 d-none';
				$desc_cls      = 'small mb-3 opacity-75';
				$feat_cls      = 'small mb-1 opacity-90';
				$cta_cls       = 'btn gf-accent-bg text-white border-0 fw-bold w-100 mt-auto';
			} else {
				$popular_badge = '';
				$card_cls      = 'card h-100 p-4 position-relative';
				$eyebrow_cls   = 'gf-eyebrow mb-2';
				$price_cls     = 'gf-price-monthly fs-2 fw-bold text-primary mb-1';
				$price_cls_yr  = 'gf-price-annual fs-2 fw-bold text-primary mb-1 d-none';
				$desc_cls      = 'small text-muted mb-3';
				$feat_cls      = 'small mb-1';
				$cta_cls       = 'btn btn-outline-primary fw-bold w-100 mt-auto';
			}
			ob_start(); ?>
<div class="col-md-4">
  <div class="<?php echo $card_cls; ?>">
    <?php echo $popular_badge; ?>
    <p class="<?php echo $eyebrow_cls; ?>"><?php echo $t['name']; ?></p>
    <p class="<?php echo $price_cls; ?>"><?php echo $t['mo_price']; ?></p>
    <p class="<?php echo $price_cls_yr; ?>"><?php echo $t['yr_price']; ?></p>
    <p class="<?php echo $desc_cls; ?>"><?php echo $t['desc']; ?></p>
    <ul class="list-unstyled mb-4">
      <li class="d-flex align-items-center gap-2 <?php echo $feat_cls; ?> mb-1"><i class="bi bi-check-lg text-success" aria-hidden="true"></i><?php echo $t['feat_1']; ?></li>
      <li class="d-flex align-items-center gap-2 <?php echo $feat_cls; ?> mb-1"><i class="bi bi-check-lg text-success" aria-hidden="true"></i><?php echo $t['feat_2']; ?></li>
      <li class="d-flex align-items-center gap-2 <?php echo $feat_cls; ?>"><i class="bi bi-check-lg text-success" aria-hidden="true"></i><?php echo $t['feat_3']; ?></li>
    </ul>
    <a href="#" class="<?php echo $cta_cls; ?>"><?php echo $t['cta']; ?></a>
  </div>
</div>
			<?php $cards_html .= ob_get_clean();
		}

		ob_start(); ?>
<!-- wp:group {"className":"<?php echo $css; ?> py-5","tagName":"section","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5">
<!-- wp:html -->
  <div class="gf-pricing-toggle-inner">
    <h2 class="gf-section-heading mb-2"><?php echo $heading; ?></h2>
    <p class="text-muted mb-4"><?php echo $subtext; ?></p>
    <div class="d-inline-flex align-items-center gap-3 mb-4 bg-light px-4 py-2 rounded-pill">
      <span class="fw-semibold small">Monthly</span>
      <label class="gf-billing-switch position-relative d-inline-block">
        <input type="checkbox" id="gfBillingToggle" class="visually-hidden" />
        <span class="gf-slider position-absolute top-0 start-0 end-0 bottom-0 rounded-pill"></span>
      </label>
      <span class="fw-semibold small">Annual</span>
      <span class="badge gf-accent-bg rounded-pill small"><?php echo $savings_note; ?></span>
    </div>
    <div class="row g-4 text-start">
      <?php echo $cards_html; ?>
    </div>
    <script>
    (function(){
      var tog = document.getElementById('gfBillingToggle');
      if(!tog) return;
      tog.addEventListener('change', function(){
        document.querySelectorAll('.gf-price-monthly').forEach(function(el){ el.classList.toggle('d-none', tog.checked); });
        document.querySelectorAll('.gf-price-annual').forEach(function(el){ el.classList.toggle('d-none', !tog.checked); });
      });
    })();
    </script>
  </div>
<!-- /wp:html -->
</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	/**
	 * Usage-based pricing table — volume tiers with per-unit price.
	 */
	public static function render_usage_based_pricing( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-usage-pricing';

		$heading = esc_html( $copy['section_heading'] ?? 'Usage-Based Pricing' );
		$subtext = esc_html( $copy['subtext']         ?? 'Pay only for what you use. No base fee.' );
		$cta     = esc_html( $copy['cta']             ?? 'Start for Free' );
		$note    = esc_html( $copy['note']            ?? 'Volume discounts apply automatically. Invoiced monthly.' );

		$row_defaults = [
			[ '0 – 10,000 requests',  '$0.001 / req',   ''           ],
			[ '10K – 100K requests',  '$0.0008 / req',  ''           ],
			[ '100K – 1M requests',   '$0.0006 / req',  'Popular'    ],
			[ '1M – 10M requests',    '$0.0004 / req',  ''           ],
			[ '10M+ requests',        'Custom pricing', 'Contact us' ],
		];

		$rows_html = '';
		for ( $i = 1; $i <= 5; $i++ ) {
			$d        = $row_defaults[ $i - 1 ];
			$vol      = esc_html( $copy[ "row_{$i}_volume" ] ?? $d[0] );
			$price    = esc_html( $copy[ "row_{$i}_price"  ] ?? $d[1] );
			$tag      = esc_html( $copy[ "row_{$i}_tag"    ] ?? $d[2] );
			$bg       = ( $i % 2 === 0 ) ? ' style="background:rgba(0,0,0,.025)"' : '';
			$tag_html = $tag ? "<span class=\"badge gf-accent-bg text-white ms-2 small\">{$tag}</span>" : '';
			$rows_html .= "<tr{$bg}><td class=\"small py-3 px-3\">{$vol}{$tag_html}</td><td class=\"small py-3 px-3 fw-bold text-primary text-end\">{$price}</td></tr>\n";
		}

		ob_start(); ?>
<!-- wp:group {"className":"<?php echo $css; ?> py-5","tagName":"section","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5">
<!-- wp:html -->
  <div class="container text-center" style="max-width:640px;">
    <h2 class="gf-section-heading mb-2"><?php echo $heading; ?></h2>
    <p class="text-muted mb-4"><?php echo $subtext; ?></p>
    <div class="border rounded overflow-hidden mb-4">
      <table class="table mb-0 align-middle">
        <thead class="table-dark">
          <tr>
            <th class="text-start small fw-semibold py-3 px-3">Volume</th>
            <th class="text-end small fw-semibold py-3 px-3">Price</th>
          </tr>
        </thead>
        <tbody><?php echo $rows_html; ?></tbody>
      </table>
    </div>
    <p class="small gf-accent-color mb-4"><?php echo $note; ?></p>
    <a href="#" class="btn btn-lg gf-accent-bg text-white border-0 fw-bold px-5"><?php echo $cta; ?></a>
  </div>
<!-- /wp:html -->
</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}
}

// Self-register renderers with PatternBuilder.
GrayFox_TB_PatternBuilder::register_renderers( [
	'comparison-table'    => [ 'GrayFox_TB_Patterns_Pricing', 'render_comparison_table'    ],
	'two-tier-pricing'    => [ 'GrayFox_TB_Patterns_Pricing', 'render_two_tier_pricing'    ],
	'feature-matrix'      => [ 'GrayFox_TB_Patterns_Pricing', 'render_feature_matrix'      ],
	'pricing-toggle'      => [ 'GrayFox_TB_Patterns_Pricing', 'render_pricing_toggle'      ],
	'usage-based-pricing' => [ 'GrayFox_TB_Patterns_Pricing', 'render_usage_based_pricing' ],
] );
