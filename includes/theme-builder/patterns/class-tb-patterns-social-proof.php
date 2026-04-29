<?php
/**
 * Social proof patterns — testimonials-single, case-study-grid,
 * review-stars-row, press-mentions.
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
 * Class GrayFox_TB_Patterns_SocialProof
 */
class GrayFox_TB_Patterns_SocialProof {

	// -------------------------------------------------------------------------
	// Renderers
	// -------------------------------------------------------------------------

	/**
	 * Single full-width testimonial with large quote mark and author.
	 */
	public static function render_testimonials_single( array $spec, array $theme ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];

		$primary_class = esc_attr( $classes[0] ?? 'gf-section-tint' );
		$heading       = esc_html( $copy['section_heading'] ?? '' );
		$quote         = esc_html( $copy['quote_1'] ?? 'This product transformed how our team works every single day.' );
		$author        = esc_html( $copy['author_1']  ?? 'Jane Smith, CEO at Acme Corp' );
		$rating        = esc_html( $copy['rating_1']  ?? '★★★★★' );

		if ( $heading ) {
			ob_start(); ?>
  <!-- wp:heading {"textAlign":"center","level":2,"className":"gf-section-heading"} -->
  <h2 class="wp-block-heading has-text-align-center gf-section-heading"><?php echo $heading; ?></h2>
  <!-- /wp:heading -->

			<?php $heading_block = ob_get_clean();
		} else {
			$heading_block = '';
		}

		ob_start(); ?>
<!-- wp:group {"className":"<?php echo $primary_class; ?> py-5","align":"full","layout":{"type":"constrained"}} -->
<div class="wp-block-group <?php echo $primary_class; ?> py-5 alignfull">
<?php echo $heading_block; ?>  <!-- wp:group {"className":"gf-testimonial-single-wrap"} -->
  <div class="wp-block-group gf-testimonial-single-wrap">
    <!-- wp:paragraph {"align":"center","className":"gf-star-rating"} -->
    <p class="has-text-align-center gf-star-rating"><?php echo $rating; ?></p>
    <!-- /wp:paragraph -->
    <!-- wp:paragraph {"align":"center","className":"gf-testimonial-quote"} -->
    <p class="has-text-align-center gf-testimonial-quote">&#8220;<?php echo $quote; ?>&#8221;</p>
    <!-- /wp:paragraph -->
    <!-- wp:group {"className":"gf-testimonial-author-group"} -->
    <div class="wp-block-group gf-testimonial-author-group">
      <!-- wp:image {"className":"gf-testimonial-avatar","sizeSlug":"full"} -->
      <figure class="wp-block-image size-full gf-testimonial-avatar"><img alt="Testimonial author" /></figure>
      <!-- /wp:image -->
      <!-- wp:paragraph {"className":"gf-testimonial-author"} -->
      <p class="gf-testimonial-author"><?php echo $author; ?></p>
      <!-- /wp:paragraph -->
    </div>
    <!-- /wp:group -->
  </div>
  <!-- /wp:group -->
</div>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	/**
	 * Three case study cards with result badges.
	 */
	public static function render_case_study_grid( array $spec, array $theme ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];

		$wrap_class = esc_attr( implode( ' ', $classes ) ?: 'gf-section-tint' );
		$heading    = esc_html( $copy['section_heading'] ?? 'Customer Success Stories' );
		$subtext    = esc_html( $copy['subtext']         ?? 'See how companies like yours achieve results.' );

		$cases = [];
		for ( $i = 1; $i <= 3; $i++ ) {
			$cases[] = [
				'company' => esc_html( $copy[ "case_{$i}_company" ] ?? "Company {$i}" ),
				'result'  => esc_html( $copy[ "case_{$i}_result"  ] ?? ( $i === 1 ? '3× revenue growth' : ( $i === 2 ? '40% cost reduction' : '2× faster delivery' ) ) ),
				'quote'   => esc_html( $copy[ "case_{$i}_quote"   ] ?? 'The results exceeded our expectations significantly.' ),
				'author'  => esc_html( $copy[ "case_{$i}_author"  ] ?? "Marketing Lead, Company {$i}" ),
				'logo'    => esc_html( $copy[ "case_{$i}_logo"    ] ?? "Company {$i} Logo" ),
			];
		}

