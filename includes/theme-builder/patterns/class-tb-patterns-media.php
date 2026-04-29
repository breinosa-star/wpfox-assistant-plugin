<?php
/**
 * Media / events patterns — portfolio-grid, speaker-grid, event-list-cards, office-cards.
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
 * Class GrayFox_TB_Patterns_Media
 */
class GrayFox_TB_Patterns_Media {

	// -------------------------------------------------------------------------
	// Renderers
	// -------------------------------------------------------------------------

	/**
	 * Portfolio grid — image cards with hover overlay showing title + category tag.
	 */
	public static function render_portfolio_grid( array $spec, array $theme ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];

		$wrap_class = esc_attr( implode( ' ', $classes ) ?: '' );
		$heading    = esc_html( $copy['section_heading'] ?? 'Our Work' );
		$subtext    = esc_html( $copy['subtext']         ?? 'A selection of projects we\'re proud of.' );
		$cta_text   = esc_html( $copy['cta_text']        ?? 'View all projects' );

		$defaults = [
			[ 'title' => 'Brand Identity Redesign', 'tag' => 'Branding',    'featured' => true  ],
			[ 'title' => 'Mobile App UI',            'tag' => 'Design',      'featured' => false ],
			[ 'title' => 'E-commerce Platform',      'tag' => 'Development', 'featured' => false ],
			[ 'title' => 'Marketing Campaign',       'tag' => 'Strategy',    'featured' => false ],
			[ 'title' => 'SaaS Dashboard',           'tag' => 'Product',     'featured' => false ],
		];

		$cards = '';
		for ( $i = 1; $i <= 5; $i++ ) {
			$d        = $defaults[ $i - 1 ];
			$title    = esc_html( $copy[ "project_{$i}_title" ] ?? $d['title'] );
			$tag      = esc_html( $copy[ "project_{$i}_tag"   ] ?? $d['tag'] );
			$featured = ! empty( $copy[ "project_{$i}_featured" ] ) || $d['featured'];
			$feat_cls = $featured ? ' gf-portfolio-featured' : '';

			ob_start(); ?>

  <!-- wp:group {"className":"gf-portfolio-card<?php echo $feat_cls; ?>"} -->
  <div class="wp-block-group gf-portfolio-card<?php echo $feat_cls; ?>">
    <!-- wp:image {"className":"gf-portfolio-img"} -->
    <figure class="wp-block-image gf-portfolio-img"><img alt="<?php echo $title; ?>" /></figure>
    <!-- /wp:image -->
    <!-- wp:group {"className":"gf-portfolio-overlay"} -->
    <div class="wp-block-group gf-portfolio-overlay">
      <!-- wp:paragraph {"className":"gf-portfolio-tag"} -->
      <p class="gf-portfolio-tag"><?php echo $tag; ?></p>
      <!-- /wp:paragraph -->
      <!-- wp:heading {"level":4,"className":"gf-portfolio-title"} -->
      <h4 class="wp-block-heading gf-portfolio-title"><?php echo $title; ?></h4>
      <!-- /wp:heading -->
    </div>
    <!-- /wp:group -->
  </div>
  <!-- /wp:group -->
			<?php $cards .= ob_get_clean();
		}

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $wrap_class; ?> py-5","align":"full"} -->
<section class="wp-block-group <?php echo $wrap_class; ?> py-5 alignfull">
  <!-- wp:heading {"textAlign":"center","level":2,"className":"gf-section-heading"} -->
  <h2 class="wp-block-heading has-text-align-center gf-section-heading"><?php echo $heading; ?></h2>
  <!-- /wp:heading -->
  <!-- wp:paragraph {"align":"center","textColor":"muted","className":"gf-section-subtext"} -->
  <p class="has-text-align-center has-muted-color has-text-color gf-section-subtext"><?php echo $subtext; ?></p>
  <!-- /wp:paragraph -->
  <!-- wp:group {"className":"gf-portfolio-grid"} -->
  <div class="wp-block-group gf-portfolio-grid">
<?php echo $cards; ?>
  </div>
  <!-- /wp:group -->
  <!-- wp:buttons {"className":"justify-content-center mt-4"} -->
  <div class="wp-block-buttons justify-content-center mt-4">
    <!-- wp:button {"className":"is-style-outline"} -->
    <div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button"><?php echo $cta_text; ?></a></div>
    <!-- /wp:button -->
  </div>
  <!-- /wp:buttons -->
</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	/**
	 * Speaker grid — conference / event speaker cards with photo, name, company, topic.
	 */
	public static function render_speaker_grid( array $spec, array $theme ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];

