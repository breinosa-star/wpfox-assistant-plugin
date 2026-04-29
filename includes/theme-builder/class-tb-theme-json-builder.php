<?php
/**
 * Generate theme.json — the WordPress block theme design token system.
 *
 * Ported from wp-theme-builder/src/theme_json_builder.py
 *
 * @package GrayFox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GrayFox_TB_ThemeJsonBuilder
 */
class GrayFox_TB_ThemeJsonBuilder {

	/**
	 * Maps style_archetype → a single concrete border-radius value used in
	 * theme.json block presets (which require a concrete value, not a CSS var).
	 *
	 * @var array<string,string>
	 */
	private const ARCHETYPE_RADIUS_MAP = [
		'soft-modern'   => '8px',
		'flat-minimal'  => '2px',
		'glassmorphism' => '14px',
		'editorial'     => '0px',
		'dark-luxury'   => '4px',
		'brutalist'     => '0px',
		'organic'       => '20px',
		'technical'     => '4px',
	];

	/** @var array<string,array<string,string>> */
	private const SPACING_SCALES = [
		'tight' => [
			'1' => '0.25rem', '2' => '0.5rem',  '3' => '0.75rem',
			'4' => '1rem',    '5' => '1.5rem',   '6' => '2rem',    '7' => '3rem',
		],
		'comfortable' => [
			'1' => '0.5rem',  '2' => '0.75rem', '3' => '1rem',
			'4' => '1.5rem',  '5' => '2rem',    '6' => '3rem',    '7' => '4rem',
		],
		'spacious' => [
			'1' => '0.75rem', '2' => '1rem',    '3' => '1.5rem',
			'4' => '2.5rem',  '5' => '3.5rem',  '6' => '5rem',    '7' => '7rem',
		],
	];

	/** @var array<int,array<string,string>> */
	private const FONT_SIZES = [
		[ 'slug' => 'xs',   'size' => '0.75rem',                   'name' => 'Extra Small' ],
		[ 'slug' => 's',    'size' => '0.875rem',                   'name' => 'Small' ],
		[ 'slug' => 'm',    'size' => '1rem',                       'name' => 'Medium' ],
		[ 'slug' => 'lg',   'size' => '1.125rem',                   'name' => 'Large' ],
		[ 'slug' => 'xl',   'size' => '1.375rem',                   'name' => 'Extra Large' ],
		[ 'slug' => '2xl',  'size' => '1.75rem',                    'name' => '2X Large' ],
		[ 'slug' => '3xl',  'size' => '2.25rem',                    'name' => '3X Large' ],
		[ 'slug' => '4xl',  'size' => 'clamp(2.5rem,4vw,3.25rem)',  'name' => '4X Large' ],
		[ 'slug' => '5xl',  'size' => 'clamp(3rem,5vw,4.5rem)',     'name' => '5X Large' ],
		[ 'slug' => 'huge', 'size' => 'clamp(3.75rem,7vw,6rem)',    'name' => 'Huge' ],
	];

