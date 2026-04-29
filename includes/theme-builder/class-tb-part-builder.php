<?php
/**
 * Generate template parts for WordPress block themes.
 *
 * Ported from wp-theme-builder/src/part_builder.py
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
 * Class GrayFox_TB_PartBuilder
 */
class GrayFox_TB_PartBuilder {

	/**
	 * Return all template parts as filename => block markup.
	 *
	 * @param array $theme manifest['theme'] block.
	 * @return array<string,string>
	 */
	public static function get_all_parts( array $theme ): array {
		return [
			'header.html'             => self::build_header(),
			'header-minimal.html'     => self::build_header_minimal(),
			'header-transparent.html' => self::build_header_transparent(),
			'footer.html'             => self::build_footer( $theme ),
			'footer-minimal.html'     => self::build_footer_minimal( $theme ),
			'sidebar.html'            => self::build_sidebar(),
			'post-meta.html'          => self::build_post_meta(),
			'breadcrumbs.html'        => self::build_breadcrumbs(),
			'author-bio.html'         => self::build_author_bio(),
			'cta-inline.html'         => self::build_cta_inline(),
			'social-links.html'       => self::build_social_links(),
		];
	}

	// -------------------------------------------------------------------------
	// Part builders
	// -------------------------------------------------------------------------

	private static function build_header(): string {
		ob_start(); ?>
<!-- wp:group {"tagName":"header","className":"site-header","backgroundColor":"primary","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"}}},"layout":{"type":"constrained"}} -->
<header class="wp-block-group site-header alignfull has-primary-background-color has-background" style="padding-top:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30);">
  <!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between","alignItems":"center"},"style":{"spacing":{"blockGap":"var:preset|spacing|50"}}} -->
  <div class="wp-block-group">
    <!-- wp:site-title {"level":0,"isLink":true,"textColor":"contrast","className":"gf-eyebrow","style":{"typography":{"textDecoration":"none"}}} /-->
    <!-- wp:navigation {"textColor":"contrast","overlayBackgroundColor":"primary","overlayTextColor":"contrast","overlayMenu":"mobile","style":{"spacing":{"blockGap":"var:preset|spacing|20"}}} /-->
  </div>
  <!-- /wp:group -->
</header>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	private static function build_header_minimal(): string {
		ob_start(); ?>
<!-- wp:group {"tagName":"header","className":"site-header","backgroundColor":"primary","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"}}},"layout":{"type":"constrained"}} -->
<header class="wp-block-group site-header alignfull has-primary-background-color has-background" style="padding-top:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30);">
  <!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"center","alignItems":"center"}} -->
  <div class="wp-block-group">
    <!-- wp:site-title {"level":0,"isLink":true,"textColor":"contrast","className":"gf-eyebrow","style":{"typography":{"textDecoration":"none"}}} /-->
  </div>
  <!-- /wp:group -->
</header>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	private static function build_header_transparent(): string {
		ob_start(); ?>
<!-- wp:group {"tagName":"header","className":"site-header site-header-transparent","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|30","bottom":"var:preset|spacing|30"}}},"layout":{"type":"constrained"}} -->
<header class="wp-block-group site-header site-header-transparent alignfull" style="padding-top:var(--wp--preset--spacing--30);padding-bottom:var(--wp--preset--spacing--30);">
  <!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between","alignItems":"center"},"style":{"spacing":{"blockGap":"var:preset|spacing|50"}}} -->
  <div class="wp-block-group">
    <!-- wp:site-title {"level":0,"isLink":true,"textColor":"contrast","style":{"typography":{"textDecoration":"none"}}} /-->
    <!-- wp:navigation {"textColor":"contrast","overlayBackgroundColor":"primary","overlayTextColor":"contrast","overlayMenu":"mobile","style":{"spacing":{"blockGap":"var:preset|spacing|20"}}} /-->
  </div>
  <!-- /wp:group -->