		$wrap_class = esc_attr( implode( ' ', $classes ) ?: 'gf-section-tint' );
		$heading    = esc_html( $copy['section_heading'] ?? 'Featured Speakers' );
		$subtext    = esc_html( $copy['subtext']         ?? 'Learn from the best in the industry.' );

		$defaults = [
			[ 'name' => 'Dr. Sarah Kim',     'company' => 'MIT',          'topic' => 'The Future of AI' ],
			[ 'name' => 'Marcus Johnson',    'company' => 'Stripe',       'topic' => 'Payments at Scale' ],
			[ 'name' => 'Aisha Patel',       'company' => 'Figma',        'topic' => 'Design Systems' ],
			[ 'name' => 'Carlos Rivera',     'company' => 'Sequoia',      'topic' => 'Building Unicorns' ],
		];

		$cards = '';
		for ( $i = 1; $i <= 4; $i++ ) {
			$d       = $defaults[ $i - 1 ];
			$name    = esc_html( $copy[ "speaker_{$i}_name"    ] ?? $d['name']    );
			$company = esc_html( $copy[ "speaker_{$i}_company" ] ?? $d['company'] );
			$topic   = esc_html( $copy[ "speaker_{$i}_topic"   ] ?? $d['topic']   );

			ob_start(); ?>

    <!-- wp:column -->
    <div class="wp-block-column">
      <!-- wp:group {"className":"gf-speaker-card"} -->
      <div class="wp-block-group gf-speaker-card">
        <!-- wp:image {"className":"gf-speaker-photo"} -->
        <figure class="wp-block-image gf-speaker-photo"><img alt="<?php echo $name; ?>" /></figure>
        <!-- /wp:image -->
        <!-- wp:heading {"level":4,"textAlign":"center"} -->
        <h4 class="wp-block-heading has-text-align-center"><?php echo $name; ?></h4>
        <!-- /wp:heading -->
        <!-- wp:paragraph {"align":"center","textColor":"muted"} -->
        <p class="has-text-align-center has-muted-color has-text-color"><?php echo $company; ?></p>
        <!-- /wp:paragraph -->
        <!-- wp:paragraph {"align":"center","className":"gf-eyebrow"} -->
        <p class="has-text-align-center gf-eyebrow"><?php echo $topic; ?></p>
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
	 * Event list cards — cards with a date badge, title, location, type tag.
	 */
	public static function render_event_list_cards( array $spec, array $theme ): string {
		$copy    = $spec['copy']        ?? [];
		$classes = $spec['css_classes'] ?? [];

		$css      = implode( ' ', $classes ) ?: 'gf-event-list';
		$heading  = esc_html( $copy['section_heading'] ?? 'Upcoming Events' );
		$subtext  = esc_html( $copy['subtext']         ?? 'Join us in person or online.' );
		$cta_text = esc_html( $copy['cta_text']        ?? 'View all events' );

		$defaults = [
			[ 'month' => 'JAN', 'day' => '15', 'title' => 'Annual Summit 2025',      'location' => 'San Francisco, CA',   'type' => 'In Person' ],
			[ 'month' => 'FEB', 'day' => '08', 'title' => 'Product Workshop',         'location' => 'Online',              'type' => 'Virtual'   ],
			[ 'month' => 'MAR', 'day' => '22', 'title' => 'Community Meetup',         'location' => 'New York, NY',        'type' => 'In Person' ],
		];

		$cards = '';
		for ( $i = 1; $i <= 3; $i++ ) {
			$d        = $defaults[ $i - 1 ];
			$month    = esc_html( $copy[ "event_{$i}_month"    ] ?? $d['month']    );
			$day      = esc_html( $copy[ "event_{$i}_day"      ] ?? $d['day']      );
			$title    = esc_html( $copy[ "event_{$i}_title"    ] ?? $d['title']    );
			$location = esc_html( $copy[ "event_{$i}_location" ] ?? $d['location'] );
			$type     = esc_html( $copy[ "event_{$i}_type"     ] ?? $d['type']     );

			ob_start(); ?>

  <!-- wp:group {"className":"gf-event-card"} -->
  <div class="wp-block-group gf-event-card">
    <!-- wp:group {"className":"gf-event-date-badge"} -->
    <div class="wp-block-group gf-event-date-badge">
      <!-- wp:paragraph {"align":"center","className":"gf-month"} -->
      <p class="has-text-align-center gf-month"><?php echo $month; ?></p>
      <!-- /wp:paragraph -->
      <!-- wp:paragraph {"align":"center","className":"gf-day"} -->
      <p class="has-text-align-center gf-day"><?php echo $day; ?></p>
      <!-- /wp:paragraph -->
    </div>
    <!-- /wp:group -->
    <!-- wp:group {"className":"flex-grow-1"} -->
    <div class="wp-block-group flex-grow-1">
      <!-- wp:paragraph {"className":"gf-event-type-badge"} -->
      <p class="gf-event-type-badge"><?php echo $type; ?></p>
      <!-- /wp:paragraph -->
      <!-- wp:heading {"level":4} -->
      <h4 class="wp-block-heading"><?php echo $title; ?></h4>
      <!-- /wp:heading -->
      <!-- wp:paragraph {"textColor":"muted"} -->
      <p class="has-muted-color has-text-color"><i class="bi bi-geo-alt"></i> <?php echo $location; ?></p>
      <!-- /wp:paragraph -->
    </div>
    <!-- /wp:group -->
    <!-- wp:buttons {"className":"ms-auto flex-shrink-0"} -->
    <div class="wp-block-buttons ms-auto flex-shrink-0">
      <!-- wp:button {"className":"is-style-outline"} -->
      <div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button">Register</a></div>
      <!-- /wp:button -->
    </div>
    <!-- /wp:buttons -->
  </div>
  <!-- /wp:group -->
			<?php $cards .= ob_get_clean();
		}

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $css; ?> py-5","align":"full"} -->
<section class="wp-block-group alignfull <?php echo $css; ?> py-5">
  <!-- wp:heading {"textAlign":"center","level":2,"textColor":"primary","className":"gf-section-heading"} -->
  <h2 class="wp-block-heading has-text-align-center has-primary-color has-text-color gf-section-heading"><?php echo $heading; ?></h2>
  <!-- /wp:heading -->
  <!-- wp:paragraph {"align":"center","textColor":"muted"} -->
  <p class="has-text-align-center has-muted-color has-text-color"><?php echo $subtext; ?></p>
  <!-- /wp:paragraph -->
  <!-- wp:group {"className":"d-flex flex-column gap-3 mt-4"} -->
  <div class="wp-block-group d-flex flex-column gap-3 mt-4">
<?php echo $cards; ?>
  </div>
  <!-- /wp:group -->
  <!-- wp:buttons {"className":"justify-content-center mt-4"} -->
  <div class="wp-block-buttons justify-content-center mt-4">
    <!-- wp:button {"className":"is-style-outline"} -->
    <div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button"><?php echo $cta_text; ?></a></div>
    <!-- /wp:button -->
  </div>
  <!-- /wp:buttons -->
</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}

	/**
	 * Office location cards — address, phone, email, map placeholder.
	 */
	public static function render_office_cards( array $spec, array $theme ): string {
		$copy       = $spec['copy']        ?? [];
		$classes    = $spec['css_classes'] ?? [];

		$wrap_class = esc_attr( implode( ' ', $classes ) ?: 'gf-section-tint' );
		$heading    = esc_html( $copy['section_heading'] ?? 'Our Offices' );
		$subtext    = esc_html( $copy['subtext']         ?? 'Come say hello at one of our locations.' );

		$defaults = [
			[
				'city'    => 'San Francisco',
				'address' => '100 Market St, Suite 400',
				'phone'   => '+1 (415) 555-0100',
				'email'   => 'sf@company.com',
			],
			[
				'city'    => 'New York',
				'address' => '350 5th Ave, Floor 20',
				'phone'   => '+1 (212) 555-0200',
				'email'   => 'nyc@company.com',
			],
			[
				'city'    => 'London',
				'address' => '1 Canary Wharf, Level 10',
				'phone'   => '+44 20 7946 0200',
				'email'   => 'london@company.com',
			],
		];

		$map_defaults = [
			'https://www.openstreetmap.org/export/embed.html?bbox=-122.406%2C37.784%2C-122.386%2C37.804&layer=mapnik',
			'https://www.openstreetmap.org/export/embed.html?bbox=-74.006%2C40.729%2C-73.966%2C40.769&layer=mapnik',
			'https://www.openstreetmap.org/export/embed.html?bbox=-0.040%2C51.495%2C0.000%2C51.520&layer=mapnik',
		];

		$cards = '';
		for ( $i = 1; $i <= 3; $i++ ) {
			$d       = $defaults[ $i - 1 ];
			$city    = esc_html( $copy[ "office_{$i}_city"    ] ?? $d['city']    );
			$address = esc_html( $copy[ "office_{$i}_address" ] ?? $d['address'] );
			$phone   = esc_html( $copy[ "office_{$i}_phone"   ] ?? $d['phone']   );
			$email   = esc_html( $copy[ "office_{$i}_email"   ] ?? $d['email']   );
			$map_url = esc_url( $copy[ "office_{$i}_map_url"  ] ?? $map_defaults[ $i - 1 ] );

			ob_start(); ?>

    <!-- wp:column -->
    <div class="wp-block-column">
      <!-- wp:group {"className":"gf-office-card"} -->
      <div class="wp-block-group gf-office-card">
        <!-- wp:html -->
        <div class="gf-office-map-placeholder">
          <iframe src="<?php echo $map_url; ?>" title="<?php echo $city; ?> map" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
        </div>
        <!-- /wp:html -->
        <!-- wp:heading {"level":4,"className":"gf-office-city"} -->
        <h4 class="wp-block-heading gf-office-city"><?php echo $city; ?></h4>
        <!-- /wp:heading -->
        <!-- wp:html -->
        <div class="gf-office-contact-list">
          <div class="gf-office-contact-row"><i class="bi bi-geo-alt gf-contact-icon" aria-hidden="true"></i><p class="mb-0"><?php echo $address; ?></p></div>
          <div class="gf-office-contact-row"><i class="bi bi-telephone gf-contact-icon" aria-hidden="true"></i><p class="mb-0"><?php echo $phone; ?></p></div>
          <div class="gf-office-contact-row"><i class="bi bi-envelope gf-contact-icon" aria-hidden="true"></i><p class="mb-0"><a href="mailto:<?php echo $email; ?>"><?php echo $email; ?></a></p></div>
        </div>
        <!-- /wp:html -->
      </div>
      <!-- /wp:group -->
    </div>
    <!-- /wp:column -->
			<?php $cards .= ob_get_clean();
		}

		ob_start(); ?>
<!-- wp:group {"tagName":"section","className":"<?php echo $wrap_class; ?> py-5","align":"full"} -->
<section class="wp-block-group <?php echo $wrap_class; ?> py-5 alignfull">
  <!-- wp:heading {"textAlign":"center","level":2,"className":"gf-section-heading"} -->
  <h2 class="wp-block-heading has-text-align-center gf-section-heading"><?php echo $heading; ?></h2>
  <!-- /wp:heading -->
  <!-- wp:paragraph {"align":"center","textColor":"muted","className":"gf-section-subtext"} -->
  <p class="has-text-align-center has-muted-color has-text-color gf-section-subtext"><?php echo $subtext; ?></p>
  <!-- /wp:paragraph -->
  <!-- wp:columns {"isStackedOnMobile":true} -->
  <div class="wp-block-columns is-stacked-on-mobile mt-4">
<?php echo $cards; ?>
  </div>
  <!-- /wp:columns -->
</section>
<!-- /wp:group -->
		<?php return ob_get_clean();
	}
}

// Self-register renderers with PatternBuilder.
GrayFox_TB_PatternBuilder::register_renderers( [
	'portfolio-grid'    => [ 'GrayFox_TB_Patterns_Media', 'render_portfolio_grid'    ],
	'speaker-grid'      => [ 'GrayFox_TB_Patterns_Media', 'render_speaker_grid'      ],
	'event-list-cards'  => [ 'GrayFox_TB_Patterns_Media', 'render_event_list_cards'  ],
	'office-cards'      => [ 'GrayFox_TB_Patterns_Media', 'render_office_cards'      ],
] );
