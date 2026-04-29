<?php
/**
 * Dashboard / data visualization pattern renderers.
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
 * Class GrayFox_TB_Patterns_Dashboard
 */
class GrayFox_TB_Patterns_Dashboard {

	// -------------------------------------------------------------------------
	// metrics-grid — 3-column KPI card grid with trend indicators
	// -------------------------------------------------------------------------

	public static function render_metrics_grid( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-metrics-grid';

		$heading = esc_html( $copy['section_heading'] ?? 'Key Metrics at a Glance' );
		$subtext = esc_html( $copy['subtext']         ?? '' );

		$default_metrics = [
			[ '98%',   'Customer satisfaction',  '+2.4%',  'up' ],
			[ '4.2M',  'API calls this month',   '+18%',   'up' ],
			[ '$1.8M', 'Monthly recurring rev.', '+12.3%', 'up' ],
			[ '1,240', 'Active workspaces',      '+94',    'up' ],
			[ '99.9%', 'Uptime SLA',             '—',      'neutral' ],
			[ '14ms',  'Avg. response time',     '-3ms',   'up' ],
		];

		$metrics = [];
		for ( $i = 1; $i <= 6; $i++ ) {
			$number = $copy[ "metric_{$i}_number" ] ?? null;
			$label  = $copy[ "metric_{$i}_label" ]  ?? null;
			if ( $number !== null && $label !== null ) {
				$metrics[] = [
					esc_html( $number ),
					esc_html( $label ),
					esc_html( $copy[ "metric_{$i}_trend" ]     ?? '' ),
					esc_attr( $copy[ "metric_{$i}_direction" ] ?? 'up' ),
				];
			}
		}
		if ( empty( $metrics ) ) {
			$metrics = $default_metrics;
		}

		$trend_icon_class = function( string $dir ): string {
			return [ 'up' => 'bi-arrow-up', 'down' => 'bi-arrow-down', 'neutral' => 'bi-dash' ][ $dir ] ?? 'bi-dash';
		};
		$trend_cls = function( string $dir ): string {
			return [ 'up' => 'gf-metric-trend-up', 'down' => 'gf-metric-trend-down', 'neutral' => 'gf-metric-trend-neutral' ][ $dir ] ?? 'gf-metric-trend-neutral';
		};

		$cards_html = '';
		foreach ( $metrics as $metric ) {
			[ $number, $label, $trend, $direction ] = $metric;
			$icon_class = $trend_icon_class( $direction );
			$cls        = $trend_cls( $direction );
			$trend_html = ( $trend && $trend !== '—' )
				? "<span class=\"gf-metric-trend {$cls}\"><i class=\"bi {$icon_class}\"></i> {$trend}</span>"
				: '';
			ob_start(); ?>

  <div class="col-md-4">
    <div class="gf-metric-card">
      <p class="gf-metric-value"><?php echo $number; ?></p>
      <p class="gf-metric-label"><?php echo $label; ?></p>
      <?php echo $trend_html; ?>
    </div>
  </div>
			<?php $cards_html .= ob_get_clean();
		}

		if ( $subtext ) {
			ob_start(); ?>
<!-- wp:paragraph {"align":"center","textColor":"muted"} -->
<p class="has-text-align-center has-muted-color has-text-color"><?php echo $subtext; ?></p>
<!-- /wp:paragraph -->
			<?php $subtext_block = ob_get_clean();
		} else {
			$subtext_block = '';
		}

		$cols = min( count( $metrics ), 3 );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?> py-5","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5">

<!-- wp:heading {"textAlign":"center"} -->
<h2 class="wp-block-heading has-text-align-center"><?php echo $heading; ?></h2>
<!-- /wp:heading -->
<?php echo $subtext_block; ?>

<!-- wp:html -->
<div class="gf-metrics-grid row g-4 mt-3">
<?php echo $cards_html; ?>
</div>
<!-- /wp:html -->

</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// chart-embed-section — SVG placeholder chart with type badge and caption
	// -------------------------------------------------------------------------

	public static function render_chart_embed_section( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-chart-section';

		$heading     = esc_html( $copy['section_heading'] ?? 'Performance Over Time' );
		$subtext     = esc_html( $copy['subtext']         ?? 'Track your key metrics across any date range.' );
		$caption     = esc_html( $copy['caption']         ?? 'Monthly active users — last 12 months' );
		$chart_type  = esc_attr( $copy['chart_type']      ?? 'line' );
		$chart_id    = esc_attr( $copy['chart_id']        ?? 'gf-chart-main' );
		$chart_label = ucfirst( $chart_type );

		if ( $chart_type === 'donut' ) {
			ob_start(); ?>
<svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" style="width:200px;height:200px;display:block;margin:auto">
  <circle cx="100" cy="100" r="80" fill="none" stroke="var(--gf-primary)" stroke-width="32" stroke-dasharray="314 502" opacity="0.85"/>
  <circle cx="100" cy="100" r="80" fill="none" stroke="var(--gf-accent)" stroke-width="32" stroke-dasharray="125 502" stroke-dashoffset="-314" opacity="0.7"/>
  <circle cx="100" cy="100" r="80" fill="none" stroke="var(--bs-border-color)" stroke-width="32" stroke-dasharray="63 502" stroke-dashoffset="-439" opacity="0.4"/>
  <circle cx="100" cy="100" r="48" fill="var(--bs-body-bg)"/>
  <text x="100" y="108" text-anchor="middle" font-size="14" fill="var(--bs-body-color)">Chart</text>
</svg>
			<?php $placeholder = ob_get_clean();
		} elseif ( $chart_type === 'bar' ) {
			ob_start(); ?>
<svg viewBox="0 0 400 200" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:200px">
  <rect x="20"  y="80"  width="40" height="110" rx="4" fill="var(--gf-primary)" opacity="0.7"/>
  <rect x="80"  y="40"  width="40" height="150" rx="4" fill="var(--gf-primary)" opacity="0.85"/>
  <rect x="140" y="100" width="40" height="90"  rx="4" fill="var(--gf-primary)" opacity="0.7"/>
  <rect x="200" y="20"  width="40" height="170" rx="4" fill="var(--gf-accent)"  opacity="0.85"/>
  <rect x="260" y="60"  width="40" height="130" rx="4" fill="var(--gf-primary)" opacity="0.7"/>
  <rect x="320" y="30"  width="40" height="160" rx="4" fill="var(--gf-accent)"  opacity="0.85"/>
  <line x1="10" y1="195" x2="390" y2="195" stroke="var(--bs-border-color)" stroke-width="1" opacity="0.3"/>
</svg>
			<?php $placeholder = ob_get_clean();
		} else {
			ob_start(); ?>
<svg viewBox="0 0 400 200" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:200px">
  <defs>
    <linearGradient id="areaGrad" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0%" stop-color="var(--gf-primary)" stop-opacity="0.2"/>
      <stop offset="100%" stop-color="var(--gf-primary)" stop-opacity="0"/>
    </linearGradient>
  </defs>
  <polyline points="10,160 60,120 110,140 160,80 210,100 260,50 310,70 360,30 390,45"
    fill="none" stroke="var(--gf-primary)" stroke-width="2.5" stroke-linejoin="round"/>
  <polygon points="10,160 60,120 110,140 160,80 210,100 260,50 310,70 360,30 390,45 390,195 10,195"
    fill="url(#areaGrad)"/>
  <circle cx="260" cy="50" r="5" fill="var(--gf-accent)"/>
  <circle cx="390" cy="45" r="5" fill="var(--gf-primary)"/>
  <line x1="10" y1="195" x2="390" y2="195" stroke="var(--bs-border-color)" stroke-width="1" opacity="0.3"/>
</svg>
			<?php $placeholder = ob_get_clean();
		}

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?>","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5">
<!-- wp:html -->
<div class="container">
  <h2 class="text-center gf-section-heading" style="color:var(--gf-primary)"><?php echo $heading; ?></h2>
  <p class="text-center gf-section-subtext" style="color:var(--gf-muted)"><?php echo $subtext; ?></p>
  <div class="gf-chart-container mt-4" id="<?php echo $chart_id; ?>" data-chart-type="<?php echo $chart_type; ?>">
    <div class="gf-chart-header text-center">
      <span class="badge bg-primary gf-chart-type-badge"><?php echo $chart_label; ?> chart</span>
    </div>
    <div class="gf-chart-canvas">
      <?php echo $placeholder; ?>
    </div>
    <p class="gf-chart-caption text-center"><?php echo $caption; ?></p>
  </div>
</div>
<!-- /wp:html -->
</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// dashboard-preview — app mockup with browser chrome, sidebar, chart & table
	// -------------------------------------------------------------------------

	public static function render_dashboard_preview( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-dashboard-preview';

		$heading     = esc_html( $copy['heading']     ?? 'Your Data, Beautifully Organized' );
		$subtext     = esc_html( $copy['subtext']     ?? 'Everything your team needs in one place — no spreadsheets required.' );
		$cta_primary = esc_html( $copy['cta_primary'] ?? 'See it live' );
		$cta_url     = esc_attr( $copy['cta_url']     ?? '#' );

		$stat_1_n = esc_html( $copy['stat_1_number'] ?? '98%' );
		$stat_1_l = esc_html( $copy['stat_1_label']  ?? 'Uptime' );
		$stat_2_n = esc_html( $copy['stat_2_number'] ?? '4.2M' );
		$stat_2_l = esc_html( $copy['stat_2_label']  ?? 'Events/mo' );
		$stat_3_n = esc_html( $copy['stat_3_number'] ?? '14ms' );
		$stat_3_l = esc_html( $copy['stat_3_label']  ?? 'Response' );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?>","align":"full","backgroundColor":"background"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5 has-background-background-color has-background">

<!-- wp:heading {"textAlign":"center","textColor":"primary"} -->
<h2 class="wp-block-heading has-text-align-center has-primary-color has-text-color"><?php echo $heading; ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","textColor":"muted"} -->
<p class="has-text-align-center has-muted-color has-text-color"><?php echo $subtext; ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"className":"justify-content-center mt-4"} -->
<div class="wp-block-buttons justify-content-center mt-4">
<!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo $cta_url; ?>"><?php echo $cta_primary; ?></a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->

<!-- wp:html -->
<div class="gf-dashboard-mockup mt-4 rounded overflow-hidden shadow-lg">
  <!-- Browser chrome -->
  <div class="gf-dashboard-chrome d-flex align-items-center gap-1 px-3 py-2" style="background:#e5e7eb;">
    <span style="width:12px;height:12px;border-radius:50%;background:#ff5f57;display:inline-block;"></span>
    <span style="width:12px;height:12px;border-radius:50%;background:#febc2e;display:inline-block;"></span>
    <span style="width:12px;height:12px;border-radius:50%;background:#28c840;display:inline-block;"></span>
    <div class="flex-grow-1 bg-white rounded px-3 py-1 small text-muted ms-2">app.yourproduct.com/dashboard</div>
  </div>

  <!-- Dashboard shell -->
  <div class="gf-dashboard-shell d-flex" style="height:400px;background:#f9fafb;">

    <!-- Icon sidebar -->
    <div class="d-flex flex-column align-items-center py-3 gap-3 bg-primary" style="width:56px;">
      <div class="rounded" style="width:28px;height:28px;background:rgba(255,255,255,.3);"></div>
      <div class="rounded-2" style="width:28px;height:4px;background:rgba(255,255,255,.5);"></div>
      <div class="rounded-2" style="width:28px;height:4px;background:rgba(255,255,255,.2);"></div>
      <div class="rounded-2" style="width:28px;height:4px;background:rgba(255,255,255,.2);"></div>
      <div class="rounded-2" style="width:28px;height:4px;background:rgba(255,255,255,.2);"></div>
    </div>

    <!-- Main content -->
    <div class="flex-grow-1 p-3 d-flex flex-column gap-3 overflow-hidden">

      <!-- KPI stats -->
      <div class="d-flex gap-3">
        <div class="flex-fill bg-white rounded p-3 shadow-sm">
          <div class="fw-bold gf-accent-color"><?php echo $stat_1_n; ?></div>
          <div class="small text-muted"><?php echo $stat_1_l; ?></div>
        </div>
        <div class="flex-fill bg-white rounded p-3 shadow-sm">
          <div class="fw-bold gf-accent-color"><?php echo $stat_2_n; ?></div>
          <div class="small text-muted"><?php echo $stat_2_l; ?></div>
        </div>
        <div class="flex-fill bg-white rounded p-3 shadow-sm">
          <div class="fw-bold gf-accent-color"><?php echo $stat_3_n; ?></div>
          <div class="small text-muted"><?php echo $stat_3_l; ?></div>
        </div>
      </div>

      <!-- Chart -->
      <div class="bg-white rounded p-3 flex-grow-1 shadow-sm">
        <svg viewBox="0 0 500 120" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:120px;">
          <polyline points="0,100 60,75 120,85 180,45 240,62 300,25 360,42 420,16 500,28"
            fill="none" stroke="var(--gf-accent)" stroke-width="2" stroke-linejoin="round" opacity="0.8"/>
          <polygon points="0,100 60,75 120,85 180,45 240,62 300,25 360,42 420,16 500,28 500,120 0,120"
            fill="var(--gf-accent)" opacity="0.07"/>
        </svg>
      </div>

      <!-- Table rows -->
      <div class="bg-white rounded overflow-hidden shadow-sm">
        <div style="height:28px;background:var(--gf-primary);opacity:.08;"></div>
        <div style="height:24px;border-bottom:1px solid #f3f4f6;"></div>
        <div style="height:24px;background:#f9fafb;border-bottom:1px solid #f3f4f6;"></div>
        <div style="height:24px;"></div>
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
	// analytics-split — 40/60 split: metric list left, area chart right
	// -------------------------------------------------------------------------

	public static function render_analytics_split( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-analytics-split';

		$heading = esc_html( $copy['section_heading'] ?? 'Real-time Insights for Every Team' );
		$subtext = esc_html( $copy['subtext']         ?? 'From high-level KPIs to granular event data — all in one view.' );

		$m1_n = esc_html( $copy['metric_1_number'] ?? '127%' );
		$m1_l = esc_html( $copy['metric_1_label']  ?? 'ROI improvement' );
		$m1_t = esc_html( $copy['metric_1_trend']  ?? '↑ vs. last quarter' );
		$m2_n = esc_html( $copy['metric_2_number'] ?? '3.4×' );
		$m2_l = esc_html( $copy['metric_2_label']  ?? 'Faster reporting' );
		$m2_t = esc_html( $copy['metric_2_trend']  ?? '↑ vs. manual process' );
		$m3_n = esc_html( $copy['metric_3_number'] ?? '62%' );
		$m3_l = esc_html( $copy['metric_3_label']  ?? 'Reduction in data errors' );
		$m3_t = esc_html( $copy['metric_3_trend']  ?? '↓ since onboarding' );
		$m4_n = esc_html( $copy['metric_4_number'] ?? '$48K' );
		$m4_l = esc_html( $copy['metric_4_label']  ?? 'Avg. cost saved / year' );
		$m4_t = esc_html( $copy['metric_4_trend']  ?? '↑ per team' );

		$chart_caption = esc_html( $copy['chart_caption'] ?? 'Weekly active users — rolling 8 weeks' );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?>","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5">

<!-- wp:heading {"textAlign":"center"} -->
<h2 class="wp-block-heading has-text-align-center"><?php echo $heading; ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","textColor":"muted"} -->
<p class="has-text-align-center has-muted-color has-text-color"><?php echo $subtext; ?></p>
<!-- /wp:paragraph -->

<!-- wp:columns -->
<div class="wp-block-columns">

<!-- wp:column {"width":"40%"} -->
<div class="wp-block-column" style="flex-basis:40%">
<!-- wp:html -->
<div class="gf-analytics-metric-list d-flex flex-column gap-4 h-100 justify-content-around align-items-end text-end">

  <div class="gf-analytics-metric">
    <div class="fs-3 fw-bold gf-accent-color"><?php echo $m1_n; ?></div>
    <div class="fw-semibold"><?php echo $m1_l; ?></div>
    <div class="small text-muted"><?php echo $m1_t; ?></div>
  </div>

  <div class="gf-analytics-metric">
    <div class="fs-3 fw-bold gf-accent-color"><?php echo $m2_n; ?></div>
    <div class="fw-semibold"><?php echo $m2_l; ?></div>
    <div class="small text-muted"><?php echo $m2_t; ?></div>
  </div>

  <div class="gf-analytics-metric">
    <div class="fs-3 fw-bold gf-accent-color"><?php echo $m3_n; ?></div>
    <div class="fw-semibold"><?php echo $m3_l; ?></div>
    <div class="small text-muted"><?php echo $m3_t; ?></div>
  </div>

  <div class="gf-analytics-metric">
    <div class="fs-3 fw-bold gf-accent-color"><?php echo $m4_n; ?></div>
    <div class="fw-semibold"><?php echo $m4_l; ?></div>
    <div class="small text-muted"><?php echo $m4_t; ?></div>
  </div>

</div>
<!-- /wp:html -->
</div>
<!-- /wp:column -->

<!-- wp:column {"width":"60%"} -->
<div class="wp-block-column" style="flex-basis:60%">
<!-- wp:html -->
<div class="gf-analytics-chart-card card p-4 shadow-sm h-100 d-flex flex-column">
  <div class="small text-muted mb-3"><?php echo $chart_caption; ?></div>
  <svg viewBox="0 0 480 220" xmlns="http://www.w3.org/2000/svg" style="width:100%;display:block;" class="flex-grow-1">
    <defs>
      <linearGradient id="analyticsGrad" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="var(--gf-accent)" stop-opacity="0.25"/>
        <stop offset="100%" stop-color="var(--gf-accent)" stop-opacity="0"/>
      </linearGradient>
    </defs>
    <line x1="0" y1="55"  x2="480" y2="55"  stroke="var(--gf-primary)" stroke-width="0.5" opacity="0.1"/>
    <line x1="0" y1="110" x2="480" y2="110" stroke="var(--gf-primary)" stroke-width="0.5" opacity="0.1"/>
    <line x1="0" y1="165" x2="480" y2="165" stroke="var(--gf-primary)" stroke-width="0.5" opacity="0.1"/>
    <polygon points="30,160 90,130 150,145 210,90 270,110 330,60 390,80 450,40 450,210 30,210"
      fill="url(#analyticsGrad)"/>
    <polyline points="30,160 90,130 150,145 210,90 270,110 330,60 390,80 450,40"
      fill="none" stroke="var(--gf-accent)" stroke-width="2.5" stroke-linejoin="round"/>
    <circle cx="330" cy="60" r="5" fill="var(--gf-accent)"/>
    <circle cx="450" cy="40" r="5" fill="var(--gf-accent)"/>
    <line x1="20" y1="210" x2="460" y2="210" stroke="var(--gf-primary)" stroke-width="0.5" opacity="0.2"/>
  </svg>
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
}

GrayFox_TB_PatternBuilder::register_renderers( [
	'metrics-grid'        => [ GrayFox_TB_Patterns_Dashboard::class, 'render_metrics_grid' ],
	'chart-embed-section' => [ GrayFox_TB_Patterns_Dashboard::class, 'render_chart_embed_section' ],
	'dashboard-preview'   => [ GrayFox_TB_Patterns_Dashboard::class, 'render_dashboard_preview' ],
	'analytics-split'     => [ GrayFox_TB_Patterns_Dashboard::class, 'render_analytics_split' ],
] );
