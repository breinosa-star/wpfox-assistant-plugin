<?php
/**
 * License validation and feature gating.
 *
 * @package GrayFox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GrayFox_License
 *
 * Handles daily license validation via Action Scheduler and feature gating.
 */
class GrayFox_License {

	/**
	 * Transient key for cached license status.
	 *
	 * @var string
	 */
	const TRANSIENT_KEY = 'grayfox_license_status';

	/**
	 * Action Scheduler hook name.
	 *
	 * @var string
	 */
	const AS_HOOK = 'grayfox_validate_license';

	// -------------------------------------------------------------------------
	// Cryptographic token verification
	// -------------------------------------------------------------------------

	/**
	 * Verify a signed license token.
	 *
	 * Token format: tier|status|expires_at|domain|key_prefix|signature_b64
	 * Fail-closed: returns false on ANY failure.
	 *
	 * @param string $token Full token string.
	 * @return array{tier: string, status: string}|false Verified claims or false.
	 */
	private static function verify_token( string $token ): array|false {
		$parts = explode( '|', $token, 6 );
		if ( count( $parts ) !== 6 ) {
			return false;
		}

		[ $tier, $status, $expires_at, $token_domain, $token_key_prefix, $sig_b64 ] = $parts;

		// 1. Check expiry.
		if ( (int) $expires_at < time() ) {
			return false;
		}

		// 2. Check domain matches current site.
		$current_domain = grayfox_normalize_domain_for_token( home_url() );
		if ( ! hash_equals( $current_domain, $token_domain ) ) {
			return false;
		}

		// 3. Check key_prefix matches stored license key prefix.
		$stored_prefix = (string) get_option( 'grayfox_license_key_prefix', '' );
		if ( $stored_prefix === '' || ! hash_equals( $stored_prefix, $token_key_prefix ) ) {
			return false;
		}

		// 4. Verify Ed25519 signature.
		if ( ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
			error_log( 'GrayFox: sodium extension not available for license token verification.' );
			return false;
		}

		$payload    = "{$tier}|{$status}|{$expires_at}|{$token_domain}|{$token_key_prefix}";
		$sig_std    = strtr( $sig_b64, '-_', '+/' );
		$signature  = base64_decode( $sig_std, true );
		$public_key = base64_decode( GRAYFOX_PLATFORM_PUBLIC_KEY_B64, true );

		if ( $signature === false || strlen( $signature ) !== SODIUM_CRYPTO_SIGN_BYTES ) {
			return false;
		}
		if ( $public_key === false || strlen( $public_key ) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES ) {
			return false;
		}

		if ( ! sodium_crypto_sign_verify_detached( $signature, $payload, $public_key ) ) {
			return false;
		}

		// 5. Validate tier and status are known values (defense in depth).
		$valid_tiers    = array( 'starter', 'growth', 'pro' );
		$valid_statuses = array( 'active', 'past_due', 'cancelled' );
		if ( ! in_array( $tier, $valid_tiers, true ) || ! in_array( $status, $valid_statuses, true ) ) {
			return false;
		}

		return array( 'tier' => $tier, 'status' => $status );
	}

	/**
	 * Get the cryptographically verified license tier.
	 *
	 * Returns 'starter' if the token is missing, expired, or invalid.
	 *
	 * @return string Tier slug (starter|growth|pro).
	 */
	public static function get_verified_tier(): string {
		$token = (string) get_option( 'grayfox_license_token', '' );
		if ( $token === '' ) {
			return 'starter';
		}
		$result = self::verify_token( $token );
		if ( $result === false ) {
			if ( function_exists( 'as_enqueue_async_action' ) ) {
				as_enqueue_async_action( 'grayfox_validate_license', array(), 'grayfox' );
			}
			return 'starter';
		}
		return $result['tier'];
	}

	/**
	 * Get the cryptographically verified license status.
	 *
	 * Returns 'unknown' if the token is missing or invalid.
	 *
	 * @return string Status slug (active|past_due|cancelled|unknown).
	 */
	public static function get_verified_status(): string {
		$token = (string) get_option( 'grayfox_license_token', '' );
		if ( $token === '' ) {
			return 'unknown';
		}
		$result = self::verify_token( $token );
		if ( $result === false ) {
			if ( function_exists( 'as_enqueue_async_action' ) ) {
				as_enqueue_async_action( 'grayfox_validate_license', array(), 'grayfox' );
			}
			return 'unknown';
		}
		return $result['status'];
	}

	// -------------------------------------------------------------------------
	// Hook registration & scheduling
	// -------------------------------------------------------------------------

