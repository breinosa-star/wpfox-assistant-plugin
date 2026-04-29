<?php
/**
 * Manifest builder — constructs a manifest from a legacy flat profile,
 * and detects whether input is already a manifest.
 *
 * Ported from wp-theme-builder/src/main.py::build_manifest_from_profile()
 *
 * @package GrayFox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GrayFox_TB_ManifestBuilder
 */
class GrayFox_TB_ManifestBuilder {

	/**
	 * Ensure input is a full manifest. Converts legacy flat profiles.
	 *
	 * @param array $profile_or_manifest Brand profile or full manifest.
	 * @return array Full manifest array.
	 */
	public static function ensure_manifest( array $profile_or_manifest ): array {
		// Detect manifest: has 'theme' key with 'colors' subkey.
		if ( isset( $profile_or_manifest['theme']['colors'] ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[TB] ensure_manifest: detected full manifest via theme.colors — returning as-is' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			return $profile_or_manifest;
		}
		// Detect manifest: has 'theme' key with 'slug' subkey.
		if ( isset( $profile_or_manifest['theme']['slug'] ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[TB] ensure_manifest: detected full manifest via theme.slug — returning as-is' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			return $profile_or_manifest;
		}
		// Legacy flat profile — convert.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[TB] ensure_manifest: legacy flat profile — calling build_manifest_from_profile()' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		return self::build_manifest_from_profile( $profile_or_manifest );
	}

	/**
	 * Build a minimal manifest from a legacy flat brand profile.
	 *
	 * Provides sensible defaults so the generator always has a complete manifest.
	 *
	 * @param array $profile Legacy flat profile (as saved by the old theme builder).
	 * @return array Full manifest.
	 */
	public static function build_manifest_from_profile( array $profile ): array {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[TB] build_manifest_from_profile: called' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		$name    = $profile['name']    ?? $profile['display_name'] ?? 'Custom Theme';
		$slug    = $profile['slug']    ?? sanitize_title( $name );
		$colors  = $profile['colors'] ?? self::default_colors_from_profile( $profile );
		$typo    = $profile['typography'] ?? [];

		// Infer heading font from legacy visual_style if present.
		if ( empty( $typo['heading_font'] ) ) {
			$typo['heading_font'] = self::heading_font_for_style( $profile['visual_style'] ?? 'clean' );
		}
		if ( empty( $typo['body_font'] ) ) {
			$typo['body_font'] = 'Inter';
		}
		if ( empty( $typo['heading_weight'] ) ) {
			$typo['heading_weight'] = '700';
		}

		return [
			'theme' => [
				'name'                    => $name,
				'slug'                    => $slug,
				'industry'                => $profile['industry'] ?? 'general',
				'colors'                  => $colors,
				'typography'              => $typo,
				'heading_letter_spacing'  => $profile['heading_letter_spacing'] ?? '-0.02em',
				'heading_text_transform'  => $profile['heading_text_transform'] ?? 'none',
				'spacing_style'           => $profile['spacing_style']    ?? 'comfortable',
				'style_archetype'         => $profile['style_archetype'] ?? 'soft-modern',
			],
			'assets' => [
				'bootstrap_components'  => [],
				'icons_used'            => [],
				'load_bootstrap_js'     => true,
				'load_bootstrap_icons'  => true,
				'load_gf_forms'         => true,
			],
			'parts' => [
				'header_variant' => 'header',
				'footer_variant' => 'footer',
			],
			// No patterns block — all patterns are pre-registered from all
			// registered renderers by GrayFox_TB_PatternBuilder::get_all_registered_patterns().
			'templates' => self::default_templates(),
		];
	}

	/**
	 * Return a map of layout_slug => default copy array for all known patterns.
	 * Used by PatternBuilder::get_all_registered_patterns() to seed renderers with
	 * fallback copy when no manifest patterns block is present.
	 */
	public static function default_copy_by_layout(): array {
		$patterns = self::default_patterns( [ 'name' => 'Your Business' ] );
		$map = [];
		foreach ( $patterns as $spec ) {
			if ( ! empty( $spec['layout'] ) && ! empty( $spec['copy'] ) ) {
				$map[ $spec['layout'] ] = $spec['copy'];
			}
		}
		return $map;
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Extract or derive colors from a legacy profile.
	 *
	 * The old theme builder stored colors as profile['colors']['primary'] etc.
	 * but some older saves may only have individual color keys at the top level.
	 */
	private static function default_colors_from_profile( array $profile ): array {
		// Try top-level color keys (very old format).
		$primary = $profile['primary_color'] ?? $profile['primary'] ?? '#1a2e4a';
		return [
			'primary'    => $primary,
			'secondary'  => $profile['secondary_color'] ?? $profile['secondary'] ?? '#2d6a8f',
			'accent'     => $profile['accent_color']    ?? $profile['accent']    ?? '#f4a723',
			'background' => '#ffffff',
			'text'       => '#1e1e1e',
			'muted'      => '#6b7280',
		];
	}

	/**
	 * Map old visual_style to a Google Font heading family.
	 */
	private static function heading_font_for_style( string $style ): string {
		return match ( $style ) {
			'bold', 'modern'    => 'Space Grotesk',
			'elegant', 'luxury' => 'Cormorant Garamond',
			'editorial'         => 'DM Serif Display',
			'friendly'          => 'Nunito',
			'strong'            => 'Montserrat',
			default             => 'DM Sans',
		};
	}

	/**
	 * Generate a full pattern library for legacy profiles.
	 *
	 * Covers all 44 registered layout renderers across every category
	 * so the WP block pattern picker always has a rich selection,
	 * comparable to Twenty Twenty-Five's 70-pattern library.
	 *
	 * Layout → pattern slug mapping
	 * ─────────────────────────────
	 * Banners / Heroes  (8): banner-hero-gradient … banner-hero-animated
	 * Features          (5): features-three-cards … features-image-split
	 * Call to Action    (3): cta-gradient-band, cta-dual-buttons, cta-newsletter
	 * Social Proof      (5): social-testimonials-grid … social-press-mentions
	 * About / Company   (5): about-team-grid … about-mission
	 * Pricing           (3): pricing-three-tier … pricing-comparison-table
	 * Stats             (1): stats-row
	 * Contact / FAQ     (2): contact-form-info, faq-accordion
	 * Case Studies      (1): proof-case-studies
	 * Media / Portfolio (4): media-portfolio-grid … media-office-cards
	 * Commerce          (3): commerce-product-grid … commerce-app-download
	 * Content Blocks    (4): content-pull-quote … content-section-divider
	 *
	 * @param array $profile Brand profile (legacy flat format).
	 * @return array Pattern manifest keyed by slug.
	 */
	private static function default_patterns( array $profile ): array {
		$name = $profile['name'] ?? 'Your Business';

		return [

			// ── Banners / Heroes (8) ─────────────────────────────────────────────
			'banner-hero-gradient' => [
				'title'       => 'Banner — Gradient Hero',
				'layout'      => 'hero-gradient',
				'css_classes' => [ 'gf-hero-section' ],
				'copy'        => [
					'headline'      => 'Welcome to ' . $name,
					'subtext'       => 'Powerful solutions designed for your success.',
					'cta_primary'   => 'Get Started',
					'cta_secondary' => 'Learn More',
				],
			],
			'banner-hero-split' => [
				'title'       => 'Banner — Split Hero',
				'layout'      => 'hero-split',
				'css_classes' => [ 'gf-hero-split' ],
				'copy'        => [
					'headline'      => 'Built for Real Results',
					'subtext'       => 'The smarter path forward for your business.',
					'cta_primary'   => 'Start Free Trial',
					'cta_secondary' => 'See a Demo',
				],
			],
			'banner-hero-centered' => [
				'title'       => 'Banner — Centered Hero',
				'layout'      => 'hero-centered',
				'css_classes' => [ 'gf-hero-centered' ],
				'copy'        => [
					'headline'      => 'The Better Way Forward',
					'subtext'       => 'Modern solutions trusted by thousands.',
					'cta_primary'   => 'Explore Now',
					'cta_secondary' => 'Watch Video',
				],
			],
			'banner-hero-video' => [
				'title'       => 'Banner — Video Hero',
				'layout'      => 'hero-video',
				'css_classes' => [ 'gf-hero-video' ],
				'copy'        => [
					'headline'      => 'See What\'s Possible',
					'subtext'       => 'Experience our platform in action.',
					'cta_primary'   => 'Watch Now',
					'cta_secondary' => 'Book a Demo',
				],
			],
			'banner-hero-minimal' => [
				'title'       => 'Banner — Minimal Hero',
				'layout'      => 'hero-minimal',
				'css_classes' => [ 'gf-hero-minimal' ],
				'copy'        => [
					'headline'      => $name,
					'subtext'       => 'Where quality meets reliability.',
					'cta_primary'   => 'Get in Touch',
					'cta_secondary' => 'Learn More',
				],
			],
			'banner-hero-form' => [
				'title'       => 'Banner — Hero with Form',
				'layout'      => 'hero-with-form',
				'css_classes' => [ 'gf-hero-with-form' ],
				'copy'        => [
					'headline'      => 'Start Growing Today',
					'subtext'       => 'Join thousands of businesses already ahead of the curve.',
					'cta_primary'   => 'Sign Up Free',
					'form_label'    => 'Enter your email',
				],
			],
			'banner-hero-fullscreen' => [
				'title'       => 'Banner — Fullscreen Hero',
				'layout'      => 'hero-fullscreen',
				'css_classes' => [ 'gf-hero-fullscreen' ],
				'copy'        => [
					'headline'      => 'Bold Vision, Real Impact',
					'subtext'       => 'We help ambitious teams achieve extraordinary results.',
					'cta_primary'   => 'Get Started',
					'cta_secondary' => 'Our Story',
				],
			],
			'banner-hero-animated' => [
				'title'       => 'Banner — Animated Hero',
				'layout'      => 'hero-animated',
				'css_classes' => [ 'gf-hero-animated' ],
				'copy'        => [
					'headline'      => 'Innovation in Motion',
					'subtext'       => 'Dynamic solutions for a fast-moving world.',
					'cta_primary'   => 'Discover How',
					'cta_secondary' => 'Contact Us',
				],
			],

			// ── Features (5) ─────────────────────────────────────────────────────
			'features-three-cards' => [
				'title'       => 'Features — Three Column Cards',
				'layout'      => 'three-column-cards',
				'css_classes' => [ 'gf-feature-card', 'gf-section-tint' ],
				'copy'        => [
					'section_heading' => 'What We Offer',
					'card_1_title'    => 'Quality',
					'card_1_body'     => 'Best-in-class solutions for your most important needs.',
					'card_1_icon'     => 'bi-star',
					'card_2_title'    => 'Speed',
					'card_2_body'     => 'Fast, reliable delivery you can count on.',
					'card_2_icon'     => 'bi-lightning',
					'card_3_title'    => 'Support',
					'card_3_body'     => 'Expert help available whenever you need it.',
					'card_3_icon'     => 'bi-headset',
				],
			],
			'features-icon-grid' => [
				'title'       => 'Features — Six Icon Grid',
				'layout'      => 'six-icon-grid',
				'css_classes' => [ 'gf-icon-grid' ],
				'copy'        => [
					'section_heading' => 'Everything You Need',
					'card_1_title'    => 'Secure',
					'card_1_body'     => 'Enterprise-grade security built in.',
					'card_1_icon'     => 'bi-shield-check',
					'card_2_title'    => 'Scalable',
					'card_2_body'     => 'Grows with your business effortlessly.',
					'card_2_icon'     => 'bi-graph-up',
					'card_3_title'    => 'Fast',
					'card_3_body'     => 'Optimized performance at every level.',
					'card_3_icon'     => 'bi-lightning-charge',
					'card_4_title'    => 'Reliable',
					'card_4_body'     => '99.9% uptime you can stake your reputation on.',
					'card_4_icon'     => 'bi-check-circle',
					'card_5_title'    => 'Integrated',
					'card_5_body'     => 'Works seamlessly with your existing tools.',
					'card_5_icon'     => 'bi-plug',
					'card_6_title'    => 'Supported',
					'card_6_body'     => 'Dedicated team ready to help 24/7.',
					'card_6_icon'     => 'bi-people',
				],
			],
			'features-checklist-split' => [
				'title'       => 'Features — Image Checklist Split',
				'layout'      => 'image-checklist-split',
				'css_classes' => [ 'gf-split-section' ],
				'copy'        => [
					'section_heading' => 'Why Choose ' . $name,
					'check_1'         => 'Proven track record with leading organizations',
					'check_2'         => 'Dedicated onboarding and implementation support',
					'check_3'         => 'Flexible pricing that scales with your team',
					'check_4'         => 'Regular updates and new features every quarter',
					'cta_primary'     => 'Get Started',
				],
			],
			'features-numbered-steps' => [
				'title'       => 'Features — Numbered Steps',
				'layout'      => 'numbered-steps',
				'css_classes' => [ 'gf-steps-section' ],
				'copy'        => [
					'section_heading' => 'How It Works',
					'step_1_title'    => 'Sign Up',
					'step_1_body'     => 'Create your account in minutes — no credit card required.',
					'step_2_title'    => 'Configure',
					'step_2_body'     => 'Set up your workspace to match your exact workflow.',
					'step_3_title'    => 'Launch',
					'step_3_body'     => 'Go live and start seeing results immediately.',
				],
			],
			'features-image-split' => [
				'title'       => 'Features — Image Text Split',
				'layout'      => 'image-text-split',
				'css_classes' => [ 'gf-image-text-section' ],
				'copy'        => [
					'section_heading' => 'Built to Perform',
					'body_text'       => 'Our platform is engineered from the ground up to deliver exceptional performance, reliability, and ease of use — so you can focus on what matters most.',
					'cta_primary'     => 'See All Features',
				],
			],

			// ── Call to Action (3) ────────────────────────────────────────────────
			'cta-gradient-band' => [
				'title'       => 'Call to Action — Gradient Band',
				'layout'      => 'gradient-cta-band',
				'css_classes' => [ 'gf-cta-gradient' ],
				'copy'        => [
					'heading'       => 'Ready to Get Started?',
					'subtext'       => 'Join thousands of businesses that trust ' . $name . ' every day.',
					'cta_primary'   => 'Start Free Trial',
					'cta_secondary' => 'Talk to Sales',
				],
			],
			'cta-dual-buttons' => [
				'title'       => 'Call to Action — Dual Buttons',
				'layout'      => 'light-dual-cta',
				'css_classes' => [ 'gf-cta-two-up' ],
				'copy'        => [
					'heading'       => 'Ready to get started?',
					'cta_primary'   => 'Get a Demo',
					'cta_secondary' => 'View Pricing',
				],
			],
			'cta-newsletter' => [
				'title'       => 'Call to Action — Newsletter Form',
				'layout'      => 'newsletter-form',
				'css_classes' => [ 'gf-newsletter-section' ],
				'copy'        => [
					'heading'     => 'Stay in the Know',
					'subtext'     => 'Get the latest insights delivered straight to your inbox.',
					'placeholder' => 'Your email address',
					'cta_primary' => 'Subscribe',
				],
			],

			// ── Social Proof (5) ──────────────────────────────────────────────────
			'social-testimonials-grid' => [
				'title'       => 'Social Proof — Testimonials Grid',
				'layout'      => 'testimonials-grid',
				'css_classes' => [ 'gf-testimonial-card', 'gf-section-tint' ],
				'copy'        => [
					'section_heading'    => 'What Our Clients Say',
					'testimonial_1_name' => 'Alex Johnson',
					'testimonial_1_role' => 'CEO, Acme Corp',
					'testimonial_1_text' => 'This has transformed how we operate. Highly recommended.',
					'testimonial_2_name' => 'Maria Garcia',
					'testimonial_2_role' => 'Director of Operations',
					'testimonial_2_text' => 'The ROI we\'ve seen in the first quarter exceeded our expectations.',
					'testimonial_3_name' => 'David Kim',
					'testimonial_3_role' => 'VP of Engineering',
					'testimonial_3_text' => 'Incredibly well-designed and a pleasure to work with.',
				],
			],
			'social-testimonial-single' => [
				'title'       => 'Social Proof — Single Testimonial',
				'layout'      => 'testimonials-single',
				'css_classes' => [ 'gf-testimonial-single' ],
				'copy'        => [
					'quote' => 'Working with ' . $name . ' was the best decision we made this year.',
					'name'  => 'Sarah Thompson',
					'role'  => 'Founder, Bright Ideas Co.',
				],
			],
			'social-review-stars' => [
				'title'       => 'Social Proof — Review Stars Row',
				'layout'      => 'review-stars-row',
				'css_classes' => [ 'gf-review-stars-section' ],
				'copy'        => [
					'section_heading' => 'Rated 5 Stars by Our Customers',
					'rating_text'     => '4.9 / 5 from 2,400+ reviews',
				],
			],
			'social-logo-strip' => [
				'title'       => 'Social Proof — Logo Strip',
				'layout'      => 'logo-strip',
				'css_classes' => [ 'gf-logo-strip' ],
				'copy'        => [
					'section_heading' => 'Trusted by Industry Leaders',
				],
			],
			'social-press-mentions' => [
				'title'       => 'Social Proof — Press Mentions',
				'layout'      => 'press-mentions',
				'css_classes' => [ 'gf-press-section' ],
				'copy'        => [
					'section_heading' => 'As Seen In',
				],
			],

			// ── About / Company (5) ───────────────────────────────────────────────
			'about-team-grid' => [
				'title'       => 'About — Team Grid',
				'layout'      => 'team-grid',
				'css_classes' => [ 'gf-team-card', 'gf-section-tint' ],
				'copy'        => [
					'section_heading' => 'Meet the Team',
					'section_subtext' => 'The passionate people behind ' . $name . '.',
				],
			],
			'about-founders-row' => [
				'title'       => 'About — Founders Row',
				'layout'      => 'founders-row',
				'css_classes' => [ 'gf-founders-section' ],
				'copy'        => [
					'section_heading' => 'Our Founders',
					'section_subtext' => 'Driven by a shared vision to build something better.',
				],
			],
			'about-advisor-grid' => [
				'title'       => 'About — Advisor Grid',
				'layout'      => 'advisor-grid',
				'css_classes' => [ 'gf-advisors-section' ],
				'copy'        => [
					'section_heading' => 'Our Advisors',
					'section_subtext' => 'Backed by deep expertise across every discipline.',
				],
			],
			'about-timeline' => [
				'title'       => 'About — Vertical Timeline',
				'layout'      => 'vertical-timeline',
				'css_classes' => [ 'gf-timeline-section' ],
				'copy'        => [
					'section_heading' => 'Our Journey',
					'event_1_year'    => '2018',
					'event_1_text'    => $name . ' is founded with a simple mission: do it better.',
					'event_2_year'    => '2020',
					'event_2_text'    => 'First major product launch — 1,000 customers in 90 days.',
					'event_3_year'    => '2022',
					'event_3_text'    => 'Series A funding secured. Team grows to 50+.',
					'event_4_year'    => 'Today',
					'event_4_text'    => 'Serving thousands of businesses across 30+ countries.',
				],
			],
			'about-mission' => [
				'title'       => 'About — Mission & Values',
				'layout'      => 'mission-values',
				'css_classes' => [ 'gf-mission-section' ],
				'copy'        => [
					'section_heading' => 'Our Mission',
					'mission_text'    => 'To empower businesses with the tools, insights, and support they need to grow sustainably.',
					'value_1_title'   => 'Integrity',
					'value_1_body'    => 'We do what we say, and say what we mean.',
					'value_2_title'   => 'Innovation',
					'value_2_body'    => 'Constantly pushing what\'s possible.',
					'value_3_title'   => 'Impact',
					'value_3_body'    => 'Every decision is made with our customers in mind.',
				],
			],

			// ── Pricing (3) ───────────────────────────────────────────────────────
			'pricing-three-tier' => [
				'title'       => 'Pricing — Three Tier',
				'layout'      => 'three-tier-pricing',
				'css_classes' => [ 'gf-pricing-card', 'gf-section-tint' ],
				'copy'        => [
					'section_heading'  => 'Simple, Transparent Pricing',
					'tier_1_name'      => 'Starter',
					'tier_1_price'     => '$29',
					'tier_1_period'    => '/mo',
					'tier_1_feature_1' => 'Up to 5 users',
					'tier_1_feature_2' => 'Core features',
					'tier_1_feature_3' => 'Email support',
					'tier_1_cta'       => 'Get Started',
					'tier_2_name'      => 'Pro',
					'tier_2_price'     => '$79',
					'tier_2_period'    => '/mo',
					'tier_2_featured'  => true,
					'tier_2_feature_1' => 'Unlimited users',
					'tier_2_feature_2' => 'All features',
					'tier_2_feature_3' => 'Priority support',
					'tier_2_cta'       => 'Start Free Trial',
					'tier_3_name'      => 'Enterprise',
					'tier_3_price'     => 'Custom',
					'tier_3_period'    => '',
					'tier_3_feature_1' => 'Custom limits',
					'tier_3_feature_2' => 'Dedicated account manager',
					'tier_3_feature_3' => 'SLA guarantee',
					'tier_3_cta'       => 'Contact Sales',
				],
			],
			'pricing-two-tier' => [
				'title'       => 'Pricing — Two Tier',
				'layout'      => 'two-tier-pricing',
				'css_classes' => [ 'gf-pricing-card' ],
				'copy'        => [
					'section_heading' => 'Choose Your Plan',
					'tier_1_name'     => 'Monthly',
					'tier_1_price'    => '$49',
					'tier_1_period'   => '/mo',
					'tier_1_cta'      => 'Start Monthly',
					'tier_2_name'     => 'Annual',
					'tier_2_price'    => '$39',
					'tier_2_period'   => '/mo, billed annually',
					'tier_2_featured' => true,
					'tier_2_badge'    => 'Save 20%',
					'tier_2_cta'      => 'Start Annual',
				],
			],
			'pricing-comparison-table' => [
				'title'       => 'Pricing — Comparison Table',
				'layout'      => 'comparison-table',
				'css_classes' => [ 'gf-comparison-table' ],
				'copy'        => [
					'section_heading' => 'Compare Plans',
				],
			],

			// ── Stats (1) ─────────────────────────────────────────────────────────
			'stats-row' => [
				'title'       => 'Stats — Key Metrics Row',
				'layout'      => 'stats-row',
				'css_classes' => [ 'gf-stats-section' ],
				'copy'        => [
					'stat_1_value' => '10,000+',
					'stat_1_label' => 'Happy Customers',
					'stat_2_value' => '99.9%',
					'stat_2_label' => 'Uptime SLA',
					'stat_3_value' => '50+',
					'stat_3_label' => 'Countries Served',
					'stat_4_value' => '4.9★',
					'stat_4_label' => 'Average Rating',
				],
			],

			// ── Contact / FAQ (2) ─────────────────────────────────────────────────
			'contact-form-info' => [
				'title'       => 'Contact — Form with Info',
				'layout'      => 'contact-form-info',
				'css_classes' => [ 'gf-contact-section' ],
				'copy'        => [
					'section_heading' => 'Get in Touch',
					'section_subtext' => 'We\'d love to hear from you. Send us a message and we\'ll respond promptly.',
					'cta_primary'     => 'Send Message',
				],
			],
			'faq-accordion' => [
				'title'       => 'FAQ — Accordion',
				'layout'      => 'accordion-faq',
				'css_classes' => [ 'gf-faq-section', 'gf-section-tint' ],
				'copy'        => [
					'section_heading' => 'Frequently Asked Questions',
					'q1'              => 'How do I get started?',
					'a1'              => 'Simply sign up for a free account and follow our onboarding guide.',
					'q2'              => 'Is there a free trial available?',
					'a2'             => 'Yes — all plans include a 14-day free trial with no credit card required.',
					'q3'              => 'Can I change my plan later?',
					'a3'              => 'Absolutely. You can upgrade, downgrade, or cancel at any time from your account settings.',
					'q4'              => 'Do you offer volume discounts?',
					'a4'              => 'Yes. Contact our sales team for custom pricing on large teams or high-volume usage.',
				],
			],

			// ── Case Studies (1) ──────────────────────────────────────────────────
			'proof-case-studies' => [
				'title'       => 'Social Proof — Case Study Grid',
				'layout'      => 'case-study-grid',
				'css_classes' => [ 'gf-case-study-card', 'gf-section-tint' ],
				'copy'        => [
					'section_heading' => 'Customer Success Stories',
				],
			],

			// ── Media / Portfolio (4) ─────────────────────────────────────────────
			'media-portfolio-grid' => [
				'title'       => 'Media — Portfolio Grid',
				'layout'      => 'portfolio-grid',
				'css_classes' => [ 'gf-portfolio-card' ],
				'copy'        => [
					'section_heading' => 'Our Work',
					'section_subtext' => 'A selection of projects we\'re proud of.',
				],
			],
			'media-event-cards' => [
				'title'       => 'Media — Event List Cards',
				'layout'      => 'event-list-cards',
				'css_classes' => [ 'gf-event-card', 'gf-section-tint' ],
				'copy'        => [
					'section_heading' => 'Upcoming Events',
				],
			],
			'media-speaker-grid' => [
				'title'       => 'Media — Speaker Grid',
				'layout'      => 'speaker-grid',
				'css_classes' => [ 'gf-speaker-section' ],
				'copy'        => [
					'section_heading' => 'Speakers & Experts',
				],
			],
			'media-office-cards' => [
				'title'       => 'Media — Office Locations',
				'layout'      => 'office-cards',
				'css_classes' => [ 'gf-office-card' ],
				'copy'        => [
					'section_heading' => 'Our Offices',
				],
			],

			// ── Commerce (3) ──────────────────────────────────────────────────────
			'commerce-product-grid' => [
				'title'       => 'Commerce — Product Grid',
				'layout'      => 'product-grid',
				'css_classes' => [ 'gf-product-card', 'gf-section-tint' ],
				'copy'        => [
					'section_heading' => 'Our Products',
					'section_subtext' => 'Explore our full range of solutions.',
				],
			],
			'commerce-job-listings' => [
				'title'       => 'Commerce — Job Listings',
				'layout'      => 'job-list-cards',
				'css_classes' => [ 'gf-job-card' ],
				'copy'        => [
					'section_heading' => 'We\'re Hiring',
					'section_subtext' => 'Join our team and help shape the future.',
				],
			],
			'commerce-app-download' => [
				'title'       => 'Commerce — App Store Download',
				'layout'      => 'app-store-download',
				'css_classes' => [ 'gf-app-download-section' ],
				'copy'        => [
					'headline'      => 'Get the App',
					'subtext'       => 'Everything you need, right in your pocket.',
					'cta_primary'   => 'Download on the App Store',
					'cta_secondary' => 'Get it on Google Play',
				],
			],

			// ── Content Blocks (4) ────────────────────────────────────────────────
			'content-pull-quote' => [
				'title'       => 'Content — Pull Quote',
				'layout'      => 'pull-quote-block',
				'css_classes' => [ 'gf-pull-quote' ],
				'copy'        => [
					'quote'       => 'The measure of success is not what you accomplish, but the challenges you overcome.',
					'attribution' => 'Team ' . $name,
				],
			],
			'content-callout-box' => [
				'title'       => 'Content — Callout Box',
				'layout'      => 'callout-box',
				'css_classes' => [ 'gf-callout-box' ],
				'copy'        => [
					'heading'  => 'Important Note',
					'body'     => 'This section highlights a key message or important detail your visitors should not miss.',
					'cta_text' => 'Learn More',
				],
			],
			'content-announcement' => [
				'title'       => 'Content — Announcement Banner',
				'layout'      => 'announcement-banner',
				'css_classes' => [ 'gf-announcement-bar' ],
				'copy'        => [
					'text'     => 'New: Introducing our latest feature — available now for all users.',
					'cta_text' => 'See What\'s New',
					'cta_url'  => '#',
				],
			],
			'content-section-divider' => [
				'title'       => 'Content — Section Divider',
				'layout'      => 'section-divider-block',
				'css_classes' => [ 'gf-section-divider' ],
				'copy'        => [],
			],
		];
	}

	/**
	 * Generate default templates for legacy profiles.
	 *
	 * The front-page composes a curated subset of the full pattern library:
	 * hero → logo strip → features → stats → testimonials → CTA.
	 * All other patterns remain available in the WP block pattern picker.
	 */
	private static function default_templates(): array {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[TB] default_templates: called' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		return [
			'front-page.html' => [
				'description' => 'Homepage',
				'type'        => 'content',
				'full_width'  => false,
				'sidebar'     => false,
				'patterns'    => [
					'hero-gradient',
					'logo-strip',
					'three-column-cards',
					'stats-row',
					'testimonials-grid',
					'gradient-cta-band',
				],
			],
			'page.html' => [
				'description' => 'Default Page',
				'type'        => 'page',
				'full_width'  => false,
				'sidebar'     => false,
			],
			'single.html' => [
				'description'   => 'Single Post',
				'type'          => 'content-single',
				'related_posts' => false,
				'inline_cta'    => false,
			],
			'archive.html' => [
				'description' => 'Archive',
				'type'        => 'archive',
				'post_type'   => 'post',
				'columns'     => 3,
				'card_style'  => 'standard',
			],
			'404.html' => [
				'description' => 'Not Found',
				'type'        => 'error',
			],
			'search.html' => [
				'description' => 'Search Results',
				'type'        => 'search',
			],
		];
	}
}
