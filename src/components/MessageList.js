/**
 * MessageList component — scrollable message list for GrayFox.
 */

/**
 * Minimal markdown-to-HTML parser for assistant messages.
 *
 * Supported syntax:
 *   **bold**, *italic*, `inline code`
 *   - bullet lists (- or *)
 *   1. numbered lists
 *   blank-line paragraph breaks
 *
 * Intentionally excludes: headers (##), code blocks (```), images, HTML pass-through.
 *
 * @param {string} text Raw markdown text.
 * @returns {string} Safe HTML string.
 */
function parseMarkdown(text) {
	// Normalize line endings.
	var lines = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n');

	var html = '';
	var i = 0;

	while (i < lines.length) {
		var line = lines[i];

		// Skip blank lines between blocks.
		if (line.trim() === '') {
			i++;
			continue;
		}

		// Numbered list block.
		if (/^\s*\d+\.\s/.test(line)) {
			html += '<ol>';
			while (i < lines.length && /^\s*\d+\.\s/.test(lines[i])) {
				html += '<li>' + inlineMarkdown(lines[i].replace(/^\s*\d+\.\s/, '')) + '</li>';
				i++;
			}
			html += '</ol>';
			continue;
		}

		// Bullet list block.
		if (/^\s*[-*]\s/.test(line)) {
			html += '<ul>';
			while (i < lines.length && /^\s*[-*]\s/.test(lines[i])) {
				html += '<li>' + inlineMarkdown(lines[i].replace(/^\s*[-*]\s/, '')) + '</li>';
				i++;
			}
			html += '</ul>';
			continue;
		}

		// Paragraph: collect consecutive non-blank, non-list lines.
		var paraLines = [];
		while (
			i < lines.length &&
			lines[i].trim() !== '' &&
			!/^\s*[-*]\s/.test(lines[i]) &&
			!/^\s*\d+\.\s/.test(lines[i])
		) {
			paraLines.push(lines[i]);
			i++;
		}
		if (paraLines.length > 0) {
			html += '<p>' + inlineMarkdown(paraLines.join(' ')) + '</p>';
		}
	}

	return html;
}

/**
 * Process inline markdown within a single line of text.
 * Escapes HTML first, then applies inline formatting.
 *
 * @param {string} text
 * @returns {string}
 */
function inlineMarkdown(text) {
	// Escape HTML entities first to prevent XSS.
	text = escapeHTML(text);

	// Inline code (before bold/italic to avoid nested interference).
	text = text.replace(/`([^`]+)`/g, '<code>$1</code>');

	// Bold: **text** or __text__
	text = text.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
	text = text.replace(/__([^_]+)__/g, '<strong>$1</strong>');

	// Italic: *text* or _text_ (single, not inside words)
	text = text.replace(/(?<![*_])\*(?![*\s])([^*]+?)(?<!\s)\*(?![*_])/g, '<em>$1</em>');
	text = text.replace(/(?<![*_])_(?![_\s])([^_]+?)(?<!\s)_(?![*_])/g, '<em>$1</em>');

	return text;
}

/**
 * Escape HTML special characters to prevent XSS.
 *
 * @param {string} str
 * @returns {string}
 */
function escapeHTML(str) {
	return str
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;')
		.replace(/'/g, '&#39;');
}

/**
 * Append a message to the messages container.
 *
 * Assistant messages are rendered as parsed markdown (innerHTML).
 * User messages are rendered as plain text (textContent) for safety.
 *
 * @param {HTMLElement} container The messages container element.
 * @param {string}      role      'user' or 'assistant'.
 * @param {string}      content   Message text content.
 * @param {string}      [status]  Optional status: 'sending', 'error'.
 * @returns {HTMLElement} The created message element.
 */
function appendMessage(container, role, content, status) {
	var messageEl = document.createElement('div');
	messageEl.className = 'grayfox-message grayfox-message--' + role;

	if (status) {
		messageEl.classList.add('grayfox-message--' + status);
	}

	var bubble = document.createElement('div');
	bubble.className = 'grayfox-bubble';

	if (role === 'assistant' && !status) {
		bubble.innerHTML = parseMarkdown(content);
	} else {
		bubble.textContent = content;
	}

	messageEl.appendChild(bubble);
	container.appendChild(messageEl);

	scrollToBottom(container);

	return messageEl;
}

/**
 * Update the content of an existing message element (used during streaming).
 *
 * @param {HTMLElement} messageEl The message element.
 * @param {string}      content   New content.
 */
function updateMessage(messageEl, content) {
	var bubble = messageEl.querySelector('.grayfox-bubble');
	if (!bubble) return;

	var role = messageEl.classList.contains('grayfox-message--assistant') ? 'assistant' : 'user';
	var isError = messageEl.classList.contains('grayfox-message--error');

	if (role === 'assistant' && !isError) {
		bubble.innerHTML = parseMarkdown(content);
	} else {
		bubble.textContent = content;
	}
}

/**
 * Scroll the container to the bottom.
 *
 * @param {HTMLElement} container The messages container.
 */
function scrollToBottom(container) {
	container.scrollTop = container.scrollHeight;
}

/**
 * Clear all messages from the container.
 *
 * @param {HTMLElement} container The messages container.
 */
function clearMessages(container) {
	container.innerHTML = '';
}

module.exports = {
	appendMessage: appendMessage,
	updateMessage: updateMessage,
	scrollToBottom: scrollToBottom,
	clearMessages: clearMessages
};
