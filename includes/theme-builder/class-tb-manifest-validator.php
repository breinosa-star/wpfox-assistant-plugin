<?php
/**
 * Manifest validator — checks a manifest before theme generation and returns
 * structured, LLM-readable error messages pointing to the exact problem and
 * where to find valid values.
 *
 * Call GrayFox_TB_ManifestValidator::validate( $manifest ) before writing any
 * files. If it returns errors, surface them back to the model — do not proceed.
 *
 * @package GrayFox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GrayFox_TB_ManifestValidator
 */
class GrayFox_TB_ManifestValidator {

	// -------------------------------------------------------------------------
	// Valid value sets
	// -------------------------------------------------------------------------

	private const VALID_SPACING_STYLES  = [ 'tight', 'comfortable', 'spacious' ];
	private const VALID_ARCHETYPES      = [
		'soft-modern',
		'flat-minimal',
		'glassmorphism',
		'editorial',
		'dark-luxury',
		'brutalist',
		'organic',
		'technical',
	];
	private const VALID_TEMPLATE_TYPES  = [
		'content',
		'content-single',
		'archive',
		'error',
		'search',
	];
	private const VALID_CARD_STYLES     = [ 'standard', 'minimal', 'portfolio', 'team' ];
	private const VALID_HEADER_VARIANTS = [ 'header', 'header-minimal', 'header-transparent' ];
	private const VALID_FOOTER_VARIANTS = [ 'footer', 'footer-minimal' ];

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Validate a full manifest array.
	 *
	 * Returns an array of human + LLM-readable error strings.
	 * An empty array means the manifest is valid.
	 *
	 * @param array $manifest Full manifest (already passed through ensure_manifest).
	 * @return string[] List of error messages. Empty = valid.
	 */
	public static function validate( array $manifest ): array {
		$errors = [];

		$errors = array_merge( $errors, self::validate_theme( $manifest['theme'] ?? [] ) );
		$errors = array_merge( $errors, self::validate_assets( $manifest['assets'] ?? [] ) );
		$errors = array_merge( $errors, self::validate_parts( $manifest['parts'] ?? [] ) );
		$errors = array_merge( $errors, self::validate_patterns( $manifest['patterns'] ?? [], $manifest ) );
		$errors = array_merge( $errors, self::validate_templates( $manifest['templates'] ?? [], $manifest ) );

		return $errors;
	}

