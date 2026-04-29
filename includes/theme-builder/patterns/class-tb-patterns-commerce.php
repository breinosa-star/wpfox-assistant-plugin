<?php
/**
 * Commerce / jobs patterns — product-grid, job-list-cards, app-store-download.
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
 * Class GrayFox_TB_Patterns_Commerce
 */
class GrayFox_TB_Patterns_Commerce {

	// -------------------------------------------------------------------------
	// Renderers
	// -------------------------------------------------------------------------

	/**
	 * Product grid — cards with image, name, price, add-to-cart button.
	 */
	public static function render_product_grid( array $spec, array $theme ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];

		$wrap_class = esc_attr( implode( ' ', $classes ) ?: '' );
		$heading    = esc_html( $copy['section_heading'] ?? 'Featured Products' );
		$subtext    = esc_html( $copy['subtext']         ?? 'Handpicked just for you.' );
		$cta_text   = esc_html( $copy['cta_text']        ?? 'Shop all products' );

		$defaults = [
			[ 'name' => 'Essential Plan',   'price' => '$29',  'original' => '',     'badge' => ''     ],
			[ 'name' => 'Professional Kit', 'price' => '$79',  'original' => '$99',  'badge' => 'Sale' ],
			[ 'name' => 'Enterprise Suite', 'price' => '$149', 'original' => '',     'badge' => ''     ],
			[ 'name' => 'Starter Bundle',   'price' => '$49',  'original' => '',     'badge' => 'New'  ],
		];

		$cards = '';
		for ( $i = 1; $i <= 4; $i++ ) {
			$d        = $defaults[ $i - 1 ];
			$name     = esc_html( $copy[ "product_{$i}_name"     ] ?? $d['name']     );
			$price    = esc_html( $copy[ "product_{$i}_price"    ] ?? $d['price']    );
			$original = esc_html( $copy[ "product_{$i}_original" ] ?? $d['original'] );
			$badge    = esc_html( $copy[ "product_{$i}_badge"    ] ?? $d['badge']    );
			$cta_btn  = esc_html( $copy[ "product_{$i}_cta"      ] ?? 'Add to cart'  );

			if ( $badge ) {
				ob_start(); ?>
        <!-- wp:paragraph {"className":"gf-product-badge"} -->
        <p class="gf-product-badge"><?php echo $badge; ?></p>
        <!-- /wp:paragraph -->
				<?php $badge_block = ob_get_clean();
			} else {
				$badge_block = '';
			}

			if ( $original ) {
				ob_start(); ?>
          <s class="gf-product-price-original"><?php echo $original; ?></s>
				<?php $original_block = ob_get_clean();
			} else {
				$original_block = '';
			}

			ob_start(); ?>

    <!-- wp:column -->
    <div class="wp-block-column">
      <!-- wp:group {"className":"gf-product-card"} -->
      <div class="wp-block-group gf-product-card">
<?php echo $badge_block; ?>
        <!-- wp:image {"className":"gf-product-img"} -->
        <figure class="wp-block-image gf-product-img"><img alt="<?php echo $name; ?>" /></figure>
        <!-- /wp:image -->
        <!-- wp:group {"className":"gf-product-card-body"} -->
        <div class="wp-block-group gf-product-card-body">
          <!-- wp:heading {"level":4,"className":"gf-card-title"} -->
          <h4 class="wp-block-heading gf-card-title"><?php echo $name; ?></h4>
          <!-- /wp:heading -->
          <!-- wp:paragraph {"className":"gf-product-price"} -->
          <p class="gf-product-price"><?php echo $price; ?><?php echo $original_block; ?></p>
          <!-- /wp:paragraph -->
          <!-- wp:buttons -->
          <div class="wp-block-buttons">
            <!-- wp:button {"backgroundColor":"primary","textColor":"contrast","width":100} -->
            <div class="wp-block-button has-custom-width wp-block-button__width-100"><a class="wp-block-button__link has-contrast-color has-primary-background-color has-text-color has-background wp-element-button"><?php echo $cta_btn; ?></a></div>
            <!-- /wp:button -->
          </div>
          <!-- /wp:buttons -->
        </div>
        <!-- /wp:group -->
      </div>
      <!-- /wp:group -->
    </div>
    <!-- /wp:column -->
			<?php $cards .= ob_get_clean();
		}

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $wrap_class; ?> py-5","align":"full"} -->
<section class="wp-block-group <?php echo $wrap_class; ?> py-5 alignfull">
  <!-- wp:heading {"textAlign":"center","level":2,"className":"gf-section-heading"} -->
  <h2 class="wp-block-heading has-text-align-center gf-section-heading"><?php echo $heading; ?></h2>
  <!-- /wp:heading -->
  <!-- wp:paragraph {"align":"center","textColor":"muted","className":"gf-section-subtext"} -->
  <p class="has-text-align-center has-muted-color has-text-color gf-section-subtext"><?php echo $subtext; ?></p>
  <!-- /wp:paragraph -->
  <!-- wp:columns {"isStackedOnMobile":true} -->
  <div class="wp-block-columns is-stacked-on-mobile">
<?php echo $cards; ?>
  </div>
  <!-- /wp:columns -->
  <!-- wp:buttons {"className":"justify-content-center mt-4"} -->
  <div class="wp-block-buttons justify-content-center mt-4">
    <!-- wp:button {"className":"is-style-outline"} -->
    <div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button"><?php echo $cta_text; ?></a></div>
    <!-- /wp:button -->
  </div>
  <!-- /wp:buttons -->
</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	/**
	 * Job listing rows — role, type badge, location, apply button.
	 */
	public static function render_job_list_cards( array $spec, array $theme ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];

