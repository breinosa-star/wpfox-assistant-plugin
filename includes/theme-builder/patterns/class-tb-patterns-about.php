<?php
/**
 * About / people patterns — team-grid, founders-row, advisor-grid, vertical-timeline.
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
 * Class GrayFox_TB_Patterns_About
 */
class GrayFox_TB_Patterns_About {

	// -------------------------------------------------------------------------
	// Renderers
	// -------------------------------------------------------------------------

	/**
	 * Four-person team grid with photo placeholder, name, role, bio.
	 */
	public static function render_team_grid( array $spec, array $theme ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];

		$wrap_class = esc_attr( implode( ' ', $classes ) ?: '' );
		$heading    = esc_html( $copy['section_heading'] ?? 'Meet the Team' );
		$subtext    = esc_html( $copy['subtext']         ?? 'The people behind the product.' );

		$members = [];
		$defaults = [
			[ 'name' => 'Alex Rivera',   'role' => 'CEO & Co-Founder',    'bio' => '10+ years building category-defining products.' ],
			[ 'name' => 'Jordan Lee',    'role' => 'CTO & Co-Founder',    'bio' => 'Former engineering lead at Google and Stripe.' ],
			[ 'name' => 'Sam Torres',    'role' => 'Head of Design',      'bio' => 'Obsessed with making complex things simple.' ],
			[ 'name' => 'Morgan Chen',   'role' => 'Head of Growth',      'bio' => 'Scaled three startups from 0 to 1M users.' ],
		];
		for ( $i = 1; $i <= 4; $i++ ) {
			$members[] = [
				'name' => esc_html( $copy[ "member_{$i}_name" ] ?? $defaults[ $i - 1 ]['name'] ),
				'role' => esc_html( $copy[ "member_{$i}_role" ] ?? $defaults[ $i - 1 ]['role'] ),
				'bio'  => esc_html( $copy[ "member_{$i}_bio"  ] ?? $defaults[ $i - 1 ]['bio'] ),
			];
		}

		$cards = '';
		foreach ( $members as $m ) {
			ob_start(); ?>

    <!-- wp:column -->
    <div class="wp-block-column">
      <!-- wp:group {"className":"gf-team-card card h-100"} -->
      <div class="wp-block-group gf-team-card card h-100">
        <!-- wp:image {"className":"gf-team-photo"} -->
        <figure class="wp-block-image gf-team-photo"><img alt="<?php echo $m['name']; ?>" /></figure>
        <!-- /wp:image -->
        <!-- wp:heading {"level":4,"textAlign":"center"} -->
        <h4 class="wp-block-heading has-text-align-center"><?php echo $m['name']; ?></h4>
        <!-- /wp:heading -->
        <!-- wp:paragraph {"align":"center","textColor":"muted"} -->
        <p class="has-text-align-center has-muted-color has-text-color"><?php echo $m['role']; ?></p>
        <!-- /wp:paragraph -->
        <!-- wp:paragraph {"align":"center","textColor":"muted"} -->
        <p class="has-text-align-center has-muted-color has-text-color"><?php echo $m['bio']; ?></p>
        <!-- /wp:paragraph -->
      </div>
      <!-- /wp:group -->
    </div>
    <!-- /wp:column -->
			<?php $cards .= ob_get_clean();
		}

		ob_start(); ?>
<!-- wp:group {"className":"<?php echo $wrap_class; ?> py-5","align":"full","layout":{"type":"constrained"}} -->
<div class="wp-block-group <?php echo $wrap_class; ?> py-5 alignfull">
  <!-- wp:heading {"textAlign":"center","level":2,"className":"gf-section-heading"} -->
  <h2 class="wp-block-heading has-text-align-center gf-section-heading"><?php echo $heading; ?></h2>
  <!-- /wp:heading -->
  <!-- wp:paragraph {"align":"center","textColor":"muted","className":"gf-section-subtext"} -->
  <p class="has-text-align-center has-muted-color has-text-color gf-section-subtext"><?php echo $subtext; ?></p>
  <!-- /wp:paragraph -->
  <!-- wp:columns {"isStackedOnMobile":true} -->
  <div class="wp-block-columns is-stacked-on-mobile">
<?php echo $cards; ?>
  </div>
  <!-- /wp:columns -->
</div>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	/**
	 * Two horizontal founder bio cards — photo + extended bio side by side.
	 */
	public static function render_founders_row( array $spec, array $theme ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];

