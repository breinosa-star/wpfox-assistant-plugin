<?php
/**
 * Admin Settings page template.
 *
 * @package GrayFox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	return;
}
?>
<div class="wrap grayfox-admin-wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<?php settings_errors( 'grayfox_options' ); ?>

	<form action="options.php" method="post">
		<?php
		settings_fields( GrayFox_Settings::OPTION_GROUP );
		do_settings_sections( GrayFox_Settings::PAGE_SLUG );
		submit_button( __( 'Save Settings', 'grayfox' ) );
		?>
	</form>

	<hr />
	<p>
		<a href="https://grayfox.io/billing" target="_blank" rel="noopener noreferrer">
			<?php esc_html_e( 'Manage your billing &amp; subscription &rarr;', 'grayfox' ); ?>
		</a>
	</p>
</div>
