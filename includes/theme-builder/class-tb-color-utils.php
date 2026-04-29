<?php
/**
 * Color manipulation utilities for WordPress theme generation.
 *
 * Ported from wp-theme-builder/src/color_utils.py
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
 * Class GrayFox_TB_ColorUtils
 *
 * Static helper for hex color manipulation, luminance, contrast, and
 * Google Fonts URL construction.
 */
class GrayFox_TB_ColorUtils {

	/**
	 * Normalize a hex color to 6-digit format with leading #.
	 *
	 * @param string $hex Raw color string (e.g. '#fff', 'aabbcc', '#1A2E4A').
	 * @return string 7-character normalized hex like '#1a2e4a'.
	 */
	public static function sanitize_hex( string $hex ): string {
		$color = ltrim( trim( $hex ), '#' );

		// Expand 3-digit shorthand.
		if ( strlen( $color ) === 3 ) {
			$color = $color[0] . $color[0] . $color[1] . $color[1] . $color[2] . $color[2];
		}

		if ( strlen( $color ) !== 6 || ! ctype_xdigit( $color ) ) {
			return '#1a2e4a'; // safe fallback
		}

		return '#' . strtolower( $color );
	}

	/**
	 * Convert a hex color to an [R, G, B] array.
	 *
	 * @param string $hex Hex color string.
	 * @return int[] Array with keys 0=>R, 1=>G, 2=>B (0-255).
	 */
	public static function hex_to_rgb( string $hex ): array {
		$color = ltrim( self::sanitize_hex( $hex ), '#' );
		return [
			hexdec( substr( $color, 0, 2 ) ),
			hexdec( substr( $color, 2, 2 ) ),
			hexdec( substr( $color, 4, 2 ) ),
		];
	}

	/**
	 * Convert RGB integers to a hex color string.
	 *
	 * @param int $r Red 0-255.
	 * @param int $g Green 0-255.
	 * @param int $b Blue 0-255.
	 * @return string Hex color like '#1a2e4a'.
	 */
	public static function rgb_to_hex( int $r, int $g, int $b ): string {
		$r = max( 0, min( 255, $r ) );
		$g = max( 0, min( 255, $g ) );
		$b = max( 0, min( 255, $b ) );
		return sprintf( '#%02x%02x%02x', $r, $g, $b );
	}

	/**
	 * Calculate WCAG 2.0 relative luminance of a color.
	 *
	 * @param string $hex Hex color string.
	 * @return float Luminance 0.0 (black) – 1.0 (white).
	 */
	public static function relative_luminance( string $hex ): float {
		[ $r, $g, $b ] = self::hex_to_rgb( $hex );

		$linearize = static function ( int $channel ): float {
			$c = $channel / 255.0;
			if ( $c <= 0.03928 ) {
				return $c / 12.92;
			}
			return ( ( $c + 0.055 ) / 1.055 ) ** 2.4;
		};

		return 0.2126 * $linearize( $r ) + 0.7152 * $linearize( $g ) + 0.0722 * $linearize( $b );
	}

	/**
	 * Return true if the color is perceptually dark (luminance < 0.5).
	 *
	 * @param string $hex Hex color string.
	 * @return bool
	 */
	public static function is_dark_color( string $hex ): bool {
		return self::relative_luminance( $hex ) < 0.5;
	}

	/**
	 * Return a high-contrast text color for the given background.
	 *
	 * @param string $hex Background hex color.
	 * @return string '#ffffff' for dark backgrounds, '#1e1e1e' for light.
	 */
	public static function contrast_color( string $hex ): string {
		return self::is_dark_color( $hex ) ? '#ffffff' : '#1e1e1e';
	}

	/**
	 * Darken a color by a ratio (0.0 = no change, 1.0 = black).
	 *
	 * @param string $hex   Base hex color.
	 * @param float  $ratio Darkening ratio 0.0–1.0.
	 * @return string Darkened hex color.
	 */
	public static function darken_color( string $hex, float $ratio ): string {
		$ratio    = max( 0.0, min( 1.0, $ratio ) );
		[ $r, $g, $b ] = self::hex_to_rgb( $hex );
		return self::rgb_to_hex(
			(int) ( $r * ( 1 - $ratio ) ),
			(int) ( $g * ( 1 - $ratio ) ),
			(int) ( $b * ( 1 - $ratio ) )
		);
	}

