'use strict';

const api             = require( '../services/api' );
const session         = require( '../store/session' );
const MessageList     = require( './MessageList' );
const MessageInput    = require( './MessageInput' );
const TypingIndicator = require( './TypingIndicator' );
const { VoiceClient } = require( '../services/voice-client' );

const WARNING_MS = 60 * 1000;

/**
 * Chat window controller.
 *
 * @param {Element} widgetEl  Root widget element.
 * @param {Object}  config    GrayFoxConfig object.
 */
function ChatWindow( widgetEl, config ) {
	this.widgetEl   = widgetEl;
	this.config     = config;
	this.sessionId  = config.sessionId || '';
	this.isOpen     = false;
	this.isSending  = false;

	this.inactivityMs = ( config.inactivityMinutes || 5 ) * 60 * 1000;
	this.warningMs    = WARNING_MS;

	this._inactivityTimer = null;
	this._warningTimer    = null;
	this._warningMsgEl    = null;

	this.windowEl   = widgetEl.querySelector( '.grayfox-window' );
	this.messagesEl = widgetEl.querySelector( '.grayfox-messages' );
	this.titleEl    = widgetEl.querySelector( '.grayfox-title' );

	const mode = widgetEl.getAttribute( 'data-mode' );
	if ( mode === 'embed' ) {
		this.inputEl = widgetEl.querySelector( '.grayfox-input-embed' );
		this.sendBtn = widgetEl.querySelector( '.grayfox-send-embed' );
	} else {
		this.inputEl = widgetEl.querySelector( '#grayfox-input' );
		this.sendBtn = widgetEl.querySelector( '#grayfox-send' );
	}

	this.closeBtn      = widgetEl.querySelector( '.grayfox-close' );
	this.micBtn        = widgetEl.querySelector( '#grayfox-mic' );
	this.voiceOverlay  = widgetEl.querySelector( '#grayfox-voice-overlay' );
	this.voiceStatusEl = widgetEl.querySelector( '.grayfox-voice-status' );
	this.voiceEndBtn   = widgetEl.querySelector( '.grayfox-voice-end' );

	this._voiceClient = null;
}

ChatWindow.prototype.initialize = function () {
	const self         = this;
	const primaryColor = this.config.primaryColor || '#6366f1';

	document.documentElement.style.setProperty( '--grayfox-primary', primaryColor );

	if ( this.titleEl ) {
		const title = this.widgetEl.getAttribute( 'data-title' ) || this.config.title || 'Chat with us';
		this.titleEl.textContent = title;
	}

	const mode = this.widgetEl.getAttribute( 'data-mode' );
	if ( mode === 'embed' ) {
		const embedColor = this.widgetEl.getAttribute( 'data-color' ) || primaryColor;
		document.documentElement.style.setProperty( '--grayfox-primary', embedColor );
	}

	MessageInput.initMessageInput( this.inputEl, this.sendBtn, function ( text ) {
		self.sendMessage( text );
	} );

	if ( this.closeBtn ) {
		this.closeBtn.addEventListener( 'click', function () {
			self.close();
		} );
	}

	document.addEventListener( 'keydown', function ( e ) {
		if ( e.key === 'Escape' && self.isOpen ) self.close();
	} );

	if ( this.micBtn ) {
		this.micBtn.addEventListener( 'click', function () {
			self._toggleVoice();
		} );
	}

	if ( this.voiceEndBtn ) {
		this.voiceEndBtn.addEventListener( 'click', function () {
			if ( self._voiceClient ) self._voiceClient.disconnect( false );
		} );
	}

	this.restoreSession();

	if ( this.sessionId ) this.resetInactivityTimer();
};

ChatWindow.prototype.open = function () {
	if ( ! this.windowEl ) return;
	this.windowEl.style.display = 'flex';
	this.isOpen = true;
	this.widgetEl.classList.add( 'grayfox-open' );

	if ( this.messagesEl && this.messagesEl.children.length === 0 && this.config.welcomeMessage ) {
		MessageList.appendMessage( this.messagesEl, 'assistant', this.config.welcomeMessage );
	}

	if ( this.inputEl ) this.inputEl.focus();
};

ChatWindow.prototype.close = function () {
	if ( ! this.windowEl ) return;
	this.windowEl.style.display = 'none';
	this.isOpen = false;
	this.widgetEl.classList.remove( 'grayfox-open' );

	if ( this._voiceClient ) {
		this._voiceClient.stop();
		this._voiceClient = null;
	}
};

