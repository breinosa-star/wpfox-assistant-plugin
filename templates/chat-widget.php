<?php
/**
 * Floating chat bubble HTML template.
 *
 * @package GrayFox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div id="grayfox-chat-widget"
	 class="grayfox-widget grayfox-position-<?php echo esc_attr( get_option( 'grayfox_widget_position', 'bottom-right' ) ); ?>"
	 data-mode="floating">

	<!-- Chat bubble trigger button -->
	<button id="grayfox-chat-trigger"
			class="grayfox-trigger"
			aria-label="<?php esc_attr_e( 'Open chat', 'kbfox' ); ?>">
		<svg class="grayfox-trigger-icon grayfox-icon-chat" viewBox="0 0 24 24" width="28" height="28" fill="white" xmlns="http://www.w3.org/2000/svg">
			<path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H5.17L4 17.17V4h16v12zM7 9h2v2H7V9zm4 0h2v2h-2V9zm4 0h2v2h-2V9z"/>
		</svg>
		<svg class="grayfox-trigger-icon grayfox-icon-close" viewBox="0 0 24 24" width="28" height="28" fill="white" xmlns="http://www.w3.org/2000/svg" style="display:none">
			<path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
		</svg>
		<span class="grayfox-badge" style="display:none">0</span>
	</button>

	<!-- Chat window (hidden by default) -->
	<div id="grayfox-chat-window"
		 class="grayfox-window"
		 style="display:none"
		 role="dialog"
		 aria-label="<?php esc_attr_e( 'Chat window', 'kbfox' ); ?>">

		<div class="grayfox-header">
			<h3 class="grayfox-title"><?php echo esc_html( get_option( 'grayfox_widget_name', 'Chat with us' ) ); ?></h3>
			<button class="grayfox-close" aria-label="<?php esc_attr_e( 'Close chat', 'kbfox' ); ?>">&times;</button>
		</div>

		<div id="<?php echo esc_attr( wp_unique_id( 'grayfox-messages-' ) ); ?>"
			 class="grayfox-messages"
			 role="log"
			 aria-live="polite"></div>

		<div class="grayfox-input-area">
			<textarea id="grayfox-input"
					  placeholder="<?php esc_attr_e( 'Type your message...', 'kbfox' ); ?>"
					  rows="1"
					  aria-label="<?php esc_attr_e( 'Message input', 'kbfox' ); ?>"></textarea>
			<button id="grayfox-send"
					aria-label="<?php esc_attr_e( 'Send message', 'kbfox' ); ?>">
				<svg viewBox="0 0 24 24" width="20" height="20" fill="white" xmlns="http://www.w3.org/2000/svg">
					<path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
				</svg>
			</button>
		</div>
	</div>
</div>
