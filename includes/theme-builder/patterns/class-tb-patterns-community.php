<?php
/**
 * Community / social pattern renderers.
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
 * Class GrayFox_TB_Patterns_Community
 */
class GrayFox_TB_Patterns_Community {

	// -------------------------------------------------------------------------
	// social-feed-embed — 4 social post cards with handle header
	// -------------------------------------------------------------------------

	public static function render_social_feed_embed( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-social-feed';

		$heading  = esc_html( $copy['section_heading'] ?? 'Follow Along' );
		$subtext  = esc_html( $copy['subtext']         ?? 'Join the conversation on social media.' );
		$handle   = esc_html( $copy['handle']          ?? '@yourbrand' );
		$platform = esc_html( $copy['platform']        ?? 'Instagram' );

		$post_card = function( $caption ) {
			ob_start(); ?>
<!-- wp:group {"className":"gf-social-card","backgroundColor":"background"} -->
<div class="wp-block-group gf-social-card has-background-background-color has-background">
<!-- wp:image {"sizeSlug":"full","className":"gf-social-post-img"} --><figure class="wp-block-image size-full gf-social-post-img"><img src="" alt="Social post" /></figure><!-- /wp:image -->
<!-- wp:group {"className":"gf-social-card-body"} -->
<div class="wp-block-group gf-social-card-body">
<!-- wp:paragraph {"textColor":"foreground"} --><p class="has-foreground-color has-text-color"><?php echo $caption; ?></p><!-- /wp:paragraph -->
<!-- wp:paragraph {"textColor":"muted"} --><p class="has-muted-color has-text-color">&#10084;&#65039; 142 · &#128172; 18</p><!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:group -->
			<?php return ob_get_clean();
		};

		$p1 = $post_card( 'Excited to announce our biggest update yet! &#128640; #product #launch' );
		$p2 = $post_card( 'Behind the scenes at our team offsite. Great energy! &#10024; #team' );
		$p3 = $post_card( 'Customer spotlight: how one team cut their workload in half &#128588; #customerwin' );
		$p4 = $post_card( 'New blog post: 5 ways to get more done with less effort. Link in bio &#128070;' );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?> py-5","backgroundColor":"background","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5 has-background-background-color has-background">

<!-- wp:group {"className":"gf-social-feed-header"} -->
<div class="wp-block-group gf-social-feed-header">
<!-- wp:group {"className":"gf-social-feed-title"} -->
<div class="wp-block-group gf-social-feed-title">
<!-- wp:heading {"textColor":"primary"} --><h2 class="wp-block-heading has-primary-color has-text-color"><?php echo $heading; ?></h2><!-- /wp:heading -->
<!-- wp:paragraph {"textColor":"muted"} --><p class="has-muted-color has-text-color"><?php echo $handle; ?> on <?php echo $platform; ?></p><!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
<!-- wp:buttons -->
<div class="wp-block-buttons">
<!-- wp:button {"variant":"outline","textColor":"primary"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link has-primary-color has-text-color wp-element-button">Follow us</a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->
</div>
<!-- /wp:group -->

<!-- wp:paragraph {"textColor":"muted"} -->
<p class="has-muted-color has-text-color"><?php echo $subtext; ?></p>
<!-- /wp:paragraph -->

<!-- wp:columns -->
<div class="wp-block-columns">
<!-- wp:column --><div class="wp-block-column"><?php echo $p1; ?></div><!-- /wp:column -->
<!-- wp:column --><div class="wp-block-column"><?php echo $p2; ?></div><!-- /wp:column -->
<!-- wp:column --><div class="wp-block-column"><?php echo $p3; ?></div><!-- /wp:column -->
<!-- wp:column --><div class="wp-block-column"><?php echo $p4; ?></div><!-- /wp:column -->
</div>
<!-- /wp:columns -->

</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// community-cta — dark primary CTA with testimonial quotes
	// -------------------------------------------------------------------------

	public static function render_community_cta( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-community-cta';

		$heading       = esc_html( $copy['heading']        ?? 'Join Our Community' );
		$subtext       = esc_html( $copy['subtext']        ?? 'Connect with thousands of users, share ideas, and get answers fast.' );
		$member_count  = esc_html( $copy['member_count']   ?? '12,400+' );
		$member_label  = esc_html( $copy['member_label']   ?? 'members and growing' );
		$cta_primary   = esc_html( $copy['cta_primary']    ?? 'Join the forum' );
		$cta_secondary = esc_html( $copy['cta_secondary']  ?? 'Join Discord' );
		$quote1        = esc_html( $copy['quote1']          ?? 'This community helped me solve a problem in under 10 minutes.' );
		$quote2        = esc_html( $copy['quote2']          ?? 'The experts here are incredibly helpful and responsive.' );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?>","backgroundColor":"primary","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> has-primary-background-color has-background py-5">

<!-- wp:columns {"className":"container-xl","verticalAlignment":"center"} -->
<div class="wp-block-columns container-xl are-vertically-aligned-center">

<!-- wp:column {"width":"60%"} -->
<div class="wp-block-column" style="flex-basis:60%">
<!-- wp:heading {"textColor":"contrast"} -->
<h2 class="wp-block-heading has-contrast-color has-text-color"><?php echo $heading; ?></h2>
<!-- /wp:heading -->
<!-- wp:paragraph {"textColor":"contrast"} -->
<p class="has-contrast-color has-text-color opacity-75"><?php echo $subtext; ?></p>
<!-- /wp:paragraph -->
<!-- wp:html -->
<div class="d-flex align-items-center gap-2 mt-4">
  <i class="bi bi-people-fill fs-5 text-white" aria-hidden="true"></i>
  <p class="mb-0 text-white"><strong><?php echo $member_count; ?></strong> <?php echo $member_label; ?></p>
</div>
<!-- /wp:html -->
<!-- wp:buttons {"className":"mt-4"} -->
<div class="wp-block-buttons mt-4">
<!-- wp:button {"backgroundColor":"contrast","textColor":"primary"} -->
<div class="wp-block-button"><a class="wp-block-button__link has-primary-color has-contrast-background-color has-text-color has-background wp-element-button"><?php echo $cta_primary; ?></a></div>
<!-- /wp:button -->
<!-- wp:button {"className":"is-style-outline","textColor":"contrast"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link has-contrast-color has-text-color wp-element-button"><?php echo $cta_secondary; ?></a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->
</div>
<!-- /wp:column -->

<!-- wp:column {"width":"40%"} -->
<div class="wp-block-column" style="flex-basis:40%">
<!-- wp:html -->
<div class="d-flex flex-column gap-3">
  <div class="d-flex align-items-start gap-3 p-3 rounded-3" style="border:1px solid rgba(255,255,255,.25)">
    <i class="bi bi-chat-quote-fill fs-4 flex-shrink-0 text-white" aria-hidden="true"></i>
    <p class="mb-0 text-white small lh-lg">&ldquo;<?php echo $quote1; ?>&rdquo;</p>
  </div>
  <div class="d-flex align-items-start gap-3 p-3 rounded-3" style="border:1px solid rgba(255,255,255,.25)">
    <i class="bi bi-trophy-fill fs-4 flex-shrink-0 text-white" aria-hidden="true"></i>
    <p class="mb-0 text-white small lh-lg">&ldquo;<?php echo $quote2; ?>&rdquo;</p>
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
	// ugc-wall — 3-column user-generated content wall
	// -------------------------------------------------------------------------

	public static function render_ugc_wall( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-ugc-wall gf-section-tint';

		$heading = esc_html( $copy['section_heading'] ?? 'What Our Customers Are Saying' );
		$subtext = esc_html( $copy['subtext']         ?? 'Real reviews from real users across the web.' );

		$ugc_card = function( $handle, $platform, $text ) {
			ob_start(); ?>
<!-- wp:group {"className":"gf-ugc-card","backgroundColor":"background"} -->
<div class="wp-block-group gf-ugc-card has-background-background-color has-background">
<!-- wp:group {"className":"gf-ugc-card-header"} -->
<div class="wp-block-group gf-ugc-card-header">
<!-- wp:image {"className":"gf-user-avatar","sizeSlug":"thumbnail"} --><figure class="wp-block-image size-thumbnail gf-user-avatar"><img src="" alt="<?php echo $handle; ?> avatar" /></figure><!-- /wp:image -->
<!-- wp:group {"className":"gf-ugc-card-meta"} -->
<div class="wp-block-group gf-ugc-card-meta">
<!-- wp:paragraph {"textColor":"foreground"} --><p class="has-foreground-color has-text-color"><?php echo $handle; ?></p><!-- /wp:paragraph -->
<!-- wp:paragraph {"textColor":"muted"} --><p class="has-muted-color has-text-color"><?php echo $platform; ?></p><!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:group -->
<!-- wp:paragraph {"textColor":"foreground"} --><p class="has-foreground-color has-text-color"><?php echo $text; ?></p><!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
			<?php return ob_get_clean();
		};

		$c1a = $ugc_card( '@sarah_k', 'Twitter / X', 'Switched to this last month and never looked back. The onboarding was incredibly smooth. &#128588;' );
		$c1b = $ugc_card( '@dev_mike', 'LinkedIn', 'Our team\'s productivity went up 40% in the first week. Highly recommend for engineering orgs.' );
		$c2a = $ugc_card( '@jenna_writes', 'Instagram', 'Finally a product that does what it says on the tin. No bloat, no confusion. &#10024;' );
		$c2b = $ugc_card( '@ops_lead', 'Twitter / X', 'The support team is exceptional. Any issue is resolved within the hour.' );
		$c3a = $ugc_card( '@techleader', 'LinkedIn', 'We evaluated 6 tools and this was the clear winner. ROI was evident in the first quarter.' );
		$c3b = $ugc_card( '@designerfiona', 'Instagram', 'The UI is gorgeous — and it\'s fast. Finally a tool that respects my time. &#128175;' );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?> py-5","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5">

<!-- wp:heading {"textAlign":"center","textColor":"primary"} -->
<h2 class="wp-block-heading has-text-align-center has-primary-color has-text-color"><?php echo $heading; ?></h2>
<!-- /wp:heading -->
<!-- wp:paragraph {"align":"center","textColor":"muted"} -->
<p class="has-text-align-center has-muted-color has-text-color"><?php echo $subtext; ?></p>
<!-- /wp:paragraph -->

<!-- wp:columns -->
<div class="wp-block-columns">
<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:group {"className":"gf-ugc-col"} -->
<div class="wp-block-group gf-ugc-col">
<?php echo $c1a; ?>
<?php echo $c1b; ?>
</div>
<!-- /wp:group -->
</div>
<!-- /wp:column -->
<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:group {"className":"gf-ugc-col"} -->
<div class="wp-block-group gf-ugc-col">
<?php echo $c2a; ?>
<?php echo $c2b; ?>
</div>
<!-- /wp:group -->
</div>
<!-- /wp:column -->
<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:group {"className":"gf-ugc-col"} -->
<div class="wp-block-group gf-ugc-col">
<?php echo $c3a; ?>
<?php echo $c3b; ?>
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
	'social-feed-embed' => [ GrayFox_TB_Patterns_Community::class, 'render_social_feed_embed' ],
	'community-cta'     => [ GrayFox_TB_Patterns_Community::class, 'render_community_cta' ],
	'ugc-wall'          => [ GrayFox_TB_Patterns_Community::class, 'render_ugc_wall' ],
] );