ChatWindow.prototype.toggle = function () {
	this.isOpen ? this.close() : this.open();
};

ChatWindow.prototype.openInVoiceMode = function () {
	if ( ! this.isOpen ) this.open();
	if ( ! this._voiceClient || this._voiceClient.getState() === 'idle' || this._voiceClient.getState() === 'ended' ) {
		this._startVoice();
	}
};

ChatWindow.prototype.sendMessage = function ( text ) {
	if ( this.isSending || ! text ) return;

	const self = this;
	this.isSending = true;

	MessageList.appendMessage( this.messagesEl, 'user', text );
	this.resetInactivityTimer();
	session.addMessage( { role: 'user', content: text, timestamp: Date.now() } );

	MessageInput.setDisabled( this.inputEl, this.sendBtn, true );
	TypingIndicator.showTypingIndicator( this.messagesEl );

	api.sendMessage( this.config.ajaxUrl, this.config.nonce, this.sessionId, text )
		.then( function ( result ) {
			if ( result.sessionId ) {
				self.sessionId = result.sessionId;
				const current  = session.loadSession() || { sessionId: '', messages: [] };
				current.sessionId = result.sessionId;
				session.saveSession( current );
			}
			self.animateResponse( result.response );
		} )
		.catch( function ( err ) {
			TypingIndicator.hideTypingIndicator( self.messagesEl );

			if ( err && err.security === 'blocked' ) {
				const msg = err.message || 'This session has been disconnected due to policy violations.';
				MessageList.appendMessage( self.messagesEl, 'assistant', msg, 'error' );
				self.isSending = false;
				MessageInput.setDisabled( self.inputEl, self.sendBtn, true );
				if ( self.inputEl ) self.inputEl.placeholder = 'Chat disabled.';
				return;
			}

			if ( err && err.security === 'warning' ) {
				const remaining  = Math.max( 0, 3 - ( err.strikes || 1 ) );
				const warningMsg = ( err.message || 'Message not allowed.' ) +
					( remaining > 0 ? ' (' + remaining + ' warning' + ( remaining !== 1 ? 's' : '' ) + ' remaining)' : '' );
				MessageList.appendMessage( self.messagesEl, 'assistant', warningMsg, 'error' );
				self.isSending = false;

				const retryAfter = err.retryAfter || 0;
				if ( retryAfter > 0 ) {
					MessageInput.setDisabled( self.inputEl, self.sendBtn, true );
					self.startThrottleCountdown( retryAfter );
				} else {
					MessageInput.setDisabled( self.inputEl, self.sendBtn, false );
				}
				return;
			}

			let errMsg = 'Sorry, something went wrong. Please try again.';
			if ( err && err.status === 403 ) {
				errMsg = 'Session expired. Please refresh and try again.';
			} else if ( err && err.status === 503 ) {
				errMsg = 'The AI assistant is not configured. Please contact the site administrator.';
			} else if ( err && err.status === 429 && err.message && ! err.security ) {
				errMsg = err.message;
			}

			MessageList.appendMessage( self.messagesEl, 'assistant', errMsg, 'error' );
			self.isSending = false;
			MessageInput.setDisabled( self.inputEl, self.sendBtn, false );
		} );
};

ChatWindow.prototype.animateResponse = function ( text ) {
	const self = this;
	TypingIndicator.hideTypingIndicator( this.messagesEl );

	if ( ! text ) {
		self.isSending = false;
		MessageInput.setDisabled( self.inputEl, self.sendBtn, false );
		return;
	}

	// Word limits per bubble position: 1st=20, 2nd=15, 3rd+=23.
	const LIMITS        = [ 20, 15, 23 ];
	const BUBBLE_DELAY  = 700;
	const words         = text.split( /\s+/ ).filter( Boolean );
	const chunks        = [];

	let wi = 0;
	while ( wi < words.length ) {
		const limit = LIMITS[ Math.min( chunks.length, LIMITS.length - 1 ) ];
		chunks.push( words.slice( wi, wi + limit ).join( ' ' ) );
		wi += limit;
	}

	function showChunk( index ) {
		if ( index >= chunks.length ) {
			session.addMessage( { role: 'assistant', content: text, timestamp: Date.now() } );
			self.isSending = false;
			MessageInput.setDisabled( self.inputEl, self.sendBtn, false );
			self.resetInactivityTimer();
			if ( self.inputEl ) self.inputEl.focus();
			return;
		}

		MessageList.appendMessage( self.messagesEl, 'assistant', chunks[ index ] );
		MessageList.scrollToBottom( self.messagesEl );

		if ( index + 1 < chunks.length ) {
			TypingIndicator.showTypingIndicator( self.messagesEl );
			MessageList.scrollToBottom( self.messagesEl );
			setTimeout( function () {
				TypingIndicator.hideTypingIndicator( self.messagesEl );
				showChunk( index + 1 );
			}, BUBBLE_DELAY );
		} else {
			showChunk( index + 1 );
		}
	}

	showChunk( 0 );
};