		$cards = '';
		foreach ( $cases as $c ) {
			ob_start(); ?>
      <div class="col-md-4">
        <div class="card h-100 border" style="border-radius:10px;overflow:hidden">
          <div style="height:6px;background:var(--gf-primary)"></div>
          <div class="card-body d-flex flex-column" style="padding:1.75rem">
            <div class="mb-3">
              <span class="badge" style="background:var(--gf-primary);color:#fff;font-size:.75rem;font-weight:700;padding:.35em .7em;border-radius:6px">
                <i class="bi bi-arrow-up-right me-1"></i><?php echo $c['result']; ?>
              </span>
            </div>
            <p style="font-weight:700;font-size:1.05rem;color:var(--gf-text);margin-bottom:.5rem"><?php echo $c['company']; ?></p>
            <p style="font-style:italic;font-size:.95rem;color:var(--gf-muted);flex-grow:1">&#8220;<?php echo $c['quote']; ?>&#8221;</p>
            <p style="font-size:.8rem;color:var(--gf-muted);margin-bottom:0">&#8212; <?php echo $c['author']; ?></p>
          </div>
        </div>
      </div>
			<?php $cards .= ob_get_clean();
		}

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $wrap_class; ?>","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $wrap_class; ?> py-5">
<!-- wp:html -->
<div class="container">
  <h2 class="text-center gf-section-heading" style="color:var(--gf-heading)"><?php echo $heading; ?></h2>
  <p class="text-center gf-section-subtext" style="color:var(--gf-muted)"><?php echo $subtext; ?></p>
  <div class="row g-4 mt-2">
<?php echo $cards; ?>
  </div>
</div>
<!-- /wp:html -->
</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	/**
	 * Star ratings summary row with aggregate score.
	 */
	public static function render_review_stars_row( array $spec, array $theme ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];

		$wrap_class = esc_attr( $classes[0] ?? 'gf-section-tint' );
		$score      = esc_html( $copy['score']         ?? '4.9' );
		$total      = esc_html( $copy['total_reviews'] ?? '2,400+' );
		$platform_1 = esc_html( $copy['platform_1']   ?? 'G2' );
		$platform_2 = esc_html( $copy['platform_2']   ?? 'Capterra' );
		$platform_3 = esc_html( $copy['platform_3']   ?? 'Trustpilot' );

		$stars = '<i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>';

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $wrap_class; ?> py-5","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $wrap_class; ?> py-5">
  <!-- wp:group {"className":"gf-review-stars-row"} -->
  <div class="wp-block-group gf-review-stars-row">
    <!-- wp:group {"className":"gf-review-score-block"} -->
    <div class="wp-block-group gf-review-score-block">
      <!-- wp:html --><div class="gf-star-rating"><?php echo $stars; ?></div><!-- /wp:html -->
      <!-- wp:heading {"level":3,"textAlign":"center","className":"gf-review-score"} -->
      <h3 class="wp-block-heading has-text-align-center gf-review-score"><?php echo $score; ?>/5</h3>
      <!-- /wp:heading -->
      <!-- wp:paragraph {"align":"center","textColor":"muted"} -->
      <p class="has-text-align-center has-muted-color has-text-color"><?php echo $total; ?> reviews</p>
      <!-- /wp:paragraph -->
    </div>
    <!-- /wp:group -->
    <!-- wp:separator {"className":"gf-review-divider is-style-wide"} -->
    <hr class="wp-block-separator has-alpha-channel-opacity gf-review-divider is-style-wide"/>
    <!-- /wp:separator -->
    <!-- wp:group {"className":"gf-review-platforms"} -->
    <div class="wp-block-group gf-review-platforms">
      <!-- wp:group {"className":"gf-review-platform-item"} -->
      <div class="wp-block-group gf-review-platform-item">
        <!-- wp:html --><div class="gf-star-rating"><?php echo $stars; ?></div><!-- /wp:html -->
        <!-- wp:paragraph {"align":"center"} -->
        <p class="has-text-align-center"><strong><?php echo $platform_1; ?></strong></p>
        <!-- /wp:paragraph -->
      </div>
      <!-- /wp:group -->
      <!-- wp:group {"className":"gf-review-platform-item"} -->
      <div class="wp-block-group gf-review-platform-item">
        <!-- wp:html --><div class="gf-star-rating"><?php echo $stars; ?></div><!-- /wp:html -->
        <!-- wp:paragraph {"align":"center"} -->
        <p class="has-text-align-center"><strong><?php echo $platform_2; ?></strong></p>
        <!-- /wp:paragraph -->
      </div>
      <!-- /wp:group -->
      <!-- wp:group {"className":"gf-review-platform-item"} -->
      <div class="wp-block-group gf-review-platform-item">
        <!-- wp:html --><div class="gf-star-rating"><?php echo $stars; ?></div><!-- /wp:html -->
        <!-- wp:paragraph {"align":"center"} -->
        <p class="has-text-align-center"><strong><?php echo $platform_3; ?></strong></p>
        <!-- /wp:paragraph -->
      </div>
      <!-- /wp:group -->
    </div>
    <!-- /wp:group -->
  </div>
  <!-- /wp:group -->
</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	/**
	 * Press mentions — logo strip + pull quote from a publication.
	 */
	public static function render_press_mentions( array $spec, array $theme ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];

