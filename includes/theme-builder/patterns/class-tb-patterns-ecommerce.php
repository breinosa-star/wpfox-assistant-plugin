<?php
/**
 * E-Commerce pattern renderers.
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
 * Class GrayFox_TB_Patterns_Ecommerce
 */
class GrayFox_TB_Patterns_Ecommerce {

	// -------------------------------------------------------------------------
	// product-shelf — 4-product card row
	// -------------------------------------------------------------------------

	public static function render_product_shelf( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-product-shelf';

		$heading   = esc_html( $copy['section_heading'] ?? 'Shop Our Products' );
		$p1_name   = esc_html( $copy['p1_name']   ?? 'Starter Kit' );
		$p1_price  = esc_html( $copy['p1_price']  ?? '$29' );
		$p1_rating = esc_html( $copy['p1_rating'] ?? '4.8' );
		$p2_name   = esc_html( $copy['p2_name']   ?? 'Pro Bundle' );
		$p2_price  = esc_html( $copy['p2_price']  ?? '$79' );
		$p2_rating = esc_html( $copy['p2_rating'] ?? '4.9' );
		$p3_name   = esc_html( $copy['p3_name']   ?? 'Enterprise Pack' );
		$p3_price  = esc_html( $copy['p3_price']  ?? '$149' );
		$p3_rating = esc_html( $copy['p3_rating'] ?? '4.7' );
		$p4_name   = esc_html( $copy['p4_name']   ?? 'Add-on Module' );
		$p4_price  = esc_html( $copy['p4_price']  ?? '$19' );
		$p4_rating = esc_html( $copy['p4_rating'] ?? '4.6' );

		$card = function( $name, $price, $rating ) {
			$alt = esc_attr( $name );
			ob_start(); ?>
<!-- wp:group {"className":"gf-shelf-card"} -->
<div class="wp-block-group gf-shelf-card">
<!-- wp:image {"sizeSlug":"full","className":"gf-product-img"} -->
<figure class="wp-block-image size-full gf-product-img"><img src="" alt="<?php echo $alt; ?> product image" /></figure>
<!-- /wp:image -->
<!-- wp:group {"className":"gf-shelf-card-body"} -->
<div class="wp-block-group gf-shelf-card-body">
<!-- wp:paragraph {"className":"gf-shelf-rating","textColor":"muted"} --><p class="gf-shelf-rating has-muted-color has-text-color"><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i> <?php echo $rating; ?></p><!-- /wp:paragraph -->
<!-- wp:heading {"level":4,"textColor":"foreground"} --><h4 class="wp-block-heading has-foreground-color has-text-color"><?php echo $name; ?></h4><!-- /wp:heading -->
<!-- wp:paragraph {"className":"gf-product-price","textColor":"primary"} --><p class="gf-product-price has-primary-color has-text-color"><?php echo $price; ?></p><!-- /wp:paragraph -->
<!-- wp:buttons -->
<div class="wp-block-buttons">
<!-- wp:button {"width":100,"backgroundColor":"primary","textColor":"contrast"} -->
<div class="wp-block-button has-custom-width wp-block-button__width-100"><a class="wp-block-button__link has-contrast-color has-primary-background-color has-text-color has-background wp-element-button">Add to cart</a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:group -->
			<?php return ob_get_clean();
		};

		$c1 = $card( $p1_name, $p1_price, $p1_rating );
		$c2 = $card( $p2_name, $p2_price, $p2_rating );
		$c3 = $card( $p3_name, $p3_price, $p3_rating );
		$c4 = $card( $p4_name, $p4_price, $p4_rating );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?> py-5","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5">

<!-- wp:heading {"textAlign":"center","textColor":"primary","className":"gf-section-heading"} -->
<h2 class="wp-block-heading has-text-align-center has-primary-color has-text-color gf-section-heading"><?php echo $heading; ?></h2>
<!-- /wp:heading -->

<!-- wp:columns {"isStackedOnMobile":true,"className":"mt-4"} -->
<div class="wp-block-columns is-stacked-on-mobile mt-4">
<!-- wp:column --><div class="wp-block-column"><?php echo $c1; ?></div><!-- /wp:column -->
<!-- wp:column --><div class="wp-block-column"><?php echo $c2; ?></div><!-- /wp:column -->
<!-- wp:column --><div class="wp-block-column"><?php echo $c3; ?></div><!-- /wp:column -->
<!-- wp:column --><div class="wp-block-column"><?php echo $c4; ?></div><!-- /wp:column -->
</div>
<!-- /wp:columns -->

</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// product-feature-highlight — image + specs + dual CTA
	// -------------------------------------------------------------------------

	public static function render_product_feature_highlight( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-product-highlight';

		$heading       = esc_html( $copy['heading']       ?? 'The Pro Bundle' );
		$subtext       = esc_html( $copy['subtext']       ?? 'Everything you need to scale your workflow, in one package.' );
		$price         = esc_html( $copy['price']         ?? '$79' );
		$spec1         = esc_html( $copy['spec1']         ?? 'Unlimited projects' );
		$spec2         = esc_html( $copy['spec2']         ?? 'Priority support' );
		$spec3         = esc_html( $copy['spec3']         ?? 'Advanced analytics' );
		$spec4         = esc_html( $copy['spec4']         ?? 'Team collaboration tools' );
		$cta_primary   = esc_html( $copy['cta_primary']   ?? 'Buy now' );
		$cta_secondary = esc_html( $copy['cta_secondary'] ?? 'Learn more' );
		$img_alt       = esc_attr( $heading );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?> py-5","backgroundColor":"background","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5 has-background-background-color has-background">

<!-- wp:columns {"verticalAlignment":"center"} -->
<div class="wp-block-columns are-vertically-aligned-center">

<!-- wp:column {"width":"50%"} -->
<div class="wp-block-column" style="flex-basis:50%">
<!-- wp:image {"sizeSlug":"full","className":"gf-collection-img"} -->
<figure class="wp-block-image size-full gf-collection-img"><img src="" alt="<?php echo $img_alt; ?> product image" /></figure>
<!-- /wp:image -->
</div>
<!-- /wp:column -->

<!-- wp:column {"width":"50%"} -->
<div class="wp-block-column" style="flex-basis:50%">
<!-- wp:heading {"textColor":"primary","className":"gf-section-heading"} -->
<h2 class="wp-block-heading has-primary-color has-text-color gf-section-heading"><?php echo $heading; ?></h2>
<!-- /wp:heading -->
<!-- wp:paragraph {"textColor":"muted"} -->
<p class="has-muted-color has-text-color"><?php echo $subtext; ?></p>
<!-- /wp:paragraph -->

<!-- wp:html -->
<ul class="gf-product-specs">
  <li><i class="bi bi-check-lg text-success" aria-hidden="true"></i> <?php echo $spec1; ?></li>
  <li><i class="bi bi-check-lg text-success" aria-hidden="true"></i> <?php echo $spec2; ?></li>
  <li><i class="bi bi-check-lg text-success" aria-hidden="true"></i> <?php echo $spec3; ?></li>
  <li><i class="bi bi-check-lg text-success" aria-hidden="true"></i> <?php echo $spec4; ?></li>
</ul>
<!-- /wp:html -->

<!-- wp:paragraph {"className":"gf-price-amount","textColor":"primary"} -->
<p class="gf-price-amount has-primary-color has-text-color"><?php echo $price; ?></p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"className":"justify-content-center"} -->
<div class="wp-block-buttons justify-content-center">
<!-- wp:button {"backgroundColor":"primary","textColor":"contrast"} -->
<div class="wp-block-button"><a class="wp-block-button__link has-contrast-color has-primary-background-color has-text-color has-background wp-element-button"><?php echo $cta_primary; ?></a></div>
<!-- /wp:button -->
<!-- wp:button {"className":"is-style-outline","textColor":"primary"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link has-primary-color has-text-color wp-element-button"><?php echo $cta_secondary; ?></a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->
</div>
<!-- /wp:column -->

</div>
<!-- /wp:columns -->

</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// cart-summary-cta — order summary card with line items + checkout button
	// -------------------------------------------------------------------------

	public static function render_cart_summary_cta( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-cart-summary';

		$heading     = esc_html( $copy['heading']     ?? 'Your Order Summary' );
		$item1       = esc_html( $copy['item1_name']  ?? 'Pro Bundle' );
		$item1_price = esc_html( $copy['item1_price'] ?? '$79.00' );
		$item2       = esc_html( $copy['item2_name']  ?? 'Add-on: Priority Support' );
		$item2_price = esc_html( $copy['item2_price'] ?? '$19.00' );
		$subtotal    = esc_html( $copy['subtotal']    ?? '$98.00' );
		$tax         = esc_html( $copy['tax']         ?? '$8.82' );
		$total       = esc_html( $copy['total']       ?? '$106.82' );
		$cta_label   = esc_html( $copy['cta_label']   ?? 'Proceed to checkout' );
		$guarantee   = esc_html( $copy['guarantee']   ?? '30-day money-back guarantee. No questions asked.' );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?>","backgroundColor":"background","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> has-background-background-color has-background py-5">
<!-- wp:html -->
<div class="container">
  <div class="mx-auto" style="max-width:500px">
    <div class="card border" style="border-radius:12px;padding:2rem">

      <h3 style="color:var(--gf-primary);margin-top:0;margin-bottom:1.5rem"><?php echo $heading; ?></h3>

      <div class="d-flex justify-content-between align-items-center py-3 border-bottom">
        <span style="color:var(--gf-text)"><?php echo $item1; ?></span>
        <span style="color:var(--gf-text)"><?php echo $item1_price; ?></span>
      </div>
      <div class="d-flex justify-content-between align-items-center py-3 border-bottom">
        <span style="color:var(--gf-text)"><?php echo $item2; ?></span>
        <span style="color:var(--gf-text)"><?php echo $item2_price; ?></span>
      </div>
      <div class="d-flex justify-content-between align-items-center py-2">
        <span style="color:var(--gf-muted);font-size:0.9rem">Subtotal</span>
        <span style="color:var(--gf-muted);font-size:0.9rem"><?php echo $subtotal; ?></span>
      </div>
      <div class="d-flex justify-content-between align-items-center py-2 border-top border-bottom mb-3">
        <span style="color:var(--gf-muted);font-size:0.9rem">Tax</span>
        <span style="color:var(--gf-muted);font-size:0.9rem"><?php echo $tax; ?></span>
      </div>
      <div class="d-flex justify-content-between align-items-center py-2 mb-4">
        <span style="font-weight:700;font-size:1.1rem;color:var(--gf-text)">Total</span>
        <span style="font-weight:700;font-size:1.25rem;color:var(--gf-primary)"><?php echo $total; ?></span>
      </div>

      <a href="#" class="btn btn-primary w-100 mb-3"><?php echo $cta_label; ?></a>

      <p class="text-center mb-0" style="font-size:0.8rem;color:var(--gf-muted)"><?php echo $guarantee; ?></p>

    </div>
  </div>
</div>
<!-- /wp:html -->
</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// collection-grid — 4 image tiles with overlay labels
	// -------------------------------------------------------------------------

	public static function render_collection_grid( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-collection-grid';

		$heading = esc_html( $copy['section_heading'] ?? 'Shop by Collection' );
		$c1      = esc_attr( $copy['col1_label']      ?? 'New Arrivals' );
		$c2      = esc_attr( $copy['col2_label']      ?? 'Best Sellers' );
		$c3      = esc_attr( $copy['col3_label']      ?? 'Sale Items' );
		$c4      = esc_attr( $copy['col4_label']      ?? 'Bundles' );

		$tile = function( $label ) {
			ob_start(); ?>
<!-- wp:group {"className":"gf-collection-tile"} -->
<div class="wp-block-group gf-collection-tile">
<!-- wp:image {"sizeSlug":"full"} -->
<figure class="wp-block-image size-full"><img src="" alt="<?php echo $label; ?> collection"/></figure>
<!-- /wp:image -->
<!-- wp:group {"className":"gf-collection-overlay"} -->
<div class="wp-block-group gf-collection-overlay">
<!-- wp:heading {"level":4,"textColor":"white"} --><h4 class="wp-block-heading has-white-color has-text-color"><?php echo $label; ?></h4><!-- /wp:heading -->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:group -->
			<?php return ob_get_clean();
		};

		$t1 = $tile( $c1 );
		$t2 = $tile( $c2 );
		$t3 = $tile( $c3 );
		$t4 = $tile( $c4 );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?> py-5","backgroundColor":"background","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5 has-background-background-color has-background">

<!-- wp:heading {"textAlign":"center","textColor":"primary"} -->
<h2 class="wp-block-heading has-text-align-center has-primary-color has-text-color"><?php echo $heading; ?></h2>
<!-- /wp:heading -->

<!-- wp:columns -->
<div class="wp-block-columns">
<!-- wp:column --><div class="wp-block-column"><?php echo $t1; ?></div><!-- /wp:column -->
<!-- wp:column --><div class="wp-block-column"><?php echo $t2; ?></div><!-- /wp:column -->
<!-- wp:column --><div class="wp-block-column"><?php echo $t3; ?></div><!-- /wp:column -->
<!-- wp:column --><div class="wp-block-column"><?php echo $t4; ?></div><!-- /wp:column -->
</div>
<!-- /wp:columns -->

</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// upsell-strip — 4 compact upsell product cards
	// -------------------------------------------------------------------------

	public static function render_upsell_strip( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-upsell-strip gf-section-tint';

		$heading  = esc_html( $copy['section_heading'] ?? 'You Might Also Like' );
		$p1       = esc_attr( $copy['p1_name']         ?? 'Add-on Module' );
		$p1_price = esc_html( $copy['p1_price']        ?? '$19' );
		$p2       = esc_attr( $copy['p2_name']         ?? 'Extended Warranty' );
		$p2_price = esc_html( $copy['p2_price']        ?? '$9/mo' );
		$p3       = esc_attr( $copy['p3_name']         ?? 'Premium Templates' );
		$p3_price = esc_html( $copy['p3_price']        ?? '$29' );
		$p4       = esc_attr( $copy['p4_name']         ?? 'Training Course' );
		$p4_price = esc_html( $copy['p4_price']        ?? '$49' );

		$ucard = function( $name, $price ) {
			ob_start(); ?>
<!-- wp:group {"className":"gf-upsell-card","backgroundColor":"background"} -->
<div class="wp-block-group gf-upsell-card has-background-background-color has-background">
<!-- wp:image {"className":"gf-mini-product-img","sizeSlug":"thumbnail"} -->
<figure class="wp-block-image size-thumbnail gf-mini-product-img"><img src="" alt="<?php echo $name; ?>" /></figure>
<!-- /wp:image -->
<!-- wp:heading {"level":5,"textColor":"foreground"} --><h5 class="wp-block-heading has-foreground-color has-text-color"><?php echo $name; ?></h5><!-- /wp:heading -->
<!-- wp:paragraph {"textColor":"primary"} --><p class="has-primary-color has-text-color"><?php echo $price; ?></p><!-- /wp:paragraph -->
<!-- wp:buttons -->
<div class="wp-block-buttons">
<!-- wp:button {"variant":"outline","textColor":"primary"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link has-primary-color has-text-color wp-element-button">Add to cart</a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->
</div>
<!-- /wp:group -->
			<?php return ob_get_clean();
		};

		$u1 = $ucard( $p1, $p1_price );
		$u2 = $ucard( $p2, $p2_price );
		$u3 = $ucard( $p3, $p3_price );
		$u4 = $ucard( $p4, $p4_price );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?> py-5","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5">

<!-- wp:heading {"textColor":"primary"} -->
<h2 class="wp-block-heading has-primary-color has-text-color"><?php echo $heading; ?></h2>
<!-- /wp:heading -->

<!-- wp:columns -->
<div class="wp-block-columns">
<!-- wp:column --><div class="wp-block-column"><?php echo $u1; ?></div><!-- /wp:column -->
<!-- wp:column --><div class="wp-block-column"><?php echo $u2; ?></div><!-- /wp:column -->
<!-- wp:column --><div class="wp-block-column"><?php echo $u3; ?></div><!-- /wp:column -->
<!-- wp:column --><div class="wp-block-column"><?php echo $u4; ?></div><!-- /wp:column -->
</div>
<!-- /wp:columns -->

</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// mini-cart-drawer — slide-out cart panel
	// -------------------------------------------------------------------------

	public static function render_mini_cart_drawer( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = $classes[0]          ?? 'gf-mini-cart-drawer';

		$heading  = esc_html( $copy['heading']       ?? 'Your Cart' );
		$subtotal = esc_html( $copy['subtotal_label'] ?? 'Subtotal' );
		$cta      = esc_html( $copy['cta_checkout']  ?? 'Proceed to Checkout' );
		$cta_shop = esc_html( $copy['cta_continue']  ?? 'Continue Shopping' );

		ob_start(); ?>
<!-- wp:html -->
<aside class="<?php echo $css; ?>">
  <div class="<?php echo $css; ?>__header">
    <h2 class="gf-cart-title"><?php echo $heading; ?></h2>
    <button class="gf-cart-close" aria-label="Close cart"><i class="bi bi-x-lg"></i></button>
  </div>
  <div class="<?php echo $css; ?>__items">
    <div class="gf-cart-item">
      <div class="gf-cart-item__image" aria-hidden="true"></div>
      <div class="gf-cart-item__details">
        <p class="gf-cart-item__name">Product Name</p>
        <p class="gf-cart-item__meta">Size: M &nbsp;|&nbsp; Qty: 1</p>
        <p class="gf-cart-item__price">$49.00</p>
      </div>
      <button class="gf-cart-item__remove" aria-label="Remove item"><i class="bi bi-trash"></i></button>
    </div>
    <div class="gf-cart-item">
      <div class="gf-cart-item__image" aria-hidden="true"></div>
      <div class="gf-cart-item__details">
        <p class="gf-cart-item__name">Another Product</p>
        <p class="gf-cart-item__meta">Color: Blue &nbsp;|&nbsp; Qty: 2</p>
        <p class="gf-cart-item__price">$98.00</p>
      </div>
      <button class="gf-cart-item__remove" aria-label="Remove item"><i class="bi bi-trash"></i></button>
    </div>
  </div>
  <div class="<?php echo $css; ?>__footer">
    <div class="gf-cart-subtotal">
      <span><?php echo $subtotal; ?></span>
      <strong>$147.00</strong>
    </div>
    <a href="#" class="btn btn-primary w-100 fw-bold mt-3"><?php echo $cta; ?></a>
    <a href="#" class="gf-cart-continue-link"><?php echo $cta_shop; ?></a>
  </div>
</aside>
<!-- /wp:html -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// empty-cart-state — empty cart view with recommendations
	// -------------------------------------------------------------------------

	public static function render_empty_cart_state( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-empty-cart';

		$heading  = esc_html( $copy['heading']                 ?? 'Your cart is empty' );
		$subtext  = esc_html( $copy['subtext']                 ?? "Looks like you haven't added anything yet." );
		$cta      = esc_html( $copy['cta_shop']                ?? 'Start Shopping' );
		$rec_head = esc_html( $copy['recommendations_heading'] ?? 'You might like' );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?> py-5","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5">
<!-- wp:html -->
<div class="container" style="max-width:800px;">
  <div class="text-center mb-5">
    <div class="gf-empty-cart__icon" aria-hidden="true">
      <i class="bi bi-cart-x"></i>
    </div>
    <h2 class="gf-section-heading"><?php echo $heading; ?></h2>
    <p class="text-muted mx-auto mb-4" style="max-width:28rem;"><?php echo $subtext; ?></p>
    <a href="/shop" class="btn btn-lg gf-accent-bg text-white border-0 fw-bold"><?php echo $cta; ?></a>
  </div>
  <div class="gf-empty-cart__recommendations">
    <h3 class="h5 text-primary mb-4"><?php echo $rec_head; ?></h3>
    <div class="row g-3">
      <div class="col-sm-4">
        <div class="gf-product-card border rounded">
          <img src="" alt="Suggested Product" class="w-100" style="height:180px;object-fit:cover;display:block;">
          <div class="gf-product-card-body">
            <p class="fw-semibold mb-0">Suggested Product</p>
            <p class="text-muted small mb-2">$39.00</p>
            <a href="#" class="small fw-semibold gf-accent-color text-decoration-none">Add to cart &rarr;</a>
          </div>
        </div>
      </div>
      <div class="col-sm-4">
        <div class="gf-product-card border rounded">
          <img src="" alt="Another Pick" class="w-100" style="height:180px;object-fit:cover;display:block;">
          <div class="gf-product-card-body">
            <p class="fw-semibold mb-0">Another Pick</p>
            <p class="text-muted small mb-2">$59.00</p>
            <a href="#" class="small fw-semibold gf-accent-color text-decoration-none">Add to cart &rarr;</a>
          </div>
        </div>
      </div>
      <div class="col-sm-4">
        <div class="gf-product-card border rounded">
          <img src="" alt="Popular Item" class="w-100" style="height:180px;object-fit:cover;display:block;">
          <div class="gf-product-card-body">
            <p class="fw-semibold mb-0">Popular Item</p>
            <p class="text-muted small mb-2">$29.00</p>
            <a href="#" class="small fw-semibold gf-accent-color text-decoration-none">Add to cart &rarr;</a>
          </div>
        </div>
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
	// checkout-progress-bar — step indicator for checkout flow
	// -------------------------------------------------------------------------

	public static function render_checkout_progress_bar( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = $classes[0]          ?? 'gf-checkout-progress';

		$step1  = esc_html( $copy['step_1'] ?? 'Cart' );
		$step2  = esc_html( $copy['step_2'] ?? 'Details' );
		$step3  = esc_html( $copy['step_3'] ?? 'Payment' );
		$step4  = esc_html( $copy['step_4'] ?? 'Confirm' );
		$active = intval( $copy['active_step'] ?? 2 );

		$steps      = [ $step1, $step2, $step3, $step4 ];
		$steps_html = '';
		foreach ( $steps as $i => $label ) {
			$num = $i + 1;
			if ( $num < $active ) {
				$cls  = 'gf-progress-step gf-progress-step--done';
				$icon = '&#10003;';
			} elseif ( $num === $active ) {
				$cls  = 'gf-progress-step gf-progress-step--active';
				$icon = (string) $num;
			} else {
				$cls  = 'gf-progress-step gf-progress-step--pending';
				$icon = (string) $num;
			}
			$connector   = ( $num < 4 ) ? '<div class="gf-checkout-progress__connector"></div>' : '';
			$aria_cur    = ( $num === $active ) ? 'step' : 'false';
			$steps_html .= "<div class=\"{$cls}\"><div class=\"gf-progress-step__bubble\" aria-current=\"{$aria_cur}\">{$icon}</div><span class=\"gf-progress-step__label\">{$label}</span></div>{$connector}\n      ";
		}

		ob_start(); ?>
<!-- wp:group {"align":"full"} -->
<div class="wp-block-group alignfull">
<!-- wp:html -->
<nav class="<?php echo $css; ?>" aria-label="Checkout progress">
  <div class="gf-checkout-progress__track">
    <?php echo $steps_html; ?>
  </div>
</nav>
<!-- /wp:html -->
</div>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// order-confirmation — thank-you page layout
	// -------------------------------------------------------------------------

	public static function render_order_confirmation( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = $classes[0]          ?? 'gf-order-confirmation';

		$heading      = esc_html( $copy['heading']            ?? 'Thank you for your order!' );
		$subtext      = esc_html( $copy['subtext']            ?? 'A confirmation email has been sent to your inbox.' );
		$order_label  = esc_html( $copy['order_label']        ?? 'Order' );
		$order_number = esc_html( $copy['order_number']       ?? '#12345' );
		$next_head    = esc_html( $copy['next_steps_heading'] ?? 'What happens next?' );
		$step1        = esc_html( $copy['next_1']             ?? "We're preparing your order for shipment." );
		$step2        = esc_html( $copy['next_2']             ?? "You'll receive a shipping confirmation email with tracking info." );
		$step3        = esc_html( $copy['next_3']             ?? 'Your order will arrive within 3–5 business days.' );
		$cta          = esc_html( $copy['cta_continue']       ?? 'Continue Shopping' );

		ob_start(); ?>
<!-- wp:html -->
<section class="<?php echo $css; ?>">
  <div class="gf-confirm-header">
    <div class="gf-confirm-icon" aria-hidden="true"><i class="bi bi-check-lg"></i></div>
    <h1><?php echo $heading; ?></h1>
    <p class="text-muted"><?php echo $subtext; ?></p>
    <p class="gf-confirm-number"><?php echo $order_label; ?> <span><?php echo $order_number; ?></span></p>
  </div>
  <div class="gf-confirm-summary">
    <h3>Order Summary</h3>
    <div class="gf-confirm-row"><span>Product Name &times; 1</span><strong>$49.00</strong></div>
    <div class="gf-confirm-row"><span>Another Product &times; 2</span><strong>$98.00</strong></div>
    <div class="gf-confirm-totals">
      <div class="gf-confirm-total-row"><span>Subtotal</span><span>$147.00</span></div>
      <div class="gf-confirm-total-row"><span>Shipping</span><span>$0.00</span></div>
      <div class="gf-confirm-total-row--grand"><span>Total</span><span>$147.00</span></div>
    </div>
  </div>
  <div class="gf-confirm-next">
    <h3><?php echo $next_head; ?></h3>
    <ol>
      <li><?php echo $step1; ?></li>
      <li><?php echo $step2; ?></li>
      <li><?php echo $step3; ?></li>
    </ol>
  </div>
  <div class="gf-confirm-cta">
    <a href="/shop" class="btn btn-primary px-5"><?php echo $cta; ?></a>
  </div>
</section>
<!-- /wp:html -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// product-detail-hero — full PDP layout with gallery + add-to-cart
	// -------------------------------------------------------------------------

	public static function render_product_detail_hero( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = $classes[0]          ?? 'gf-product-detail-hero';

		$name         = esc_html( $copy['product_name']    ?? 'Product Name' );
		$tagline      = esc_html( $copy['tagline']         ?? 'Short product tagline or SKU' );
		$price        = esc_html( $copy['price']           ?? '$99.00' );
		$description  = esc_html( $copy['description']     ?? 'Describe the product in 1–2 sentences. Highlight the key benefit.' );
		$cta          = esc_html( $copy['cta_add_to_cart'] ?? 'Add to Cart' );
		$cta_wishlist = esc_html( $copy['cta_wishlist']    ?? 'Save to Wishlist' );

		ob_start(); ?>
<!-- wp:html -->
<section class="<?php echo $css; ?>">
  <div class="gf-pdp-layout">

    <div class="gf-pdp-gallery">
      <div class="gf-pdp-main-img">Product Image</div>
      <div class="gf-pdp-thumbs">
        <div class="gf-pdp-thumb gf-pdp-thumb--active"></div>
        <div class="gf-pdp-thumb"></div>
        <div class="gf-pdp-thumb"></div>
        <div class="gf-pdp-thumb"></div>
      </div>
    </div>

    <div class="gf-pdp-details">
      <p class="gf-pdp-tagline"><?php echo $tagline; ?></p>
      <h1 class="gf-pdp-title"><?php echo $name; ?></h1>
      <div class="gf-pdp-stars">
        <span aria-label="4.8 out of 5 stars">
          <i class="bi bi-star-fill gf-pdp-star" aria-hidden="true"></i>
          <i class="bi bi-star-fill gf-pdp-star" aria-hidden="true"></i>
          <i class="bi bi-star-fill gf-pdp-star" aria-hidden="true"></i>
          <i class="bi bi-star-fill gf-pdp-star" aria-hidden="true"></i>
          <i class="bi bi-star-fill gf-pdp-star" aria-hidden="true"></i>
        </span>
        <span class="gf-pdp-review">4.8 &mdash; 124 reviews</span>
      </div>
      <p class="gf-pdp-price"><?php echo $price; ?></p>
      <p class="gf-pdp-desc"><?php echo $description; ?></p>
      <div class="gf-pdp-size-group">
        <label class="gf-pdp-size-label">Size</label>
        <div class="gf-pdp-sizes">
          <button class="gf-pdp-size-btn gf-pdp-size-btn--active">M</button>
          <button class="gf-pdp-size-btn">S</button>
          <button class="gf-pdp-size-btn">L</button>
          <button class="gf-pdp-size-btn">XL</button>
        </div>
      </div>
      <div class="gf-pdp-qty-group">
        <label class="gf-pdp-qty-label">Qty</label>
        <div class="gf-pdp-qty-stepper">
          <button class="gf-pdp-qty-btn" aria-label="Decrease quantity">&#8722;</button>
          <span class="gf-pdp-qty-val">1</span>
          <button class="gf-pdp-qty-btn" aria-label="Increase quantity">&#43;</button>
        </div>
      </div>
      <div class="gf-pdp-actions">
        <a href="#" class="btn btn-primary fw-bold flex-grow-1 text-center"><?php echo $cta; ?></a>
        <a href="#" class="btn btn-outline-primary fw-bold"><?php echo $cta_wishlist; ?></a>
      </div>
      <div class="gf-pdp-trust">
        <span><i class="bi bi-truck" aria-hidden="true"></i> Free shipping over $50</span>
        <span><i class="bi bi-arrow-return-left" aria-hidden="true"></i> 30-day returns</span>
        <span><i class="bi bi-lock-fill" aria-hidden="true"></i> Secure checkout</span>
      </div>
    </div>

  </div>
</section>
<!-- /wp:html -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// product-variants-selector — color swatches + size buttons
	// -------------------------------------------------------------------------

	public static function render_product_variants_selector( array $spec, array $manifest ): string {
		$copy        = $spec['copy']        ?? [];
		$classes     = $spec['css_classes'] ?? [];
		$section_css = implode( ' ', $classes ) ?: 'gf-section-tint';

		$color_label    = esc_html( $copy['color_label']    ?? 'Color' );
		$size_label     = esc_html( $copy['size_label']     ?? 'Size' );
		$selected_color = esc_html( $copy['selected_color'] ?? 'Midnight Blue' );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $section_css; ?> py-5","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $section_css; ?> py-5">
<!-- wp:html -->
<div class="container">
  <div class="gf-product-variants">

    <div class="gf-variants-group">
      <div class="gf-variants-group-header">
        <label class="gf-variants-label"><?php echo $color_label; ?></label>
        <span class="gf-variants-selected"><?php echo $selected_color; ?></span>
      </div>
      <div class="gf-swatches">
        <button class="gf-swatch gf-swatch--active" aria-label="Midnight Blue" title="Midnight Blue" style="background:#1a2e4a"></button>
        <button class="gf-swatch" aria-label="Forest Green" title="Forest Green" style="background:#14532d"></button>
        <button class="gf-swatch" aria-label="Crimson" title="Crimson" style="background:#991b1b"></button>
        <button class="gf-swatch" aria-label="Stone" title="Stone" style="background:#78716c"></button>
        <button class="gf-swatch gf-swatch--light" aria-label="Cream" title="Cream" style="background:#fef3c7"></button>
      </div>
    </div>

    <div class="gf-variants-group">
      <div class="gf-variants-group-header">
        <label class="gf-variants-label"><?php echo $size_label; ?></label>
        <a href="#size-guide" class="gf-variants-guide">Size guide</a>
      </div>
      <div class="gf-sizes">
        <button class="gf-size-btn" disabled aria-label="XS — out of stock">XS</button>
        <button class="gf-size-btn">S</button>
        <button class="gf-size-btn gf-size-btn--active" aria-pressed="true">M</button>
        <button class="gf-size-btn">L</button>
        <button class="gf-size-btn">XL</button>
        <button class="gf-size-btn" disabled aria-label="XXL — out of stock">XXL</button>
      </div>
    </div>

    <p class="gf-variants-stock"><i class="bi bi-exclamation-triangle-fill"></i> Only 4 left in stock</p>

  </div>
</div>
<!-- /wp:html -->
</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// product-reviews-section — star rating summary + review cards
	// -------------------------------------------------------------------------

	public static function render_product_reviews_section( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = $classes[0]          ?? 'gf-product-reviews';

		$heading       = esc_html( $copy['section_heading'] ?? 'Customer Reviews' );
		$avg_rating    = esc_html( $copy['avg_rating']      ?? '4.8' );
		$total_reviews = esc_html( $copy['total_reviews']   ?? '124 reviews' );
		$q1            = esc_html( $copy['quote_1']         ?? 'Exactly what I was looking for. Great quality and fast shipping.' );
		$a1            = esc_html( $copy['author_1']        ?? 'Sarah K.' );
		$q2            = esc_html( $copy['quote_2']         ?? 'Fits perfectly and looks even better in person. Will definitely order again.' );
		$a2            = esc_html( $copy['author_2']        ?? 'Marcus T.' );
		$q3            = esc_html( $copy['quote_3']         ?? 'Good value. The material feels premium and the sizing is accurate.' );
		$a3            = esc_html( $copy['author_3']        ?? 'Priya M.' );

		ob_start(); ?>
<!-- wp:html -->
<section class="<?php echo $css; ?> py-5">

  <h2 class="gf-section-heading gf-reviews-heading"><?php echo $heading; ?></h2>

  <div class="gf-reviews-summary">
    <div class="gf-reviews-score-block">
      <div class="gf-reviews-avg"><?php echo $avg_rating; ?></div>
      <div class="gf-reviews-stars"><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i></div>
      <div class="gf-reviews-total"><?php echo $total_reviews; ?></div>
    </div>
    <div class="gf-reviews-bars">
      <div class="gf-reviews-bar-row"><span class="gf-reviews-bar-label">5 <i class="bi bi-star-fill"></i></span><div class="gf-reviews-bar-track"><div class="gf-reviews-bar-fill" style="width:78%"></div></div><span class="gf-reviews-bar-pct">78%</span></div>
      <div class="gf-reviews-bar-row"><span class="gf-reviews-bar-label">4 <i class="bi bi-star-fill"></i></span><div class="gf-reviews-bar-track"><div class="gf-reviews-bar-fill" style="width:14%"></div></div><span class="gf-reviews-bar-pct">14%</span></div>
      <div class="gf-reviews-bar-row"><span class="gf-reviews-bar-label">3 <i class="bi bi-star-fill"></i></span><div class="gf-reviews-bar-track"><div class="gf-reviews-bar-fill" style="width:5%"></div></div><span class="gf-reviews-bar-pct">5%</span></div>
      <div class="gf-reviews-bar-row"><span class="gf-reviews-bar-label">2 <i class="bi bi-star-fill"></i></span><div class="gf-reviews-bar-track"><div class="gf-reviews-bar-fill" style="width:2%"></div></div><span class="gf-reviews-bar-pct">2%</span></div>
      <div class="gf-reviews-bar-row"><span class="gf-reviews-bar-label">1 <i class="bi bi-star-fill"></i></span><div class="gf-reviews-bar-track"><div class="gf-reviews-bar-fill" style="width:1%"></div></div><span class="gf-reviews-bar-pct">1%</span></div>
    </div>
  </div>

  <div class="gf-reviews-list">
    <div class="gf-review-card">
      <div class="gf-review-card-header"><strong><?php echo $a1; ?></strong><span class="gf-review-card-stars"><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i></span></div>
      <p class="gf-review-card-body">&ldquo;<?php echo $q1; ?>&rdquo;</p>
      <p class="gf-review-card-meta">Verified purchase &middot; 2 weeks ago</p>
    </div>
    <div class="gf-review-card">
      <div class="gf-review-card-header"><strong><?php echo $a2; ?></strong><span class="gf-review-card-stars"><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i></span></div>
      <p class="gf-review-card-body">&ldquo;<?php echo $q2; ?>&rdquo;</p>
      <p class="gf-review-card-meta">Verified purchase &middot; 1 month ago</p>
    </div>
    <div class="gf-review-card">
      <div class="gf-review-card-header"><strong><?php echo $a3; ?></strong><span class="gf-review-card-stars"><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star"></i></span></div>
      <p class="gf-review-card-body">&ldquo;<?php echo $q3; ?>&rdquo;</p>
      <p class="gf-review-card-meta">Verified purchase &middot; 2 months ago</p>
    </div>
  </div>

</section>
<!-- /wp:html -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// related-products-strip — horizontal 4-card grid
	// -------------------------------------------------------------------------

	public static function render_related_products_strip( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = $classes[0]          ?? 'gf-related-products';

		$heading  = esc_html( $copy['section_heading'] ?? 'You May Also Like' );

		$defaults = [
			[ 'name' => 'Related Product A', 'price' => '$49.00' ],
			[ 'name' => 'Related Product B', 'price' => '$79.00' ],
			[ 'name' => 'Related Product C', 'price' => '$39.00' ],
			[ 'name' => 'Related Product D', 'price' => '$59.00' ],
		];

		$cards = '';
		for ( $i = 1; $i <= 4; $i++ ) {
			$d     = $defaults[ $i - 1 ];
			$name  = esc_html( $copy[ "product_{$i}_name"  ] ?? $d['name']  );
			$price = esc_html( $copy[ "product_{$i}_price" ] ?? $d['price'] );
			$cta   = esc_html( $copy[ "product_{$i}_cta"   ] ?? 'Add to cart' );
			$alt   = esc_attr( $name );

			ob_start(); ?>
    <div class="col-6 col-md-3">
      <div class="gf-related-card">
        <figure class="gf-related-card-img"><img src="" alt="<?php echo $alt; ?>" /></figure>
        <div class="gf-related-card-body">
          <p class="gf-related-card-title"><?php echo $name; ?></p>
          <p class="gf-related-card-price"><?php echo $price; ?></p>
          <a href="#" class="btn btn-sm btn-primary w-100"><?php echo $cta; ?></a>
        </div>
      </div>
    </div>
			<?php $cards .= ob_get_clean();
		}

		ob_start(); ?>
<!-- wp:html -->
<section class="<?php echo $css; ?> py-5">
  <div class="container">
    <h2 class="gf-section-heading mb-4"><?php echo $heading; ?></h2>
    <div class="row g-3">
<?php echo $cards; ?>
    </div>
  </div>
</section>
<!-- /wp:html -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// wishlist-grid — saved items grid with remove buttons
	// -------------------------------------------------------------------------

	public static function render_wishlist_grid( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = $classes[0]          ?? 'gf-wishlist-grid';

		$heading  = esc_html( $copy['section_heading'] ?? 'Your Saved Items' );
		$empty    = esc_html( $copy['empty_message']   ?? "You haven't saved any items yet." );
		$cta_shop = esc_html( $copy['cta_shop']        ?? 'Explore the Shop' );

		ob_start(); ?>
<!-- wp:html -->
<section class="<?php echo $css; ?> gf-section" style="padding:4rem 1rem;max-width:1200px;margin:0 auto;">
  <h1 style="color:var(--wp--preset--color--primary);margin-bottom:2rem;"><?php echo $heading; ?></h1>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:1.5rem;">
    <div style="border:1px solid var(--wp--preset--color--muted,#e5e7eb);border-radius:8px;overflow:hidden;position:relative;">
      <button aria-label="Remove from wishlist" style="position:absolute;top:.5rem;right:.5rem;background:rgba(255,255,255,.9);border:none;border-radius:50%;width:28px;height:28px;cursor:pointer;font-size:.9rem;">&times;</button>
      <div style="aspect-ratio:1/1;background:var(--wp--preset--color--muted,#f3f4f6);"></div>
      <div style="padding:1rem;"><p style="font-weight:600;margin:0 0 .25rem;">Saved Product A</p><p style="color:var(--wp--preset--color--muted,#6b7280);font-size:.875rem;margin:0 0 .75rem;">$49.00</p><a href="#" class="wp-element-button" style="background-color:var(--wp--preset--color--primary);color:#fff;padding:.5rem 1rem;border-radius:4px;text-decoration:none;font-size:.875rem;display:block;text-align:center;">Add to Cart</a></div>
    </div>
    <div style="border:1px solid var(--wp--preset--color--muted,#e5e7eb);border-radius:8px;overflow:hidden;position:relative;">
      <button aria-label="Remove from wishlist" style="position:absolute;top:.5rem;right:.5rem;background:rgba(255,255,255,.9);border:none;border-radius:50%;width:28px;height:28px;cursor:pointer;font-size:.9rem;">&times;</button>
      <div style="aspect-ratio:1/1;background:var(--wp--preset--color--muted,#f3f4f6);"></div>
      <div style="padding:1rem;"><p style="font-weight:600;margin:0 0 .25rem;">Saved Product B</p><p style="color:var(--wp--preset--color--muted,#6b7280);font-size:.875rem;margin:0 0 .75rem;">$89.00</p><a href="#" class="wp-element-button" style="background-color:var(--wp--preset--color--primary);color:#fff;padding:.5rem 1rem;border-radius:4px;text-decoration:none;font-size:.875rem;display:block;text-align:center;">Add to Cart</a></div>
    </div>
    <div style="grid-column:1/-1;text-align:center;padding:3rem;display:none;">
      <p style="color:var(--wp--preset--color--muted,#6b7280);margin-bottom:1rem;"><?php echo $empty; ?></p>
      <a href="/shop" style="color:var(--wp--preset--color--accent);font-weight:600;"><?php echo $cta_shop; ?></a>
    </div>
  </div>
</section>
<!-- /wp:html -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// catalog-filter-bar — sticky filter pills + sort dropdown
	// -------------------------------------------------------------------------

	public static function render_catalog_filter_bar( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = $classes[0]          ?? 'gf-catalog-filter-bar';

		$f1           = esc_html( $copy['filter_1']    ?? 'All' );
		$f2           = esc_html( $copy['filter_2']    ?? 'New Arrivals' );
		$f3           = esc_html( $copy['filter_3']    ?? 'Sale' );
		$f4           = esc_html( $copy['filter_4']    ?? 'Under $50' );
		$f5           = esc_html( $copy['filter_5']    ?? 'Featured' );
		$result_count = esc_html( $copy['result_count'] ?? '48 products' );
		$sort_label   = esc_html( $copy['sort_label']  ?? 'Sort by' );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?>","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?>">
<!-- wp:html -->
<div class="gf-catalog-filter-bar__bar">
  <div class="container">
    <div class="d-flex align-items-center gap-3 flex-wrap">
      <div class="d-flex gap-2 flex-wrap flex-grow-1" role="group" aria-label="Filter products">
        <button aria-pressed="true" class="btn btn-primary btn-sm rounded-pill"><?php echo $f1; ?></button>
        <button aria-pressed="false" class="btn btn-outline-secondary btn-sm rounded-pill"><?php echo $f2; ?></button>
        <button aria-pressed="false" class="btn btn-outline-secondary btn-sm rounded-pill"><?php echo $f3; ?></button>
        <button aria-pressed="false" class="btn btn-outline-secondary btn-sm rounded-pill"><?php echo $f4; ?></button>
        <button aria-pressed="false" class="btn btn-outline-secondary btn-sm rounded-pill"><?php echo $f5; ?></button>
      </div>
      <div class="d-flex align-items-center gap-3 ms-auto text-nowrap">
        <span class="gf-catalog-filter-bar__count"><?php echo $result_count; ?></span>
        <label class="gf-catalog-filter-bar__sort-label d-flex align-items-center gap-2 mb-0">
          <?php echo $sort_label; ?>
          <select class="form-select form-select-sm gf-catalog-filter-bar__select">
            <option>Featured</option>
            <option>Price: Low to High</option>
            <option>Price: High to Low</option>
            <option>Newest</option>
            <option>Best Sellers</option>
          </select>
        </label>
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
	// category-hero — full-width dark hero for shop category pages
	// -------------------------------------------------------------------------

	public static function render_category_hero( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = $classes[0]          ?? 'gf-category-hero';

		$category_name = esc_html( $copy['category_name']  ?? 'Shop All' );
		$product_count = esc_html( $copy['product_count']  ?? '48 products' );
		$description   = esc_html( $copy['description']    ?? 'Discover our full collection of handcrafted essentials.' );
		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?>","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?>">
<!-- wp:html -->
<div class="gf-category-hero__overlay" aria-hidden="true"></div>
<div class="container gf-category-hero__content">
  <p class="gf-category-hero__eyebrow"><?php echo $product_count; ?></p>
  <h1 class="gf-category-hero__title"><?php echo $category_name; ?></h1>
  <p class="gf-category-hero__desc"><?php echo $description; ?></p>
</div>
<!-- /wp:html -->
</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// my-account-dashboard — account page with orders list + sidebar nav
	// -------------------------------------------------------------------------

	public static function render_my_account_dashboard( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = $classes[0]          ?? 'gf-my-account';

		$heading         = esc_html( $copy['heading']         ?? 'My Account' );
		$orders_label    = esc_html( $copy['orders_label']    ?? 'Recent Orders' );
		$addresses_label = esc_html( $copy['addresses_label'] ?? 'Saved Addresses' );
		$profile_label   = esc_html( $copy['profile_label']   ?? 'Profile & Password' );
		$wishlist_label  = esc_html( $copy['wishlist_label']  ?? 'Wishlist' );
		$logout_label    = esc_html( $copy['logout_label']    ?? 'Log Out' );

		ob_start(); ?>
<!-- wp:html -->
<section class="<?php echo $css; ?>">
  <h1 class="gf-account-title"><?php echo $heading; ?></h1>
  <div class="gf-account-layout">

    <nav aria-label="Account navigation">
      <div class="gf-account-nav">
        <a href="#orders" class="gf-account-nav-link gf-account-nav-link--active">
          <i class="bi bi-box-seam"></i><?php echo $orders_label; ?>
        </a>
        <a href="#addresses" class="gf-account-nav-link">
          <i class="bi bi-house"></i><?php echo $addresses_label; ?>
        </a>
        <a href="#profile" class="gf-account-nav-link">
          <i class="bi bi-person"></i><?php echo $profile_label; ?>
        </a>
        <a href="#wishlist" class="gf-account-nav-link">
          <i class="bi bi-heart"></i><?php echo $wishlist_label; ?>
        </a>
        <a href="/logout" class="gf-account-nav-link gf-account-nav-link--logout">
          <i class="bi bi-box-arrow-right"></i><?php echo $logout_label; ?>
        </a>
      </div>
    </nav>

    <div id="orders">
      <h2 class="gf-account-section-title"><?php echo $orders_label; ?></h2>
      <div class="gf-orders-table">
        <div class="gf-orders-table-head">
          <span>Order</span><span>Date</span><span>Status</span><span>Total</span><span></span>
        </div>
        <div class="gf-orders-table-row">
          <span class="gf-order-num">#10042</span>
          <span class="gf-order-date">Jan 14, 2026</span>
          <span><span class="badge text-bg-success">Delivered</span></span>
          <span class="gf-order-total">$147.00</span>
          <a href="#" class="gf-order-view">View</a>
        </div>
        <div class="gf-orders-table-row">
          <span class="gf-order-num">#10039</span>
          <span class="gf-order-date">Dec 30, 2025</span>
          <span><span class="badge text-bg-warning">In Transit</span></span>
          <span class="gf-order-total">$79.00</span>
          <a href="#" class="gf-order-view">View</a>
        </div>
        <div class="gf-orders-table-row">
          <span class="gf-order-num">#10021</span>
          <span class="gf-order-date">Nov 18, 2025</span>
          <span><span class="badge text-bg-success">Delivered</span></span>
          <span class="gf-order-total">$229.00</span>
          <a href="#" class="gf-order-view">View</a>
        </div>
      </div>
    </div>

  </div>
</section>
<!-- /wp:html -->
		<?php return ob_get_clean();
	}
}

GrayFox_TB_PatternBuilder::register_renderers( [
	'product-shelf'              => [ GrayFox_TB_Patterns_Ecommerce::class, 'render_product_shelf' ],
	'product-feature-highlight'  => [ GrayFox_TB_Patterns_Ecommerce::class, 'render_product_feature_highlight' ],
	'cart-summary-cta'           => [ GrayFox_TB_Patterns_Ecommerce::class, 'render_cart_summary_cta' ],
	'collection-grid'            => [ GrayFox_TB_Patterns_Ecommerce::class, 'render_collection_grid' ],
	'upsell-strip'               => [ GrayFox_TB_Patterns_Ecommerce::class, 'render_upsell_strip' ],
	'mini-cart-drawer'           => [ GrayFox_TB_Patterns_Ecommerce::class, 'render_mini_cart_drawer' ],
	'empty-cart-state'           => [ GrayFox_TB_Patterns_Ecommerce::class, 'render_empty_cart_state' ],
	'checkout-progress-bar'      => [ GrayFox_TB_Patterns_Ecommerce::class, 'render_checkout_progress_bar' ],
	'order-confirmation'         => [ GrayFox_TB_Patterns_Ecommerce::class, 'render_order_confirmation' ],
	'product-detail-hero'        => [ GrayFox_TB_Patterns_Ecommerce::class, 'render_product_detail_hero' ],
	'product-variants-selector'  => [ GrayFox_TB_Patterns_Ecommerce::class, 'render_product_variants_selector' ],
	'product-reviews-section'    => [ GrayFox_TB_Patterns_Ecommerce::class, 'render_product_reviews_section' ],
	'related-products-strip'     => [ GrayFox_TB_Patterns_Ecommerce::class, 'render_related_products_strip' ],
	'wishlist-grid'              => [ GrayFox_TB_Patterns_Ecommerce::class, 'render_wishlist_grid' ],
	'catalog-filter-bar'         => [ GrayFox_TB_Patterns_Ecommerce::class, 'render_catalog_filter_bar' ],
	'category-hero'              => [ GrayFox_TB_Patterns_Ecommerce::class, 'render_category_hero' ],
	'my-account-dashboard'       => [ GrayFox_TB_Patterns_Ecommerce::class, 'render_my_account_dashboard' ],
] );
