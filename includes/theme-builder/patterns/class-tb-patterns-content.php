<?php
/**
 * Content hub renderers: blog-preview-grid, resource-library, knowledge-base-index,
 * changelog-list, glossary-index.
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
 * Class GrayFox_TB_Patterns_Content
 */
class GrayFox_TB_Patterns_Content {

	// -------------------------------------------------------------------------
	// blog-preview-grid — featured post + 3 thumbnail sidebar
	// -------------------------------------------------------------------------

	public static function render_blog_preview_grid( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-blog-preview';

		$heading   = esc_html( $copy['section_heading'] ?? 'Latest from the Blog' );
		$subtext   = esc_html( $copy['subtext']         ?? 'Insights, updates, and ideas from our team.' );
		$cta_label = esc_html( $copy['cta_label']       ?? 'View all posts' );

		$sidebar_posts = [
			[
				'cat'   => esc_html( $copy['post2_category'] ?? 'Product' ),
				'title' => esc_html( $copy['post2_title']    ?? 'Second article title goes here' ),
				'date'  => esc_html( $copy['post2_date']     ?? 'March 28, 2025' ),
			],
			[
				'cat'   => esc_html( $copy['post3_category'] ?? 'Engineering' ),
				'title' => esc_html( $copy['post3_title']    ?? 'Third article with a slightly longer title' ),
				'date'  => esc_html( $copy['post3_date']     ?? 'March 15, 2025' ),
			],
			[
				'cat'   => esc_html( $copy['post4_category'] ?? 'Company' ),
				'title' => esc_html( $copy['post4_title']    ?? 'Fourth article for the sidebar list' ),
				'date'  => esc_html( $copy['post4_date']     ?? 'March 2, 2025' ),
			],
		];

		$sidebar_html = '';
		foreach ( $sidebar_posts as $post ) {
			ob_start(); ?>
<div class="gf-blog-sidebar-item">
  <div class="gf-blog-sidebar-thumb">
    <img src="" alt="<?php echo $post['title']; ?>"/>
  </div>
  <div class="gf-blog-sidebar-body">
    <span class="gf-post-card-category"><?php echo $post['cat']; ?></span>
    <h4 class="gf-blog-sidebar-title"><?php echo $post['title']; ?></h4>
    <span class="gf-blog-post-date"><i class="bi bi-calendar3 me-1"></i><?php echo $post['date']; ?></span>
  </div>
</div>
			<?php $sidebar_html .= ob_get_clean();
		}

		$feat_cat   = esc_html( $copy['post1_category'] ?? 'Featured' );
		$feat_title = esc_html( $copy['post1_title']    ?? 'Your most compelling featured article headline' );
		$feat_desc  = esc_html( $copy['post1_desc']     ?? 'A two-sentence summary of the featured post that gives readers enough context to click through.' );
		$feat_date  = esc_html( $copy['post1_date']     ?? 'April 10, 2025 · 6 min read' );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?>","backgroundColor":"background","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> has-background-background-color has-background py-5">

<!-- wp:html -->
<div class="container">

  <div class="gf-blog-header">
    <div>
      <h2 class="gf-section-heading"><?php echo $heading; ?></h2>
      <p class="gf-section-subtext" style="color:var(--gf-muted);margin-top:.5rem"><?php echo $subtext; ?></p>
    </div>
    <a href="#" class="gf-blog-view-all"><?php echo $cta_label; ?> <i class="bi bi-arrow-right-short"></i></a>
  </div>

  <div class="row g-4 mt-2">

    <div class="col-lg-7">
      <article class="gf-blog-featured">
        <div class="gf-blog-featured-img">
          <img src="" alt="<?php echo $feat_title; ?>"/>
        </div>
        <div class="gf-blog-featured-body">
          <span class="gf-post-card-category"><?php echo $feat_cat; ?></span>
          <h3 class="gf-blog-featured-title"><?php echo $feat_title; ?></h3>
          <p class="gf-blog-featured-desc"><?php echo $feat_desc; ?></p>
          <span class="gf-blog-post-date"><i class="bi bi-calendar3 me-1"></i><?php echo $feat_date; ?></span>
        </div>
      </article>
    </div>

    <div class="col-lg-5">
      <div class="gf-blog-sidebar">
        <?php echo $sidebar_html; ?>
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
	// resource-library — 6 resource cards in 2 rows of 3
	// -------------------------------------------------------------------------

	public static function render_resource_library( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-resource-library gf-section-tint';

		$heading = esc_html( $copy['section_heading'] ?? 'Resource Library' );
		$subtext = esc_html( $copy['subtext']         ?? 'Guides, reports, and tools to help you succeed.' );

		$type_icons = [
			'Ebook'      => 'bi-book-fill',
			'Report'     => 'bi-bar-chart-fill',
			'Template'   => 'bi-file-earmark-check-fill',
			'Tool'       => 'bi-tools',
			'Guide'      => 'bi-compass-fill',
			'Case Study' => 'bi-trophy-fill',
		];

		$resources = [
			[ $copy['r1_title'] ?? 'The Complete Buyer\'s Guide',     $copy['r1_type'] ?? 'Ebook' ],
			[ $copy['r2_title'] ?? '2025 Industry Benchmark Report',  $copy['r2_type'] ?? 'Report' ],
			[ $copy['r3_title'] ?? 'Getting Started Checklist',       $copy['r3_type'] ?? 'Template' ],
			[ $copy['r4_title'] ?? 'ROI Calculator Spreadsheet',      $copy['r4_type'] ?? 'Tool' ],
			[ $copy['r5_title'] ?? 'Onboarding Playbook',             $copy['r5_type'] ?? 'Guide' ],
			[ $copy['r6_title'] ?? 'Case Study Collection',           $copy['r6_type'] ?? 'Case Study' ],
		];

		$make_card = function( $title, $rtype ) use ( $type_icons ) {
			$icon  = $type_icons[ $rtype ] ?? 'bi-file-earmark-fill';
			$title = esc_html( $title );
			$rtype = esc_html( $rtype );
			ob_start(); ?>
<!-- wp:group {"className":"gf-resource-card","backgroundColor":"background"} -->
<div class="wp-block-group gf-resource-card has-background-background-color has-background">
<!-- wp:html --><i class="bi <?php echo $icon; ?> gf-resource-icon" aria-hidden="true"></i><!-- /wp:html -->
<!-- wp:paragraph {"className":"gf-resource-type","textColor":"accent"} --><p class="gf-resource-type has-accent-color has-text-color"><?php echo $rtype; ?></p><!-- /wp:paragraph -->
<!-- wp:heading {"level":4,"textColor":"foreground"} --><h4 class="wp-block-heading has-foreground-color has-text-color"><?php echo $title; ?></h4><!-- /wp:heading -->
<!-- wp:html --><a href="#" class="gf-resource-download-link">Download free <i class="bi bi-arrow-right"></i></a><!-- /wp:html -->
</div>
<!-- /wp:group -->
			<?php return ob_get_clean();
		};

		$row1_cols = '';
		$row2_cols = '';
		for ( $i = 0; $i < 3; $i++ ) {
			$card1      = $make_card( $resources[ $i ][0], $resources[ $i ][1] );
			$card2      = $make_card( $resources[ $i + 3 ][0], $resources[ $i + 3 ][1] );
			$row1_cols .= "<!-- wp:column -->\n<div class=\"wp-block-column\">{$card1}</div>\n<!-- /wp:column -->\n";
			$row2_cols .= "<!-- wp:column -->\n<div class=\"wp-block-column\">{$card2}</div>\n<!-- /wp:column -->\n";
		}

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?> py-5","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5">

<!-- wp:heading {"textAlign":"center","textColor":"primary","className":"gf-section-heading"} -->
<h2 class="wp-block-heading has-text-align-center has-primary-color has-text-color gf-section-heading"><?php echo $heading; ?></h2>
<!-- /wp:heading -->
<!-- wp:paragraph {"align":"center","textColor":"muted","className":"gf-section-subtext"} -->
<p class="has-text-align-center has-muted-color has-text-color gf-section-subtext"><?php echo $subtext; ?></p>
<!-- /wp:paragraph -->

<!-- wp:columns {"isStackedOnMobile":true,"className":"mt-4"} -->
<div class="wp-block-columns mt-4">
<?php echo $row1_cols; ?>
</div>
<!-- /wp:columns -->

<!-- wp:columns {"isStackedOnMobile":true,"className":"mt-3"} -->
<div class="wp-block-columns mt-3">
<?php echo $row2_cols; ?>
</div>
<!-- /wp:columns -->

</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// knowledge-base-index — 6 category tiles
	// -------------------------------------------------------------------------

	public static function render_knowledge_base_index( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-kb-index';

		$heading = esc_html( $copy['section_heading'] ?? 'Knowledge Base' );
		$subtext = esc_html( $copy['subtext']         ?? 'Browse by category to find answers fast.' );

		$cats = [
			[ 'bi-rocket-takeoff-fill', $copy['cat1_title'] ?? 'Getting Started',   $copy['cat1_desc'] ?? 'Setup guides, first steps, and quick wins.' ],
			[ 'bi-credit-card-fill',    $copy['cat2_title'] ?? 'Account & Billing',  $copy['cat2_desc'] ?? 'Manage your subscription and payment details.' ],
			[ 'bi-plug-fill',           $copy['cat3_title'] ?? 'Integrations',       $copy['cat3_desc'] ?? 'Connect the tools you already use.' ],
			[ 'bi-tools',               $copy['cat4_title'] ?? 'Troubleshooting',    $copy['cat4_desc'] ?? 'Diagnose and resolve common issues.' ],
			[ 'bi-code-slash',          $copy['cat5_title'] ?? 'API Reference',      $copy['cat5_desc'] ?? 'Endpoints, authentication, and code samples.' ],
			[ 'bi-star-fill',           $copy['cat6_title'] ?? 'Best Practices',     $copy['cat6_desc'] ?? 'Tips from power users and our team.' ],
		];

		$make_tile = function( string $icon, $title, $desc ) {
			$title = esc_html( $title );
			$desc  = esc_html( $desc );
			ob_start(); ?>
<!-- wp:group {"className":"gf-kb-tile","backgroundColor":"background"} -->
<div class="wp-block-group gf-kb-tile has-background-background-color has-background">
<!-- wp:html --><i class="bi <?php echo $icon; ?> gf-kb-icon" aria-hidden="true"></i><!-- /wp:html -->
<!-- wp:heading {"level":3,"textColor":"foreground"} --><h3 class="wp-block-heading has-foreground-color has-text-color"><?php echo $title; ?></h3><!-- /wp:heading -->
<!-- wp:paragraph {"textColor":"muted"} --><p class="has-muted-color has-text-color"><?php echo $desc; ?></p><!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
			<?php return ob_get_clean();
		};

		$row1_cols = '';
		$row2_cols = '';
		for ( $i = 0; $i < 3; $i++ ) {
			$tile1      = $make_tile( $cats[ $i ][0], $cats[ $i ][1], $cats[ $i ][2] );
			$tile2      = $make_tile( $cats[ $i + 3 ][0], $cats[ $i + 3 ][1], $cats[ $i + 3 ][2] );
			$row1_cols .= "<!-- wp:column -->\n<div class=\"wp-block-column\">{$tile1}</div>\n<!-- /wp:column -->\n";
			$row2_cols .= "<!-- wp:column -->\n<div class=\"wp-block-column\">{$tile2}</div>\n<!-- /wp:column -->\n";
		}

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?> py-5","backgroundColor":"background","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5 has-background-background-color has-background">

<!-- wp:heading {"textAlign":"center","textColor":"primary","className":"gf-section-heading"} -->
<h2 class="wp-block-heading has-text-align-center has-primary-color has-text-color gf-section-heading"><?php echo $heading; ?></h2>
<!-- /wp:heading -->
<!-- wp:paragraph {"align":"center","textColor":"muted"} -->
<p class="has-text-align-center has-muted-color has-text-color"><?php echo $subtext; ?></p>
<!-- /wp:paragraph -->

<!-- wp:columns {"className":"mt-4"} -->
<div class="wp-block-columns mt-4">
<?php echo $row1_cols; ?>
</div>
<!-- /wp:columns -->

<!-- wp:columns {"className":"mt-3"} -->
<div class="wp-block-columns mt-3">
<?php echo $row2_cols; ?>
</div>
<!-- /wp:columns -->

</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// changelog-list — versioned timeline list
	// -------------------------------------------------------------------------

	public static function render_changelog_list( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-changelog';

		$heading    = esc_html( $copy['section_heading'] ?? 'Changelog' );
		$subtext    = esc_html( $copy['subtext']         ?? 'A running log of improvements and fixes.' );
		$v1         = esc_html( $copy['v1_version']      ?? 'v2.4.0' );
		$v1_date    = esc_html( $copy['v1_date']         ?? 'April 8, 2025' );
		$v1_item1   = esc_html( $copy['v1_item1']        ?? 'Added dark mode support across all dashboards' );
		$v1_item2   = esc_html( $copy['v1_item2']        ?? 'Improved CSV export performance by 60%' );
		$v1_item3   = esc_html( $copy['v1_item3']        ?? 'Fixed edge case in date range filter' );
		$v2         = esc_html( $copy['v2_version']      ?? 'v2.3.1' );
		$v2_date    = esc_html( $copy['v2_date']         ?? 'March 22, 2025' );
		$v2_item1   = esc_html( $copy['v2_item1']        ?? 'Patched authentication token refresh bug' );
		$v2_item2   = esc_html( $copy['v2_item2']        ?? 'Updated API rate limit documentation' );
		$v3         = esc_html( $copy['v3_version']      ?? 'v2.3.0' );
		$v3_date    = esc_html( $copy['v3_date']         ?? 'March 10, 2025' );
		$v3_item1   = esc_html( $copy['v3_item1']        ?? 'Launched integrations marketplace' );
		$v3_item2   = esc_html( $copy['v3_item2']        ?? 'New webhook event types for pipeline triggers' );
		$v3_item3   = esc_html( $copy['v3_item3']        ?? 'Redesigned onboarding checklist' );

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?> py-5","backgroundColor":"background","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5 has-background-background-color has-background">

<!-- wp:heading {"textColor":"primary"} -->
<h2 class="wp-block-heading has-primary-color has-text-color"><?php echo $heading; ?></h2>
<!-- /wp:heading -->
<!-- wp:paragraph {"textColor":"muted"} -->
<p class="has-muted-color has-text-color"><?php echo $subtext; ?></p>
<!-- /wp:paragraph -->

<!-- wp:html -->
<div class="container">
  <div class="gf-changelog-row">
    <div class="gf-changelog-version">
      <p style="color:var(--gf-primary);font-weight:700;margin:0 0 .25rem"><?php echo $v1; ?></p>
      <p style="color:var(--gf-muted);font-size:.875rem;margin:0"><?php echo $v1_date; ?></p>
    </div>
    <div class="gf-changelog-entry gf-changelog-entry-active">
      <ul style="margin:0;padding-left:1.25rem;color:var(--gf-text)"><li><?php echo $v1_item1; ?></li><li><?php echo $v1_item2; ?></li><li><?php echo $v1_item3; ?></li></ul>
    </div>
  </div>
  <div class="gf-changelog-row">
    <div class="gf-changelog-version">
      <p style="color:var(--gf-muted);font-weight:700;margin:0 0 .25rem"><?php echo $v2; ?></p>
      <p style="color:var(--gf-muted);font-size:.875rem;margin:0"><?php echo $v2_date; ?></p>
    </div>
    <div class="gf-changelog-entry">
      <ul style="margin:0;padding-left:1.25rem;color:var(--gf-text)"><li><?php echo $v2_item1; ?></li><li><?php echo $v2_item2; ?></li></ul>
    </div>
  </div>
  <div class="gf-changelog-row">
    <div class="gf-changelog-version">
      <p style="color:var(--gf-muted);font-weight:700;margin:0 0 .25rem"><?php echo $v3; ?></p>
      <p style="color:var(--gf-muted);font-size:.875rem;margin:0"><?php echo $v3_date; ?></p>
    </div>
    <div class="gf-changelog-entry">
      <ul style="margin:0;padding-left:1.25rem;color:var(--gf-text)"><li><?php echo $v3_item1; ?></li><li><?php echo $v3_item2; ?></li><li><?php echo $v3_item3; ?></li></ul>
    </div>
  </div>
</div>
<!-- /wp:html -->

</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// glossary-index — term / definition rows
	// -------------------------------------------------------------------------

	public static function render_glossary_index( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-glossary';

		$heading = esc_html( $copy['section_heading'] ?? 'Glossary' );
		$subtext = esc_html( $copy['subtext']         ?? 'Definitions for common terms in the industry.' );

		// Discover terms dynamically: term1/def1, term2/def2, … until neither key exists.
		$terms = [];
		$i     = 1;
		while ( isset( $copy[ "term{$i}" ] ) || isset( $copy[ "def{$i}" ] ) ) {
			$terms[] = [ $copy[ "term{$i}" ] ?? '', $copy[ "def{$i}" ] ?? '' ];
			$i++;
		}

		// Placeholder defaults shown when no copy has been supplied yet.
		if ( empty( $terms ) ) {
			$terms = [
				[ 'API',        'Application Programming Interface — a set of rules that allows software to communicate with other software.' ],
				[ 'Churn Rate', 'The percentage of customers who cancel their subscription within a given time period.' ],
				[ 'MRR',        'Monthly Recurring Revenue — predictable revenue a business expects each month from subscriptions.' ],
				[ 'NPS',        'Net Promoter Score — a metric measuring customer loyalty on a scale of −100 to 100.' ],
				[ 'SLA',        'Service Level Agreement — a contract specifying the expected level of service between provider and client.' ],
				[ 'Webhook',    'An HTTP callback that delivers real-time data to other applications when an event occurs.' ],
			];
		}

		$last      = count( $terms ) - 1;
		$rows_html = '';
		foreach ( $terms as $i => [ $term, $def ] ) {
			$term   = esc_html( $term );
			$def    = esc_html( $def );
			$border = ( $i < $last ) ? 'border-bottom:1px solid var(--wp--preset--color--muted,#e5e7eb);' : '';
			$rows_html .= '<div style="display:flex;gap:2rem;' . $border . 'padding:1.25rem 0">'
				. '<strong style="min-width:140px;flex-shrink:0;color:var(--wp--preset--color--primary)">' . $term . '</strong>'
				. '<span style="color:var(--wp--preset--color--foreground,#1a1a1a)">' . $def . '</span>'
				. '</div>';
		}

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?> py-5","backgroundColor":"background","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5 has-background-background-color has-background">

<!-- wp:heading {"textColor":"primary"} -->
<h2 class="wp-block-heading has-primary-color has-text-color"><?php echo $heading; ?></h2>
<!-- /wp:heading -->
<!-- wp:paragraph {"textColor":"muted"} -->
<p class="has-muted-color has-text-color"><?php echo $subtext; ?></p>
<!-- /wp:paragraph -->

<!-- wp:html -->
<div class="container mt-4" style="max-width:860px;">
<?php echo $rows_html; ?>
</div>
<!-- /wp:html -->

</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}
}

GrayFox_TB_PatternBuilder::register_renderers( [
	'blog-preview-grid'    => [ GrayFox_TB_Patterns_Content::class, 'render_blog_preview_grid' ],
	'resource-library'     => [ GrayFox_TB_Patterns_Content::class, 'render_resource_library' ],
	'knowledge-base-index' => [ GrayFox_TB_Patterns_Content::class, 'render_knowledge_base_index' ],
	'changelog-list'       => [ GrayFox_TB_Patterns_Content::class, 'render_changelog_list' ],
	'glossary-index'       => [ GrayFox_TB_Patterns_Content::class, 'render_glossary_index' ],
] );