	/**
	 * Generate a complete theme.json string from a brand profile / manifest theme block.
	 *
	 * @param array $theme manifest['theme'] (or legacy flat profile).
	 * @return string Pretty-printed JSON string.
	 */
	public static function build( array $theme ): string {
		$colors  = $theme['colors']       ?? [];
		$typo    = $theme['typography']   ?? [];
		$spacing_style = $theme['spacing_style']    ?? 'comfortable';
		$archetype     = $theme['style_archetype'] ?? 'soft-modern';

		$heading_font           = GrayFox_TB_ColorUtils::strip_local_prefix( $typo['heading_font']  ?? 'Inter' );
		$body_font              = GrayFox_TB_ColorUtils::strip_local_prefix( $typo['body_font']     ?? 'Inter' );
		$heading_weight         = $typo['heading_weight']  ?? '700';
		$heading_letter_spacing = $theme['heading_letter_spacing'] ?? '-0.02em';
		$heading_text_transform = $theme['heading_text_transform'] ?? 'none';

		$btn_radius = self::ARCHETYPE_RADIUS_MAP[ $archetype ] ?? '8px';
		$spacing    = self::SPACING_SCALES[ $spacing_style ]   ?? self::SPACING_SCALES['comfortable'];

		$primary    = $colors['primary']    ?? '#1a2e4a';
		$secondary  = $colors['secondary']  ?? '#2d6a8f';
		$accent     = $colors['accent']     ?? '#f4a723';
		$background = $colors['background'] ?? '#ffffff';
		$foreground = $colors['text']       ?? '#1e1e1e';
		$muted      = $colors['muted']      ?? '#6b7280';

		$contrast_on_primary = GrayFox_TB_ColorUtils::contrast_color( $primary );

		$data = [
			'$schema' => 'https://schemas.wp.org/trunk/theme.json',
			'version' => 3,
			'settings' => [
				'appearanceTools' => true,
				'color' => [
					'defaultPalette' => false,
					'palette'        => [
						[ 'slug' => 'primary',    'name' => 'Primary',    'color' => $primary ],
						[ 'slug' => 'secondary',  'name' => 'Secondary',  'color' => $secondary ],
						[ 'slug' => 'accent',     'name' => 'Accent',     'color' => $accent ],
						[ 'slug' => 'background', 'name' => 'Background', 'color' => $background ],
						[ 'slug' => 'foreground', 'name' => 'Text',       'color' => $foreground ],
						[ 'slug' => 'muted',      'name' => 'Muted',      'color' => $muted ],
						[ 'slug' => 'contrast',   'name' => 'Contrast',   'color' => $contrast_on_primary ],
					],
				],
				'typography' => [
					'defaultFontSizes' => false,
					'customFontSize'   => true,
					'fontFamilies'     => [
						[
							'fontFamily' => '"' . $heading_font . '", sans-serif',
							'name'       => 'Heading',
							'slug'       => 'heading',
						],
						[
							'fontFamily' => '"' . $body_font . '", sans-serif',
							'name'       => 'Body',
							'slug'       => 'body',
						],
					],
					'fontSizes' => self::FONT_SIZES,
				],
				'spacing' => [
					'spacingSizes' => [
						[ 'slug' => '10',          'size' => $spacing['1'], 'name' => 'XS' ],
						[ 'slug' => '20',          'size' => $spacing['2'], 'name' => 'S' ],
						[ 'slug' => 'button-pad',  'size' => $spacing['2'], 'name' => 'Button Pad' ],
						[ 'slug' => '30',          'size' => $spacing['3'], 'name' => 'M' ],
						[ 'slug' => '40',          'size' => $spacing['4'], 'name' => 'L' ],
						[ 'slug' => 'card-gap',    'size' => $spacing['4'], 'name' => 'Card Gap' ],
						[ 'slug' => '50',          'size' => $spacing['5'], 'name' => 'XL' ],
						[ 'slug' => '60',          'size' => $spacing['6'], 'name' => '2XL' ],
						[ 'slug' => 'section-gap', 'size' => $spacing['6'], 'name' => 'Section Gap' ],
						[ 'slug' => '70',          'size' => $spacing['7'], 'name' => '3XL' ],
					],
				],
				'layout' => [
					'contentSize' => '900px',
					'wideSize'    => '1280px',
				],
			],
			'styles' => [
				'color' => [
					'background' => 'var:preset|color|background',
					'text'       => 'var:preset|color|foreground',
				],
				'typography' => [
					'fontFamily' => 'var:preset|typography|font-family|body',
					'fontSize'   => '1.0625rem',
					'lineHeight' => '1.75',
				],
				'spacing' => [
					'blockGap' => 'var:preset|spacing|40',
				],
				'elements' => [
					'heading' => [
						'typography' => [
							'fontFamily'    => 'var:preset|typography|font-family|heading',
							'fontWeight'    => $heading_weight,
							'lineHeight'    => '1.2',
							'letterSpacing' => $heading_letter_spacing,
							'textTransform' => $heading_text_transform,
						],
					],
					'h1' => [ 'typography' => [ 'fontSize' => 'clamp(2.75rem,5vw,4.5rem)' ] ],
					'h2' => [ 'typography' => [ 'fontSize' => 'clamp(1.875rem,3.5vw,3rem)' ] ],
					'h3' => [ 'typography' => [ 'fontSize' => 'clamp(1.4rem,2.5vw,2rem)' ] ],
					'h4' => [ 'typography' => [ 'fontSize' => 'clamp(1.1rem,2vw,1.375rem)' ] ],
					'link' => [
						'color'  => [ 'text' => 'var:preset|color|primary' ],
						':hover' => [ 'color' => [ 'text' => 'var:preset|color|accent' ] ],
					],
					'button' => [
						'color' => [
							'background' => 'var:preset|color|primary',
							'text'       => $contrast_on_primary,
						],
						'typography' => [
							'fontFamily' => 'var:preset|typography|font-family|heading',
							'fontWeight' => '600',
						],
						'border'  => [ 'radius' => $btn_radius ],
						'spacing' => [
							'padding' => [
								'top'    => 'var:preset|spacing|20',
								'bottom' => 'var:preset|spacing|20',
								'left'   => 'var:preset|spacing|40',
								'right'  => 'var:preset|spacing|40',
							],
						],
						':hover' => [
							'color' => [ 'background' => 'var:preset|color|accent' ],
						],
					],
				],
				'blocks' => [
					'core/post-title' => [
						'typography' => [
							'fontFamily' => 'var:preset|typography|font-family|heading',
							'fontWeight' => $heading_weight,
						],
					],
					'core/site-title' => [
						'typography' => [
							'fontFamily' => 'var:preset|typography|font-family|heading',
							'fontWeight' => $heading_weight,
						],
					],
					'core/navigation' => [
						'typography' => [
							'fontFamily' => 'var:preset|typography|font-family|body',
						],
					],
				],
			],
		];

		return wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}
}
