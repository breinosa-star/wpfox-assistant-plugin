'use strict';

/**
 * Wire up textarea + send button to call onSubmit( text ) on submit.
 *
 * @param {Element}  inputEl
 * @param {Element}  sendBtn
 * @param {Function} onSubmit  Called with trimmed non-empty string.
 */
function initMessageInput( inputEl, sendBtn, onSubmit ) {
	if ( ! inputEl || ! sendBtn ) return;

	function submit() {
		const text = inputEl.value.trim();
		if ( ! text ) return;
		inputEl.value = '';
		resetHeight( inputEl );
		onSubmit( text );
	}

	sendBtn.addEventListener( 'click', function ( e ) {
		e.preventDefault();
		submit();
	} );

	inputEl.addEventListener( 'keydown', function ( e ) {
		if ( e.key === 'Enter' && ! e.shiftKey ) {
			e.preventDefault();
			submit();
		}
	} );

	inputEl.addEventListener( 'input', function () {
		autoResize( inputEl );
	} );
}

function autoResize( inputEl ) {
	inputEl.style.height = 'auto';
	const max = 120;
	inputEl.style.height = Math.min( inputEl.scrollHeight, max ) + 'px';
}

function resetHeight( inputEl ) {
	inputEl.style.height = 'auto';
}

/**
 * Enable or disable the input + send button together.
 */
function setDisabled( inputEl, sendBtn, disabled ) {
	if ( inputEl ) inputEl.disabled = disabled;
	if ( sendBtn ) sendBtn.disabled = disabled;
}

module.exports = { initMessageInput, setDisabled };
