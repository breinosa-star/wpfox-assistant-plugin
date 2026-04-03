/**
 * GrayFox Chat Widget — entry point.
 *
 * Initializes the chat widget when the DOM is ready.
 * Reads configuration from window.GrayFoxConfig (set via wp_localize_script).
 */

var ChatWindow = require('./components/ChatWindow');

(function () {
	'use strict';

	/**
	 * Initialize all chat instances on the page.
	 */
	function init() {
		var config = window.GrayFoxConfig;

		if (!config) {
			return;
		}

		// --- Floating widget ---
		var floatingWidget = document.getElementById('grayfox-chat-widget');
		if (floatingWidget) {
			var chatWindow = new ChatWindow(floatingWidget, config);
			chatWindow.initialize();

			// Trigger button toggle.
			var triggerBtn = document.getElementById('grayfox-chat-trigger');
			if (triggerBtn) {
				triggerBtn.addEventListener('click', function () {
					chatWindow.toggle();

					// Toggle icon between chat and close.
					var chatIcon = triggerBtn.querySelector('.grayfox-icon-chat');
					var closeIcon = triggerBtn.querySelector('.grayfox-icon-close');
					if (chatIcon && closeIcon) {
						if (chatWindow.isOpen) {
							chatIcon.style.display = 'none';
							closeIcon.style.display = 'block';
						} else {
							chatIcon.style.display = 'block';
							closeIcon.style.display = 'none';
						}
					}
				});
			}

			// Also handle the close button inside the window.
			var closeBtn = floatingWidget.querySelector('.grayfox-close');
			if (closeBtn) {
				closeBtn.addEventListener('click', function () {
					// Reset trigger icon.
					var chatIcon = triggerBtn ? triggerBtn.querySelector('.grayfox-icon-chat') : null;
					var closeIcon = triggerBtn ? triggerBtn.querySelector('.grayfox-icon-close') : null;
					if (chatIcon && closeIcon) {
						chatIcon.style.display = 'block';
						closeIcon.style.display = 'none';
					}
				});
			}
		}

		// --- Embedded widgets (from shortcode) ---
		var embeds = document.querySelectorAll('.grayfox-embed');
		for (var i = 0; i < embeds.length; i++) {
			var embedEl = embeds[i];
			var embedConfig = Object.assign({}, config);

			// Override with per-embed data attributes.
			if (embedEl.getAttribute('data-color')) {
				embedConfig.primaryColor = embedEl.getAttribute('data-color');
			}
			if (embedEl.getAttribute('data-title')) {
				embedConfig.title = embedEl.getAttribute('data-title');
			}

			var embedChat = new ChatWindow(embedEl, embedConfig);
			embedChat.initialize();

			// Embeds are always visible — show welcome message immediately.
			if (embedChat.messagesEl && embedChat.messagesEl.children.length === 0 && embedConfig.welcomeMessage) {
				var MessageList = require('./components/MessageList');
				MessageList.appendMessage(embedChat.messagesEl, 'assistant', embedConfig.welcomeMessage);
			}
		}
	}

	// Wait for DOM ready.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
