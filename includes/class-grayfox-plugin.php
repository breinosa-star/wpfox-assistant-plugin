<?php
/**
 * Main plugin class (Singleton).
 *
 * @package GrayFox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GrayFox_Plugin
 *
 * Singleton that wires all plugin components together.
 */
if ( ! class_exists( 'GrayFox_Plugin' ) ) {
class GrayFox_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var GrayFox_Plugin|null
	 */
	private static ?GrayFox_Plugin $instance = null;

	/**
	 * Hook loader instance.
	 *
	 * @var GrayFox_Loader
	 */
	private GrayFox_Loader $loader;

	/**
	 * Get the singleton instance.
	 *
	 * @return GrayFox_Plugin
	 */
	public static function get_instance(): GrayFox_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor. Wires up all components and runs the loader.
	 */
	private function __construct() {
		$this->loader = new GrayFox_Loader();

		$db        = new GrayFox_DB();
		$widget    = new GrayFox_Widget();
		$shortcode = new GrayFox_Shortcode();
		$chat      = new GrayFox_Chat();
		$rag       = GrayFox_RAG::get_instance();
		$rest_api  = new GrayFox_REST_API();

		$widget->register( $this->loader );
		$shortcode->register( $this->loader );
		$chat->register( $this->loader );
		$rag->register( $this->loader );
		$rest_api->register( $this->loader );

		if ( is_admin() ) {
			$settings = new GrayFox_Settings();
			$admin    = new GrayFox_Admin();
			$settings->register( $this->loader );
			$admin->register( $this->loader );
		}

		$this->loader->run();
	}

	/**
	 * Get the plugin URL.
	 *
	 * @return string
	 */
	public function get_plugin_url(): string {
		return GRAYFOX_URL;
	}

	/**
	 * Get the plugin directory path.
	 *
	 * @return string
	 */
	public function get_plugin_path(): string {
		return GRAYFOX_PATH;
	}
}
} // end class_exists GrayFox_Plugin
