<?php
/**
 * Location pattern renderers: map-embed-section, service-area-list, multi-location-cards.
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
 * Class GrayFox_TB_Patterns_Location
 */
class GrayFox_TB_Patterns_Location {

	// -------------------------------------------------------------------------
	// map-embed-section — map placeholder + contact info sidebar
	// -------------------------------------------------------------------------

	public static function render_map_embed_section( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-map-section';

		$heading  = esc_html( $copy['section_heading'] ?? 'Find Us' );
		$address  = esc_html( $copy['address']         ?? '123 Main Street, Suite 400' );
		$city     = esc_html( $copy['city']            ?? 'San Francisco, CA 94107' );
		$phone    = esc_html( $copy['phone']           ?? '+1 (415) 555-0100' );
		$email    = esc_html( $copy['email']           ?? 'hello@company.com' );
		$hours    = esc_html( $copy['hours']           ?? 'Mon–Fri: 9am – 6pm' );
		$hours2   = esc_html( $copy['hours2']          ?? 'Sat: 10am – 2pm' );
		$map_url  = esc_attr( $copy['map_embed_url']   ?? 'https://www.openstreetmap.org/export/embed.html?bbox=-122.4244%2C37.7729%2C-122.4044%2C37.7829&layer=mapnik&marker=37.7749%2C-122.4194' );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?> py-5","backgroundColor":"background","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5 has-background-background-color has-background">

<!-- wp:heading {"textAlign":"center","textColor":"primary"} -->
<h2 class="wp-block-heading has-text-align-center has-primary-color has-text-color"><?php echo $heading; ?></h2>
<!-- /wp:heading -->

<!-- wp:columns {"isStackedOnMobile":true,"className":"mt-4","verticalAlignment":"top"} -->
<div class="wp-block-columns is-stacked-on-mobile are-vertically-aligned-top mt-4">

<!-- wp:column {"width":"60%"} -->
<div class="wp-block-column" style="flex-basis:60%">
<!-- wp:html -->
<iframe
  src="<?php echo $map_url; ?>"
  width="100%" height="420"
  style="border:0;border-radius:12px;display:block;"
  allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade"
  title="Location map"></iframe>
<!-- /wp:html -->
</div>
<!-- /wp:column -->

<!-- wp:column {"width":"40%"} -->
<div class="wp-block-column" style="flex-basis:40%">
<!-- wp:html -->
<div class="d-flex flex-column gap-4 ps-lg-3">

  <div class="d-flex align-items-start gap-3">
    <i class="bi bi-geo-alt-fill fs-5 flex-shrink-0 mt-1" style="color:var(--wp--preset--color--primary)"></i>
    <div>
      <p class="fw-semibold text-uppercase mb-1 small" style="color:var(--wp--preset--color--primary);letter-spacing:.06em">Address</p>
      <p class="mb-0"><?php echo $address; ?><br><?php echo $city; ?></p>
    </div>
  </div>

  <div class="d-flex align-items-start gap-3">
    <i class="bi bi-telephone-fill fs-5 flex-shrink-0 mt-1" style="color:var(--wp--preset--color--primary)"></i>
    <div>
      <p class="fw-semibold text-uppercase mb-1 small" style="color:var(--wp--preset--color--primary);letter-spacing:.06em">Phone</p>
      <p class="mb-0"><?php echo $phone; ?></p>
    </div>
  </div>

  <div class="d-flex align-items-start gap-3">
    <i class="bi bi-envelope-fill fs-5 flex-shrink-0 mt-1" style="color:var(--wp--preset--color--primary)"></i>
    <div>
      <p class="fw-semibold text-uppercase mb-1 small" style="color:var(--wp--preset--color--primary);letter-spacing:.06em">Email</p>
      <p class="mb-0"><?php echo $email; ?></p>
    </div>
  </div>

  <div class="d-flex align-items-start gap-3">
    <i class="bi bi-clock-fill fs-5 flex-shrink-0 mt-1" style="color:var(--wp--preset--color--primary)"></i>
    <div>
      <p class="fw-semibold text-uppercase mb-1 small" style="color:var(--wp--preset--color--primary);letter-spacing:.06em">Hours</p>
      <p class="mb-0"><?php echo $hours; ?><br><?php echo $hours2; ?></p>
    </div>
  </div>

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
	// service-area-list — 3-column 12-area grid
	// -------------------------------------------------------------------------

	public static function render_service_area_list( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-service-areas gf-section-tint';

		$heading = esc_html( $copy['section_heading'] ?? 'Areas We Serve' );
		$subtext = esc_html( $copy['subtext']         ?? 'We provide service across these locations.' );

		$areas = [];
		for ( $i = 1; $i <= 12; $i++ ) {
			$areas[] = esc_html( $copy[ "area{$i}" ] ?? "City {$i}" );
		}

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?>","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5">

<!-- wp:heading {"textAlign":"center","textColor":"primary"} -->
<h2 class="wp-block-heading has-text-align-center has-primary-color has-text-color"><?php echo $heading; ?></h2>
<!-- /wp:heading -->
<!-- wp:paragraph {"align":"center","textColor":"muted"} -->
<p class="has-text-align-center has-muted-color has-text-color"><?php echo $subtext; ?></p>
<!-- /wp:paragraph -->

<!-- wp:columns {"style":{"spacing":{"blockGap":"1rem","margin":{"top":"2.5rem"}}}} -->
<div class="wp-block-columns">
<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:group {"style":{"spacing":{"blockGap":"0.75rem"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group">
<!-- wp:paragraph {"textColor":"foreground"} --><p class="has-foreground-color has-text-color">&#128205; <?php echo $areas[0]; ?></p><!-- /wp:paragraph -->
<!-- wp:paragraph {"textColor":"foreground"} --><p class="has-foreground-color has-text-color">&#128205; <?php echo $areas[1]; ?></p><!-- /wp:paragraph -->
<!-- wp:paragraph {"textColor":"foreground"} --><p class="has-foreground-color has-text-color">&#128205; <?php echo $areas[2]; ?></p><!-- /wp:paragraph -->
<!-- wp:paragraph {"textColor":"foreground"} --><p class="has-foreground-color has-text-color">&#128205; <?php echo $areas[3]; ?></p><!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:column -->
<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:group {"style":{"spacing":{"blockGap":"0.75rem"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group">
<!-- wp:paragraph {"textColor":"foreground"} --><p class="has-foreground-color has-text-color">&#128205; <?php echo $areas[4]; ?></p><!-- /wp:paragraph -->
<!-- wp:paragraph {"textColor":"foreground"} --><p class="has-foreground-color has-text-color">&#128205; <?php echo $areas[5]; ?></p><!-- /wp:paragraph -->
<!-- wp:paragraph {"textColor":"foreground"} --><p class="has-foreground-color has-text-color">&#128205; <?php echo $areas[6]; ?></p><!-- /wp:paragraph -->
<!-- wp:paragraph {"textColor":"foreground"} --><p class="has-foreground-color has-text-color">&#128205; <?php echo $areas[7]; ?></p><!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:column -->
<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:group {"style":{"spacing":{"blockGap":"0.75rem"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group">
<!-- wp:paragraph {"textColor":"foreground"} --><p class="has-foreground-color has-text-color">&#128205; <?php echo $areas[8]; ?></p><!-- /wp:paragraph -->
<!-- wp:paragraph {"textColor":"foreground"} --><p class="has-foreground-color has-text-color">&#128205; <?php echo $areas[9]; ?></p><!-- /wp:paragraph -->
<!-- wp:paragraph {"textColor":"foreground"} --><p class="has-foreground-color has-text-color">&#128205; <?php echo $areas[10]; ?></p><!-- /wp:paragraph -->
<!-- wp:paragraph {"textColor":"foreground"} --><p class="has-foreground-color has-text-color">&#128205; <?php echo $areas[11]; ?></p><!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:column -->
</div>
<!-- /wp:columns -->

</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// multi-location-cards — 3 location cards with address, phone, hours, CTA
	// -------------------------------------------------------------------------

	public static function render_multi_location_cards( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-locations';

		$heading  = esc_html( $copy['section_heading'] ?? 'Our Locations' );
		$l1_name  = esc_html( $copy['l1_name']         ?? 'San Francisco HQ' );
		$l1_addr  = esc_html( $copy['l1_address']      ?? '123 Market St, SF CA 94107' );
		$l1_phone = esc_html( $copy['l1_phone']        ?? '+1 415 555 0100' );
		$l1_hours = esc_html( $copy['l1_hours']        ?? 'Mon–Fri 9am–6pm' );
		$l2_name  = esc_html( $copy['l2_name']         ?? 'New York Office' );
		$l2_addr  = esc_html( $copy['l2_address']      ?? '350 5th Ave, New York NY 10118' );
		$l2_phone = esc_html( $copy['l2_phone']        ?? '+1 212 555 0200' );
		$l2_hours = esc_html( $copy['l2_hours']        ?? 'Mon–Fri 9am–6pm' );
		$l3_name  = esc_html( $copy['l3_name']         ?? 'London Office' );
		$l3_addr  = esc_html( $copy['l3_address']      ?? '30 St Mary Axe, London EC3A 8BF' );
		$l3_phone = esc_html( $copy['l3_phone']        ?? '+44 20 7946 0100' );
		$l3_hours = esc_html( $copy['l3_hours']        ?? 'Mon–Fri 9am–5pm GMT' );

		$loc_card = function( string $name, string $addr, string $phone, string $hours ): string {
			ob_start(); ?>
  <div class="col-md-4">
    <div class="gf-location-card">
      <h3 class="gf-location-name"><?php echo $name; ?></h3>
      <div class="d-flex align-items-start gap-2 mb-2">
        <i class="bi bi-geo-alt-fill flex-shrink-0 mt-1" style="color:var(--wp--preset--color--primary)"></i>
        <span><?php echo $addr; ?></span>
      </div>
      <div class="d-flex align-items-start gap-2 mb-2">
        <i class="bi bi-telephone-fill flex-shrink-0 mt-1" style="color:var(--wp--preset--color--primary)"></i>
        <span><?php echo $phone; ?></span>
      </div>
      <div class="d-flex align-items-start gap-2 mb-3">
        <i class="bi bi-clock flex-shrink-0 mt-1" style="color:var(--wp--preset--color--primary)"></i>
        <span class="small text-muted"><?php echo $hours; ?></span>
      </div>
      <a href="#" class="btn btn-sm btn-outline-primary fw-semibold mt-auto">
        <i class="bi bi-arrow-right-circle me-1"></i>Get directions
      </a>
    </div>
  </div>
			<?php return ob_get_clean();
		};

		$c1 = $loc_card( $l1_name, $l1_addr, $l1_phone, $l1_hours );
		$c2 = $loc_card( $l2_name, $l2_addr, $l2_phone, $l2_hours );
		$c3 = $loc_card( $l3_name, $l3_addr, $l3_phone, $l3_hours );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?> py-5","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5">

<!-- wp:heading {"textAlign":"center","textColor":"primary"} -->
<h2 class="wp-block-heading has-text-align-center has-primary-color has-text-color"><?php echo $heading; ?></h2>
<!-- /wp:heading -->

<!-- wp:html -->
<div class="row g-4 mt-2">
<?php echo $c1; ?>
<?php echo $c2; ?>
<?php echo $c3; ?>
</div>
<!-- /wp:html -->

</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}
}

GrayFox_TB_PatternBuilder::register_renderers( [
	'map-embed-section'    => [ GrayFox_TB_Patterns_Location::class, 'render_map_embed_section' ],
	'service-area-list'    => [ GrayFox_TB_Patterns_Location::class, 'render_service_area_list' ],
	'multi-location-cards' => [ GrayFox_TB_Patterns_Location::class, 'render_multi_location_cards' ],
] );
