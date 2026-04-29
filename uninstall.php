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

global $wpdb;

// Remove all plugin options and transients by prefix.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'grayfox\_%' OR option_name LIKE '\_transient\_grayfox%' OR option_name LIKE '\_transient\_timeout\_grayfox%'" );

// Drop all custom tables.
GrayFox_DB::drop_tables();
