/**
 * API service — POST to WordPress AJAX endpoint for GrayFox.
 *
 * All LLM calls are server-side only. The browser sends the WP nonce
 * and session token — never an LLM API key.
 *
 * sendMessage() performs Step 1 only: POSTs the message and retrieves a
 * single-use stream_token. The caller is responsible for opening the
 * EventSource to the grayfox_chat_stream action.
 */

/**
 * Send a chat message to the WordPress AJAX endpoint.
 *
 * @param {string} ajaxUrl   WordPress admin-ajax.php URL.
 * @param {string} nonce     WordPress nonce for grayfox_chat action.
 * @param {string} sessionId Current session ID (may be empty for new sessions).
 * @param {string} message   User message text.
 * @returns {Promise<{ sessionId: string, streamToken: string }>}
 */
async function sendMessage(ajaxUrl, nonce, sessionId, message) {
	var formData = new FormData();
	formData.append('action', 'grayfox_chat');
	formData.append('nonce', nonce);
	formData.append('session_id', sessionId || '');
	formData.append('message', message);

	var response = await fetch(ajaxUrl, {
		method: 'POST',
		body: formData,
	});

	// Always attempt to parse the JSON body — security errors (blocked sessions,
	// rate limits) arrive as 4xx responses whose body contains { security, message }.
	// Throwing before parsing would discard that context and show a generic error.
	var data;
	try {
		data = await response.json();
	} catch (_) {
		var httpErr = new Error('Chat request failed: ' + response.status);
		httpErr.status = response.status;
		throw httpErr;
	}

	if (!data.success) {
		var apiErr = new Error((data.data && data.data.message) ? data.data.message : 'Chat error');
		apiErr.security = (data.data && data.data.security) || null;
		apiErr.strikes  = (data.data && data.data.strikes)  || 0;
		apiErr.status   = response.status;
		throw apiErr;
	}

	return {
		sessionId: data.data.session_id,
		streamToken: data.data.stream_token,
	};
}

module.exports = {
	sendMessage: sendMessage
};