	/**
	 * Register hooks with the loader.
	 *
	 * @param GrayFox_Loader $loader Loader instance.
	 */
	public function register( GrayFox_Loader $loader ): void {
		$loader->add_action( 'init', $this, 'schedule_validation' );
		$loader->add_action( self::AS_HOOK, $this, 'validate_license' );
	}

	/**
	 * Schedule daily license validation via Action Scheduler.
	 * Only schedules if Action Scheduler is available and not already scheduled.
	 */
	public function schedule_validation(): void {
		if ( ! function_exists( 'as_has_scheduled_action' ) ) {
			return;
		}

		if ( ! as_has_scheduled_action( self::AS_HOOK ) ) {
			as_schedule_recurring_action(
				time() + DAY_IN_SECONDS,
				DAY_IN_SECONDS,
				self::AS_HOOK,
				array(),
				'grayfox'
			);
		}
	}

	/**
	 * Validate the license key against the GrayFox platform.
	 * Stores result in a transient for 23 hours.
	 *
	 * @return bool True if license is valid.
	 */
	public function validate_license(): bool {
		$encrypted_key = get_option( 'grayfox_license_key', '' );

		if ( empty( $encrypted_key ) ) {
			$this->store_invalid();
			return false;
		}

		$license_key  = grayfox_decrypt( $encrypted_key );
		$platform_url = get_option( 'grayfox_platform_url', 'https://api.grayfox.io' );

		$response = wp_remote_post(
			trailingslashit( $platform_url ) . 'v1/validate',
			array(
				'headers'     => array( 'Content-Type' => 'application/json' ),
				'body'        => wp_json_encode( array(
					'license_key' => $license_key,
					'domain'      => get_site_url(),
				) ),
				'timeout'     => 15,
				'data_format' => 'body',
			)
		);

		if ( is_wp_error( $response ) ) {
			// Keep cached status on network error — do not invalidate.
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 200 && $code < 300 && ! empty( $body['valid'] ) ) {
			$tier     = sanitize_text_field( $body['tier'] ?? 'starter' );
			$features = isset( $body['features'] ) && is_array( $body['features'] )
				? array_map( 'sanitize_text_field', $body['features'] )
				: array();

			update_option( 'grayfox_license_tier', $tier );
			update_option( 'grayfox_license_features', wp_json_encode( $features ) );

			if ( ! empty( $body['valid_until'] ) ) {
				update_option( 'grayfox_license_valid_until', sanitize_text_field( $body['valid_until'] ) );
			}

			// Store signed license token for cryptographic verification.
			if ( isset( $body['license_token'] ) ) {
				update_option( 'grayfox_license_token', sanitize_text_field( $body['license_token'] ) );
			}

			// Store key_prefix for token verification (derive from stored license key).
			if ( '' === get_option( 'grayfox_license_key_prefix', '' ) ) {
				$raw_key = grayfox_decrypt( get_option( 'grayfox_license_key', '' ) );
				if ( $raw_key && strlen( $raw_key ) >= 8 ) {
					update_option( 'grayfox_license_key_prefix', substr( $raw_key, 0, 8 ) );
				}
			}

			set_transient( self::TRANSIENT_KEY, array(
				'valid'    => true,
				'tier'     => $tier,
				'features' => $features,
			), 23 * HOUR_IN_SECONDS );

			return true;
		}

		$this->store_invalid();
		return false;
	}

	/**
	 * Store an invalid license status in the transient.
	 */
	private function store_invalid(): void {
		set_transient( self::TRANSIENT_KEY, array(
			'valid'    => false,
			'tier'     => '',
			'features' => array(),
		), HOUR_IN_SECONDS );
	}

	/**
	 * Get the current license tier.
	 *
	 * @deprecated 1.1.0 Use GrayFox_License::get_verified_tier() for access control.
	 *             This method reads the raw wp_options value and bypasses Ed25519 token
	 *             verification, making it trivially bypassable by editing the database.
	 *
	 * @return string Tier slug (starter|growth|pro) or empty string.
	 */
	public function get_tier(): string {
		_doing_it_wrong(
			__METHOD__,
			'Use GrayFox_License::get_verified_tier() for access control. get_tier() reads raw wp_options and bypasses cryptographic token verification.',
			'1.1.0'
		);
		return (string) get_option( 'grayfox_license_tier', '' );
	}

	/**
	 * Get the array of enabled features for the current license.
	 *
	 * @return array
	 */
	public function get_features(): array {
		$raw = get_option( 'grayfox_license_features', '[]' );
		$features = json_decode( $raw, true );
		return is_array( $features ) ? $features : array();
	}

	/**
	 * Check if a specific feature is enabled for the current license.
	 *
	 * @param string $feature Feature slug.
	 * @return bool
	 */
	public function is_feature_enabled( string $feature ): bool {
		return in_array( $feature, $this->get_features(), true );
	}
}
