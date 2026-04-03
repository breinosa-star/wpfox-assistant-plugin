<?php
/**
 * [grayfox_chat] shortcode handler.
 *
 * @package GrayFox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GrayFox_Shortcode
 *
 * Registers the [grayfox_chat] shortcode and renders the inline embed.
 */
class GrayFox_Shortcode {

	/**
	 * Whether scripts have already been enqueued for the shortcode.
	 *
	 * @var bool
	 */
	private bool $enqueued = false;

	/**
	 * Register hooks with the loader.
	 *
	 * @param GrayFox_Loader $loader Loader instance.
	 */
	public function register( GrayFox_Loader $loader ): void {
		$loader->add_action( 'init', $this, 'register_shortcode' );
	}

	/**
	 * Register the shortcode with WordPress.
	 */
	public function register_shortcode(): void {
		add_shortcode( 'grayfox_chat', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Render the shortcode output.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_shortcode( array $atts ): string {
		$atts = shortcode_atts(
			array(
				'title' => get_option( 'grayfox_widget_name', 'Chat with us' ),
				'color' => get_option( 'grayfox_widget_color', '#6366f1' ),
			),
			$atts,
			'grayfox_chat'
		);

		$this->enqueue_assets();

		ob_start();
		$shortcode_title = $atts['title'];
		$shortcode_color = $atts['color'];
		include GRAYFOX_PATH . 'templates/chat-embed.php';
		return (string) ob_get_clean();
	}

	/**
	 * Enqueue scripts and styles if not already done.
	 */
	private function enqueue_assets(): void {
		if ( $this->enqueued ) {
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

		// IMPORTANT: LLM API key is never included here.
		wp_localize_script( 'grayfox-chat', 'GrayFoxConfig', array(
			'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
			'nonce'          => wp_create_nonce( 'grayfox_chat' ),
			'sessionId'      => '',
			'title'          => get_option( 'grayfox_widget_name', 'Chat with us' ),
			'primaryColor'   => get_option( 'grayfox_widget_color', '#6366f1' ),
			'welcomeMessage' => get_option( 'grayfox_widget_welcome_message', 'Hello! Who am I speaking with today?' ),
			'position'       => get_option( 'grayfox_widget_position', 'bottom-right' ),
			'siteUrl'        => get_site_url(),
			'version'        => GRAYFOX_VERSION,
		) );

		$this->enqueued = true;
	}
}
