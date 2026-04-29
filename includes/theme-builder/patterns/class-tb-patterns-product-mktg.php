<?php
/**
 * Product / project marketing pattern renderers.
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
 * Class GrayFox_TB_Patterns_ProductMktg
 */
class GrayFox_TB_Patterns_ProductMktg {

	// -------------------------------------------------------------------------
	// product-roadmap — 3-column kanban-style roadmap (Planned / In Progress / Shipped)
	// -------------------------------------------------------------------------

	public static function render_product_roadmap( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-section-tint';

		$heading        = esc_html( $copy['section_heading'] ?? 'Product Roadmap' );
		$subtext        = esc_html( $copy['subtext']         ?? "Here's what we're working on and what's coming next." );
		$planned_title  = esc_html( $copy['planned_title']   ?? 'Planned' );
		$progress_title = esc_html( $copy['progress_title']  ?? 'In Progress' );
		$shipped_title  = esc_html( $copy['shipped_title']   ?? 'Shipped' );

		$planned_items = [];
		for ( $i = 1; $i <= 4; $i++ ) {
			if ( ! empty( $copy[ "planned_{$i}" ] ) ) {
				$planned_items[] = esc_html( $copy[ "planned_{$i}" ] );
			}
		}
		if ( empty( $planned_items ) ) {
			$planned_items = [ 'Advanced reporting', 'Mobile app', 'API v2', 'Custom integrations' ];
		}

		$progress_items = [];
		for ( $i = 1; $i <= 4; $i++ ) {
			if ( ! empty( $copy[ "progress_{$i}" ] ) ) {
				$progress_items[] = esc_html( $copy[ "progress_{$i}" ] );
			}
		}
		if ( empty( $progress_items ) ) {
			$progress_items = [ 'Dark mode support', 'Team collaboration', 'Zapier integration' ];
		}

		$shipped_items = [];
		for ( $i = 1; $i <= 4; $i++ ) {
			if ( ! empty( $copy[ "shipped_{$i}" ] ) ) {
				$shipped_items[] = esc_html( $copy[ "shipped_{$i}" ] );
			}
		}
		if ( empty( $shipped_items ) ) {
			$shipped_items = [ 'Custom dashboards', 'SSO / SAML', 'Audit logs', 'Bulk export' ];
		}

		$build_rows = function( array $items, string $icon, string $cls ): string {
			$rows = '';
			foreach ( $items as $item ) {
				ob_start(); ?>
<li class="gf-roadmap-item">
<span class="gf-roadmap-icon <?php echo $cls; ?>"><?php echo $icon; ?></span>
<span><?php echo $item; ?></span>
</li>
				<?php $rows .= ob_get_clean();
			}
			return $rows;
		};

		$planned_rows  = $build_rows( $planned_items,  '<i class="bi bi-circle"></i>',           'gf-roadmap-planned' );
		$progress_rows = $build_rows( $progress_items, '<i class="bi bi-circle-half"></i>',       'gf-roadmap-progress' );
		$shipped_rows  = $build_rows( $shipped_items,  '<i class="bi bi-check-circle-fill"></i>', 'gf-roadmap-shipped' );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?> gf-roadmap-section py-5","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> gf-roadmap-section py-5">

<!-- wp:heading {"textAlign":"center","className":"gf-section-heading"} -->
<h2 class="wp-block-heading has-text-align-center gf-section-heading"><?php echo $heading; ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","textColor":"muted","className":"gf-section-subtext"} -->
<p class="has-text-align-center has-muted-color has-text-color gf-section-subtext"><?php echo $subtext; ?></p>
<!-- /wp:paragraph -->

<!-- wp:columns {"isStackedOnMobile":true,"className":"mt-4"} -->
<div class="wp-block-columns is-stacked-on-mobile mt-4">

<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:heading {"level":3,"textColor":"muted"} --><h3 class="wp-block-heading has-muted-color has-text-color"><?php echo $planned_title; ?></h3><!-- /wp:heading -->
<!-- wp:html -->
<ul class="gf-roadmap-list">
<?php echo $planned_rows; ?>
</ul>
<!-- /wp:html -->
</div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:heading {"level":3,"textColor":"primary"} --><h3 class="wp-block-heading has-primary-color has-text-color"><?php echo $progress_title; ?></h3><!-- /wp:heading -->
<!-- wp:html -->
<ul class="gf-roadmap-list">
<?php echo $progress_rows; ?>
</ul>
<!-- /wp:html -->
</div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:heading {"level":3,"textColor":"muted"} --><h3 class="wp-block-heading has-muted-color has-text-color"><?php echo $shipped_title; ?></h3><!-- /wp:heading -->
<!-- wp:html -->
<ul class="gf-roadmap-list">
<?php echo $shipped_rows; ?>
</ul>
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
	// developer-api-teaser — dark primary bg, copy left + code window right
	// -------------------------------------------------------------------------

	public static function render_developer_api_teaser( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-api-teaser';

		$heading     = esc_html( $copy['heading']     ?? 'Built for developers' );
		$subtext     = esc_html( $copy['subtext']     ?? 'A clean, well-documented API with SDKs for every major language. Ship faster with less boilerplate.' );
		$cta_primary = esc_html( $copy['cta_primary'] ?? 'View API Docs' );
		$cta_url     = esc_attr( $copy['cta_url']     ?? '#' );

		$lang_1  = esc_html( $copy['language_1'] ?? 'JavaScript' );
		$lang_2  = esc_html( $copy['language_2'] ?? 'Python' );
		$lang_3  = esc_html( $copy['language_3'] ?? 'cURL' );

		$feat_1  = esc_html( $copy['feature_1'] ?? 'RESTful API with OpenAPI spec' );
		$feat_2  = esc_html( $copy['feature_2'] ?? 'SDKs for JS, Python, Ruby, Go' );
		$feat_3  = esc_html( $copy['feature_3'] ?? 'Webhooks with retry logic' );
		$feat_4  = esc_html( $copy['feature_4'] ?? 'Sandbox environment included' );

		$default_code = "// Initialize the client\nimport { NexusClient } from '@nexus/sdk';\n\nconst client = new NexusClient({\n  apiKey: process.env.NEXUS_API_KEY,\n});\n\n// Fetch your data\nconst results = await client.query({\n  resource: 'feedback',\n  filter:   { status: 'open' },\n  limit:    50,\n});\n\nconsole.log(results.data);";
		$code_block   = htmlspecialchars( $copy['code_block'] ?? $default_code );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?> py-5","backgroundColor":"primary","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5 has-primary-background-color has-background">

<!-- wp:columns {"verticalAlignment":"center","className":"container-xl"} -->
<div class="wp-block-columns are-vertically-aligned-center container-xl">

<!-- wp:column {"width":"45%"} -->
<div class="wp-block-column" style="flex-basis:45%">

<!-- wp:heading {"level":2,"className":"text-white"} -->
<h2 class="wp-block-heading text-white"><?php echo $heading; ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"text-white opacity-75"} -->
<p class="text-white opacity-75"><?php echo $subtext; ?></p>
<!-- /wp:paragraph -->

<!-- wp:html -->
<ul style="list-style:none;padding:0;margin:1.5rem 0;display:flex;flex-direction:column;gap:0.6rem">
<li style="color:#fff;display:flex;gap:0.5rem;align-items:flex-start"><i class="bi bi-check-circle-fill" style="color:var(--wp--preset--color--accent);flex-shrink:0;margin-top:0.15rem"></i> <?php echo $feat_1; ?></li>
<li style="color:#fff;display:flex;gap:0.5rem;align-items:flex-start"><i class="bi bi-check-circle-fill" style="color:var(--wp--preset--color--accent);flex-shrink:0;margin-top:0.15rem"></i> <?php echo $feat_2; ?></li>
<li style="color:#fff;display:flex;gap:0.5rem;align-items:flex-start"><i class="bi bi-check-circle-fill" style="color:var(--wp--preset--color--accent);flex-shrink:0;margin-top:0.15rem"></i> <?php echo $feat_3; ?></li>
<li style="color:#fff;display:flex;gap:0.5rem;align-items:flex-start"><i class="bi bi-check-circle-fill" style="color:var(--wp--preset--color--accent);flex-shrink:0;margin-top:0.15rem"></i> <?php echo $feat_4; ?></li>
</ul>
<!-- /wp:html -->

<!-- wp:buttons -->
<div class="wp-block-buttons">
<!-- wp:button {"backgroundColor":"contrast","textColor":"primary"} -->
<div class="wp-block-button"><a class="wp-block-button__link has-primary-color has-contrast-background-color has-text-color has-background wp-element-button" href="<?php echo $cta_url; ?>"><?php echo $cta_primary; ?></a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->

</div>
<!-- /wp:column -->

<!-- wp:column {"width":"55%"} -->
<div class="wp-block-column" style="flex-basis:55%">
<!-- wp:html -->
<div class="gf-code-window" style="background:#0d1117;border-radius:10px;overflow:hidden;font-family:monospace">
  <div style="background:#161b22;padding:0.6rem 1rem;display:flex;align-items:center;gap:0.4rem">
    <span style="width:12px;height:12px;border-radius:50%;background:#ff5f57;display:inline-block"></span>
    <span style="width:12px;height:12px;border-radius:50%;background:#febc2e;display:inline-block"></span>
    <span style="width:12px;height:12px;border-radius:50%;background:#28c840;display:inline-block"></span>
    <div style="margin-left:0.75rem;display:flex;gap:0.25rem">
      <span style="padding:0.2rem 0.75rem;border-radius:4px 4px 0 0;background:#0d1117;color:#e6edf3;font-size:0.78rem"><?php echo $lang_1; ?></span>
      <span style="padding:0.2rem 0.75rem;font-size:0.78rem;color:#8b949e"><?php echo $lang_2; ?></span>
      <span style="padding:0.2rem 0.75rem;font-size:0.78rem;color:#8b949e"><?php echo $lang_3; ?></span>
    </div>
  </div>
  <pre style="margin:0;padding:1.25rem;color:#e6edf3;font-size:0.82rem;line-height:1.7;overflow-x:auto"><code><?php echo $code_block; ?></code></pre>
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
	// pricing-faq-hybrid — 3 price cards + accordion FAQ below
	// -------------------------------------------------------------------------

	public static function render_pricing_faq_hybrid( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-pricing-faq';

		$heading = esc_html( $copy['section_heading'] ?? 'Simple, Transparent Pricing' );
		$subtext = esc_html( $copy['subtext']         ?? 'No hidden fees. Cancel any time.' );

		$t1_name  = esc_html( $copy['tier_1_name']  ?? 'Starter' );
		$t1_price = esc_html( $copy['tier_1_price'] ?? '$0' );
		$t1_desc  = esc_html( $copy['tier_1_desc']  ?? 'Perfect for individuals and small projects.' );
		$t1_cta   = esc_html( $copy['tier_1_cta']   ?? 'Get started free' );

		$t2_name  = esc_html( $copy['tier_2_name']  ?? 'Pro' );
		$t2_price = esc_html( $copy['tier_2_price'] ?? '$49/mo' );
		$t2_desc  = esc_html( $copy['tier_2_desc']  ?? 'Everything you need to grow your business.' );
		$t2_cta   = esc_html( $copy['tier_2_cta']   ?? 'Start free trial' );

		$t3_name  = esc_html( $copy['tier_3_name']  ?? 'Scale' );
		$t3_price = esc_html( $copy['tier_3_price'] ?? '$149/mo' );
		$t3_desc  = esc_html( $copy['tier_3_desc']  ?? 'Advanced features for larger teams.' );
		$t3_cta   = esc_html( $copy['tier_3_cta']   ?? 'Contact sales' );

		$faq_heading = esc_html( $copy['faq_heading'] ?? 'Pricing questions' );

		$faqs = [];
		for ( $i = 1; $i <= 6; $i++ ) {
			$q = $copy[ "q_{$i}" ] ?? null;
			$a = $copy[ "a_{$i}" ] ?? null;
			if ( $q && $a ) {
				$faqs[] = [ esc_html( $q ), esc_html( $a ) ];
			}
		}
		if ( empty( $faqs ) ) {
			$faqs = [
				[ 'Can I change plans at any time?', 'Yes — upgrades take effect immediately. Downgrades apply at the next billing cycle.' ],
				[ 'Is there a free trial?', 'Pro and Scale plans include a 14-day free trial. No credit card required.' ],
				[ 'What payment methods do you accept?', 'We accept all major credit cards, PayPal, and ACH bank transfers for annual plans.' ],
				[ 'Do you offer discounts for nonprofits?', 'Yes — contact us for nonprofit and educational pricing.' ],
			];
		}

		$accordion = '';
		foreach ( $faqs as $idx => $faq ) {
			[ $q, $a ] = $faq;
			$open_attr = ( $idx === 0 ) ? ' open' : '';
			ob_start(); ?>
  <details class="gf-faq-item"<?php echo $open_attr; ?>>
    <summary><?php echo $q; ?></summary>
    <p><?php echo $a; ?></p>
  </details>
			<?php $accordion .= ob_get_clean();
		}

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?> py-5","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5">

<!-- wp:heading {"textAlign":"center","className":"gf-section-heading"} -->
<h2 class="wp-block-heading has-text-align-center gf-section-heading"><?php echo $heading; ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","textColor":"muted","className":"gf-section-subtext"} -->
<p class="has-text-align-center has-muted-color has-text-color gf-section-subtext"><?php echo $subtext; ?></p>
<!-- /wp:paragraph -->

<!-- wp:columns {"isStackedOnMobile":true} -->
<div class="wp-block-columns is-stacked-on-mobile mt-4">

<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:group {"className":"gf-pricing-card"} -->
<div class="wp-block-group gf-pricing-card">
<!-- wp:heading {"level":3} --><h3 class="wp-block-heading"><?php echo $t1_name; ?></h3><!-- /wp:heading -->
<!-- wp:paragraph {"className":"gf-price-amount"} --><p class="gf-price-amount"><?php echo $t1_price; ?></p><!-- /wp:paragraph -->
<!-- wp:paragraph {"textColor":"muted"} --><p class="has-muted-color has-text-color"><?php echo $t1_desc; ?></p><!-- /wp:paragraph -->
<!-- wp:buttons {"className":"justify-content-center"} --><div class="wp-block-buttons justify-content-center"><!-- wp:button {"className":"is-style-outline","width":100} --><div class="wp-block-button is-style-outline has-custom-width wp-block-button__width-100"><a class="wp-block-button__link wp-element-button"><?php echo $t1_cta; ?></a></div><!-- /wp:button --></div><!-- /wp:buttons -->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:group {"className":"gf-pricing-card gf-pricing-card--featured","backgroundColor":"primary"} -->
<div class="wp-block-group gf-pricing-card gf-pricing-card--featured has-primary-background-color has-background">
<!-- wp:heading {"level":3,"textColor":"contrast"} --><h3 class="wp-block-heading has-contrast-color has-text-color"><?php echo $t2_name; ?></h3><!-- /wp:heading -->
<!-- wp:paragraph {"className":"gf-price-amount","textColor":"contrast"} --><p class="gf-price-amount has-contrast-color has-text-color"><?php echo $t2_price; ?></p><!-- /wp:paragraph -->
<!-- wp:paragraph {"textColor":"contrast"} --><p class="has-contrast-color has-text-color"><?php echo $t2_desc; ?></p><!-- /wp:paragraph -->
<!-- wp:buttons {"className":"justify-content-center"} --><div class="wp-block-buttons justify-content-center"><!-- wp:button {"backgroundColor":"contrast","textColor":"primary","width":100} --><div class="wp-block-button has-custom-width wp-block-button__width-100"><a class="wp-block-button__link has-primary-color has-contrast-background-color has-text-color has-background wp-element-button"><?php echo $t2_cta; ?></a></div><!-- /wp:button --></div><!-- /wp:buttons -->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:group {"className":"gf-pricing-card"} -->
<div class="wp-block-group gf-pricing-card">
<!-- wp:heading {"level":3} --><h3 class="wp-block-heading"><?php echo $t3_name; ?></h3><!-- /wp:heading -->
<!-- wp:paragraph {"className":"gf-price-amount"} --><p class="gf-price-amount"><?php echo $t3_price; ?></p><!-- /wp:paragraph -->
<!-- wp:paragraph {"textColor":"muted"} --><p class="has-muted-color has-text-color"><?php echo $t3_desc; ?></p><!-- /wp:paragraph -->
<!-- wp:buttons {"className":"justify-content-center"} --><div class="wp-block-buttons justify-content-center"><!-- wp:button {"className":"is-style-outline","width":100} --><div class="wp-block-button is-style-outline has-custom-width wp-block-button__width-100"><a class="wp-block-button__link wp-element-button"><?php echo $t3_cta; ?></a></div><!-- /wp:button --></div><!-- /wp:buttons -->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:column -->

</div>
<!-- /wp:columns -->

<!-- wp:separator -->
<hr class="wp-block-separator has-alpha-channel-opacity"/>
<!-- /wp:separator -->

<!-- wp:heading {"textAlign":"center","level":3,"className":"gf-section-heading"} -->
<h3 class="wp-block-heading has-text-align-center gf-section-heading"><?php echo $faq_heading; ?></h3>
<!-- /wp:heading -->

<!-- wp:html -->
<div class="gf-faq-accordion">
<?php echo $accordion; ?>
</div>
<!-- /wp:html -->

</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// product-screenshot-carousel — tab-driven screenshot carousel with dots
	// -------------------------------------------------------------------------

	public static function render_product_screenshot_carousel( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-screenshot-carousel';

		$heading = esc_html( $copy['section_heading'] ?? 'See It in Action' );
		$subtext = esc_html( $copy['subtext']         ?? 'A closer look at everything inside the product.' );

		$slides = [];
		for ( $i = 1; $i <= 5; $i++ ) {
			$caption = $copy[ "caption_{$i}" ] ?? null;
			$label   = $copy[ "tab_{$i}" ]     ?? null;
			if ( $caption !== null || $label !== null ) {
				$slides[] = [
					'label'   => esc_html( $label   ?? "Screen {$i}" ),
					'caption' => esc_html( $caption ?? "Product screenshot {$i} — describe what the user sees here." ),
				];
			}
		}
		if ( empty( $slides ) ) {
			$slides = [
				[ 'label' => 'Dashboard', 'caption' => "Get a bird's-eye view of all your key metrics in one place." ],
				[ 'label' => 'Analytics', 'caption' => 'Drill into trends with powerful filtering and date range controls.' ],
				[ 'label' => 'Settings',  'caption' => 'Configure your workspace to fit how your team works.' ],
			];
		}

		$tab_buttons = '';
		$slide_items = '';
		$dot_buttons = '';

		foreach ( $slides as $idx => $slide ) {
			$active_tab    = ( $idx === 0 ) ? ' gf-carousel-tab--active' : '';
			$active_slide  = ( $idx === 0 ) ? ' gf-carousel-slide--active' : '';
			$active_dot    = ( $idx === 0 ) ? ' gf-carousel-dot--active' : '';
			$dot_num       = $idx + 1;

			$tab_buttons .= "<button class=\"gf-carousel-tab{$active_tab}\" data-slide=\"{$idx}\">{$slide['label']}</button>\n";

			ob_start(); ?>
<div class="gf-carousel-slide<?php echo $active_slide; ?>" data-slide="<?php echo $idx; ?>">
  <div class="gf-screenshot-frame">
    <img src="" alt="<?php echo $slide['label']; ?> screenshot" class="gf-screenshot-slide"/>
  </div>
  <p class="gf-carousel-caption"><?php echo $slide['caption']; ?></p>
</div>

			<?php $slide_items .= ob_get_clean();

			$dot_buttons .= "<button class=\"gf-carousel-dot{$active_dot}\" data-slide=\"{$idx}\" aria-label=\"Slide {$dot_num}\"></button>\n";
		}

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?> py-5","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5">

<!-- wp:heading {"textAlign":"center","className":"gf-section-heading"} -->
<h2 class="wp-block-heading has-text-align-center gf-section-heading"><?php echo $heading; ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center","textColor":"muted","className":"gf-section-subtext"} -->
<p class="has-text-align-center has-muted-color has-text-color gf-section-subtext"><?php echo $subtext; ?></p>
<!-- /wp:paragraph -->

<!-- wp:html -->
<div class="gf-carousel-wrapper">

  <div class="gf-carousel-tabs" role="tablist">
    <?php echo $tab_buttons; ?>
  </div>

  <div class="gf-carousel-track">
    <?php echo $slide_items; ?>
  </div>

  <div class="gf-carousel-controls">
    <button class="gf-carousel-prev" aria-label="Previous slide" onclick="gfCarouselPrev(this)"><i class="bi bi-chevron-left"></i></button>
    <div class="gf-carousel-dots">
      <?php echo $dot_buttons; ?>
    </div>
    <button class="gf-carousel-next" aria-label="Next slide" onclick="gfCarouselNext(this)"><i class="bi bi-chevron-right"></i></button>
  </div>

</div>
<!-- /wp:html -->

</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}
}

GrayFox_TB_PatternBuilder::register_renderers( [
	'product-roadmap'              => [ GrayFox_TB_Patterns_ProductMktg::class, 'render_product_roadmap' ],
	'developer-api-teaser'         => [ GrayFox_TB_Patterns_ProductMktg::class, 'render_developer_api_teaser' ],
	'pricing-faq-hybrid'           => [ GrayFox_TB_Patterns_ProductMktg::class, 'render_pricing_faq_hybrid' ],
	'product-screenshot-carousel'  => [ GrayFox_TB_Patterns_ProductMktg::class, 'render_product_screenshot_carousel' ],
] );
