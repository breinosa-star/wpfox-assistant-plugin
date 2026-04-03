/**
 * SSE (Server-Sent Events) client for GrayFox streaming responses.
 */

/**
 * Create an SSE client for streaming chat responses from the WP AJAX endpoint.
 *
 * @param {string} sessionId    Session ID.
 * @param {string} streamToken  Unused (kept for API symmetry); GrayFox uses WP nonce.
 * @param {string} streamUrl    Full SSE stream URL (admin-ajax.php with params).
 * @returns {Object} SSEClient instance.
 */
function SSEClient(sessionId, streamToken, streamUrl) {
	this.sessionId = sessionId;
	this.streamToken = streamToken;
	this.streamUrl = streamUrl;
	this.eventSource = null;
	this.messageCallback = null;
	this.errorCallback = null;
	this.retryCount = 0;
	this.maxRetries = 3;
	this.closed = false;
	this.receivedFirstMessage = false;
	this.connectTimer = null;
}

/**
 * Connect to the SSE stream.
 */
SSEClient.prototype.connect = function () {
	var self = this;
	this.closed = false;
	this.receivedFirstMessage = false;

	this.eventSource = new EventSource(this.streamUrl);

	// Timeout: if no message arrives within 15s, treat as permanent error.
	this.connectTimer = setTimeout(function () {
		if (!self.receivedFirstMessage && !self.closed) {
			if (self.errorCallback) {
				self.errorCallback({ type: 'error', message: 'Connection failed. Please try again.' });
			}
			self.close();
		}
	}, 15000);

	this.eventSource.onmessage = function (event) {
		self.receivedFirstMessage = true;
		clearTimeout(self.connectTimer);
		self.connectTimer = null;
		self.retryCount = 0;

		var raw = event.data;

		// Guard against WordPress error sentinels (wp_die() echoes 0 or -1).
		if (raw === '0' || raw === '-1' || raw === '') {
			if (self.errorCallback) {
				self.errorCallback({ type: 'error', message: 'Stream connection failed' });
			}
			self.close();
			return;
		}

		// Handle [DONE] sentinel.
		if (raw === '[DONE]') {
			self.close();
			return;
		}

		try {
			var data = JSON.parse(raw);

			if (data.type === 'error') {
				if (self.errorCallback) {
					self.errorCallback(data);
				}
				self.close();
				return;
			}

			if (self.messageCallback) {
				self.messageCallback(data);
			}
		} catch (e) {
			// Non-JSON — treat as plain text token.
			if (self.messageCallback) {
				self.messageCallback({ type: 'token', token: raw });
			}
		}
	};

	this.eventSource.onerror = function () {
		if (self.closed) return;

		self.eventSource.close();

		// If we never received a message, treat as permanent failure.
		if (!self.receivedFirstMessage) {
			if (self.errorCallback) {
				self.errorCallback({ type: 'error', message: 'Connection failed. Please try again.' });
			}
			self.closed = true;
			return;
		}

		if (self.retryCount < self.maxRetries) {
			self.retryCount++;
			var delay = Math.pow(2, self.retryCount) * 1000; // Exponential backoff.
			setTimeout(function () {
				if (!self.closed) {
					self.connect();
				}
			}, delay);
		} else {
			if (self.errorCallback) {
				self.errorCallback({ type: 'error', message: 'Connection lost. Please try again.' });
			}
			self.closed = true;
		}
	};
};

/**
 * Set the message callback.
 *
 * @param {Function} callback Called with parsed data object.
 */
SSEClient.prototype.onMessage = function (callback) {
	this.messageCallback = callback;
};

/**
 * Set the error callback.
 *
 * @param {Function} callback Called with { type, message }.
 */
SSEClient.prototype.onError = function (callback) {
	this.errorCallback = callback;
};

/**
 * Close the SSE connection.
 */
SSEClient.prototype.close = function () {
	this.closed = true;
	if (this.connectTimer) {
		clearTimeout(this.connectTimer);
		this.connectTimer = null;
	}
	if (this.eventSource) {
		this.eventSource.close();
		this.eventSource = null;
	}
};

module.exports = SSEClient;
