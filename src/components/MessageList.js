'use strict';

function escapeHtml( str ) {
	return str
		.replace( /&/g,  '&amp;' )
		.replace( /</g,  '&lt;' )
		.replace( />/g,  '&gt;' )
		.replace( /"/g,  '&quot;' )
		.replace( /'/g,  '&#39;' );
}

function renderInline( text ) {
	text = escapeHtml( text );
	text = text.replace( /`([^`]+)`/g,           '<code>$1</code>' );
	text = text.replace( /\*\*([^*]+)\*\*/g,      '<strong>$1</strong>' );
	text = text.replace( /__([^_]+)__/g,           '<strong>$1</strong>' );
	text = text.replace( /(?<![*_])\*(?![*\s])([^*]+?)(?<!\s)\*(?![*_])/g, '<em>$1</em>' );
	text = text.replace( /(?<![*_])_(?![_\s])([^_]+?)(?<!\s)_(?![*_])/g,   '<em>$1</em>' );
	return text;
}

/**
 * Parse a plain-text markdown-like string into HTML.
 */
function parseMarkdown( text ) {
	const lines = text.replace( /\r\n/g, '\n' ).replace( /\r/g, '\n' ).split( '\n' );
	let html = '';
	let i    = 0;

	while ( i < lines.length ) {
		const line = lines[ i ];

		if ( line.trim() === '' ) { i++; continue; }

		// Ordered list.
		if ( /^\s*\d+\.\s/.test( line ) ) {
			html += '<ol>';
			while ( i < lines.length && /^\s*\d+\.\s/.test( lines[ i ] ) ) {
				html += '<li>' + renderInline( lines[ i ].replace( /^\s*\d+\.\s/, '' ) ) + '</li>';
				i++;
			}
			html += '</ol>';
			continue;
		}

		// Unordered list.
		if ( /^\s*[-*]\s/.test( line ) ) {
			html += '<ul>';
			while ( i < lines.length && /^\s*[-*]\s/.test( lines[ i ] ) ) {
				html += '<li>' + renderInline( lines[ i ].replace( /^\s*[-*]\s/, '' ) ) + '</li>';
				i++;
			}
			html += '</ul>';
			continue;
		}

		// Paragraph — collect consecutive non-blank, non-list lines.
		const paragraph = [];
		while (
			i < lines.length &&
			lines[ i ].trim() !== '' &&
			! /^\s*[-*]\s/.test( lines[ i ] ) &&
			! /^\s*\d+\.\s/.test( lines[ i ] )
		) {
			paragraph.push( lines[ i ] );
			i++;
		}
		if ( paragraph.length ) {
			html += '<p>' + renderInline( paragraph.join( ' ' ) ) + '</p>';
		}
	}

	return html;
}

function scrollToBottom( containerEl ) {
	containerEl.scrollTop = containerEl.scrollHeight;
}

/**
 * Append a message bubble to the container.
 *
 * @param {Element} containerEl
 * @param {string}  role        'user' | 'assistant'
 * @param {string}  text
 * @param {string}  [modifier]  CSS modifier class (e.g. 'error', 'warning')
 * @returns {Element} The message element.
 */
function appendMessage( containerEl, role, text, modifier ) {
	const msg    = document.createElement( 'div' );
	msg.className = 'grayfox-message grayfox-message--' + role;
	if ( modifier ) msg.classList.add( 'grayfox-message--' + modifier );

	const bubble = document.createElement( 'div' );
	bubble.className = 'grayfox-bubble';

	if ( role === 'assistant' && ! modifier ) {
		bubble.innerHTML = parseMarkdown( text );
	} else {
		bubble.textContent = text;
	}

	msg.appendChild( bubble );
	containerEl.appendChild( msg );
	scrollToBottom( containerEl );
	return msg;
}

/**
 * Update the text/HTML of an existing message element.
 */
function updateMessage( msgEl, text ) {
	const bubble = msgEl.querySelector( '.grayfox-bubble' );
	if ( ! bubble ) return;
	const isAssistant = msgEl.classList.contains( 'grayfox-message--assistant' );
	const isError     = msgEl.classList.contains( 'grayfox-message--error' );
	if ( isAssistant && ! isError ) {
		bubble.innerHTML = parseMarkdown( text );
	} else {
		bubble.textContent = text;
	}
}

function clearMessages( containerEl ) {
	containerEl.innerHTML = '';
}

module.exports = { appendMessage, updateMessage, scrollToBottom, clearMessages };
