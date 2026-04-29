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

		require_once GRAYFOX_PATH . 'includes/class-grayfox-site-builder-tools.php';
		require_once GRAYFOX_PATH . 'includes/class-grayfox-site-builder.php';
		require_once GRAYFOX_PATH . 'includes/class-grayfox-audit.php';

		// Theme Builder modules — load helpers first, then pattern groups, then main class.
		foreach ( glob( GRAYFOX_PATH . 'includes/theme-builder/class-tb-*.php' ) as $tb_file ) {
			require_once $tb_file;
		}
		foreach ( glob( GRAYFOX_PATH . 'includes/theme-builder/patterns/class-tb-patterns-*.php' ) as $tb_pattern_file ) {
			require_once $tb_pattern_file;
		}
		require_once GRAYFOX_PATH . 'includes/class-grayfox-theme-builder.php';

		$db        = new GrayFox_DB();
		$settings  = new GrayFox_Settings();
		$widget    = new GrayFox_Widget();
		$shortcode = new GrayFox_Shortcode();
		$admin     = new GrayFox_Admin();
		$chat      = new GrayFox_Chat();
		$rag       = GrayFox_RAG::get_instance();
		$site_builder  = GrayFox_SiteBuilder::get_instance();
		$audit         = GrayFox_Audit::get_instance();

		$settings->register( $this->loader );
		$widget->register( $this->loader );
		$shortcode->register( $this->loader );
		$admin->register( $this->loader );
		$chat->register( $this->loader );
		$rag->register( $this->loader );
		$site_builder->register( $this->loader );
		$audit->register( $this->loader );

		$theme_builder = GrayFox_ThemeBuilder::get_instance();
		$theme_builder->register( $this->loader );

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
