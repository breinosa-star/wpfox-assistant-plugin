<?php
/**
 * Floating chat bubble HTML template.
 *
 * @package GrayFox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$voice_active        = get_option( 'grayfox_voice_enabled', false ) && is_ssl();
$voice_trigger_color = get_option( 'grayfox_voice_trigger_color', '#10b981' );
?>
<div id="grayfox-chat-widget"
	 class="grayfox-widget grayfox-position-<?php echo esc_attr( get_option( 'grayfox_widget_position', 'bottom-right' ) ); ?><?php echo $voice_active ? ' grayfox-has-voice-trigger' : ''; ?>"
	 data-mode="floating"
	 style="<?php echo $voice_active ? '--grayfox-voice-trigger:' . esc_attr( $voice_trigger_color ) . ';' : ''; ?>">

	<div class="grayfox-triggers">

		<?php if ( $voice_active ) : ?>
		<!-- Voice trigger button -->
		<button id="grayfox-voice-trigger"
				class="grayfox-trigger grayfox-trigger--voice"
				aria-label="<?php esc_attr_e( 'Start voice session', 'kbfox' ); ?>">
			<svg viewBox="0 0 24 24" width="28" height="28" fill="white" xmlns="http://www.w3.org/2000/svg">
				<path d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3zm5.91-3c-.49 0-.9.36-.98.85C16.52 14.2 14.47 16 12 16s-4.52-1.8-4.93-4.15c-.08-.49-.49-.85-.98-.85-.61 0-1.09.54-1 1.14.49 3 2.89 5.35 5.91 5.78V20c0 .55.45 1 1 1s1-.45 1-1v-2.08c3.02-.43 5.42-2.78 5.91-5.78.1-.6-.39-1.14-1-1.14z"/>
			</svg>
		</button>
		<?php endif; ?>

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

	</div><!-- .grayfox-triggers -->

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
			<?php if ( $voice_active ) : ?>
			<button id="grayfox-mic"
					class="grayfox-mic"
					type="button"
					aria-label="<?php esc_attr_e( 'Start voice session', 'kbfox' ); ?>">
				<svg viewBox="0 0 24 24" width="20" height="20" fill="white" xmlns="http://www.w3.org/2000/svg">
					<path d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3zm5.91-3c-.49 0-.9.36-.98.85C16.52 14.2 14.47 16 12 16s-4.52-1.8-4.93-4.15c-.08-.49-.49-.85-.98-.85-.61 0-1.09.54-1 1.14.49 3 2.89 5.35 5.91 5.78V20c0 .55.45 1 1 1s1-.45 1-1v-2.08c3.02-.43 5.42-2.78 5.91-5.78.1-.6-.39-1.14-1-1.14z"/>
				</svg>
			</button>
			<?php endif; ?>
		</div>

		<!-- Voice mode overlay — replaces messages+input during a voice session -->
		<?php if ( $voice_active ) : ?>
		<div id="grayfox-voice-overlay" class="grayfox-voice-overlay" style="display:none">

			<!-- Visual state indicator: orb + rings for AI speaking, mic pulse for listening -->
			<div class="grayfox-voice-visualizer" aria-hidden="true">
				<div class="grayfox-voice-ring grayfox-voice-ring--1"></div>
				<div class="grayfox-voice-ring grayfox-voice-ring--2"></div>
				<div class="grayfox-voice-ring grayfox-voice-ring--3"></div>
				<div class="grayfox-voice-orb">
					<!-- Spinner: shown while connecting (before first AI utterance) -->
					<svg class="grayfox-voice-icon grayfox-voice-icon--connecting" viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="white" xmlns="http://www.w3.org/2000/svg">
						<circle cx="12" cy="12" r="9" stroke-width="2.5" stroke-opacity="0.3"/>
						<path d="M12 3a9 9 0 0 1 9 9" stroke-width="2.5" stroke-linecap="round"/>
					</svg>
					<!-- Mic icon: shown while listening -->
					<svg class="grayfox-voice-icon grayfox-voice-icon--mic" viewBox="0 0 24 24" width="32" height="32" fill="white" xmlns="http://www.w3.org/2000/svg" style="display:none">
						<path d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3zm5.91-3c-.49 0-.9.36-.98.85C16.52 14.2 14.47 16 12 16s-4.52-1.8-4.93-4.15c-.08-.49-.49-.85-.98-.85-.61 0-1.09.54-1 1.14.49 3 2.89 5.35 5.91 5.78V20c0 .55.45 1 1 1s1-.45 1-1v-2.08c3.02-.43 5.42-2.78 5.91-5.78.1-.6-.39-1.14-1-1.14z"/>
					</svg>
					<!-- Sound bars: shown while AI is speaking -->
					<div class="grayfox-voice-bars" style="display:none" aria-hidden="true">
						<span></span><span></span><span></span><span></span><span></span>
					</div>
				</div>
			</div>

			<!-- Live status label — visible and announced to screen readers -->
			<p class="grayfox-voice-status"
			   role="status"
			   aria-live="polite"></p>

			<button class="grayfox-voice-end" type="button">
				<?php esc_html_e( 'End call', 'kbfox' ); ?>
			</button>
		</div>
		<?php endif; ?>
	</div>
</div>