	/**
	 * Same as validate() but throws a RuntimeException on the first error.
	 * Useful in CLI / test contexts.
	 *
	 * @param array $manifest
	 * @throws \RuntimeException
	 */
	public static function validate_or_throw( array $manifest ): void {
		$errors = self::validate( $manifest );
		if ( ! empty( $errors ) ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- internal dev error never displayed to browser; $errors values already esc_html()'d above
			throw new \RuntimeException(
				'Manifest validation failed:' . "\n" . implode( "\n", array_map( fn( $e ) => '  - ' . esc_html( $e ), $errors ) )
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
	}

	// -------------------------------------------------------------------------
	// Section validators
	// -------------------------------------------------------------------------

	private static function validate_theme( array $theme ): array {
		$errors = [];
		$loc    = 'manifest.theme';

		if ( empty( $theme['name'] ) ) {
			$errors[] = "{$loc}.name is required.";
		}
		if ( empty( $theme['slug'] ) ) {
			$errors[] = "{$loc}.slug is required (lowercase, hyphens only).";
		} elseif ( ! preg_match( '/^[a-z0-9\-]+$/', $theme['slug'] ) ) {
			$errors[] = "{$loc}.slug \"{$theme['slug']}\" is invalid — use lowercase letters, numbers, and hyphens only.";
		}

		// Colors.
		$colors   = $theme['colors'] ?? [];
		$required = [ 'primary', 'secondary', 'accent', 'background', 'text', 'muted' ];
		foreach ( $required as $key ) {
			if ( empty( $colors[ $key ] ) ) {
				$errors[] = "{$loc}.colors.{$key} is required.";
			} elseif ( ! self::is_valid_hex( $colors[ $key ] ) ) {
				$errors[] = "{$loc}.colors.{$key} \"{$colors[$key]}\" is not a valid hex color (e.g. \"#1a2e4a\").";
			}
		}

		// Typography.
		$typo = $theme['typography'] ?? [];
		if ( empty( $typo['heading_font'] ) ) {
			$errors[] = "{$loc}.typography.heading_font is required (e.g. \"Space Grotesk\").";
		}
		if ( empty( $typo['body_font'] ) ) {
			$errors[] = "{$loc}.typography.body_font is required (e.g. \"Inter\").";
		}

		// Enum fields.
		if ( isset( $theme['spacing_style'] ) && ! in_array( $theme['spacing_style'], self::VALID_SPACING_STYLES, true ) ) {
			$valid    = implode( ', ', self::VALID_SPACING_STYLES );
			$errors[] = "{$loc}.spacing_style \"{$theme['spacing_style']}\" is invalid. Valid values: {$valid}.";
		}
		if ( isset( $theme['style_archetype'] ) && ! in_array( $theme['style_archetype'], self::VALID_ARCHETYPES, true ) ) {
			$valid    = implode( ', ', self::VALID_ARCHETYPES );
			$errors[] = "{$loc}.style_archetype \"{$theme['style_archetype']}\" is invalid. Valid values: {$valid}.";
		}

		return $errors;
	}

	private static function validate_assets( array $assets ): array {
		// assets block is fully optional — no required keys.
		// Only validate types if present.
		$errors = [];

		$bool_flags = [
			'load_bootstrap_js',
			'load_bootstrap_icons',
			'load_gf_forms',
			'woocommerce',
			'dashboard',
		];
		foreach ( $bool_flags as $flag ) {
			if ( isset( $assets[ $flag ] ) && ! is_bool( $assets[ $flag ] ) ) {
				$errors[] = "manifest.assets.{$flag} must be true or false, got: " . json_encode( $assets[ $flag ] ) . '.';
			}
		}

		return $errors;
	}

	private static function validate_parts( array $parts ): array {
		$errors = [];

		if ( isset( $parts['header_variant'] ) && ! in_array( $parts['header_variant'], self::VALID_HEADER_VARIANTS, true ) ) {
			$valid    = implode( ', ', self::VALID_HEADER_VARIANTS );
			$errors[] = "manifest.parts.header_variant \"{$parts['header_variant']}\" is invalid. Valid values: {$valid}.";
		}
		if ( isset( $parts['footer_variant'] ) && ! in_array( $parts['footer_variant'], self::VALID_FOOTER_VARIANTS, true ) ) {
			$valid    = implode( ', ', self::VALID_FOOTER_VARIANTS );
			$errors[] = "manifest.parts.footer_variant \"{$parts['footer_variant']}\" is invalid. Valid values: {$valid}.";
		}

		return $errors;
	}

	private static function validate_patterns( array $patterns, array $manifest ): array {
		// Patterns block is now optional — all patterns are pre-registered at build time.
		// This validator only runs checks when a patterns block is explicitly present
		// (e.g. legacy manifests). An absent / empty patterns block is valid.
		if ( empty( $patterns ) ) {
			return [];
		}

		$errors            = [];
		$valid_layouts     = GrayFox_TB_PatternBuilder::get_registered_layouts();
		sort( $valid_layouts );
		$valid_layouts_str = implode( ', ', $valid_layouts );

		foreach ( $patterns as $slug => $spec ) {
			$loc = "manifest.patterns[\"{$slug}\"]";

			// Slug format.
			if ( ! preg_match( '/^[a-z0-9\-]+$/', $slug ) ) {
				$errors[] = "{$loc}: slug \"{$slug}\" is invalid — use lowercase letters, numbers, and hyphens only.";
			}

			// Required fields.
			if ( empty( $spec['title'] ) ) {
				$errors[] = "{$loc}.title is required.";
			}
			if ( empty( $spec['layout'] ) ) {
				$errors[] = "{$loc}.layout is required. Valid layout types: {$valid_layouts_str}.";
				continue; // Skip layout-specific checks if missing.
			}

			// Layout key must exist in the renderer registry.
			if ( ! in_array( $spec['layout'], $valid_layouts, true ) ) {
				$errors[] = "{$loc}.layout \"{$spec['layout']}\" is not a registered layout type. "
					. "Valid layout types: {$valid_layouts_str}. "
					. 'See manifest.patterns[*].layout in SKILL.md for descriptions of each layout.';
			}

			// css_classes must be an array if present.
			if ( isset( $spec['css_classes'] ) && ! is_array( $spec['css_classes'] ) ) {
				$errors[] = "{$loc}.css_classes must be an array of strings, not a plain string.";
			}

			// copy must be an array if present.
			if ( isset( $spec['copy'] ) && ! is_array( $spec['copy'] ) ) {
				$errors[] = "{$loc}.copy must be an object/array, not a string.";
			}
		}

		return $errors;
	}

	private static function validate_templates( array $templates, array $manifest ): array {
		$errors = [];

		// In the pre-registration model templates reference registered layout slugs directly.
		// For legacy manifests that still carry a patterns block, validate against those keys instead.
		$manifest_pattern_slugs = array_keys( $manifest['patterns'] ?? [] );
		$valid_pattern_refs     = ! empty( $manifest_pattern_slugs )
			? $manifest_pattern_slugs
			: GrayFox_TB_PatternBuilder::get_registered_layouts();

		$valid_types_str = implode( ', ', self::VALID_TEMPLATE_TYPES );

		if ( empty( $templates ) ) {
			$errors[] = 'manifest.templates is empty — at least index.html or front-page.html is required.';
			return $errors;
		}

		foreach ( $templates as $filename => $spec ) {
			$loc = "manifest.templates[\"{$filename}\"]";

			// Filename format.
			if ( ! preg_match( '/^[a-z0-9\-]+\.html$/', $filename ) ) {
				$errors[] = "{$loc}: filename \"{$filename}\" is invalid — must be lowercase with .html extension (e.g. \"front-page.html\").";
			}

			// Type.
			$type = $spec['type'] ?? '';
			if ( empty( $type ) ) {
				$errors[] = "{$loc}.type is required. Valid template types: {$valid_types_str}.";
				continue;
			}
			if ( ! in_array( $type, self::VALID_TEMPLATE_TYPES, true ) ) {
				$errors[] = "{$loc}.type \"{$type}\" is invalid. Valid template types: {$valid_types_str}.";
				continue;
			}

			// archive: validate card_style if present.
			if ( 'archive' === $type && isset( $spec['card_style'] ) ) {
				if ( ! in_array( $spec['card_style'], self::VALID_CARD_STYLES, true ) ) {
					$valid    = implode( ', ', self::VALID_CARD_STYLES );
					$errors[] = "{$loc}.card_style \"{$spec['card_style']}\" is invalid. Valid values: {$valid}.";
				}
			}

			// content: validate patterns array if present.
			if ( 'content' === $type && ! empty( $spec['patterns'] ) ) {
				$valid_str = implode( ', ', $valid_pattern_refs );
				foreach ( $spec['patterns'] as $i => $pattern_slug ) {
					if ( ! in_array( $pattern_slug, $valid_pattern_refs, true ) ) {
						$errors[] = "{$loc}.patterns[{$i}] \"{$pattern_slug}\" is not a registered layout slug. Valid values: {$valid_str}.";
					}
				}
			}
		}

		return $errors;
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private static function is_valid_hex( string $value ): bool {
		return (bool) preg_match( '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value );
	}
}