	/**
	 * Lighten a color by a ratio (0.0 = no change, 1.0 = white).
	 *
	 * @param string $hex   Base hex color.
	 * @param float  $ratio Lightening ratio 0.0–1.0.
	 * @return string Lightened hex color.
	 */
	public static function lighten_color( string $hex, float $ratio ): string {
		$ratio    = max( 0.0, min( 1.0, $ratio ) );
		[ $r, $g, $b ] = self::hex_to_rgb( $hex );
		return self::rgb_to_hex(
			(int) ( $r + ( 255 - $r ) * $ratio ),
			(int) ( $g + ( 255 - $g ) * $ratio ),
			(int) ( $b + ( 255 - $b ) * $ratio )
		);
	}

	/**
	 * Tint a color towards white (alias for lighten_color).
	 *
	 * @param string $hex   Base hex color.
	 * @param float  $ratio Tint ratio 0.0–1.0.
	 * @return string Tinted hex color.
	 */
	public static function tint_color( string $hex, float $ratio ): string {
		return self::lighten_color( $hex, $ratio );
	}

	/**
	 * Return true if the font name uses the local: prefix convention.
	 *
	 * A font declared as 'local:Moneta Regular' references a file bundled
	 * inside the theme's assets/fonts/ directory rather than Google Fonts.
	 *
	 * @param string $font_name Font name, possibly prefixed with 'local:'.
	 * @return bool
	 */
	public static function is_local_font( string $font_name ): bool {
		return str_starts_with( $font_name, 'local:' );
	}

	/**
	 * Strip the 'local:' prefix and return the bare font family name.
	 *
	 * @param string $font_name Font name, possibly prefixed with 'local:'.
	 * @return string Bare font family name.
	 */
	public static function strip_local_prefix( string $font_name ): string {
		return self::is_local_font( $font_name )
			? trim( substr( $font_name, strlen( 'local:' ) ) )
			: $font_name;
	}

	/**
	 * Build @font-face blocks for a locally hosted font.
	 *
	 * Expects woff2 files at:
	 *   assets/fonts/<slug>/<slug>-<weight>.woff2
	 *
	 * @param string   $font_name Bare font family name (no 'local:' prefix).
	 * @param string[] $weights   CSS weight values. Defaults to ['400','700'].
	 * @return string CSS @font-face declarations.
	 */
	public static function build_font_face_css( string $font_name, array $weights = [ '400', '700' ] ): string {
		$slug   = strtolower( str_replace( ' ', '-', $font_name ) );
		$blocks = [ "/* LOCAL FONT: place {$slug}-<weight>.woff2 files in assets/fonts/{$slug}/ */" ];

		foreach ( $weights as $weight ) {
			ob_start(); ?>
@font-face {
    font-family: '<?php echo $font_name; ?>';
    src: url('assets/fonts/<?php echo $slug; ?>/<?php echo $slug; ?>-<?php echo $weight; ?>.woff2') format('woff2');
    font-weight: <?php echo $weight; ?>;
    font-style: normal;
    font-display: swap;
}
			<?php $blocks[] = ob_get_clean();
		}

		return implode( "\n", $blocks );
	}

	/**
	 * Build a Google Fonts CSS2 URL for heading and body fonts.
	 *
	 * Fonts with the 'local:' prefix are excluded (they don't need a network request).
	 *
	 * @param string $heading_font Heading font name (may carry 'local:' prefix).
	 * @param string $body_font    Body font name (may carry 'local:' prefix).
	 * @return string Google Fonts URL, or empty string if all fonts are local.
	 */
	public static function build_google_fonts_url( string $heading_font, string $body_font ): string {
		$families = [];

		if ( ! self::is_local_font( $heading_font ) ) {
			$families[] = 'family=' . str_replace( ' ', '+', $heading_font ) . ':wght@400;600;700;800';
		}

		if ( ! self::is_local_font( $body_font ) && $body_font !== $heading_font ) {
			$families[] = 'family=' . str_replace( ' ', '+', $body_font ) . ':wght@400;500;600;700';
		}

		if ( empty( $families ) ) {
			return '';
		}

		return 'https://fonts.googleapis.com/css2?' . implode( '&', $families ) . '&display=swap';
	}
}