ChatWindow.prototype.restoreSession = function () {
	const saved = session.loadSession();
	if ( ! saved || ! saved.sessionId ) return;
	this.sessionId = saved.sessionId;
	if ( saved.messages && saved.messages.length > 0 ) {
		this.renderLocalMessages( saved.messages );
	}
};

ChatWindow.prototype.renderLocalMessages = function ( messages ) {
	MessageList.clearMessages( this.messagesEl );
	const self = this;
	messages.forEach( function ( msg ) {
		MessageList.appendMessage( self.messagesEl, msg.role, msg.content );
	} );
};

ChatWindow.prototype.resetInactivityTimer = function () {
	const self = this;
	clearTimeout( this._inactivityTimer );
	clearTimeout( this._warningTimer );

	if ( this._warningMsgEl && this._warningMsgEl.parentNode ) {
		this._warningMsgEl.parentNode.removeChild( this._warningMsgEl );
		this._warningMsgEl = null;
	}

	this._inactivityTimer = setTimeout( function () {
		self.showInactivityWarning();
	}, this.inactivityMs );
};

ChatWindow.prototype.showInactivityWarning = function () {
	const self = this;
	this._warningMsgEl = MessageList.appendMessage(
		this.messagesEl,
		'assistant',
		'Still there? This conversation will close in 60 seconds due to inactivity.',
		'warning'
	);
	this._warningTimer = setTimeout( function () {
		self.endSession();
	}, this.warningMs );
};

ChatWindow.prototype.endSession = function () {
	clearTimeout( this._inactivityTimer );
	clearTimeout( this._warningTimer );
	this._warningMsgEl = null;

	session.clearSession();
	this.sessionId = '';

	MessageList.clearMessages( this.messagesEl );
	if ( this.config.welcomeMessage ) {
		MessageList.appendMessage( this.messagesEl, 'assistant', this.config.welcomeMessage );
	}

	this.isSending = false;
	MessageInput.setDisabled( this.inputEl, this.sendBtn, false );
	if ( this.inputEl ) this.inputEl.focus();
};

ChatWindow.prototype.startThrottleCountdown = function ( seconds ) {
	const self        = this;
	let   remaining   = seconds;
	const placeholder = this.inputEl ? this.inputEl.placeholder : '';

	function tick() {
		if ( ! self.inputEl ) return;
		self.inputEl.placeholder = 'Please wait ' + remaining + 's…';
		if ( remaining <= 0 ) {
			self.inputEl.placeholder = placeholder;
			MessageInput.setDisabled( self.inputEl, self.sendBtn, false );
			if ( self.inputEl ) self.inputEl.focus();
			return;
		}
		remaining--;
		setTimeout( tick, 1000 );
	}

	tick();
};

// ─── Voice ───────────────────────────────────────────────────────────────────

ChatWindow.prototype._toggleVoice = function () {
	if ( this._voiceClient && this._voiceClient.getState() === 'active' ) {
		this._voiceClient.stop();
	} else {
		this._startVoice();
	}
};

