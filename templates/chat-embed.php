<?php
/**
 * Inline/shortcode embed HTML template.
 *
 * Variables available:
 *   $shortcode_title — Chat title (from shortcode attr or option).
 *   $shortcode_color — Primary color (from shortcode attr or option).
 *
 * @package GrayFox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$grayfox_embed_title = isset( $shortcode_title ) ? $shortcode_title : get_option( 'grayfox_widget_name', 'Chat with us' );
$grayfox_embed_color = isset( $shortcode_color ) ? $shortcode_color : get_option( 'grayfox_widget_color', '#6366f1' );
$grayfox_embed_id    = wp_unique_id( 'grayfox-embed-' );
?>
<div id="<?php echo esc_attr( $grayfox_embed_id ); ?>"
	 class="grayfox-widget grayfox-embed"
	 data-mode="embed"
	 data-color="<?php echo esc_attr( $grayfox_embed_color ); ?>"
	 data-title="<?php echo esc_attr( $grayfox_embed_title ); ?>">

	<div class="grayfox-window grayfox-window--embed">
		<div class="grayfox-header" style="background-color: <?php echo esc_attr( $grayfox_embed_color ); ?>">
			<h3 class="grayfox-title"><?php echo esc_html( $grayfox_embed_title ); ?></h3>
		</div>

		<div class="grayfox-messages"
			 role="log"
			 aria-live="polite"></div>

		<div class="grayfox-input-area">
			<textarea class="grayfox-input-embed"
					  placeholder="<?php esc_attr_e( 'Type your message...', 'kbfox' ); ?>"
					  rows="1"
					  aria-label="<?php esc_attr_e( 'Message input', 'kbfox' ); ?>"></textarea>
			<button class="grayfox-send-embed"
					aria-label="<?php esc_attr_e( 'Send message', 'kbfox' ); ?>">
				<svg viewBox="0 0 24 24" width="20" height="20" fill="white" xmlns="http://www.w3.org/2000/svg">
					<path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
				</svg>
			</button>
		</div>
	</div>
</div>
