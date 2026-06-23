'use strict';

const ChatWindow  = require( './components/ChatWindow' );
const MessageList = require( './components/MessageList' );

function init() {
	const config = window.GrayFoxConfig;
	if ( ! config ) return;

	// Floating widget.
	const widgetEl = document.getElementById( 'grayfox-chat-widget' );
	if ( widgetEl ) {
		const widget    = new ChatWindow( widgetEl, config );
		widget.initialize();

		const triggerEl = document.getElementById( 'grayfox-chat-trigger' );
		if ( triggerEl ) {
			triggerEl.addEventListener( 'click', function () {
				widget.toggle();

				const iconChat  = triggerEl.querySelector( '.grayfox-icon-chat' );
				const iconClose = triggerEl.querySelector( '.grayfox-icon-close' );
				if ( iconChat && iconClose ) {
					if ( widget.isOpen ) {
						iconChat.style.display  = 'none';
						iconClose.style.display = 'block';
					} else {
						iconChat.style.display  = 'block';
						iconClose.style.display = 'none';
					}
				}
			} );
		}

		const voiceTriggerEl = document.getElementById( 'grayfox-voice-trigger' );
		if ( voiceTriggerEl ) {
			voiceTriggerEl.addEventListener( 'click', function () {
				widget.openInVoiceMode();
			} );
		}

		const closeBtn = widgetEl.querySelector( '.grayfox-close' );
		if ( closeBtn ) {
			closeBtn.addEventListener( 'click', function () {
				const iconChat  = triggerEl ? triggerEl.querySelector( '.grayfox-icon-chat' )  : null;
				const iconClose = triggerEl ? triggerEl.querySelector( '.grayfox-icon-close' ) : null;
				if ( iconChat && iconClose ) {
					iconChat.style.display  = 'block';
					iconClose.style.display = 'none';
				}
			} );
		}
	}

	// Inline embed widgets.
	const embeds = document.querySelectorAll( '.grayfox-embed' );
	for ( let i = 0; i < embeds.length; i++ ) {
		const embedEl  = embeds[ i ];
		const embedCfg = Object.assign( {}, config );

		if ( embedEl.getAttribute( 'data-color' ) ) {
			embedCfg.primaryColor = embedEl.getAttribute( 'data-color' );
		}
		if ( embedEl.getAttribute( 'data-title' ) ) {
			embedCfg.title = embedEl.getAttribute( 'data-title' );
		}

		const embedWidget = new ChatWindow( embedEl, embedCfg );
		embedWidget.initialize();

		if ( embedWidget.messagesEl && embedWidget.messagesEl.children.length === 0 && embedCfg.welcomeMessage ) {
			const ML = MessageList;
			ML.appendMessage( embedWidget.messagesEl, 'assistant', embedCfg.welcomeMessage );
		}
	}
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', init );
} else {
	init();
}
