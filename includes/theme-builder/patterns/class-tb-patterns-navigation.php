<?php
/**
 * Navigation / wayfinding pattern renderers.
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
 * Class GrayFox_TB_Patterns_Navigation
 */
class GrayFox_TB_Patterns_Navigation {

	// -------------------------------------------------------------------------
	// anchor-nav-bar — sticky in-page anchor navigation with CTA
	// -------------------------------------------------------------------------

	public static function render_anchor_nav_bar( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-anchor-nav';

		$link1        = esc_html( $copy['link1_label']  ?? 'Overview' );
		$link1_anchor = esc_attr( $copy['link1_anchor'] ?? '#overview' );
		$link2        = esc_html( $copy['link2_label']  ?? 'Features' );
		$link2_anchor = esc_attr( $copy['link2_anchor'] ?? '#features' );
		$link3        = esc_html( $copy['link3_label']  ?? 'Pricing' );
		$link3_anchor = esc_attr( $copy['link3_anchor'] ?? '#pricing' );
		$link4        = esc_html( $copy['link4_label']  ?? 'FAQ' );
		$link4_anchor = esc_attr( $copy['link4_anchor'] ?? '#faq' );
		$cta_label    = esc_html( $copy['cta_label']    ?? 'Get started' );

		ob_start(); ?>
<!-- wp:group {"className":"<?php echo $css; ?>","align":"full","backgroundColor":"background"} -->
<div class="wp-block-group alignfull <?php echo $css; ?> has-background-background-color has-background">
<!-- wp:html -->
<div class="container">
  <div class="gf-anchor-nav-inner">
    <nav class="gf-anchor-links" aria-label="Page sections">
      <a href="<?php echo $link1_anchor; ?>"><?php echo $link1; ?></a>
      <a href="<?php echo $link2_anchor; ?>"><?php echo $link2; ?></a>
      <a href="<?php echo $link3_anchor; ?>"><?php echo $link3; ?></a>
      <a href="<?php echo $link4_anchor; ?>"><?php echo $link4; ?></a>
    </nav>
    <a href="#" class="btn btn-primary btn-sm"><?php echo $cta_label; ?></a>
  </div>
</div>
<!-- /wp:html -->
</div>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// breadcrumb-band — simple 3-level breadcrumb strip
	// -------------------------------------------------------------------------

	public static function render_breadcrumb_band( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-breadcrumb-band gf-section-tint';

		$home_label    = esc_html( $copy['home_label']    ?? 'Home' );
		$parent_label  = esc_html( $copy['parent_label']  ?? 'Blog' );
		$current_label = esc_html( $copy['current_label'] ?? 'Article Title' );

		ob_start(); ?>
<!-- wp:group {"className":"<?php echo $css; ?>","align":"full"} -->
<div class="wp-block-group alignfull <?php echo $css; ?>">
<!-- wp:html -->
<div class="container">
  <nav class="gf-breadcrumbs" aria-label="Breadcrumb">
    <a href="/"><?php echo $home_label; ?></a>
    <span class="gf-breadcrumbs-sep" aria-hidden="true"><i class="bi bi-chevron-right"></i></span>
    <a href="#"><?php echo $parent_label; ?></a>
    <span class="gf-breadcrumbs-sep" aria-hidden="true"><i class="bi bi-chevron-right"></i></span>
    <span style="color:var(--gf-text);font-weight:600"><?php echo $current_label; ?></span>
  </nav>
</div>
<!-- /wp:html -->
</div>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// step-indicator — 4-step horizontal progress indicator
	// -------------------------------------------------------------------------

	public static function render_step_indicator( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-step-indicator';

		$s1     = esc_html( $copy['step1_label'] ?? 'Account Info' );
		$s2     = esc_html( $copy['step2_label'] ?? 'Plan Selection' );
		$s3     = esc_html( $copy['step3_label'] ?? 'Payment' );
		$s4     = esc_html( $copy['step4_label'] ?? 'Confirmation' );
		$active = intval( $copy['active_step']   ?? 2 );

		$steps = [ $s1, $s2, $s3, $s4 ];
		$step_html = '';
		foreach ( $steps as $i => $label ) {
			$num      = $i + 1;
			$done     = ( $num <= $active );
			$num_cls  = $done ? 'gf-step-num gf-step-num--done' : 'gf-step-num';
			$lbl_cls  = $done ? 'gf-step-label gf-step-label--done' : 'gf-step-label';
			$sep_cls  = ( $num < $active ) ? 'gf-step-sep gf-step-sep--done' : 'gf-step-sep';

			$step_html .= "<div class=\"gf-step-item\"><span class=\"{$num_cls}\">{$num}</span><span class=\"{$lbl_cls}\">{$label}</span></div>";
			if ( $num < 4 ) {
				$step_html .= "<div class=\"{$sep_cls}\"></div>";
			}
		}

		ob_start(); ?>
<!-- wp:group {"className":"<?php echo $css; ?>","align":"full","backgroundColor":"background"} -->
<div class="wp-block-group alignfull <?php echo $css; ?> has-background-background-color has-background">
<!-- wp:html -->
<div class="gf-step-track">
<?php echo $step_html; ?>
</div>
<!-- /wp:html -->
</div>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// table-of-contents — sidebar TOC card with 6 links
	// -------------------------------------------------------------------------

	public static function render_table_of_contents( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-toc';

		$heading = esc_html( $copy['heading']  ?? 'In This Article' );
		$s1      = esc_html( $copy['section1'] ?? 'Introduction' );
		$s2      = esc_html( $copy['section2'] ?? 'Key Concepts' );
		$s3      = esc_html( $copy['section3'] ?? 'Step-by-Step Guide' );
		$s4      = esc_html( $copy['section4'] ?? 'Common Mistakes to Avoid' );
		$s5      = esc_html( $copy['section5'] ?? 'Advanced Techniques' );
		$s6      = esc_html( $copy['section6'] ?? 'Conclusion' );

		ob_start(); ?>
<!-- wp:group {"className":"<?php echo $css; ?>","style":{"border":{"width":"1px","color":"var(--wp--preset--color--muted)","radius":"10px"},"spacing":{"padding":{"top":"1.5rem","bottom":"1.5rem","left":"1.5rem","right":"1.5rem"}}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group <?php echo $css; ?>">

<!-- wp:heading {"level":4,"textColor":"primary","style":{"typography":{"fontSize":"0.9rem","textTransform":"uppercase","letterSpacing":"0.08em"}}} -->
<h4 class="wp-block-heading has-primary-color has-text-color"><?php echo $heading; ?></h4>
<!-- /wp:heading -->

<!-- wp:separator {"backgroundColor":"muted"} --><hr class="wp-block-separator has-text-color has-muted-color has-alpha-channel-opacity"/><!-- /wp:separator -->

<!-- wp:group {"style":{"spacing":{"blockGap":"0.5rem"}},"layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group">
<!-- wp:paragraph {"style":{"typography":{"fontSize":"0.9rem"}},"textColor":"foreground"} --><p class="has-foreground-color has-text-color"><a href="#s1" style="color:var(--wp--preset--color--primary);text-decoration:none">1. <?php echo $s1; ?></a></p><!-- /wp:paragraph -->
<!-- wp:paragraph {"style":{"typography":{"fontSize":"0.9rem"}},"textColor":"foreground"} --><p class="has-foreground-color has-text-color"><a href="#s2" style="color:var(--wp--preset--color--primary);text-decoration:none">2. <?php echo $s2; ?></a></p><!-- /wp:paragraph -->
<!-- wp:paragraph {"style":{"typography":{"fontSize":"0.9rem"}},"textColor":"foreground"} --><p class="has-foreground-color has-text-color"><a href="#s3" style="color:var(--wp--preset--color--primary);text-decoration:none">3. <?php echo $s3; ?></a></p><!-- /wp:paragraph -->
<!-- wp:paragraph {"style":{"typography":{"fontSize":"0.9rem"}},"textColor":"foreground"} --><p class="has-foreground-color has-text-color"><a href="#s4" style="color:var(--wp--preset--color--primary);text-decoration:none">4. <?php echo $s4; ?></a></p><!-- /wp:paragraph -->
<!-- wp:paragraph {"style":{"typography":{"fontSize":"0.9rem"}},"textColor":"foreground"} --><p class="has-foreground-color has-text-color"><a href="#s5" style="color:var(--wp--preset--color--primary);text-decoration:none">5. <?php echo $s5; ?></a></p><!-- /wp:paragraph -->
<!-- wp:paragraph {"style":{"typography":{"fontSize":"0.9rem"}},"textColor":"foreground"} --><p class="has-foreground-color has-text-color"><a href="#s6" style="color:var(--wp--preset--color--primary);text-decoration:none">6. <?php echo $s6; ?></a></p><!-- /wp:paragraph -->
</div>
<!-- /wp:group -->

</div>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}
}

GrayFox_TB_PatternBuilder::register_renderers( [
	'anchor-nav-bar'     => [ GrayFox_TB_Patterns_Navigation::class, 'render_anchor_nav_bar' ],
	'breadcrumb-band'    => [ GrayFox_TB_Patterns_Navigation::class, 'render_breadcrumb_band' ],
	'step-indicator'     => [ GrayFox_TB_Patterns_Navigation::class, 'render_step_indicator' ],
	'table-of-contents'  => [ GrayFox_TB_Patterns_Navigation::class, 'render_table_of_contents' ],
] );
