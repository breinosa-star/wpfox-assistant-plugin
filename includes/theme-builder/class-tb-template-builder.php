<?php
/**
 * Generate block templates for WordPress block themes.
 *
 * Ported from wp-theme-builder/src/template_builder.py
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
 * Class GrayFox_TB_TemplateBuilder
 */
class GrayFox_TB_TemplateBuilder {

	/**
	 * Return all templates as filename => block markup.
	 *
	 * @param array $manifest Full manifest array.
	 * @return array<string,string>
	 */
	public static function get_all_templates( array $manifest ): array {
		$result    = [];
		$templates = $manifest['templates'] ?? [];

		foreach ( $templates as $filename => $spec ) {
			$result[ $filename ] = self::render_template( $filename, $spec, $manifest );
		}

		// Always ensure index.html exists as a fallback.
		if ( ! isset( $result['index.html'] ) ) {
			$result['index.html'] = self::render_index_fallback( $manifest );
		}

		return $result;
	}

	// -------------------------------------------------------------------------
	// Dispatcher
	// -------------------------------------------------------------------------

	private static function render_template( string $filename, array $spec, array $manifest ): string {
		$type = $spec['type'] ?? 'content';

		// page.html is the WordPress generic Page template — it must always include
		// wp:post-content so the editor-saved blocks are rendered. Pattern composition
		// (no wp:post-content) is only appropriate for the front page.
		if ( 'page.html' === $filename ) {
			return self::render_content( $spec, $manifest );
		}

		switch ( $type ) {
			case 'content':
				return self::render_pattern_composition( $spec, $manifest );
			case 'content-single':
				return self::render_single( $spec, $manifest );
			case 'archive':
				return self::render_archive( $spec, $manifest );
			case 'error':
				return self::render_404( $spec, $manifest );
			case 'search':
				return self::render_search( $spec, $manifest );
			default:
				return self::render_content( $spec, $manifest );
		}
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private static function header_part( array $manifest ): string {
		$variant = $manifest['parts']['header_variant'] ?? 'header';
		return '<!-- wp:template-part {"slug":"' . esc_attr( $variant ) . '","tagName":"header"} /-->';
	}

	private static function footer_part( array $manifest ): string {
		$variant = $manifest['parts']['footer_variant'] ?? 'footer';
		return '<!-- wp:template-part {"slug":"' . esc_attr( $variant ) . '","tagName":"footer"} /-->';
	}

	private static function theme_slug( array $manifest ): string {
		return $manifest['theme']['slug'] ?? 'theme';
	}

	// -------------------------------------------------------------------------
	// Template type renderers
	// -------------------------------------------------------------------------

	/**
	 * Pattern composition: header + series of wp:pattern references + footer.
	 */
	private static function render_pattern_composition( array $spec, array $manifest ): string {
		$slug     = self::theme_slug( $manifest );
		$patterns = $spec['patterns'] ?? [];

		$pattern_blocks = '';
		foreach ( $patterns as $pattern_slug ) {
			$pattern_blocks .= "\n<!-- wp:pattern {\"slug\":\"{$slug}/{$pattern_slug}\"} /-->";
		}

		return self::header_part( $manifest )
			. $pattern_blocks
			. "\n" . self::footer_part( $manifest );
	}

	/**
	 * Standard content page: header → post-title → post-content → footer.
	 */
	private static function render_content( array $spec, array $manifest ): string {
		$sidebar = ! empty( $spec['sidebar'] );

		if ( $sidebar ) {
			// Sidebar layout needs an explicit wrapper for the two-column arrangement.
			// The outer group is flex/flow, not constrained, so post-content can still
			// render full-width sections inside the main column.
			ob_start(); ?>

<!-- wp:group {"tagName":"main","className":"gf-with-sidebar","layout":{"type":"constrained"}} -->
<main class="wp-block-group gf-with-sidebar">
  <!-- wp:group {"className":"gf-main-content","layout":{"type":"constrained"}} -->
  <div class="wp-block-group gf-main-content">
    <!-- wp:post-title {"level":1,"className":"gf-section-heading"} /-->
    <!-- wp:post-content {"layout":{"type":"constrained"}} /-->
  </div>
  <!-- /wp:group -->
  <!-- wp:template-part {"slug":"sidebar","tagName":"aside"} /-->
</main>
<!-- /wp:group -->
			<?php $content_block = ob_get_clean();
			return self::header_part( $manifest ) . $content_block . "\n" . self::footer_part( $manifest );
		}

		// wp:post-content is a direct sibling of the header/footer with no outer
		// constrained wrapper. This lets its own constrained layout constrain
		// non-aligned blocks to contentSize while align:full blocks correctly
		// break out to 100vw via negative-margin expansion.
		return self::header_part( $manifest )
			. "\n<!-- wp:post-content {\"align\":\"full\",\"layout\":{\"type\":\"constrained\"}} /-->\n"
			. self::footer_part( $manifest );
	}

	/**
	 * Single post: header → featured image → meta → title → content → author-bio → footer.
	 */
	private static function render_single( array $spec, array $manifest ): string {
		$related     = ! empty( $spec['related_posts'] );
		$inline_cta  = ! empty( $spec['inline_cta'] );

		if ( $related ) {
			ob_start(); ?>

<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group">
  <!-- wp:heading {"level":3,"className":"gf-section-heading"} -->
  <h3 class="wp-block-heading gf-section-heading">Related Posts</h3>
  <!-- /wp:heading -->
  <!-- wp:query {"queryId":1,"query":{"perPage":3,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","inherit":false}} -->
  <div class="wp-block-query">
    <!-- wp:post-template {"layout":{"type":"grid","columnCount":3}} -->
      <!-- wp:group {"className":"gf-post-card","style":{"spacing":{"padding":{"top":"1.5rem","bottom":"1.5rem","left":"1.5rem","right":"1.5rem"}}}} -->
      <div class="wp-block-group gf-post-card">
        <!-- wp:post-featured-image {"isLink":true,"aspectRatio":"16/9"} /-->
        <!-- wp:post-title {"level":4,"isLink":true} /-->
        <!-- wp:post-excerpt {"moreText":""} /-->
      </div>
      <!-- /wp:group -->
    <!-- /wp:post-template -->
  </div>
  <!-- /wp:query -->
</div>
<!-- /wp:group -->
			<?php $related_block = ob_get_clean();
		} else {
			$related_block = '';
		}

		$cta_block = $inline_cta
			? "\n<!-- wp:template-part {\"slug\":\"cta-inline\"} /-->"
			: '';

		ob_start(); ?>

<!-- wp:group {"tagName":"main","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"layout":{"type":"constrained"}} -->
<main class="wp-block-group">
  <!-- wp:post-featured-image {"aspectRatio":"16/9","style":{"border":{"radius":"8px"},"spacing":{"margin":{"bottom":"var:preset|spacing|50"}}}} /-->
  <!-- wp:template-part {"slug":"post-meta"} /-->
  <!-- wp:post-title {"level":1,"className":"gf-section-heading"} /-->
  <!-- wp:post-content {"layout":{"type":"constrained"}} /-->
  <!-- wp:template-part {"slug":"author-bio"} /-->
</main>
<!-- /wp:group -->
	<?php return self::header_part( $manifest ) . ob_get_clean() . $related_block . $cta_block . "\n" . self::footer_part( $manifest );
	}

	/**
	 * Archive: header → query title → post grid → footer.
	 */
	private static function render_archive( array $spec, array $manifest ): string {
		$columns    = (int) ( $spec['columns'] ?? 3 );
		$card_style = $spec['card_style'] ?? 'standard';
		$post_type  = $spec['post_type'] ?? 'post';

		if ( 'minimal' === $card_style ) {
			ob_start(); ?>
      <!-- wp:post-title {"level":3,"isLink":true} /-->
      <!-- wp:post-date {"textColor":"muted"} /-->
			<?php $card_inner = ob_get_clean();
		} elseif ( 'portfolio' === $card_style ) {
			ob_start(); ?>
      <!-- wp:post-featured-image {"isLink":true,"aspectRatio":"1"} /-->
      <!-- wp:post-title {"level":3,"isLink":true} /-->
			<?php $card_inner = ob_get_clean();
		} else {
			ob_start(); ?>
      <!-- wp:post-featured-image {"isLink":true,"aspectRatio":"16/9"} /-->
      <!-- wp:post-title {"level":3,"isLink":true} /-->
      <!-- wp:post-excerpt {"moreText":""} /-->
      <!-- wp:post-date {"textColor":"muted"} /-->
			<?php $card_inner = ob_get_clean();
		}

		ob_start(); ?>

<!-- wp:group {"tagName":"main","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"layout":{"type":"constrained"}} -->
<main class="wp-block-group">
  <!-- wp:group {"style":{"spacing":{"padding":{"bottom":"var:preset|spacing|50"}}},"layout":{"type":"constrained"}} -->
  <div class="wp-block-group">
    <!-- wp:query-title {"type":"archive","textAlign":"center","className":"gf-section-heading"} /-->
    <!-- wp:term-description {"textAlign":"center","textColor":"muted"} /-->
  </div>
  <!-- /wp:group -->
  <!-- wp:query {"queryId":0,"query":{"perPage":9,"pages":0,"offset":0,"postType":"<?php echo $post_type; ?>","order":"desc","orderBy":"date","inherit":true}} -->
  <div class="wp-block-query">
    <!-- wp:post-template {"layout":{"type":"grid","columnCount":<?php echo $columns; ?>}} -->
      <!-- wp:group {"className":"gf-post-card","style":{"spacing":{"padding":{"top":"1.5rem","bottom":"1.5rem","left":"1.5rem","right":"1.5rem"}}}} -->
      <div class="wp-block-group gf-post-card">
<?php echo $card_inner; ?>
      </div>
      <!-- /wp:group -->
    <!-- /wp:post-template -->
    <!-- wp:query-pagination {"layout":{"type":"flex","justifyContent":"center"}} -->
      <!-- wp:query-pagination-previous /-->
      <!-- wp:query-pagination-numbers /-->
      <!-- wp:query-pagination-next /-->
    <!-- /wp:query-pagination -->
  </div>
  <!-- /wp:query -->
</main>
<!-- /wp:group -->
	<?php return self::header_part( $manifest ) . ob_get_clean() . "\n" . self::footer_part( $manifest );
	}

	/**
	 * 404 error page.
	 */
	private static function render_404( array $spec, array $manifest ): string {
		ob_start(); ?>

<!-- wp:group {"tagName":"main","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"layout":{"type":"constrained"}} -->
<main class="wp-block-group">
  <!-- wp:group {"textAlign":"center","layout":{"type":"constrained","contentSize":"640px"}} -->
  <div class="wp-block-group has-text-align-center">
    <!-- wp:heading {"level":1,"textColor":"primary","style":{"typography":{"fontSize":"clamp(5rem,15vw,10rem)","fontWeight":"800","lineHeight":"1"}}} -->
    <h1 class="wp-block-heading has-primary-color has-text-color">404</h1>
    <!-- /wp:heading -->
    <!-- wp:heading {"level":2,"className":"gf-section-heading"} -->
    <h2 class="wp-block-heading gf-section-heading">Page Not Found</h2>
    <!-- /wp:heading -->
    <!-- wp:paragraph {"textColor":"muted","className":"gf-section-subtext"} -->
    <p class="gf-section-subtext has-muted-color has-text-color">The page you&#8217;re looking for doesn&#8217;t exist or has been moved.</p>
    <!-- /wp:paragraph -->
    <!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
    <div class="wp-block-buttons">
      <!-- wp:button {"backgroundColor":"primary","textColor":"contrast"} -->
      <div class="wp-block-button"><a class="wp-block-button__link has-contrast-color has-primary-background-color has-text-color has-background wp-element-button" href="/">Back to Home</a></div>
      <!-- /wp:button -->
    </div>
    <!-- /wp:buttons -->
  </div>
  <!-- /wp:group -->
</main>
<!-- /wp:group -->
		<?php return self::header_part( $manifest ) . ob_get_clean() . "\n" . self::footer_part( $manifest );
	}

	/**
	 * Search results page.
	 */
	private static function render_search( array $spec, array $manifest ): string {
		ob_start(); ?>

<!-- wp:group {"tagName":"main","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"layout":{"type":"constrained"}} -->
<main class="wp-block-group">
  <!-- wp:search {"label":"Search","showLabel":false,"placeholder":"Search...","buttonText":"Search","buttonPosition":"button-outside","buttonUseIcon":true,"align":"center"} /-->
  <!-- wp:query-title {"type":"search","textAlign":"center","className":"gf-section-heading","style":{"spacing":{"margin":{"top":"var:preset|spacing|50"}}}} /-->
  <!-- wp:query {"queryId":0,"query":{"perPage":10,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"relevance","inherit":true}} -->
  <div class="wp-block-query">
    <!-- wp:post-template -->
      <!-- wp:group {"className":"gf-post-card","style":{"spacing":{"padding":{"top":"1.5rem","bottom":"1.5rem"}}}} -->
      <div class="wp-block-group gf-post-card">
        <!-- wp:post-title {"level":3,"isLink":true} /-->
        <!-- wp:post-excerpt {"moreText":"Read more"} /-->
        <!-- wp:post-date {"textColor":"muted"} /-->
      </div>
      <!-- /wp:group -->
    <!-- /wp:post-template -->
    <!-- wp:query-no-results -->
      <!-- wp:paragraph {"textColor":"muted"} -->
      <p class="has-muted-color has-text-color">No results found. Try a different search term.</p>
      <!-- /wp:paragraph -->
    <!-- /wp:query-no-results -->
    <!-- wp:query-pagination {"layout":{"type":"flex","justifyContent":"center"}} -->
      <!-- wp:query-pagination-previous /-->
      <!-- wp:query-pagination-numbers /-->
      <!-- wp:query-pagination-next /-->
    <!-- /wp:query-pagination -->
  </div>
  <!-- /wp:query -->
</main>
<!-- /wp:group -->
		<?php return self::header_part( $manifest ) . ob_get_clean() . "\n" . self::footer_part( $manifest );
	}

	/**
	 * Minimal index.html fallback (always generated).
	 */
	private static function render_index_fallback( array $manifest ): string {
		ob_start(); ?>

<!-- wp:group {"tagName":"main","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"layout":{"type":"constrained"}} -->
<main class="wp-block-group">
  <!-- wp:query {"queryId":0,"query":{"perPage":10,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","inherit":true}} -->
  <div class="wp-block-query">
    <!-- wp:post-template {"layout":{"type":"grid","columnCount":2}} -->
      <!-- wp:group {"className":"gf-post-card","style":{"spacing":{"padding":{"top":"1.5rem","bottom":"1.5rem","left":"1.5rem","right":"1.5rem"}}}} -->
      <div class="wp-block-group gf-post-card">
        <!-- wp:post-featured-image {"isLink":true,"aspectRatio":"16/9"} /-->
        <!-- wp:post-title {"level":3,"isLink":true} /-->
        <!-- wp:post-excerpt {"moreText":""} /-->
        <!-- wp:post-date {"textColor":"muted"} /-->
      </div>
      <!-- /wp:group -->
    <!-- /wp:post-template -->
    <!-- wp:query-pagination {"layout":{"type":"flex","justifyContent":"center"}} -->
      <!-- wp:query-pagination-previous /-->
      <!-- wp:query-pagination-numbers /-->
      <!-- wp:query-pagination-next /-->
    <!-- /wp:query-pagination -->
  </div>
  <!-- /wp:query -->
</main>
<!-- /wp:group -->
		<?php return self::header_part( $manifest ) . ob_get_clean() . "\n" . self::footer_part( $manifest );
	}
}
