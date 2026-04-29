<?php
/**
 * Features layout renderers: two-column, four-column, tabbed, alternating, icon-list-split.
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
 * Class GrayFox_TB_Patterns_Features
 */
class GrayFox_TB_Patterns_Features {

	// -------------------------------------------------------------------------
	// two-column-cards — wider cards with icon, title, longer body
	// -------------------------------------------------------------------------

	public static function render_two_column_cards( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-feature-card';

		$heading = esc_html( $copy['section_heading'] ?? 'Why Choose Us' );
		$subtext = esc_html( $copy['section_subtext'] ?? '' );

		if ( $subtext ) {
			ob_start(); ?>
<!-- wp:paragraph {"align":"center","textColor":"muted","className":"gf-section-subtext"} -->
<p class="has-text-align-center gf-section-subtext has-muted-color has-text-color"><?php echo $subtext; ?></p>
<!-- /wp:paragraph -->
			<?php $subtext_block = ob_get_clean();
		} else {
			$subtext_block = '';
		}

		$cards = '';
		for ( $i = 1; $i <= 4; $i++ ) {
			$title = esc_html( $copy[ "card_{$i}_title" ] ?? "Feature {$i}" );
			$body  = esc_html( $copy[ "card_{$i}_body" ]  ?? 'Describe this feature and the specific value it delivers to your customer.' );
			$icon  = esc_attr( $copy[ "card_{$i}_icon" ]  ?? 'bi-check-circle' );
			ob_start(); ?>

<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:group {"className":"<?php echo $css; ?> gf-card-two-col","style":{"spacing":{"padding":{"top":"2rem","bottom":"2rem","left":"2rem","right":"2rem"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group <?php echo $css; ?> gf-card-two-col">
<!-- wp:html --><i class="<?php echo $icon; ?> gf-feature-icon-lg" aria-hidden="true"></i><!-- /wp:html -->
<!-- wp:heading {"level":3,"textColor":"primary"} -->
<h3 class="wp-block-heading has-primary-color has-text-color"><?php echo $title; ?></h3>
<!-- /wp:heading -->
<!-- wp:paragraph {"textColor":"muted"} -->
<p class="has-muted-color has-text-color"><?php echo $body; ?></p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:column -->
			<?php $cards .= ob_get_clean();
		}

		ob_start(); ?>
<!-- wp:group {"tagName":"section","align":"full","backgroundColor":"background","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"}}},"layout":{"type":"constrained"}} -->
<section class="wp-block-group alignfull has-background-background-color has-background py-5">

<!-- wp:heading {"level":2,"textAlign":"center","textColor":"primary","className":"gf-section-heading"} -->
<h2 class="wp-block-heading has-text-align-center gf-section-heading has-primary-color has-text-color"><?php echo $heading; ?></h2>
<!-- /wp:heading -->

<?php echo $subtext_block; ?>
<!-- wp:columns {"isStackedOnMobile":true,"style":{"spacing":{"blockGap":"2rem","margin":{"top":"3rem"}}}} -->
<div class="wp-block-columns">
<?php echo $cards; ?>
</div>
<!-- /wp:columns -->

</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// four-column-cards — compact 4-col icon+title+text grid
	// -------------------------------------------------------------------------

	public static function render_four_column_cards( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-four-column-cards gf-section-tint';

		$heading = esc_html( $copy['section_heading'] ?? 'Everything You Need' );
		$subtext = esc_html( $copy['section_subtext'] ?? '' );

		if ( $subtext ) {
			ob_start(); ?>
<!-- wp:paragraph {"align":"center","textColor":"muted","className":"gf-section-subtext"} -->
<p class="has-text-align-center gf-section-subtext has-muted-color has-text-color"><?php echo $subtext; ?></p>
<!-- /wp:paragraph -->

			<?php $subtext_block = ob_get_clean();
		} else {
			$subtext_block = '';
		}

		$cards_html = '';
		for ( $i = 1; $i <= 8; $i++ ) {
			$title = esc_html( $copy[ "card_{$i}_title" ] ?? "Capability {$i}" );
			$body  = esc_html( $copy[ "card_{$i}_body" ]  ?? 'Short description of this capability.' );
			$icon  = esc_attr( $copy[ "card_{$i}_icon" ]  ?? 'bi-check-circle' );
			ob_start(); ?>
    <div class="col">
      <div class="gf-feature-card h-100">
        <div class="gf-feature-icon" aria-hidden="true"><i class="bi <?php echo $icon; ?>"></i></div>
        <h4 class="h6 fw-bold mb-2 text-primary"><?php echo $title; ?></h4>
        <p class="small text-muted mb-0"><?php echo $body; ?></p>
      </div>
    </div>
			<?php $cards_html .= ob_get_clean();
		}

		ob_start(); ?>
<!-- wp:group {"tagName":"section","align":"full","className":"<?php echo $css; ?> py-5"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5">

<!-- wp:heading {"level":2,"textAlign":"center","textColor":"primary","className":"gf-section-heading"} -->
<h2 class="wp-block-heading has-text-align-center gf-section-heading has-primary-color has-text-color"><?php echo $heading; ?></h2>
<!-- /wp:heading -->

<?php echo $subtext_block; ?><!-- wp:html -->
<div class="container py-3">
  <div class="row g-4 row-cols-1 row-cols-sm-2 row-cols-lg-4">
<?php echo $cards_html; ?>
  </div>
</div>
<!-- /wp:html -->

</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// tabbed-features — Bootstrap tab panel, 4 tabs
	// -------------------------------------------------------------------------

	public static function render_tabbed_features( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-tabbed-features';

		$heading = esc_html( $copy['section_heading'] ?? 'How It Works' );
		$subtext = esc_html( $copy['section_subtext'] ?? '' );

		$subtext_html = $subtext ? '<p class="gf-section-subtext" style="text-align:center;color:var(--wp--preset--color--muted);margin-top:0.5rem">' . $subtext . '</p>' : '';

		$nav_items  = '';
		$pane_items = '';
		for ( $i = 0; $i < 4; $i++ ) {
			$n     = $i + 1;
			$label = esc_html( $copy[ "tab_{$n}_label" ] ?? "Step {$n}" );
			$title = esc_html( $copy[ "tab_{$n}_title" ] ?? "Feature {$n}" );
			$body  = esc_html( $copy[ "tab_{$n}_body" ]  ?? 'Explain what happens in this step or what this feature does.' );
			$tid   = "gf-tab-{$i}";
			$active   = $i === 0 ? 'active' : '';
			$selected = $i === 0 ? 'true' : 'false';
			$show     = $i === 0 ? 'show active' : '';
			$nav_items  .= "<button class=\"nav-link {$active}\" id=\"{$tid}-tab\" data-bs-toggle=\"tab\" data-bs-target=\"#{$tid}\" type=\"button\" role=\"tab\" aria-controls=\"{$tid}\" aria-selected=\"{$selected}\">{$label}</button>\n";
			ob_start(); ?>
<div class="tab-pane fade <?php echo $show; ?>" id="<?php echo $tid; ?>" role="tabpanel" aria-labelledby="<?php echo $tid; ?>-tab">
  <div class="gf-tab-content-row">
    <div class="gf-tab-text">
      <h3 style="color:var(--wp--preset--color--primary)"><?php echo $title; ?></h3>
      <p style="color:var(--wp--preset--color--muted)"><?php echo $body; ?></p>
    </div>
    <div class="gf-tab-image">
      <img src="" alt="<?php echo $title; ?> screenshot" class="gf-screenshot-img"/>
    </div>
  </div>
</div>
			<?php $pane_items .= ob_get_clean();
		}

		ob_start(); ?>
<!-- wp:group {"tagName":"section","align":"full","className":"<?php echo $css; ?>","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"}}},"layout":{"type":"constrained"}} -->
<section class="wp-block-group <?php echo $css; ?> alignfull py-5">

<!-- wp:html -->
<style>
.gf-tabbed-features .nav-tabs { border-bottom:2px solid var(--wp--preset--color--muted,#e5e7eb); gap:0; }
.gf-tabbed-features .nav-link { color:var(--wp--preset--color--muted); border:none; border-bottom:2px solid transparent; margin-bottom:-2px; padding:.75rem 1.5rem; font-weight:500; border-radius:0; }
.gf-tabbed-features .nav-link.active { color:var(--wp--preset--color--primary); border-bottom-color:var(--wp--preset--color--primary); background:transparent; }
.gf-tab-content-row { display:flex; gap:3rem; align-items:flex-start; margin-top:2.5rem; flex-wrap:wrap; }
.gf-tab-text { flex:1; min-width:260px; }
.gf-tab-image { flex:1.2; min-width:260px; }
</style>
<h2 style="text-align:center;color:var(--wp--preset--color--primary);margin-bottom:0.5rem"><?php echo $heading; ?></h2>
<?php echo $subtext_html; ?>
<div style="margin-top:2.5rem">
  <ul class="nav nav-tabs" role="tablist">
    <?php echo $nav_items; ?>
  </ul>
  <div class="tab-content">
    <?php echo $pane_items; ?>
  </div>
</div>
<!-- /wp:html -->

</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// alternating-image-text — 2-3 rows alternating image/text
	// -------------------------------------------------------------------------

	public static function render_alternating_image_text( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-alternating';

		$heading = esc_html( $copy['section_heading'] ?? '' );
		$subtext = esc_html( $copy['section_subtext'] ?? '' );

		$header_block = '';
		if ( $heading ) {
			if ( $subtext ) {
				ob_start(); ?>
<!-- wp:paragraph {"align":"center","textColor":"muted","className":"gf-section-subtext"} -->
<p class="has-text-align-center gf-section-subtext has-muted-color has-text-color"><?php echo $subtext; ?></p>
<!-- /wp:paragraph -->
				<?php $sub_block = ob_get_clean();
			} else {
				$sub_block = '';
			}
			ob_start(); ?>
<!-- wp:heading {"level":2,"textAlign":"center","textColor":"primary","className":"gf-section-heading"} -->
<h2 class="wp-block-heading has-text-align-center gf-section-heading has-primary-color has-text-color"><?php echo $heading; ?></h2>
<!-- /wp:heading -->
<?php echo $sub_block; ?>
			<?php $header_block = ob_get_clean();
		}

		$defaults = [
			[ 'See the Whole Picture', 'Get a live view of every transaction as it happens — no refresh required.', 'Learn more' ],
			[ 'Collaborate Without Friction', 'Assign tasks, leave comments, and approve changes directly in your workflow.', '' ],
			[ 'Close With Confidence', 'Automated checks surface discrepancies before they become problems.', 'Get started' ],
		];

		$row_blocks = '';
		for ( $i = 1; $i <= 3; $i++ ) {
			$title = esc_html( $copy[ "row_{$i}_title" ] ?? $defaults[ $i - 1 ][0] );
			$body  = esc_html( $copy[ "row_{$i}_body" ]  ?? $defaults[ $i - 1 ][1] );
			$cta   = esc_html( $copy[ "row_{$i}_cta" ]   ?? $defaults[ $i - 1 ][2] );

			$cta_block = '';
			if ( $cta ) {
				ob_start(); ?>
<!-- wp:buttons {"style":{"spacing":{"margin":{"top":"1.5rem"}}}} -->
<div class="wp-block-buttons">
<!-- wp:button {"backgroundColor":"primary","textColor":"contrast"} -->
<div class="wp-block-button"><a class="wp-block-button__link has-contrast-color has-primary-background-color has-text-color has-background wp-element-button"><?php echo $cta; ?></a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->
				<?php $cta_block = ob_get_clean();
			}

			ob_start(); ?>
<!-- wp:column {"width":"50%"} -->
<div class="wp-block-column" style="flex-basis:50%">
<!-- wp:image {"sizeSlug":"large","className":"gf-feature-img","style":{"border":{"radius":"10px"}}} -->
<figure class="wp-block-image size-large gf-feature-img"><img src="" alt="Feature illustration"/></figure>
<!-- /wp:image -->
</div>
<!-- /wp:column -->
			<?php $img_col = ob_get_clean();
			ob_start(); ?>
<!-- wp:column {"width":"50%","verticalAlignment":"center"} -->
<div class="wp-block-column is-vertically-aligned-center" style="flex-basis:50%">
<!-- wp:heading {"level":3,"textColor":"primary"} -->
<h3 class="wp-block-heading has-primary-color has-text-color"><?php echo $title; ?></h3>
<!-- /wp:heading -->
<!-- wp:paragraph {"textColor":"muted"} -->
<p class="has-muted-color has-text-color"><?php echo $body; ?></p>
<!-- /wp:paragraph --><?php echo $cta_block; ?>
</div>
<!-- /wp:column -->
			<?php $text_col = ob_get_clean();

			$left  = ( $i % 2 === 1 ) ? $img_col  : $text_col;
			$right = ( $i % 2 === 1 ) ? $text_col : $img_col;
			$margin = $i === 1 ? '0' : '4rem';

			ob_start(); ?>
<!-- wp:columns {"isStackedOnMobile":true,"verticalAlignment":"center","style":{"spacing":{"blockGap":"4rem","margin":{"top":"<?php echo $margin; ?>","bottom":"0"}}}} -->
<div class="wp-block-columns are-vertically-aligned-center">
<?php echo $left; ?>
<?php echo $right; ?>
</div>
<!-- /wp:columns -->

			<?php $row_blocks .= ob_get_clean();
		}

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?>","align":"full","backgroundColor":"background","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"}}},"layout":{"type":"constrained"}} -->
<section class="wp-block-group <?php echo $css; ?> alignfull has-background-background-color has-background py-5">

<?php echo $header_block; ?><?php echo $row_blocks; ?>
</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// icon-list-split — icon checklist left, image right
	// -------------------------------------------------------------------------

	public static function render_icon_list_split( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-icon-list-split gf-section-tint';

		$heading   = esc_html( $copy['section_heading'] ?? 'Why Teams Choose Us' );
		$subtext   = esc_html( $copy['section_subtext'] ?? '' );
		$cta_label = esc_html( $copy['cta_label']       ?? '' );

		$defaults = [
			[ 'bi-check-circle-fill', 'Fast onboarding', 'Most teams are live within one business day.' ],
			[ 'bi-check-circle-fill', 'No engineering required', 'Connect your tools with zero code using our guided setup.' ],
			[ 'bi-check-circle-fill', 'SOC 2 Type II certified', 'Your data is encrypted and audited end-to-end.' ],
			[ 'bi-check-circle-fill', '24/7 support', 'Our team is available around the clock, every day of the year.' ],
		];

		$item_blocks = '';
		for ( $i = 1; $i <= 6; $i++ ) {
			$title = esc_html( $copy[ "item_{$i}_title" ] ?? ( $defaults[ $i - 1 ][1] ?? '' ) );
			if ( ! $title ) {
				break;
			}
			$body = esc_html( $copy[ "item_{$i}_body" ] ?? ( $defaults[ $i - 1 ][2] ?? '' ) );
			$icon = esc_attr( $copy[ "item_{$i}_icon" ] ?? ( $defaults[ $i - 1 ][0] ?? 'bi-check-circle-fill' ) );
			if ( $body ) {
				ob_start(); ?>

<!-- wp:paragraph {"style":{"typography":{"fontSize":"0.9rem"}},"textColor":"muted"} -->
<p class="has-muted-color has-text-color"><?php echo $body; ?></p>
<!-- /wp:paragraph -->
				<?php $body_line = ob_get_clean();
			} else {
				$body_line = '';
			}
			ob_start(); ?>
<!-- wp:group {"className":"gf-icon-list-item"} -->
<div class="wp-block-group gf-icon-list-item">
<!-- wp:html --><i class="bi <?php echo $icon; ?> gf-check-icon" aria-hidden="true"></i><!-- /wp:html -->
<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group">
<!-- wp:paragraph {"style":{"typography":{"fontWeight":"600"}},"textColor":"primary"} -->
<p class="has-primary-color has-text-color"><strong><?php echo $title; ?></strong></p>
<!-- /wp:paragraph --><?php echo $body_line; ?>
</div>
<!-- /wp:group -->
</div>
<!-- /wp:group -->

			<?php $item_blocks .= ob_get_clean();
		}

		if ( $cta_label ) {
			ob_start(); ?>
<!-- wp:buttons {"className":"mt-4"} -->
<div class="wp-block-buttons mt-4">
<!-- wp:button {"backgroundColor":"primary","textColor":"contrast"} -->
<div class="wp-block-button"><a class="wp-block-button__link has-contrast-color has-primary-background-color has-text-color has-background wp-element-button"><?php echo $cta_label; ?></a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->
			<?php $cta_block = ob_get_clean();
		} else {
			$cta_block = '';
		}

		if ( $subtext ) {
			ob_start(); ?>
<!-- wp:paragraph {"textColor":"muted","className":"gf-section-subtext"} -->
<p class="gf-section-subtext has-muted-color has-text-color"><?php echo $subtext; ?></p>
<!-- /wp:paragraph -->
			<?php $subtext_block = ob_get_clean();
		} else {
			$subtext_block = '';
		}

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?> py-5","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|70","bottom":"var:preset|spacing|70"}}},"layout":{"type":"constrained"}} -->
<section class="wp-block-group <?php echo $css; ?> py-5 alignfull">

<!-- wp:columns {"isStackedOnMobile":true,"verticalAlignment":"center","style":{"spacing":{"blockGap":"5rem"}}} -->
<div class="wp-block-columns is-stacked-on-mobile are-vertically-aligned-center">

<!-- wp:column {"width":"50%"} -->
<div class="wp-block-column" style="flex-basis:50%">
<!-- wp:heading {"level":2,"textColor":"primary","className":"gf-section-heading"} -->
<h2 class="wp-block-heading gf-section-heading has-primary-color has-text-color"><?php echo $heading; ?></h2>
<!-- /wp:heading -->
<?php echo $subtext_block; ?>
<!-- wp:group {"className":"d-flex flex-column gap-4"} -->
<div class="wp-block-group d-flex flex-column gap-4">
<?php echo $item_blocks; ?>
</div>
<!-- /wp:group -->
<?php echo $cta_block; ?>
</div>
<!-- /wp:column -->

<!-- wp:column {"width":"50%"} -->
<div class="wp-block-column" style="flex-basis:50%">
<!-- wp:image {"sizeSlug":"large","className":"gf-feature-img","style":{"border":{"radius":"12px"}}} -->
<figure class="wp-block-image size-large gf-feature-img"><img src="" alt="Feature illustration — replace with your product screenshot"/></figure>
<!-- /wp:image -->
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
	'two-column-cards'      => [ GrayFox_TB_Patterns_Features::class, 'render_two_column_cards' ],
	'four-column-cards'     => [ GrayFox_TB_Patterns_Features::class, 'render_four_column_cards' ],
	'tabbed-features'       => [ GrayFox_TB_Patterns_Features::class, 'render_tabbed_features' ],
	'alternating-image-text'=> [ GrayFox_TB_Patterns_Features::class, 'render_alternating_image_text' ],
	'icon-list-split'       => [ GrayFox_TB_Patterns_Features::class, 'render_icon_list_split' ],
] );
