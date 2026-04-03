/**
 * ChatWindow — main chat controller for GrayFox.
 */

var api = require('../services/api');
var SSEClient = require('../services/sse-client');
var session = require('../store/session');
var MessageList = require('./MessageList');
var MessageInput = require('./MessageInput');
var TypingIndicator = require('./TypingIndicator');

/**
 * Create a ChatWindow controller.
 *
 * @param {HTMLElement} widgetEl The root widget element (#grayfox-chat-widget or embed).
 * @param {Object}      config   GrayFoxConfig values.
 */
function ChatWindow(widgetEl, config) {
	this.widgetEl = widgetEl;
	this.config = config;
	this.sessionId = config.sessionId || '';
	this.sseClient = null;
	this.isOpen = false;
	this.isSending = false;

	// DOM references.
	this.windowEl = widgetEl.querySelector('.grayfox-window');
	this.messagesEl = widgetEl.querySelector('.grayfox-messages');
	this.titleEl = widgetEl.querySelector('.grayfox-title');

	// Determine input/send elements based on mode.
	var mode = widgetEl.getAttribute('data-mode');
	if (mode === 'embed') {
		this.inputEl = widgetEl.querySelector('.grayfox-input-embed');
		this.sendBtn = widgetEl.querySelector('.grayfox-send-embed');
	} else {
		this.inputEl = widgetEl.querySelector('#grayfox-input');
		this.sendBtn = widgetEl.querySelector('#grayfox-send');
	}

	this.closeBtn = widgetEl.querySelector('.grayfox-close');
}

/**
 * Initialize the chat window.
 */
ChatWindow.prototype.initialize = function () {
	var self = this;

	// Apply theme color.
	var color = this.config.primaryColor || '#6366f1';
	document.documentElement.style.setProperty('--grayfox-primary', color);

	// Set title if element exists.
	if (this.titleEl) {
		var title = this.widgetEl.getAttribute('data-title') || this.config.title || 'Chat with us';
		this.titleEl.textContent = title;
	}

	// Apply custom color in embed mode.
	var mode = this.widgetEl.getAttribute('data-mode');
	if (mode === 'embed') {
		var embedColor = this.widgetEl.getAttribute('data-color') || color;
		document.documentElement.style.setProperty('--grayfox-primary', embedColor);
	}

	// Wire input handler.
	MessageInput.initMessageInput(this.inputEl, this.sendBtn, function (text) {
		self.sendMessage(text);
	});

	// Wire close button.
	if (this.closeBtn) {
		this.closeBtn.addEventListener('click', function () {
			self.close();
		});
	}

	// Escape key to close.
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && self.isOpen) {
			self.close();
		}
	});

	// Restore session.
	this.restoreSession();
};

/**
 * Open the chat window.
 */
ChatWindow.prototype.open = function () {
	if (!this.windowEl) return;
	this.windowEl.style.display = 'flex';
	this.isOpen = true;
	this.widgetEl.classList.add('grayfox-open');

	// Show welcome message if no messages exist.
	if (this.messagesEl && this.messagesEl.children.length === 0 && this.config.welcomeMessage) {
		MessageList.appendMessage(this.messagesEl, 'assistant', this.config.welcomeMessage);
	}

	// Focus input.
	if (this.inputEl) {
		this.inputEl.focus();
	}
};

/**
 * Close the chat window.
 */
ChatWindow.prototype.close = function () {
	if (!this.windowEl) return;
	this.windowEl.style.display = 'none';
	this.isOpen = false;
	this.widgetEl.classList.remove('grayfox-open');
};

/**
 * Toggle the chat window.
 */
ChatWindow.prototype.toggle = function () {
	if (this.isOpen) {
		this.close();
	} else {
		this.open();
	}
};

/**
 * Send a message.
 *
 * @param {string} text User message text.
 */