		$css      = implode( ' ', $classes ) ?: 'gf-job-list-cards';
		$heading  = esc_html( $copy['section_heading'] ?? 'Open Positions' );
		$subtext  = esc_html( $copy['subtext']         ?? 'Join our team and help us build the future.' );
		$cta_text = esc_html( $copy['cta_text']        ?? 'See all openings' );

		$type_classes = [
			'Full-time' => 'gf-job-badge gf-job-badge-full',
			'Part-time' => 'gf-job-badge gf-job-badge-part',
			'Remote'    => 'gf-job-badge gf-job-badge-remote',
			'Contract'  => 'gf-job-badge gf-job-badge-part',
		];

		$defaults = [
			[ 'title' => 'Senior Frontend Engineer',   'dept' => 'Engineering', 'location' => 'Remote',          'type' => 'Full-time' ],
			[ 'title' => 'Product Designer',            'dept' => 'Design',      'location' => 'San Francisco',   'type' => 'Full-time' ],
			[ 'title' => 'Growth Marketing Manager',    'dept' => 'Marketing',   'location' => 'New York / Remote','type' => 'Full-time' ],
			[ 'title' => 'Customer Success Manager',    'dept' => 'Success',     'location' => 'Remote',          'type' => 'Remote'    ],
		];

		$rows = '';
		for ( $i = 1; $i <= 4; $i++ ) {
			$d        = $defaults[ $i - 1 ];
			$title    = esc_html( $copy[ "job_{$i}_title"    ] ?? $d['title']    );
			$dept     = esc_html( $copy[ "job_{$i}_dept"     ] ?? $d['dept']     );
			$location = esc_html( $copy[ "job_{$i}_location" ] ?? $d['location'] );
			$type     = esc_html( $copy[ "job_{$i}_type"     ] ?? $d['type']     );
			$badge_class = esc_attr( $type_classes[ $type ] ?? 'gf-job-badge gf-job-badge-full' );

			ob_start(); ?>

  <!-- wp:group {"className":"gf-job-card"} -->
  <div class="wp-block-group gf-job-card">
    <!-- wp:group {"className":"flex-grow-1"} -->
    <div class="wp-block-group flex-grow-1">
      <!-- wp:paragraph {"className":"<?php echo $badge_class; ?>","style":{"typography":{"fontSize":".7rem","fontWeight":"700","textTransform":"uppercase","letterSpacing":".06em"}}} -->
      <p class="<?php echo $badge_class; ?>"><?php echo $type; ?></p>
      <!-- /wp:paragraph -->
      <!-- wp:heading {"level":4,"style":{"typography":{"fontSize":"1.05rem","fontWeight":"700"}}} -->
      <h4 class="wp-block-heading"><?php echo $title; ?></h4>
      <!-- /wp:heading -->
      <!-- wp:paragraph {"textColor":"muted","style":{"typography":{"fontSize":".85rem"}}} -->
      <p class="has-muted-color has-text-color"><?php echo $dept; ?> &middot; <?php echo $location; ?></p>
      <!-- /wp:paragraph -->
    </div>
    <!-- /wp:group -->
    <!-- wp:buttons {"className":"flex-shrink-0"} -->
    <div class="wp-block-buttons flex-shrink-0">
      <!-- wp:button {"backgroundColor":"primary","textColor":"contrast","className":"gf-apply-btn"} -->
      <div class="wp-block-button gf-apply-btn"><a class="wp-block-button__link has-contrast-color has-primary-background-color has-text-color has-background wp-element-button">Apply</a></div>
      <!-- /wp:button -->
    </div>
    <!-- /wp:buttons -->
  </div>
  <!-- /wp:group -->
			<?php $rows .= ob_get_clean();
		}

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?> py-5","align":"full"} -->
<section class="wp-block-group <?php echo $css; ?> py-5 alignfull">
  <!-- wp:heading {"textAlign":"center","level":2,"textColor":"primary","className":"gf-section-heading"} -->
  <h2 class="wp-block-heading has-text-align-center gf-section-heading has-primary-color has-text-color"><?php echo $heading; ?></h2>
  <!-- /wp:heading -->
  <!-- wp:paragraph {"align":"center","textColor":"muted"} -->
  <p class="has-text-align-center has-muted-color has-text-color"><?php echo $subtext; ?></p>
  <!-- /wp:paragraph -->
  <!-- wp:group {"className":"d-flex flex-column gap-3 mt-4"} -->
  <div class="wp-block-group d-flex flex-column gap-3 mt-4">
<?php echo $rows; ?>
  </div>
  <!-- /wp:group -->
  <!-- wp:buttons {"className":"justify-content-center mt-4"} -->
  <div class="wp-block-buttons justify-content-center mt-4">
    <!-- wp:button {"className":"is-style-outline"} -->
    <div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button"><?php echo $cta_text; ?></a></div>
    <!-- /wp:button -->
  </div>
  <!-- /wp:buttons -->
</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	/**
	 * App store download — centered heading, subtext, iOS + Android buttons.
	 */
	public static function render_app_store_download( array $spec, array $theme ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];

