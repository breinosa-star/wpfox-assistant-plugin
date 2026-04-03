/**
 * Session storage manager for GrayFox.
 *
 * Persists session_id and messages in sessionStorage so state
 * survives in-page navigations but clears when the tab closes.
 * Uses sessionStorage (NOT localStorage) by design.
 */

var STORAGE_KEY = 'grayfox_session';

/**
 * Save session data to sessionStorage.
 *
 * @param {Object} data
 * @param {string} data.sessionId
 * @param {Array}  data.messages
 */
function saveSession(data) {
	try {
		sessionStorage.setItem(STORAGE_KEY, JSON.stringify(data));
	} catch (e) {
		// Storage full or unavailable — silently ignore.
	}
}

/**
 * Load session data from sessionStorage.
 *
 * @returns {Object|null} { sessionId, messages } or null
 */
function loadSession() {
	try {
		var raw = sessionStorage.getItem(STORAGE_KEY);
		if (!raw) return null;
		var data = JSON.parse(raw);
		if (data && data.sessionId) return data;
		return null;
	} catch (e) {
		return null;
	}
}

/**
 * Clear stored session data.
 */
function clearSession() {
	try {
		sessionStorage.removeItem(STORAGE_KEY);
	} catch (e) {
		// Ignore.
	}
}

/**
 * Append a message to the stored session.
 *
 * @param {Object} message { role, content, timestamp }
 */
function addMessage(message) {
	var storedSession = loadSession();
	if (!storedSession) {
		storedSession = { sessionId: '', messages: [] };
	}
	storedSession.messages.push(message);
	saveSession(storedSession);
}

module.exports = {
	saveSession: saveSession,
	loadSession: loadSession,
	clearSession: clearSession,
	addMessage: addMessage
};