</header>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	private static function build_footer( array $theme ): string {
		$name = esc_html( $theme['name'] ?? 'Your Business' );
		ob_start(); ?>
<!-- wp:group {"tagName":"footer","className":"site-footer","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|40"}}},"layout":{"type":"constrained"}} -->
<footer class="wp-block-group site-footer alignfull" style="padding-top:var(--wp--preset--spacing--60);padding-bottom:var(--wp--preset--spacing--40);">

  <!-- wp:columns {"isStackedOnMobile":true,"style":{"spacing":{"blockGap":"2.5rem","padding":{"bottom":"var:preset|spacing|50"}}}} -->
  <div class="wp-block-columns" style="padding-bottom:var(--wp--preset--spacing--50);">

    <!-- wp:column {"width":"40%"} -->
    <div class="wp-block-column" style="flex-basis:40%">
      <!-- wp:site-title {"level":0,"isLink":true,"textColor":"contrast","style":{"typography":{"textDecoration":"none"}}} /-->
      <!-- wp:site-tagline {"textColor":"contrast","style":{"spacing":{"margin":{"top":"0.5rem"}}}} /-->
    </div>
    <!-- /wp:column -->

    <!-- wp:column {"width":"30%"} -->
    <div class="wp-block-column" style="flex-basis:30%">
      <!-- wp:heading {"level":6,"textColor":"contrast"} -->
      <h6 class="wp-block-heading has-contrast-color has-text-color">Quick Links</h6>
      <!-- /wp:heading -->
      <!-- wp:navigation {"textColor":"contrast","layout":{"type":"flex","orientation":"vertical"},"style":{"spacing":{"blockGap":"0.4rem"}}} /-->
    </div>
    <!-- /wp:column -->

    <!-- wp:column {"width":"30%"} -->
    <div class="wp-block-column" style="flex-basis:30%">
      <!-- wp:heading {"level":6,"textColor":"contrast"} -->
      <h6 class="wp-block-heading has-contrast-color has-text-color">Get In Touch</h6>
      <!-- /wp:heading -->
      <!-- wp:paragraph {"textColor":"contrast","className":"gf-section-subtext","style":{"spacing":{"margin":{"top":"0"}}}} -->
      <p class="gf-section-subtext has-contrast-color has-text-color" style="margin-top:0">We&#8217;d love to hear from you. Reach out to learn how <?php echo $name; ?> can help.</p>
      <!-- /wp:paragraph -->
    </div>
    <!-- /wp:column -->

  </div>
  <!-- /wp:columns -->

  <!-- wp:separator {"className":"is-style-wide","backgroundColor":"contrast"} -->
  <hr class="wp-block-separator is-style-wide has-contrast-background-color has-background"/>
  <!-- /wp:separator -->

  <!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|30"},"blockGap":"1rem"}},"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","alignItems":"center"}} -->
  <div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--30);">
    <!-- wp:paragraph {"className":"gf-footer-copyright","textColor":"contrast"} -->
    <p class="gf-footer-copyright has-contrast-color has-text-color">&copy; 2026 <?php echo $name; ?>. All Rights Reserved.</p>
    <!-- /wp:paragraph -->
    <!-- wp:navigation {"textColor":"contrast","layout":{"type":"flex","flexWrap":"wrap"},"style":{"spacing":{"blockGap":"1.25rem"}}} /-->
  </div>
  <!-- /wp:group -->

</footer>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	private static function build_footer_minimal( array $theme ): string {
		$name = esc_html( $theme['name'] ?? 'Your Business' );
		ob_start(); ?>
<!-- wp:group {"tagName":"footer","className":"site-footer site-footer-minimal","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40"}}},"layout":{"type":"constrained"}} -->
<footer class="wp-block-group site-footer site-footer-minimal alignfull" style="padding-top:var(--wp--preset--spacing--40);padding-bottom:var(--wp--preset--spacing--40);">
  <!-- wp:paragraph {"align":"center","className":"gf-footer-copyright","textColor":"contrast"} -->
  <p class="has-text-align-center gf-footer-copyright has-contrast-color has-text-color">&copy; 2026 <?php echo $name; ?>. All Rights Reserved.</p>
  <!-- /wp:paragraph -->