		$wrap_class = esc_attr( implode( ' ', $classes ) ?: 'gf-section-tint' );
		$heading    = esc_html( $copy['section_heading'] ?? 'Get the App' );
		$subtext    = esc_html( $copy['subtext']         ?? 'Available on iOS and Android. Free to download.' );
		$eyebrow    = esc_html( $copy['eyebrow']         ?? 'Mobile App' );
		$ios_text   = esc_html( $copy['ios_text']        ?? 'Download on the App Store' );
		$android_text = esc_html( $copy['android_text']  ?? 'Get it on Google Play' );
		$ios_url    = esc_url(   $copy['ios_url']        ?? '#' );
		$android_url = esc_url(  $copy['android_url']    ?? '#' );
		$rating     = esc_html( $copy['rating']          ?? '4.9★ · 10K+ ratings' );

		ob_start(); ?>
<!-- wp:group {"className":"<?php echo $wrap_class; ?> py-5","align":"full","layout":{"type":"constrained","contentSize":"640px"}} -->
<div class="wp-block-group <?php echo $wrap_class; ?> py-5 alignfull">
  <!-- wp:paragraph {"align":"center","className":"gf-eyebrow"} -->
  <p class="has-text-align-center gf-eyebrow"><?php echo $eyebrow; ?></p>
  <!-- /wp:paragraph -->
  <!-- wp:heading {"textAlign":"center","level":2,"className":"gf-section-heading"} -->
  <h2 class="wp-block-heading has-text-align-center gf-section-heading"><?php echo $heading; ?></h2>
  <!-- /wp:heading -->
  <!-- wp:paragraph {"align":"center","textColor":"muted","className":"gf-section-subtext"} -->
  <p class="has-text-align-center has-muted-color has-text-color gf-section-subtext"><?php echo $subtext; ?></p>
  <!-- /wp:paragraph -->
  <!-- wp:html -->
  <div class="d-flex flex-wrap justify-content-center gap-3 mt-2">
    <a href="<?php echo $ios_url; ?>" class="gf-app-download-btn">
      <i class="bi bi-apple fs-3"></i>
      <span class="d-flex flex-column text-start lh-sm">
        <span class="gf-app-btn-sub">Download on the</span>
        <span class="gf-app-btn-main"><?php echo $ios_text; ?></span>
      </span>
    </a>
    <a href="<?php echo $android_url; ?>" class="gf-app-download-btn gf-app-download-btn--outline">
      <i class="bi bi-google-play fs-3"></i>
      <span class="d-flex flex-column text-start lh-sm">
        <span class="gf-app-btn-sub">Get it on</span>
        <span class="gf-app-btn-main"><?php echo $android_text; ?></span>
      </span>
    </a>
  </div>
  <!-- /wp:html -->
  <!-- wp:paragraph {"align":"center","textColor":"muted"} -->
  <p class="has-text-align-center has-muted-color has-text-color"><?php echo $rating; ?></p>
  <!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}
}

// Self-register renderers with PatternBuilder.
GrayFox_TB_PatternBuilder::register_renderers( [
	'product-grid'       => [ 'GrayFox_TB_Patterns_Commerce', 'render_product_grid'       ],
	'job-list-cards'     => [ 'GrayFox_TB_Patterns_Commerce', 'render_job_list_cards'     ],
	'app-store-download' => [ 'GrayFox_TB_Patterns_Commerce', 'render_app_store_download' ],
] );