		$wrap_class = esc_attr( $classes[0] ?? '' );
		$heading    = esc_html( $copy['section_heading'] ?? 'As seen in' );
		$quote      = esc_html( $copy['featured_quote']  ?? 'A game-changer for modern product teams.' );
		$source     = esc_html( $copy['featured_source'] ?? 'TechCrunch' );

		$logos = [];
		for ( $i = 1; $i <= 5; $i++ ) {
			$logos[] = esc_html( $copy[ "logo_{$i}_alt" ] ?? "Publication {$i}" );
		}

		$logo_items = '';
		foreach ( $logos as $alt ) {
			ob_start(); ?>

    <!-- wp:group {"className":"gf-logo-item"} -->
    <div class="wp-block-group gf-logo-item">
      <!-- wp:image {} -->
      <figure class="wp-block-image"><img alt="<?php echo $alt; ?>" /></figure>
      <!-- /wp:image -->
    </div>
    <!-- /wp:group -->
			<?php $logo_items .= ob_get_clean();
		}

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $wrap_class; ?> py-5","align":"full"} -->
<section class="wp-block-group <?php echo $wrap_class; ?> py-5 alignfull">
  <!-- wp:paragraph {"align":"center","textColor":"muted","className":"gf-eyebrow"} -->
  <p class="has-text-align-center has-muted-color has-text-color gf-eyebrow"><?php echo $heading; ?></p>
  <!-- /wp:paragraph -->
  <!-- wp:group {"className":"gf-logo-grid"} -->
  <div class="wp-block-group gf-logo-grid">
<?php echo $logo_items; ?>
  </div>
  <!-- /wp:group -->
  <!-- wp:html -->
  <blockquote class="gf-press-blockquote">
    <p>&#8220;<?php echo $quote; ?>&#8221;</p>
    <cite>— <?php echo $source; ?></cite>
  </blockquote>
  <!-- /wp:html -->
</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}
	/**
	 * Video testimonial — 55/45 columns: video thumbnail left, blockquote right.
	 */
	public static function render_video_testimonial( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-video-testimonial';

		$quote     = esc_html( $copy['quote']       ?? 'This solution completely transformed how our team operates day to day.' );
		$author    = esc_html( $copy['author_name'] ?? 'Sarah Johnson' );
		$role      = esc_html( $copy['author_role'] ?? 'VP of Operations, Acme Corp' );
		$video_url = esc_url( $copy['video_url']    ?? '' );
		$thumb_alt = esc_attr( $copy['thumb_alt']   ?? 'Video testimonial thumbnail' );

		ob_start(); ?>
<!-- wp:group {"className":"<?php echo $css; ?> py-5","tagName":"section","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5">
<!-- wp:html -->
  <div class="container" style="max-width:1000px;">
    <div class="row g-5 align-items-center">

      <div class="col-md-6">
        <div class="gf-video-thumb bg-primary rounded overflow-hidden position-relative" style="aspect-ratio:16/9;">
          <img src="<?php echo $video_url; ?>" alt="<?php echo $thumb_alt; ?>" class="w-100 h-100 object-fit-cover opacity-75" />
          <div class="position-absolute top-0 start-0 end-0 bottom-0 d-flex align-items-center justify-content-center">
            <button class="rounded-circle bg-white bg-opacity-90 border-0 d-flex align-items-center justify-content-center shadow" style="width:64px;height:64px;" aria-label="Play video">
              <svg viewBox="0 0 24 24" width="28" height="28" fill="var(--gf-primary)"><polygon points="5,3 19,12 5,21"/></svg>
            </button>
          </div>
        </div>
      </div>

      <div class="col-md-6">
        <blockquote class="gf-video-quote ps-4 mb-4" style="border-left:4px solid var(--gf-accent);">
          <p class="fs-5 fst-italic lh-lg mb-0">&ldquo;<?php echo $quote; ?>&rdquo;</p>
        </blockquote>
        <div class="d-flex align-items-center gap-3">
          <div class="rounded-circle bg-primary flex-shrink-0" style="width:44px;height:44px;"></div>
          <div>
            <p class="fw-bold text-primary mb-0 small"><?php echo $author; ?></p>
            <p class="text-muted small mb-0"><?php echo $role; ?></p>
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

	/**
	 * Awards shelf — horizontal row of trophy badges with name, year, and body.
	 */
	public static function render_awards_shelf( array $spec, array $manifest ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];
		$css     = implode( ' ', $classes ) ?: 'gf-awards-shelf gf-section-tint';

		$heading = esc_html( $copy['section_heading'] ?? 'Recognition & Awards' );
		$subtext = esc_html( $copy['subtext']         ?? 'Honored by industry leaders for innovation and impact.' );

		$award_defaults = [
			[ 'name' => 'Best Product 2024',   'year' => '2024', 'body' => 'TechCrunch Disrupt', 'icon' => 'bi-award-fill'        ],
			[ 'name' => 'Top 50 SaaS',         'year' => '2024', 'body' => 'G2 Summer Report',   'icon' => 'bi-patch-check-fill'  ],
			[ 'name' => "Editor's Choice",     'year' => '2023', 'body' => 'Product Hunt',        'icon' => 'bi-trophy-fill'       ],
			[ 'name' => 'Fast Company Top 10', 'year' => '2023', 'body' => 'Fast Company',        'icon' => 'bi-star-fill'         ],
		];

		$badges_html = '';
		for ( $i = 1; $i <= 4; $i++ ) {
			$d    = $award_defaults[ $i - 1 ];
			$name = esc_html( $copy[ "award_{$i}_name" ] ?? $d['name'] );
			$year = esc_html( $copy[ "award_{$i}_year" ] ?? $d['year'] );
			$body = esc_html( $copy[ "award_{$i}_body" ] ?? $d['body'] );
			$icon = $d['icon'];
			ob_start(); ?>
<div class="gf-award-badge">
  <div class="gf-award-icon">
    <i class="bi <?php echo $icon; ?>"></i>
  </div>
  <p class="gf-award-name"><?php echo $name; ?></p>
  <p class="gf-award-year"><?php echo $year; ?></p>
  <p class="gf-award-body"><?php echo $body; ?></p>
</div>
			<?php $badges_html .= ob_get_clean();
		}

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?>","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5">

<!-- wp:heading {"textAlign":"center","className":"gf-section-heading"} -->
<h2 class="wp-block-heading has-text-align-center gf-section-heading"><?php echo $heading; ?></h2>
<!-- /wp:heading -->
<!-- wp:paragraph {"align":"center","textColor":"muted","className":"gf-section-subtext"} -->
<p class="has-text-align-center has-muted-color has-text-color gf-section-subtext"><?php echo $subtext; ?></p>
<!-- /wp:paragraph -->

<!-- wp:html -->
<div class="container mt-4">
  <div class="gf-awards-row">
    <?php echo $badges_html; ?>
  </div>
</div>
<!-- /wp:html -->

</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}
}

// Self-register renderers with PatternBuilder.
GrayFox_TB_PatternBuilder::register_renderers( [
	'testimonials-single' => [ 'GrayFox_TB_Patterns_SocialProof', 'render_testimonials_single' ],
	'case-study-grid'     => [ 'GrayFox_TB_Patterns_SocialProof', 'render_case_study_grid'     ],
	'review-stars-row'    => [ 'GrayFox_TB_Patterns_SocialProof', 'render_review_stars_row'    ],
	'press-mentions'      => [ 'GrayFox_TB_Patterns_SocialProof', 'render_press_mentions'      ],
	'video-testimonial'   => [ 'GrayFox_TB_Patterns_SocialProof', 'render_video_testimonial'   ],
	'awards-shelf'        => [ 'GrayFox_TB_Patterns_SocialProof', 'render_awards_shelf'        ],
] );
