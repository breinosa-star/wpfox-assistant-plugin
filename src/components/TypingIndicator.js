/**
 * Typing indicator component — animated dots for GrayFox.
 */

/**
 * Create a typing indicator DOM element.
 *
 * @returns {HTMLElement}
 */
function createTypingIndicator() {
	var wrapper = document.createElement('div');
	wrapper.className = 'grayfox-message grayfox-message--assistant grayfox-typing-indicator';

	var dots = document.createElement('div');
	dots.className = 'grayfox-typing-dots';

	for (var i = 0; i < 3; i++) {
		var dot = document.createElement('span');
		dot.className = 'grayfox-dot';
		dots.appendChild(dot);
	}

	wrapper.appendChild(dots);
	return wrapper;
}

/**
 * Show the typing indicator in a messages container.
 *
 * @param {HTMLElement} container Messages container.
 * @returns {HTMLElement} The indicator element (for later removal).
 */
function showTypingIndicator(container) {
	// Remove any existing indicator first.
	hideTypingIndicator(container);

	var indicator = createTypingIndicator();
	container.appendChild(indicator);
	container.scrollTop = container.scrollHeight;
	return indicator;
}

/**
 * Hide (remove) the typing indicator from a container.
 *
 * @param {HTMLElement} container Messages container.
 */
function hideTypingIndicator(container) {
	var existing = container.querySelector('.grayfox-typing-indicator');
	if (existing) {
		existing.parentNode.removeChild(existing);
	}
}

module.exports = {
	showTypingIndicator: showTypingIndicator,
	hideTypingIndicator: hideTypingIndicator
};