		$wrap_class = esc_attr( implode( ' ', $classes ) ?: '' );
		$heading    = esc_html( $copy['section_heading'] ?? 'Founded on a mission' );

		$founders = [
			[
				'name'    => esc_html( $copy['founder_1_name']    ?? 'Alex Rivera' ),
				'role'    => esc_html( $copy['founder_1_role']    ?? 'CEO & Co-Founder' ),
				'bio'     => esc_html( $copy['founder_1_bio']     ?? 'Before starting this company, Alex spent a decade at the intersection of design and engineering, launching products used by millions.' ),
				'connect' => esc_html( $copy['founder_1_connect'] ?? 'linkedin.com/in/alexrivera' ),
			],
			[
				'name'    => esc_html( $copy['founder_2_name']    ?? 'Jordan Lee' ),
				'role'    => esc_html( $copy['founder_2_role']    ?? 'CTO & Co-Founder' ),
				'bio'     => esc_html( $copy['founder_2_bio']     ?? 'Jordan previously led platform engineering at two unicorn startups and is passionate about developer experience.' ),
				'connect' => esc_html( $copy['founder_2_connect'] ?? 'linkedin.com/in/jordanlee' ),
			],
		];

		$cards = '';
		foreach ( $founders as $f ) {
			ob_start(); ?>

    <!-- wp:column -->
    <div class="wp-block-column">
      <!-- wp:group {"className":"gf-founders-card h-100","layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"top"},"style":{"spacing":{"blockGap":"2rem"}}} -->
      <div class="wp-block-group gf-founders-card h-100">
        <!-- wp:image {"style":{"border":{"radius":"50%"}},"className":"gf-team-photo"} -->
        <figure class="wp-block-image gf-team-photo"><img alt="<?php echo $f['name']; ?>" /></figure>
        <!-- /wp:image -->
        <!-- wp:group {"layout":{"type":"flex","orientation":"vertical"},"style":{"spacing":{"blockGap":".5rem"}}} -->
        <div class="wp-block-group">
          <!-- wp:heading {"level":3,"style":{"typography":{"fontSize":"1.2rem","fontWeight":"700"}}} -->
          <h3 class="wp-block-heading"><?php echo $f['name']; ?></h3>
          <!-- /wp:heading -->
          <!-- wp:paragraph {"textColor":"muted","style":{"typography":{"fontSize":".8rem","fontWeight":"700","textTransform":"uppercase","letterSpacing":".06em"}}} -->
          <p class="has-muted-color has-text-color"><?php echo $f['role']; ?></p>
          <!-- /wp:paragraph -->
          <!-- wp:paragraph {"textColor":"muted","style":{"typography":{"fontSize":".9rem","lineHeight":"1.65"}}} -->
          <p class="has-muted-color has-text-color"><?php echo $f['bio']; ?></p>
          <!-- /wp:paragraph -->
        </div>
        <!-- /wp:group -->
      </div>
      <!-- /wp:group -->
    </div>
    <!-- /wp:column -->
			<?php $cards .= ob_get_clean();
		}

		ob_start(); ?>
<!-- wp:group {"className":"<?php echo $wrap_class; ?> py-5","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group <?php echo $wrap_class; ?> py-5 alignfull">
  <!-- wp:heading {"textAlign":"center","level":2,"className":"gf-section-heading"} -->
  <h2 class="wp-block-heading has-text-align-center gf-section-heading"><?php echo $heading; ?></h2>
  <!-- /wp:heading -->
  <!-- wp:columns {"isStackedOnMobile":true,"style":{"spacing":{"blockGap":{"top":"var:preset|spacing|50","left":"var:preset|spacing|50"},"margin":{"top":"var:preset|spacing|50"}}}} -->
  <div class="wp-block-columns is-stacked-on-mobile">
<?php echo $cards; ?>
  </div>
  <!-- /wp:columns -->
</div>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	/**
	 * Advisor grid — smaller cards, typically 4–6 advisors in two rows.
	 */
	public static function render_advisor_grid( array $spec, array $theme ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];

		$wrap_class = esc_attr( implode( ' ', $classes ) ?: 'gf-section-tint' );
		$heading    = esc_html( $copy['section_heading'] ?? 'Our Advisors' );
		$subtext    = esc_html( $copy['subtext']         ?? 'Guided by world-class industry leaders.' );

		$defaults = [
			[ 'name' => 'Dr. Emily Park',   'role' => 'Former VP, Google',       'org' => 'Google' ],
			[ 'name' => 'Marcus Webb',      'role' => 'Partner, Sequoia Capital', 'org' => 'Sequoia' ],
			[ 'name' => 'Priya Nair',       'role' => 'CTO, Stripe',             'org' => 'Stripe' ],
			[ 'name' => 'Carlos Mendez',    'role' => 'CEO, Techstars',          'org' => 'Techstars' ],
		];

		$cards = '';
		for ( $i = 1; $i <= 4; $i++ ) {
			$d     = $defaults[ $i - 1 ];
			$name  = esc_html( $copy[ "advisor_{$i}_name" ] ?? $d['name'] );
			$role  = esc_html( $copy[ "advisor_{$i}_role" ] ?? $d['role'] );
			$org   = esc_html( $copy[ "advisor_{$i}_org"  ] ?? $d['org'] );

			ob_start(); ?>

    <!-- wp:column -->
    <div class="wp-block-column">
      <!-- wp:group {"className":"gf-team-card card h-100","layout":{"type":"flex","orientation":"vertical","justifyContent":"center"}} -->
      <div class="wp-block-group gf-team-card card h-100">
        <!-- wp:image {"className":"gf-team-photo","style":{"border":{"radius":"50%"}}} -->
        <figure class="wp-block-image gf-team-photo"><img alt="<?php echo $name; ?>" /></figure>
        <!-- /wp:image -->
        <!-- wp:heading {"level":5,"textAlign":"center","style":{"typography":{"fontSize":".95rem","fontWeight":"700"}}} -->
        <h5 class="wp-block-heading has-text-align-center"><?php echo $name; ?></h5>
        <!-- /wp:heading -->
        <!-- wp:paragraph {"align":"center","textColor":"muted","style":{"typography":{"fontSize":".8rem"}}} -->
        <p class="has-text-align-center has-muted-color has-text-color"><?php echo $role; ?></p>
        <!-- /wp:paragraph -->
      </div>
      <!-- /wp:group -->
    </div>
    <!-- /wp:column -->
			<?php $cards .= ob_get_clean();
		}

		ob_start(); ?>
<!-- wp:group {"className":"<?php echo $wrap_class; ?> py-5","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group <?php echo $wrap_class; ?> py-5 alignfull">
  <!-- wp:heading {"textAlign":"center","level":2,"className":"gf-section-heading"} -->
  <h2 class="wp-block-heading has-text-align-center gf-section-heading"><?php echo $heading; ?></h2>
  <!-- /wp:heading -->
  <!-- wp:paragraph {"align":"center","textColor":"muted","className":"gf-section-subtext"} -->
  <p class="has-text-align-center has-muted-color has-text-color gf-section-subtext"><?php echo $subtext; ?></p>
  <!-- /wp:paragraph -->
  <!-- wp:columns {"isStackedOnMobile":true,"style":{"spacing":{"blockGap":{"top":"var:preset|spacing|40","left":"var:preset|spacing|40"},"margin":{"top":"var:preset|spacing|50"}}}} -->
  <div class="wp-block-columns is-stacked-on-mobile">
<?php echo $cards; ?>
  </div>
  <!-- /wp:columns -->
</div>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	/**
	 * Vertical timeline with gradient connector line — milestones / history.
	 */
	public static function render_vertical_timeline( array $spec, array $theme ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];

		$wrap_class = esc_attr( implode( ' ', $classes ) ?: '' );
		$heading    = esc_html( $copy['section_heading'] ?? 'Our Journey' );
		$subtext    = esc_html( $copy['subtext']         ?? 'From a small idea to a global product.' );

		$defaults = [
			[ 'year' => '2019', 'title' => 'Founded',         'text' => 'Started in a garage with a bold vision and two laptops.' ],
			[ 'year' => '2020', 'title' => 'First 1,000 users', 'text' => 'Launched publicly and hit 1K users in 90 days.' ],
			[ 'year' => '2022', 'title' => 'Series A',         'text' => 'Raised $12M to expand the team and accelerate growth.' ],
			[ 'year' => '2024', 'title' => '1M users',         'text' => 'Crossed one million active users across 40 countries.' ],
		];

		$items = '';
		for ( $i = 1; $i <= 4; $i++ ) {
			$d     = $defaults[ $i - 1 ];
			$year  = esc_html( $copy[ "item_{$i}_year"  ] ?? $d['year'] );
			$title = esc_html( $copy[ "item_{$i}_title" ] ?? $d['title'] );
			$text  = esc_html( $copy[ "item_{$i}_text"  ] ?? $d['text'] );

			ob_start(); ?>

  <!-- wp:group {"className":"gf-timeline-item","layout":{"type":"constrained"}} -->
  <div class="wp-block-group gf-timeline-item">
    <!-- wp:paragraph {"className":"gf-timeline-year","style":{"typography":{"fontSize":".75rem","fontWeight":"700","textTransform":"uppercase","letterSpacing":".08em"}}} -->
    <p class="gf-timeline-year"><?php echo $year; ?></p>
    <!-- /wp:paragraph -->
    <!-- wp:heading {"level":4,"style":{"typography":{"fontSize":"1.05rem","fontWeight":"700"}}} -->
    <h4 class="wp-block-heading"><?php echo $title; ?></h4>
    <!-- /wp:heading -->
    <!-- wp:paragraph {"textColor":"muted","style":{"typography":{"fontSize":".9rem","lineHeight":"1.65"}}} -->
    <p class="has-muted-color has-text-color"><?php echo $text; ?></p>
    <!-- /wp:paragraph -->
  </div>
  <!-- /wp:group -->
			<?php $items .= ob_get_clean();
		}

		ob_start(); ?>
<!-- wp:group {"className":"<?php echo $wrap_class; ?>","align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|60"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group <?php echo $wrap_class; ?> alignfull">
  <!-- wp:heading {"textAlign":"center","level":2,"className":"gf-section-heading"} -->
  <h2 class="wp-block-heading has-text-align-center gf-section-heading"><?php echo $heading; ?></h2>
  <!-- /wp:heading -->
  <!-- wp:paragraph {"align":"center","textColor":"muted","className":"gf-section-subtext"} -->
  <p class="has-text-align-center has-muted-color has-text-color gf-section-subtext"><?php echo $subtext; ?></p>
  <!-- /wp:paragraph -->
  <!-- wp:group {"className":"gf-timeline","layout":{"type":"constrained","contentSize":"600px"},"style":{"spacing":{"margin":{"top":"var:preset|spacing|50"}}}} -->
  <div class="wp-block-group gf-timeline">
<?php echo $items; ?>
  </div>
  <!-- /wp:group -->
</div>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}
	/**
	 * Team list — stacked rows of member photo (15%) + name/role/bio (85%).
	 */
	public static function render_team_list( array $spec, array $theme ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];

		$wrap_class = esc_attr( implode( ' ', $classes ) ?: '' );
		$heading    = esc_html( $copy['section_heading'] ?? 'Our Team' );
		$subtext    = esc_html( $copy['subtext']         ?? 'The people who make it happen.' );

		$defaults = [
			[ 'name' => 'Alex Rivera',  'role' => 'CEO & Co-Founder',   'bio' => '10+ years building category-defining products used by millions.' ],
			[ 'name' => 'Jordan Lee',   'role' => 'CTO & Co-Founder',   'bio' => 'Former engineering lead at Google and Stripe. Loves distributed systems.' ],
			[ 'name' => 'Sam Torres',   'role' => 'Head of Design',     'bio' => 'Obsessed with turning complexity into beautiful, simple experiences.' ],
			[ 'name' => 'Morgan Chen',  'role' => 'Head of Growth',     'bio' => 'Scaled three startups from 0 to 1M users through product-led growth.' ],
			[ 'name' => 'Riley Park',   'role' => 'Head of Engineering','bio' => 'Open-source contributor, conference speaker, caffeine-dependent.' ],
			[ 'name' => 'Casey Nguyen', 'role' => 'Head of Customer Success', 'bio' => 'Champions customer outcomes with empathy and relentless follow-through.' ],
		];

		$rows = '';
		for ( $i = 1; $i <= 6; $i++ ) {
			$d    = $defaults[ $i - 1 ];
			$name = esc_html( $copy[ "member_{$i}_name" ] ?? $d['name'] );
			$role = esc_html( $copy[ "member_{$i}_role" ] ?? $d['role'] );
			$bio  = esc_html( $copy[ "member_{$i}_bio"  ] ?? $d['bio']  );

			ob_start(); ?>

<!-- wp:group {"className":"gf-team-row"} -->
<div class="wp-block-group gf-team-row">
<!-- wp:columns -->
<div class="wp-block-columns">
<!-- wp:column {"width":"15%"} -->
<div class="wp-block-column" style="flex-basis:15%">
<!-- wp:image {"className":"gf-team-photo"} -->
<figure class="wp-block-image gf-team-photo"><img alt="<?php echo $name; ?>" /></figure>
<!-- /wp:image -->
</div>
<!-- /wp:column -->
<!-- wp:column {"width":"85%"} -->
<div class="wp-block-column" style="flex-basis:85%">
<!-- wp:heading {"level":4} -->
<h4 class="wp-block-heading"><?php echo $name; ?></h4>
<!-- /wp:heading -->
<!-- wp:paragraph {"textColor":"accent"} -->
<p class="has-accent-color has-text-color"><?php echo $role; ?></p>
<!-- /wp:paragraph -->
<!-- wp:paragraph {"textColor":"muted"} -->
<p class="has-muted-color has-text-color"><?php echo $bio; ?></p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:column -->
</div>
<!-- /wp:columns -->
</div>
<!-- /wp:group -->
			<?php $rows .= ob_get_clean();
		}

		ob_start(); ?>
<!-- wp:group {"className":"<?php echo $wrap_class; ?> py-5","align":"full","layout":{"type":"constrained"}} -->
<div class="wp-block-group <?php echo $wrap_class; ?> py-5 alignfull">
  <!-- wp:heading {"textAlign":"center","level":2,"className":"gf-section-heading"} -->
  <h2 class="wp-block-heading has-text-align-center gf-section-heading"><?php echo $heading; ?></h2>
  <!-- /wp:heading -->
  <!-- wp:paragraph {"align":"center","textColor":"muted","className":"gf-section-subtext"} -->
  <p class="has-text-align-center has-muted-color has-text-color gf-section-subtext"><?php echo $subtext; ?></p>
  <!-- /wp:paragraph -->
  <!-- wp:group {"className":"gf-team-list"} -->
  <div class="wp-block-group gf-team-list">
<?php echo $rows; ?>
  </div>
  <!-- /wp:group -->
</div>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	/**
	 * Founder bio full — large photo left (40%), long bio + pull quote right (60%).
	 */
	public static function render_founder_bio_full( array $spec, array $theme ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];

		$css = implode( ' ', $classes ) ?: 'gf-founder-bio';

		$eyebrow = esc_html( $copy['eyebrow'] ?? 'Our Story' );
		$name    = esc_html( $copy['name']    ?? 'Founder Name' );
		$role    = esc_html( $copy['role']    ?? 'Co-Founder & CEO' );
		$p1      = esc_html( $copy['p1']      ?? 'Growing up in a household where entrepreneurship was the dinner table conversation, I learned early on that building something meaningful requires equal parts vision and stubbornness. After years in corporate strategy, I kept seeing the same gap — teams with great ideas but no scalable way to execute them.' );
		$p2      = esc_html( $copy['p2']      ?? 'The idea for this company came on a flight back from a failed product launch at my previous job. We had the talent, the budget, and the demand — but we lacked the operational layer that could translate strategy into consistent delivery. I landed and started writing the first version of what would become our core platform.' );
		$p3      = esc_html( $copy['p3']      ?? 'The first two years were anything but linear. We rebuilt the product architecture twice, cycled through three go-to-market approaches, and came within weeks of running out of runway. What kept us going was relentless customer feedback and a founding team that genuinely believed the problem was worth solving.' );
		$p4      = esc_html( $copy['p4']      ?? 'Today we serve hundreds of teams across three continents, but the mission has never changed: make great execution accessible to every organization, not just the ones with the deepest pockets. That\'s what gets me out of bed every morning.' );
		$quote1  = esc_html( $copy['quote1']  ?? 'The best companies are built by people who can\'t stop thinking about the problem they\'re solving.' );
		$cta     = esc_html( $copy['cta']     ?? '' );

		$cta_block = '';
		if ( $cta ) {
			ob_start(); ?>

<!-- wp:buttons {"className":"mt-4"} -->
<div class="wp-block-buttons mt-4">
<!-- wp:button {"backgroundColor":"primary","textColor":"contrast"} -->
<div class="wp-block-button"><a class="wp-block-button__link has-contrast-color has-primary-background-color has-text-color has-background wp-element-button"><?php echo $cta; ?></a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->
			<?php $cta_block = ob_get_clean();
		}

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?> py-5","align":"full","backgroundColor":"background"} -->
<section class="wp-block-group <?php echo $css; ?> py-5 alignfull has-background-background-color has-background">

<!-- wp:columns {"isStackedOnMobile":true,"verticalAlignment":"center"} -->
<div class="wp-block-columns is-stacked-on-mobile are-vertically-aligned-center">

<!-- wp:column {"width":"40%"} -->
<div class="wp-block-column" style="flex-basis:40%">
<!-- wp:image {"sizeSlug":"large","className":"gf-founder-photo","style":{"border":{"radius":"12px"}}} -->
<figure class="wp-block-image size-large gf-founder-photo"><img src="" alt="<?php echo $name; ?> — founder photo"/></figure>
<!-- /wp:image -->
</div>
<!-- /wp:column -->

<!-- wp:column {"width":"60%"} -->
<div class="wp-block-column" style="flex-basis:60%">
<!-- wp:paragraph {"textColor":"accent","className":"gf-eyebrow"} -->
<p class="gf-eyebrow has-accent-color has-text-color"><?php echo $eyebrow; ?></p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":2,"textColor":"primary"} -->
<h2 class="wp-block-heading has-primary-color has-text-color"><?php echo $name; ?></h2>
<!-- /wp:heading -->
<!-- wp:paragraph {"textColor":"muted","className":"fw-semibold"} -->
<p class="has-muted-color has-text-color fw-semibold"><?php echo $role; ?></p>
<!-- /wp:paragraph -->
<!-- wp:paragraph {"textColor":"foreground"} -->
<p class="has-foreground-color has-text-color"><?php echo $p1; ?></p>
<!-- /wp:paragraph -->
<!-- wp:paragraph {"textColor":"foreground"} -->
<p class="has-foreground-color has-text-color"><?php echo $p2; ?></p>
<!-- /wp:paragraph -->
<!-- wp:paragraph {"textColor":"foreground"} -->
<p class="has-foreground-color has-text-color"><?php echo $p3; ?></p>
<!-- /wp:paragraph -->
<!-- wp:paragraph {"textColor":"foreground"} -->
<p class="has-foreground-color has-text-color"><?php echo $p4; ?></p>
<!-- /wp:paragraph -->
<!-- wp:html -->
<blockquote style="border-left:4px solid var(--wp--preset--color--accent);padding-left:1.25rem;margin:1.5rem 0;font-style:italic;color:var(--wp--preset--color--primary)">
  &ldquo;<?php echo $quote1; ?>&rdquo;
</blockquote>
<!-- /wp:html -->
<?php echo $cta_block; ?>
</div>
<!-- /wp:column -->

</div>
<!-- /wp:columns -->

</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}
}

// Self-register renderers with PatternBuilder.
GrayFox_TB_PatternBuilder::register_renderers( [
	'team-grid'          => [ 'GrayFox_TB_Patterns_About', 'render_team_grid'          ],
	'founders-row'       => [ 'GrayFox_TB_Patterns_About', 'render_founders_row'       ],
	'advisor-grid'       => [ 'GrayFox_TB_Patterns_About', 'render_advisor_grid'       ],
	'vertical-timeline'  => [ 'GrayFox_TB_Patterns_About', 'render_vertical_timeline'  ],
	'team-list'          => [ 'GrayFox_TB_Patterns_About', 'render_team_list'          ],
	'founder-bio-full'   => [ 'GrayFox_TB_Patterns_About', 'render_founder_bio_full'   ],
] );
