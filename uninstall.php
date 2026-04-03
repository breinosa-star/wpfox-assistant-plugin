<?php
/**
 * GrayFox uninstall script.
 *
 * Runs when the plugin is deleted via WP Admin > Plugins.
 * Drops all custom tables and removes all plugin options.
 *
 * @package GrayFox
 */

// Only run in uninstall context.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-grayfox-db.php';

// Remove all plugin options.
$grayfox_options = array(
	'grayfox_license_key',
	'grayfox_license_tier',
	'grayfox_license_valid_until',
	'grayfox_license_features',
	'grayfox_license_token',
	'grayfox_license_key_prefix',
	'grayfox_llm_provider',
	'grayfox_llm_api_key',
	'grayfox_llm_model',
	'grayfox_platform_url',
	'grayfox_widget_name',
	'grayfox_widget_color',
	'grayfox_widget_position',
	'grayfox_widget_welcome_message',
	'grayfox_enable_widget',
);

foreach ( $grayfox_options as $option ) {
	delete_option( $option );
}

// Delete transients.
delete_transient( 'grayfox_license_status' );

// Drop all custom tables.
GrayFox_DB::drop_tables();
