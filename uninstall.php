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
	'grayfox_llm_provider',
	'grayfox_llm_api_key',
	'grayfox_llm_model',
	'grayfox_llm_max_tokens',
	'grayfox_widget_name',
	'grayfox_widget_color',
	'grayfox_widget_position',
	'grayfox_widget_welcome_message',
	'grayfox_enable_widget',
	'grayfox_inactivity_timeout',
	'grayfox_session_message_limit',
	'grayfox_db_version',
);

foreach ( $grayfox_options as $option ) {
	delete_option( $option );
}

// Drop all custom tables.
GrayFox_DB::drop_tables();
