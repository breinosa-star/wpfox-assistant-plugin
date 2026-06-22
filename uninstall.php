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
$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE option_name LIKE %s", $wpdb->options, $wpdb->esc_like( 'grayfox_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE option_name LIKE %s", $wpdb->options, $wpdb->esc_like( '_transient_grayfox' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE option_name LIKE %s", $wpdb->options, $wpdb->esc_like( '_transient_timeout_grayfox' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

// Drop all custom tables.
GrayFox_DB::drop_tables();
