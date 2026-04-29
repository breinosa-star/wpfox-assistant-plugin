'use strict';

const SESSION_KEY = 'grayfox_session';

function saveSession( session ) {
	try {
		sessionStorage.setItem( SESSION_KEY, JSON.stringify( session ) );
	} catch { /* storage unavailable */ }
}

function loadSession() {
	try {
		const raw = sessionStorage.getItem( SESSION_KEY );
		if ( ! raw ) return null;
		const session = JSON.parse( raw );
		return session && session.sessionId ? session : null;
	} catch {
		return null;
	}
}

function clearSession() {
	try {
		sessionStorage.removeItem( SESSION_KEY );
	} catch { /* ignore */ }
}

function addMessage( message ) {
	let session = loadSession();
	if ( ! session ) session = { sessionId: '', messages: [] };
	session.messages.push( message );
	saveSession( session );
}

module.exports = { saveSession, loadSession, clearSession, addMessage };
