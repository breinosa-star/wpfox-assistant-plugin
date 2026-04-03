<?php
/**
 * Google Connect admin page template.
 *
 * @package GrayFox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

$google          = GrayFox_Google::get_instance();
$is_connected    = $google->is_connected();
$connected_email = get_option( 'grayfox_google_connected_email', '' );
$client_id_raw   = get_option( 'grayfox_google_client_id', '' );
$client_id       = ! empty( $client_id_raw ) ? grayfox_decrypt( $client_id_raw ) : '';
$client_secret   = get_option( 'grayfox_google_client_secret', '' );
$has_secret      = ! empty( $client_secret );

$show_success   = isset( $_GET['connected'] ) && '1' === $_GET['connected']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$error_param    = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( $_GET['error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$tier = get_option( 'grayfox_license_tier', 'starter' );
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Google Connect', 'grayfox' ); ?></h1>

	<?php if ( 'starter' === $tier ) : ?>
	<div class="notice notice-warning">
		<p>
			<?php esc_html_e( 'Google integration requires the Growth plan or higher.', 'grayfox' ); ?>
			&nbsp;<a href="https://grayfox.io/pricing" target="_blank" rel="noopener noreferrer" class="button button-primary">
				<?php esc_html_e( 'Upgrade to Growth', 'grayfox' ); ?>
			</a>
		</p>
	</div>
	<?php endif; ?>

	<?php if ( $show_success ) : ?>
	<div id="grayfox-google-success-notice" class="notice notice-success is-dismissible">
		<p><?php esc_html_e( 'Google account connected successfully.', 'grayfox' ); ?></p>
	</div>
	<?php endif; ?>

	<?php if ( ! empty( $error_param ) ) : ?>
	<div class="notice notice-error is-dismissible">
		<p>
			<?php
			$error_messages = array(
				'access_denied'         => __( 'Authorization was denied. Please try again.', 'grayfox' ),
				'token_request_failed'  => __( 'Could not reach Google servers. Please check your network and try again.', 'grayfox' ),
				'token_exchange_failed' => __( 'Token exchange failed. Verify your Client ID and Client Secret.', 'grayfox' ),
				'missing_code'          => __( 'Authorization code was not received. Please try again.', 'grayfox' ),
			);
			echo esc_html( $error_messages[ $error_param ] ?? __( 'An unknown error occurred. Please try again.', 'grayfox' ) );
			?>
		</p>
	</div>
	<?php endif; ?>

	<!-- Google Credentials Form -->
	<div class="card" style="max-width:700px;padding:20px;margin-bottom:24px;">
		<h2><?php esc_html_e( 'Google API Credentials', 'grayfox' ); ?></h2>
		<p>
			<?php esc_html_e( 'Enter your Google Cloud Console OAuth2 credentials. These are required before connecting.', 'grayfox' ); ?>
			&nbsp;<a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Open Google Cloud Console', 'grayfox' ); ?>
			</a>
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="grayfox-client-id"><?php esc_html_e( 'Client ID', 'grayfox' ); ?></label>
				</th>
				<td>
					<input type="text"
						   id="grayfox-client-id"
						   class="regular-text"
						   value="<?php echo esc_attr( $client_id ); ?>"
						   placeholder="<?php esc_attr_e( 'your-client-id.apps.googleusercontent.com', 'grayfox' ); ?>"
						   autocomplete="off" />
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="grayfox-client-secret"><?php esc_html_e( 'Client Secret', 'grayfox' ); ?></label>
				</th>
				<td>
					<input type="password"
						   id="grayfox-client-secret"
						   class="regular-text"
						   value="<?php echo esc_attr( $has_secret ? str_repeat( '*', 24 ) : '' ); ?>"
						   placeholder="<?php esc_attr_e( $has_secret ? 'Leave blank to keep existing' : 'Enter your client secret', 'grayfox' ); ?>"
						   autocomplete="new-password" />
					<p class="description">
						<?php esc_html_e( 'Stored encrypted. Never exposed to the browser.', 'grayfox' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<p>
			<button type="button"
					id="grayfox-save-credentials"
					class="button button-secondary">
				<?php esc_html_e( 'Save Credentials', 'grayfox' ); ?>
			</button>
			<span id="grayfox-credentials-result" style="margin-left:10px;"></span>
		</p>
	</div>

	<?php if ( $is_connected ) : ?>
	<!-- Connected State -->
	<div class="card" style="max-width:700px;padding:20px;margin-bottom:24px;">
		<h2>
			<span style="color:#00a32a;">&#10003;</span>
			<?php esc_html_e( 'Google Account Connected', 'grayfox' ); ?>
		</h2>
		<?php if ( ! empty( $connected_email ) ) : ?>
		<p>
			<strong><?php esc_html_e( 'Connected account:', 'grayfox' ); ?></strong>
			<?php echo esc_html( $connected_email ); ?>
		</p>
		<?php endif; ?>
		<p><strong><?php esc_html_e( 'Authorized permissions:', 'grayfox' ); ?></strong></p>
		<ul style="list-style:disc;margin-left:20px;">
			<li><?php esc_html_e( 'Google Calendar — read and create events', 'grayfox' ); ?></li>
			<li><?php esc_html_e( 'Google Drive — browse and read files (read-only)', 'grayfox' ); ?></li>
			<li><?php esc_html_e( 'Google Docs — read document content', 'grayfox' ); ?></li>
			<li><?php esc_html_e( 'Google Sheets — read spreadsheet data', 'grayfox' ); ?></li>
		</ul>
		<p>
			<button type="button"
					id="grayfox-disconnect-google"
					class="button button-secondary"
					style="color:#b32d2e;border-color:#b32d2e;"
					data-nonce="<?php echo esc_attr( wp_create_nonce( 'grayfox_google_disconnect' ) ); ?>">
				<?php esc_html_e( 'Disconnect Google Account', 'grayfox' ); ?>
			</button>
		</p>
	</div>

	<?php else : ?>
	<!-- Not Connected State -->
	<div class="card" style="max-width:700px;padding:20px;margin-bottom:24px;">
		<h2><?php esc_html_e( 'Connect Google Account', 'grayfox' ); ?></h2>
		<p>
			<?php esc_html_e( 'Connecting your Google account unlocks Calendar booking, Drive document sync, and Sheets analytics. One connection covers all services.', 'grayfox' ); ?>
		</p>
		<p><strong><?php esc_html_e( 'Permissions that will be requested:', 'grayfox' ); ?></strong></p>
		<ul style="list-style:disc;margin-left:20px;">
			<li><?php esc_html_e( 'Google Calendar — read and create appointment events', 'grayfox' ); ?></li>
			<li><?php esc_html_e( 'Google Drive — browse and read files for knowledge base sync (read-only)', 'grayfox' ); ?></li>
			<li><?php esc_html_e( 'Google Docs — read document content for knowledge base', 'grayfox' ); ?></li>
			<li><?php esc_html_e( 'Google Sheets — read spreadsheet data for analytics', 'grayfox' ); ?></li>
		</ul>
		<?php if ( empty( $client_id ) ) : ?>
		<div class="notice notice-warning inline" style="margin:12px 0;">
			<p><?php esc_html_e( 'Enter and save your Google Client ID and Client Secret above before connecting.', 'grayfox' ); ?></p>
		</div>
		<?php else : ?>
		<p>
			<a href="<?php echo esc_url( $google->get_authorization_url() ); ?>"
			   class="button button-primary"
			   id="grayfox-connect-google">
				<?php esc_html_e( 'Connect Google Account', 'grayfox' ); ?>
			</a>
		</p>
		<?php endif; ?>
		<p style="margin-top:16px;">
			<a href="https://grayfox.io/docs/google-oauth-setup" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'How to set up a Google Cloud project and OAuth credentials', 'grayfox' ); ?>
			</a>
		</p>
	</div>
	<?php endif; ?>

</div><!-- .wrap -->

