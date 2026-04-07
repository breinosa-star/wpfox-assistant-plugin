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

		// Run dbDelta for schema upgrades on existing installs.
		if ( get_option( 'grayfox_db_version', '1.0.0' ) !== '1.1.0' ) {
			GrayFox_DB::create_tables();
			update_option( 'grayfox_db_version', '1.1.0' );
		}

		require_once GRAYFOX_PATH . 'includes/class-grayfox-drive.php';
		require_once GRAYFOX_PATH . 'includes/class-grayfox-site-builder.php';
		require_once GRAYFOX_PATH . 'includes/class-grayfox-audit.php';

		$db        = new GrayFox_DB();
		$settings  = new GrayFox_Settings();
		$license   = new GrayFox_License();
		$widget    = new GrayFox_Widget();
		$shortcode = new GrayFox_Shortcode();
		$admin     = new GrayFox_Admin();
		$chat      = new GrayFox_Chat();
		$rag       = GrayFox_RAG::get_instance();
		$google    = GrayFox_Google::get_instance();
		$booking   = GrayFox_Booking::get_instance();
		$drive     = GrayFox_Drive::get_instance();
		$sheets    = GrayFox_Sheets::get_instance();

		$site_builder = GrayFox_SiteBuilder::get_instance();
		$audit        = GrayFox_Audit::get_instance();

		$settings->register( $this->loader );
		$license->register( $this->loader );
		$widget->register( $this->loader );
		$shortcode->register( $this->loader );
		$admin->register( $this->loader );
		$chat->register( $this->loader );
		$rag->register( $this->loader );
		$google->register( $this->loader );
		$booking->register( $this->loader );
		$drive->register( $this->loader );
		$sheets->register( $this->loader );
		$site_builder->register( $this->loader );
		$audit->register( $this->loader );

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
