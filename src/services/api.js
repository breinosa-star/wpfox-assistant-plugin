'use strict';

/**
 * Send a chat message via WordPress AJAX.
 *
 * @param {string} ajaxUrl
 * @param {string} nonce
 * @param {string} sessionId
 * @param {string} message
 * @returns {Promise<{sessionId: string, streamToken: string}>}
 */
async function sendMessage( ajaxUrl, nonce, sessionId, message ) {
	const data = new FormData();
	data.append( 'action',     'grayfox_chat' );
	data.append( 'nonce',      nonce );
	data.append( 'session_id', sessionId || '' );
	data.append( 'message',    message );

	const response = await fetch( ajaxUrl, { method: 'POST', body: data } );

	let json;
	try {
		json = await response.json();
	} catch {
		const err = new Error( 'Chat request failed: ' + response.status );
		err.status = response.status;
		throw err;
	}

	if ( ! json.success ) {
		const err = new Error( json.data && json.data.message ? json.data.message : 'Chat error' );
		err.security    = json.data && json.data.security    || null;
		err.strikes     = json.data && json.data.strikes     || 0;
		err.retryAfter  = json.data && json.data.retry_after || 0;
		err.status      = response.status;
		throw err;
	}

	return {
		sessionId:   json.data.session_id,
		streamToken: json.data.stream_token,
	};
}

module.exports = { sendMessage };
