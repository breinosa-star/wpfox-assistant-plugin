'use strict';

function createTypingEl() {
	const msg = document.createElement( 'div' );
	msg.className = 'grayfox-message grayfox-message--assistant grayfox-typing-indicator';

	const dots = document.createElement( 'div' );
	dots.className = 'grayfox-typing-dots';

	for ( let i = 0; i < 3; i++ ) {
		const dot = document.createElement( 'span' );
		dot.className = 'grayfox-dot';
		dots.appendChild( dot );
	}

	msg.appendChild( dots );
	return msg;
}

/**
 * Append a typing indicator to containerEl and return the element.
 */
function showTypingIndicator( containerEl ) {
	hideTypingIndicator( containerEl );
	const el = createTypingEl();
	containerEl.appendChild( el );
	containerEl.scrollTop = containerEl.scrollHeight;
	return el;
}

function hideTypingIndicator( containerEl ) {
	const existing = containerEl.querySelector( '.grayfox-typing-indicator' );
	if ( existing ) existing.parentNode.removeChild( existing );
}

module.exports = { showTypingIndicator, hideTypingIndicator };
