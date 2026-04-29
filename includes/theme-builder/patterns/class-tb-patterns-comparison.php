<?php
/**
 * Comparison pattern renderers: vs-competitor-table, tier-comparison-cards.
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
 * Class GrayFox_TB_Patterns_Comparison
 */
class GrayFox_TB_Patterns_Comparison {

	// -------------------------------------------------------------------------
	// vs-competitor-table — feature comparison table vs. competitors
	// -------------------------------------------------------------------------

	public static function render_vs_competitor_table( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-vs-table';

		$heading    = esc_html( $copy['section_heading'] ?? 'Why Choose Us?' );
		$us_label   = esc_html( $copy['us_label']        ?? 'Us' );
		$them_label = esc_html( $copy['them_label']      ?? 'Others' );

		$f1       = esc_html( $copy['f1']      ?? 'Real-time sync' );
		$f2       = esc_html( $copy['f2']      ?? 'Unlimited users' );
		$f3       = esc_html( $copy['f3']      ?? 'Priority support' );
		$f4       = esc_html( $copy['f4']      ?? 'API access' );
		$f5       = esc_html( $copy['f5']      ?? 'Custom reports' );
		$f6       = esc_html( $copy['f6']      ?? 'No setup fees' );
		$them_f1  = esc_html( $copy['them_f1'] ?? '✗' );
		$them_f2  = esc_html( $copy['them_f2'] ?? '✗' );
		$them_f3  = esc_html( $copy['them_f3'] ?? 'Add-on' );
		$them_f4  = esc_html( $copy['them_f4'] ?? 'Limited' );
		$them_f5  = esc_html( $copy['them_f5'] ?? '✗' );
		$them_f6  = esc_html( $copy['them_f6'] ?? '✗' );

		$row = function( $feature, $them_val ) {
			ob_start(); ?>
<!-- wp:group {"style":{"border":{"bottom":{"color":"var(--wp--preset--color--muted)","width":"1px"}},"spacing":{"padding":{"top":"0.9rem","bottom":"0.9rem"}}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group">
<!-- wp:paragraph {"textColor":"foreground","style":{"layout":{"flexGrow":1}}} --><p class="has-foreground-color has-text-color" style="flex:1"><?php echo $feature; ?></p><!-- /wp:paragraph -->
<!-- wp:paragraph {"textColor":"primary","style":{"typography":{"fontWeight":"700","textAlign":"center"},"layout":{"selfStretch":"fixed","flexSize":"120px"}}} --><p class="has-primary-color has-text-color" style="width:120px;text-align:center;font-weight:700">✓</p><!-- /wp:paragraph -->
<!-- wp:paragraph {"textColor":"muted","style":{"typography":{"textAlign":"center"},"layout":{"selfStretch":"fixed","flexSize":"120px"}}} --><p class="has-muted-color has-text-color" style="width:120px;text-align:center"><?php echo $them_val; ?></p><!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
			<?php return ob_get_clean();
		};

		$r1 = $row( $f1, $them_f1 );
		$r2 = $row( $f2, $them_f2 );
		$r3 = $row( $f3, $them_f3 );
		$r4 = $row( $f4, $them_f4 );
		$r5 = $row( $f5, $them_f5 );
		$r6 = $row( $f6, $them_f6 );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?>","backgroundColor":"background","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> has-background-background-color has-background py-5">

<!-- wp:heading {"textAlign":"center","textColor":"primary"} -->
<h2 class="wp-block-heading has-text-align-center has-primary-color has-text-color"><?php echo $heading; ?></h2>
<!-- /wp:heading -->

<!-- wp:group {"style":{"border":{"width":"1px","color":"var(--wp--preset--color--muted)","radius":"12px"},"spacing":{"padding":{"top":"0","bottom":"0","left":"0","right":"0"},"margin":{"top":"2.5rem"}}},"backgroundColor":"background","layout":{"type":"constrained"}} -->
<div class="wp-block-group has-background-background-color has-background">

<!-- wp:group {"style":{"border":{"bottom":{"color":"var(--wp--preset--color--muted)","width":"1px"}},"spacing":{"padding":{"top":"1rem","bottom":"1rem","left":"1.5rem","right":"1.5rem"}}},"layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group">
<!-- wp:paragraph {"style":{"layout":{"flexGrow":1}}} --><p style="flex:1"></p><!-- /wp:paragraph -->
<!-- wp:paragraph {"style":{"typography":{"fontWeight":"700","fontSize":"0.9rem","textAlign":"center"},"layout":{"selfStretch":"fixed","flexSize":"120px"}},"textColor":"primary"} --><p class="has-primary-color has-text-color" style="width:120px;text-align:center;font-weight:700"><?php echo $us_label; ?></p><!-- /wp:paragraph -->
<!-- wp:paragraph {"style":{"typography":{"fontWeight":"700","fontSize":"0.9rem","textAlign":"center"},"layout":{"selfStretch":"fixed","flexSize":"120px"}},"textColor":"muted"} --><p class="has-muted-color has-text-color" style="width:120px;text-align:center;font-weight:700"><?php echo $them_label; ?></p><!-- /wp:paragraph -->
</div>
<!-- /wp:group -->

<!-- wp:group {"style":{"spacing":{"padding":{"left":"1.5rem","right":"1.5rem"}}},"layout":{"type":"flex","orientation":"vertical","blockGap":"0"}} -->
<div class="wp-block-group">
<?php echo $r1; ?>
<?php echo $r2; ?>
<?php echo $r3; ?>
<?php echo $r4; ?>
<?php echo $r5; ?>
<?php echo $r6; ?>
</div>
<!-- /wp:group -->

</div>
<!-- /wp:group -->

</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// tier-comparison-cards — 3-column plan cards with included/excluded lists
	// -------------------------------------------------------------------------

	public static function render_tier_comparison_cards( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-tier-compare';

		$heading  = esc_html( $copy['section_heading'] ?? 'Compare Plans' );
		$subtext  = esc_html( $copy['subtext']         ?? 'Pick the plan that fits your team.' );

		$t1_name  = esc_html( $copy['t1_name']     ?? 'Free' );
		$t1_price = esc_html( $copy['t1_price']    ?? '$0' );
		$t1_inc   = esc_html( $copy['t1_includes'] ?? '5 projects, 1 user, basic analytics' );
		$t1_exc   = esc_html( $copy['t1_excludes'] ?? 'No API access, no priority support' );

		$t2_name  = esc_html( $copy['t2_name']     ?? 'Pro' );
		$t2_price = esc_html( $copy['t2_price']    ?? '$49/mo' );
		$t2_inc   = esc_html( $copy['t2_includes'] ?? 'Unlimited projects, 10 users, full analytics, API access' );
		$t2_exc   = esc_html( $copy['t2_excludes'] ?? 'No dedicated account manager' );

		$t3_name  = esc_html( $copy['t3_name']     ?? 'Enterprise' );
		$t3_price = esc_html( $copy['t3_price']    ?? 'Custom' );
		$t3_inc   = esc_html( $copy['t3_includes'] ?? 'Everything in Pro plus dedicated support, SSO, SLA, custom contracts' );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?>","backgroundColor":"background","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> has-background-background-color has-background py-5">

<!-- wp:heading {"textAlign":"center","textColor":"primary"} -->
<h2 class="wp-block-heading has-text-align-center has-primary-color has-text-color"><?php echo $heading; ?></h2>
<!-- /wp:heading -->
<!-- wp:paragraph {"align":"center","textColor":"muted"} -->
<p class="has-text-align-center has-muted-color has-text-color"><?php echo $subtext; ?></p>
<!-- /wp:paragraph -->

<!-- wp:columns {"style":{"spacing":{"blockGap":"1.5rem","margin":{"top":"2.5rem"}}},"verticalAlignment":"top"} -->
<div class="wp-block-columns are-vertically-aligned-top">

<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:group {"style":{"border":{"width":"1px","color":"var(--wp--preset--color--muted)","radius":"10px"},"spacing":{"padding":{"top":"2rem","bottom":"2rem","left":"1.75rem","right":"1.75rem"}}},"backgroundColor":"background","layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group has-background-background-color has-background">
<!-- wp:heading {"level":3,"textColor":"primary"} --><h3 class="wp-block-heading has-primary-color has-text-color"><?php echo $t1_name; ?></h3><!-- /wp:heading -->
<!-- wp:paragraph {"style":{"typography":{"fontSize":"2rem","fontWeight":"700"}},"textColor":"foreground"} --><p class="has-foreground-color has-text-color"><?php echo $t1_price; ?></p><!-- /wp:paragraph -->
<!-- wp:separator {"backgroundColor":"muted","style":{"spacing":{"margin":{"top":"1rem","bottom":"1rem"}}}} --><hr class="wp-block-separator has-text-color has-muted-color has-alpha-channel-opacity"/><!-- /wp:separator -->
<!-- wp:paragraph {"style":{"typography":{"fontWeight":"600","fontSize":"0.85rem","textTransform":"uppercase"}},"textColor":"primary"} --><p class="has-primary-color has-text-color">What's included</p><!-- /wp:paragraph -->
<!-- wp:paragraph {"textColor":"foreground"} --><p class="has-foreground-color has-text-color"><?php echo $t1_inc; ?></p><!-- /wp:paragraph -->
<!-- wp:separator {"backgroundColor":"muted","style":{"spacing":{"margin":{"top":"1rem","bottom":"1rem"}}}} --><hr class="wp-block-separator has-text-color has-muted-color has-alpha-channel-opacity"/><!-- /wp:separator -->
<!-- wp:paragraph {"style":{"typography":{"fontWeight":"600","fontSize":"0.85rem","textTransform":"uppercase"}},"textColor":"muted"} --><p class="has-muted-color has-text-color">Not included</p><!-- /wp:paragraph -->
<!-- wp:paragraph {"textColor":"muted"} --><p class="has-muted-color has-text-color"><?php echo $t1_exc; ?></p><!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:group {"style":{"border":{"width":"2px","color":"var(--wp--preset--color--primary)","radius":"10px"},"spacing":{"padding":{"top":"2rem","bottom":"2rem","left":"1.75rem","right":"1.75rem"}}},"backgroundColor":"background","layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group has-background-background-color has-background">
<!-- wp:heading {"level":3,"textColor":"primary"} --><h3 class="wp-block-heading has-primary-color has-text-color"><?php echo $t2_name; ?></h3><!-- /wp:heading -->
<!-- wp:paragraph {"style":{"typography":{"fontSize":"2rem","fontWeight":"700"}},"textColor":"primary"} --><p class="has-primary-color has-text-color"><?php echo $t2_price; ?></p><!-- /wp:paragraph -->
<!-- wp:separator {"backgroundColor":"muted","style":{"spacing":{"margin":{"top":"1rem","bottom":"1rem"}}}} --><hr class="wp-block-separator has-text-color has-muted-color has-alpha-channel-opacity"/><!-- /wp:separator -->
<!-- wp:paragraph {"style":{"typography":{"fontWeight":"600","fontSize":"0.85rem","textTransform":"uppercase"}},"textColor":"primary"} --><p class="has-primary-color has-text-color">What's included</p><!-- /wp:paragraph -->
<!-- wp:paragraph {"textColor":"foreground"} --><p class="has-foreground-color has-text-color"><?php echo $t2_inc; ?></p><!-- /wp:paragraph -->
<!-- wp:separator {"backgroundColor":"muted","style":{"spacing":{"margin":{"top":"1rem","bottom":"1rem"}}}} --><hr class="wp-block-separator has-text-color has-muted-color has-alpha-channel-opacity"/><!-- /wp:separator -->
<!-- wp:paragraph {"style":{"typography":{"fontWeight":"600","fontSize":"0.85rem","textTransform":"uppercase"}},"textColor":"muted"} --><p class="has-muted-color has-text-color">Not included</p><!-- /wp:paragraph -->
<!-- wp:paragraph {"textColor":"muted"} --><p class="has-muted-color has-text-color"><?php echo $t2_exc; ?></p><!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:group {"style":{"border":{"width":"1px","color":"var(--wp--preset--color--muted)","radius":"10px"},"spacing":{"padding":{"top":"2rem","bottom":"2rem","left":"1.75rem","right":"1.75rem"}}},"backgroundColor":"background","layout":{"type":"flex","orientation":"vertical"}} -->
<div class="wp-block-group has-background-background-color has-background">
<!-- wp:heading {"level":3,"textColor":"primary"} --><h3 class="wp-block-heading has-primary-color has-text-color"><?php echo $t3_name; ?></h3><!-- /wp:heading -->
<!-- wp:paragraph {"style":{"typography":{"fontSize":"2rem","fontWeight":"700"}},"textColor":"foreground"} --><p class="has-foreground-color has-text-color"><?php echo $t3_price; ?></p><!-- /wp:paragraph -->
<!-- wp:separator {"backgroundColor":"muted","style":{"spacing":{"margin":{"top":"1rem","bottom":"1rem"}}}} --><hr class="wp-block-separator has-text-color has-muted-color has-alpha-channel-opacity"/><!-- /wp:separator -->
<!-- wp:paragraph {"style":{"typography":{"fontWeight":"600","fontSize":"0.85rem","textTransform":"uppercase"}},"textColor":"primary"} --><p class="has-primary-color has-text-color">What's included</p><!-- /wp:paragraph -->
<!-- wp:paragraph {"textColor":"foreground"} --><p class="has-foreground-color has-text-color"><?php echo $t3_inc; ?></p><!-- /wp:paragraph -->
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
}

GrayFox_TB_PatternBuilder::register_renderers( [
	'vs-competitor-table'    => [ GrayFox_TB_Patterns_Comparison::class, 'render_vs_competitor_table' ],
	'tier-comparison-cards'  => [ GrayFox_TB_Patterns_Comparison::class, 'render_tier_comparison_cards' ],
] );