</footer>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	private static function build_sidebar(): string {
		ob_start(); ?>
<!-- wp:group {"tagName":"aside","className":"gf-sidebar","layout":{"type":"constrained"}} -->
<aside class="wp-block-group gf-sidebar">

  <!-- wp:group {"className":"gf-sidebar-widget","style":{"spacing":{"padding":{"top":"1.5rem","bottom":"1.5rem","left":"1.5rem","right":"1.5rem"}}}} -->
  <div class="wp-block-group gf-sidebar-widget">
    <!-- wp:heading {"level":6,"className":"gf-sidebar-widget-title"} -->
    <h6 class="wp-block-heading gf-sidebar-widget-title">Search</h6>
    <!-- /wp:heading -->
    <!-- wp:search {"label":"Search","showLabel":false,"placeholder":"Search...","buttonText":"Go","buttonPosition":"button-inside","buttonUseIcon":true} /-->
  </div>
  <!-- /wp:group -->

  <!-- wp:group {"className":"gf-sidebar-widget","style":{"spacing":{"padding":{"top":"1.5rem","bottom":"1.5rem","left":"1.5rem","right":"1.5rem"}}}} -->
  <div class="wp-block-group gf-sidebar-widget">
    <!-- wp:heading {"level":6,"className":"gf-sidebar-widget-title"} -->
    <h6 class="wp-block-heading gf-sidebar-widget-title">Recent Posts</h6>
    <!-- /wp:heading -->
    <!-- wp:latest-posts {"postsToShow":5,"displayPostDate":true} /-->
  </div>
  <!-- /wp:group -->

  <!-- wp:group {"className":"gf-sidebar-widget","style":{"spacing":{"padding":{"top":"1.5rem","bottom":"1.5rem","left":"1.5rem","right":"1.5rem"}}}} -->
  <div class="wp-block-group gf-sidebar-widget">
    <!-- wp:heading {"level":6,"className":"gf-sidebar-widget-title"} -->
    <h6 class="wp-block-heading gf-sidebar-widget-title">Categories</h6>
    <!-- /wp:heading -->
    <!-- wp:categories {"showPostCounts":true} /-->
  </div>
  <!-- /wp:group -->

</aside>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	private static function build_post_meta(): string {
		ob_start(); ?>
<!-- wp:group {"style":{"spacing":{"blockGap":"1rem","margin":{"bottom":"var:preset|spacing|50"}}},"layout":{"type":"flex","flexWrap":"wrap","alignItems":"center"}} -->
<div class="wp-block-group">
  <!-- wp:post-author {"showAvatar":true,"avatarSize":32,"byline":"By","textColor":"muted"} /-->
  <!-- wp:post-date {"textColor":"muted"} /-->
  <!-- wp:post-terms {"term":"category","separator":" · ","textColor":"muted"} /-->
</div>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	private static function build_breadcrumbs(): string {
		ob_start(); ?>
<!-- wp:group {"tagName":"nav","className":"gf-breadcrumbs","style":{"spacing":{"margin":{"bottom":"var:preset|spacing|40"}}},"layout":{"type":"flex","flexWrap":"wrap","alignItems":"center"}} -->
<nav class="wp-block-group gf-breadcrumbs" aria-label="Breadcrumb">
  <!-- wp:paragraph {"textColor":"muted"} -->
  <p class="has-muted-color has-text-color"><a href="/">Home</a> <span class="gf-breadcrumbs-sep">/</span> <span>Current Page</span></p>
  <!-- /wp:paragraph -->
</nav>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	private static function build_author_bio(): string {
		ob_start(); ?>
<!-- wp:group {"className":"gf-author-bio-card","style":{"spacing":{"margin":{"top":"var:preset|spacing|60"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group gf-author-bio-card">
  <!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap","alignItems":"flex-start"},"style":{"spacing":{"blockGap":"1.5rem"}}} -->
  <div class="wp-block-group">
    <!-- wp:avatar {"size":80,"style":{"border":{"radius":"50%"}}} /-->
    <!-- wp:group {"style":{"spacing":{"blockGap":"0.5rem"}},"layout":{"type":"constrained"}} -->
    <div class="wp-block-group">
      <!-- wp:heading {"level":4,"className":"gf-card-title"} -->
      <h4 class="wp-block-heading gf-card-title">About the Author</h4>
      <!-- /wp:heading -->
      <!-- wp:post-author-name {"textColor":"primary"} /-->
      <!-- wp:post-author-biography {"textColor":"muted"} /-->
    </div>
    <!-- /wp:group -->
  </div>
  <!-- /wp:group -->
</div>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	private static function build_cta_inline(): string {
		ob_start(); ?>
<!-- wp:group {"className":"gf-cta-two-up","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|50","left":"var:preset|spacing|50","right":"var:preset|spacing|50"}},"border":{"radius":"10px"}},"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between","alignItems":"center"}} -->
<div class="wp-block-group gf-cta-two-up alignfull">
  <!-- wp:group {"style":{"spacing":{"blockGap":"0.5rem"}},"layout":{"type":"constrained","contentSize":"460px"}} -->
  <div class="wp-block-group">
    <!-- wp:heading {"level":3,"className":"gf-card-title","textColor":"foreground"} -->
    <h3 class="wp-block-heading gf-card-title has-foreground-color has-text-color">Want to learn more?</h3>
    <!-- /wp:heading -->
    <!-- wp:paragraph {"textColor":"muted","className":"gf-section-subtext","style":{"spacing":{"margin":{"top":"0.4rem","bottom":"0"}}}} -->
    <p class="gf-section-subtext has-muted-color has-text-color">Download our free guide and see how others are doing it.</p>
    <!-- /wp:paragraph -->
  </div>
  <!-- /wp:group -->
  <!-- wp:buttons -->
  <div class="wp-block-buttons">
    <!-- wp:button {"backgroundColor":"primary","textColor":"contrast"} -->
    <div class="wp-block-button"><a class="wp-block-button__link has-contrast-color has-primary-background-color has-text-color has-background wp-element-button">Download Free Guide</a></div>
    <!-- /wp:button -->
  </div>
  <!-- /wp:buttons -->
</div>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	private static function build_social_links(): string {
		ob_start(); ?>
<!-- wp:social-links {"iconColor":"primary","iconColorValue":"var(--wp--preset--color--primary)","size":"has-small-icon-size","style":{"spacing":{"blockGap":{"top":"0.5rem","left":"0.5rem"},"margin":{"top":"1rem"}}}} -->
<ul class="wp-block-social-links has-small-icon-size has-icon-color">
  <!-- wp:social-link {"url":"#","service":"linkedin"} /-->
  <!-- wp:social-link {"url":"#","service":"twitter"} /-->
  <!-- wp:social-link {"url":"#","service":"github"} /-->
</ul>
<!-- /wp:social-links -->
		<?php return ob_get_clean();
	}
}