ChatWindow.prototype.sendMessage = function (text) {
	if (this.isSending || !text) return;

	var self = this;
	this.isSending = true;

	// Append user message to UI.
	MessageList.appendMessage(this.messagesEl, 'user', text);

	// Save to session.
	session.addMessage({ role: 'user', content: text, timestamp: Date.now() });

	// Disable input while sending.
	MessageInput.setDisabled(this.inputEl, this.sendBtn, true);

	// Show typing indicator.
	TypingIndicator.showTypingIndicator(this.messagesEl);

	// Step 1: POST message to get stream_token, then open SSE stream.
	api.sendMessage(
		this.config.ajaxUrl,
		this.config.nonce,
		this.sessionId,
		text
	).then(function (result) {
		// Store session ID returned by server.
		if (result.sessionId) {
			self.sessionId = result.sessionId;
			var stored = session.loadSession() || { sessionId: '', messages: [] };
			stored.sessionId = result.sessionId;
			session.saveSession(stored);
		}

		// Hide typing indicator — SSE stream will start.
		TypingIndicator.hideTypingIndicator(self.messagesEl);

		// Step 2: Open SSE stream using the single-use stream_token.
		self.streamResponse(result.streamToken);

	}).catch(function (err) {
		TypingIndicator.hideTypingIndicator(self.messagesEl);

		// Security: session blocked — disable input permanently.
		if (err && err.security === 'blocked') {
			var blockedMsg = err.message || 'This session has been disconnected due to policy violations.';
			MessageList.appendMessage(self.messagesEl, 'assistant', blockedMsg, 'error');
			self.isSending = false;
			MessageInput.setDisabled(self.inputEl, self.sendBtn, true);
			if (self.inputEl) {
				self.inputEl.placeholder = 'Chat disabled.';
			}
			return;
		}

		// Security: warning — show message, re-enable input.
		if (err && err.security === 'warning') {
			var strikesLeft = Math.max(0, 3 - (err.strikes || 1));
			var warnMsg = (err.message || 'Message not allowed.') +
				(strikesLeft > 0 ? ' (' + strikesLeft + ' warning' + (strikesLeft !== 1 ? 's' : '') + ' remaining)' : '');
			MessageList.appendMessage(self.messagesEl, 'assistant', warnMsg, 'error');
			self.isSending = false;
			MessageInput.setDisabled(self.inputEl, self.sendBtn, false);
			return;
		}

		var msg = 'Sorry, something went wrong. Please try again.';
		if (err && err.status === 403) {
			msg = 'Session expired. Please refresh and try again.';
		} else if (err && err.status === 503) {
			msg = 'The AI assistant is not configured. Please contact the site administrator.';
		}
		MessageList.appendMessage(self.messagesEl, 'assistant', msg, 'error');
		self.isSending = false;
		MessageInput.setDisabled(self.inputEl, self.sendBtn, false);
	});
};

/**
 * Stream the assistant response via SSE from WP AJAX endpoint.
 *
 * @param {string} streamToken Single-use token returned by grayfox_chat POST action.
 */
ChatWindow.prototype.streamResponse = function (streamToken) {
	var self = this;
	var assistantContent = '';
	var assistantMsgEl = null;

	// Close previous SSE if any.
	if (this.sseClient) {
		this.sseClient.close();
	}

	// SSE URL: includes stream_token, session_id, and stream-specific nonce.
	var sseUrl = this.config.ajaxUrl +
		'?action=grayfox_chat_stream' +
		'&stream_token=' + encodeURIComponent(streamToken) +
		'&session_id=' + encodeURIComponent(this.sessionId) +
		'&nonce=' + encodeURIComponent(this.config.streamNonce);

	this.sseClient = new SSEClient(this.sessionId, streamToken, sseUrl);

	this.sseClient.onMessage(function (data) {
		// Handle stream completion.
		if (data.done) {
			self.sseClient.close();
			return;
		}

		// Handle error event from stream.
		if (data.error) {
			if (self.sseClient && self.sseClient.errorCallback) {
				self.sseClient.errorCallback({ type: 'error', message: data.error });
			}
			return;
		}

		// Handle token streaming.
		if (data.token) {
			var chunk = data.token;
			assistantContent += chunk;

			if (!assistantMsgEl) {
				assistantMsgEl = MessageList.appendMessage(self.messagesEl, 'assistant', assistantContent);
			} else {
				MessageList.updateMessage(assistantMsgEl, assistantContent);
				MessageList.scrollToBottom(self.messagesEl);
			}
		}
	});

	this.sseClient.onError(function (data) {
		TypingIndicator.hideTypingIndicator(self.messagesEl);

		var errorMsg = (data && data.message) ? data.message : 'Connection error. Please try again.';
		if (!assistantMsgEl) {
			MessageList.appendMessage(self.messagesEl, 'assistant', errorMsg, 'error');
		}

		self.isSending = false;
		MessageInput.setDisabled(self.inputEl, self.sendBtn, false);
	});

	this.sseClient.connect();

	// Poll for completion.
	var checkInterval = setInterval(function () {
		if (self.sseClient && self.sseClient.closed) {
			clearInterval(checkInterval);

			if (assistantContent) {
				session.addMessage({ role: 'assistant', content: assistantContent, timestamp: Date.now() });
			}

			self.isSending = false;
			MessageInput.setDisabled(self.inputEl, self.sendBtn, false);

			if (self.inputEl) {
				self.inputEl.focus();
			}
		}
	}, 200);
};

/**
 * Restore a previous session from sessionStorage.
 */
ChatWindow.prototype.restoreSession = function () {
	var stored = session.loadSession();
	if (!stored || !stored.sessionId) return;

	this.sessionId = stored.sessionId;

	// Re-render messages from session storage.
	if (stored.messages && stored.messages.length > 0) {
		this.renderLocalMessages(stored.messages);
	}
};

/**
 * Render messages from local session storage.
 *
 * @param {Array} messages Array of { role, content }.
 */
ChatWindow.prototype.renderLocalMessages = function (messages) {
	MessageList.clearMessages(this.messagesEl);
	var self = this;
	messages.forEach(function (msg) {
		MessageList.appendMessage(self.messagesEl, msg.role, msg.content);
	});
};

/**
 * Show an error in the chat window.
 *
 * @param {string} message Error message.
 */
ChatWindow.prototype.showError = function (message) {
	if (this.messagesEl) {
		MessageList.appendMessage(this.messagesEl, 'assistant', message, 'error');
	}
	if (this.inputEl && this.sendBtn) {
		MessageInput.setDisabled(this.inputEl, this.sendBtn, true);
	}
};

module.exports = ChatWindow;