ChatWindow.prototype._startVoice = function () {
	const self   = this;
	const config = this.config;

	this._voiceClient = new VoiceClient( {
		restUrl:    config.restUrl   || '',
		restNonce:  config.restNonce || '',
		onStateChange: function ( state ) {
			self._onVoiceStateChange( state );
		},
		onError: function ( message ) {
			MessageList.appendMessage( self.messagesEl, 'assistant', message, 'error' );
			self._onVoiceStateChange( 'idle' );
		},
		onAiSpeaking: function ( speaking ) {
			if ( self.voiceOverlay ) {
				if ( speaking ) {
					self.voiceOverlay.classList.remove( 'grayfox-voice-overlay--connecting' );
				}
				self.voiceOverlay.classList.toggle( 'grayfox-voice-overlay--speaking',  speaking );
				self.voiceOverlay.classList.toggle( 'grayfox-voice-overlay--listening', ! speaking );
			}
			const connectingIcon = self.voiceOverlay ? self.voiceOverlay.querySelector( '.grayfox-voice-icon--connecting' ) : null;
			const micIcon        = self.voiceOverlay ? self.voiceOverlay.querySelector( '.grayfox-voice-icon--mic' )        : null;
			const bars           = self.voiceOverlay ? self.voiceOverlay.querySelector( '.grayfox-voice-bars' )             : null;
			if ( connectingIcon ) connectingIcon.style.display = 'none';
			if ( micIcon ) micIcon.style.display = speaking ? 'none' : '';
			if ( bars )    bars.style.display    = speaking ? 'flex' : 'none';
			if ( self.voiceStatusEl ) {
				self.voiceStatusEl.textContent = speaking
					? ( config.voiceLabelSpeaking  || '' )
					: ( config.voiceLabelListening || '' );
			}
		},
	} );

	this._voiceClient.start();
};

ChatWindow.prototype._onVoiceStateChange = function ( state ) {
	const showOverlay = ( state === 'active' || state === 'connecting' || state === 'disconnecting' );

	// Show/hide the voice overlay and text UI.
	if ( this.voiceOverlay ) {
		this.voiceOverlay.style.display = showOverlay ? 'flex' : 'none';
		// --connecting stays on through 'active' until onAiSpeaking(true) clears it.
		if ( state === 'connecting' ) {
			this.voiceOverlay.classList.add( 'grayfox-voice-overlay--connecting' );
		}
		this.voiceOverlay.classList.toggle( 'grayfox-voice-overlay--disconnecting', state === 'disconnecting' );
		if ( state === 'disconnecting' || state === 'ended' || state === 'error' ) {
			this.voiceOverlay.classList.remove(
				'grayfox-voice-overlay--connecting',
				'grayfox-voice-overlay--speaking',
				'grayfox-voice-overlay--listening'
			);
		}
	}
	if ( this.messagesEl ) {
		this.messagesEl.style.display = showOverlay ? 'none' : '';
	}
	const inputArea = this.widgetEl.querySelector( '.grayfox-input-area' );
	if ( inputArea ) {
		inputArea.style.display = showOverlay ? 'none' : '';
	}

	// Disable end button and reset icons during disconnecting.
	if ( state === 'disconnecting' ) {
		if ( this.voiceEndBtn ) this.voiceEndBtn.disabled = true;
		const connectingIcon = this.voiceOverlay ? this.voiceOverlay.querySelector( '.grayfox-voice-icon--connecting' ) : null;
		const micIcon        = this.voiceOverlay ? this.voiceOverlay.querySelector( '.grayfox-voice-icon--mic' )        : null;
		const bars           = this.voiceOverlay ? this.voiceOverlay.querySelector( '.grayfox-voice-bars' )             : null;
		if ( connectingIcon ) connectingIcon.style.display = 'none';
		if ( micIcon ) micIcon.style.display = '';
		if ( bars )    bars.style.display    = 'none';
	} else if ( this.voiceEndBtn ) {
		this.voiceEndBtn.disabled = false;
	}

	// Update status label.
	if ( this.voiceStatusEl ) {
		if ( state === 'connecting' || state === 'active' ) {
			this.voiceStatusEl.textContent = this.config.voiceLabelConnecting || '';
		}
		if ( state === 'disconnecting' ) {
			this.voiceStatusEl.textContent = this.config.voiceLabelDisconnecting || '';
		}
		if ( state === 'ended' || state === 'error' ) {
			this.voiceStatusEl.textContent = '';
		}
	}

	// Intentional end → close the whole widget.
	if ( state === 'ended' ) {
		this._voiceClient = null;
		this.close();
	}

	// Error → stay open in text mode so the user can see what went wrong.
	if ( state === 'error' ) {
		this._voiceClient = null;
	}
};

ChatWindow.prototype.showError = function ( message ) {
	if ( this.messagesEl ) {
		MessageList.appendMessage( this.messagesEl, 'assistant', message, 'error' );
	}
	if ( this.inputEl && this.sendBtn ) {
		MessageInput.setDisabled( this.inputEl, this.sendBtn, true );
	}
};

module.exports = ChatWindow;
