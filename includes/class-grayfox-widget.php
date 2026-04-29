<?php
/**
 * Floating chat bubble widget.
 *
 * @package GrayFox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GrayFox_Widget
 *
 * Enqueues front-end assets and renders the floating chat bubble via wp_footer.
 */
class GrayFox_Widget {

	/**
	 * Register hooks with the loader.
	 *
	 * @param GrayFox_Loader $loader Loader instance.
	 */
	public function register( GrayFox_Loader $loader ): void {
		$loader->add_action( 'wp_enqueue_scripts', $this, 'enqueue_assets' );
		$loader->add_action( 'wp_footer', $this, 'render_floating_widget' );
	}

	/**
	 * Whether the widget should load on the current page.
	 *
	 * @return bool
	 */
	public function should_load(): bool {
		$enabled = get_option( 'grayfox_enable_widget', true );
		return (bool) $enabled;
	}

	/**
	 * Enqueue front-end CSS and JS.
	 * NOTE: GrayFoxConfig must NOT contain the LLM API key.
	 */
	public function enqueue_assets(): void {
		if ( ! $this->should_load() ) {
			return;
		}

		wp_enqueue_style(
			'grayfox-chat',
			GRAYFOX_URL . 'assets/dist/grayfox-chat.min.css',
			array(),
			GRAYFOX_VERSION
		);

		wp_enqueue_script(
			'grayfox-chat',
			GRAYFOX_URL . 'assets/dist/grayfox-chat.min.js',
			array(),
			GRAYFOX_VERSION,
			true
		);

		// IMPORTANT: LLM API key is never included here — all LLM calls are server-side.
		$config = array(
			'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
			'nonce'             => wp_create_nonce( 'grayfox_chat' ),
			'streamNonce'       => wp_create_nonce( 'grayfox_chat_stream' ),
			'sessionId'         => '',
			'title'             => get_option( 'grayfox_widget_name', 'Chat with us' ),
			'primaryColor'      => get_option( 'grayfox_widget_color', '#6366f1' ),
			'welcomeMessage'    => get_option( 'grayfox_widget_welcome_message', 'Hello! Who am I speaking with today?' ),
			'position'          => get_option( 'grayfox_widget_position', 'bottom-right' ),
			'siteUrl'           => get_site_url(),
			'version'           => GRAYFOX_VERSION,
			'inactivityMinutes' => (int) get_option( 'grayfox_inactivity_timeout', 5 ),
		);
		$config = apply_filters( 'grayfox_widget_config', $config );
		wp_localize_script( 'grayfox-chat', 'GrayFoxConfig', $config );
	}

	/**
	 * Render the floating widget HTML in the footer.
	 */
	public function render_floating_widget(): void {
		if ( ! $this->should_load() ) {
			return;
		}

		include GRAYFOX_PATH . 'templates/chat-widget.php';
	}
}
