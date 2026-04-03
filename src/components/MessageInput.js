/**
 * MessageInput component — text input + send button logic for GrayFox.
 */

/**
 * Initialize the message input behavior.
 *
 * @param {HTMLTextAreaElement} inputEl   The textarea element.
 * @param {HTMLButtonElement}   sendBtn   The send button element.
 * @param {Function}            onSend    Callback invoked with the trimmed message text.
 */
function initMessageInput(inputEl, sendBtn, onSend) {
	if (!inputEl || !sendBtn) return;

	/**
	 * Handle sending: read input, clear it, call callback.
	 */
	function handleSend() {
		var text = inputEl.value.trim();
		if (!text) return;

		inputEl.value = '';
		resetHeight(inputEl);
		onSend(text);
	}

	// Click handler.
	sendBtn.addEventListener('click', function (e) {
		e.preventDefault();
		handleSend();
	});

	// Enter to send (Shift+Enter for newline).
	inputEl.addEventListener('keydown', function (e) {
		if (e.key === 'Enter' && !e.shiftKey) {
			e.preventDefault();
			handleSend();
		}
	});

	// Auto-resize textarea.
	inputEl.addEventListener('input', function () {
		autoResize(inputEl);
	});
}

/**
 * Auto-resize textarea to fit content (up to a max height).
 *
 * @param {HTMLTextAreaElement} el
 */
function autoResize(el) {
	el.style.height = 'auto';
	var maxHeight = 120;
	el.style.height = Math.min(el.scrollHeight, maxHeight) + 'px';
}

/**
 * Reset textarea height to default.
 *
 * @param {HTMLTextAreaElement} el
 */
function resetHeight(el) {
	el.style.height = 'auto';
}

/**
 * Disable or enable the input.
 *
 * @param {HTMLTextAreaElement} inputEl
 * @param {HTMLButtonElement}   sendBtn
 * @param {boolean}             disabled
 */
function setDisabled(inputEl, sendBtn, disabled) {
	if (inputEl) inputEl.disabled = disabled;
	if (sendBtn) sendBtn.disabled = disabled;
}

module.exports = {
	initMessageInput: initMessageInput,
	setDisabled: setDisabled
};
