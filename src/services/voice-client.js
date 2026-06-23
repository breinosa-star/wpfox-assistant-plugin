'use strict';

/**
 * VoiceClient — WebRTC voice agent for the GrayFox chat widget.
 *
 * Flow:
 *  1. Fetch an ephemeral token from WordPress (POST /grayfox/v1/voice/session)
 *  2. Open a WebRTC peer connection directly to the OpenAI Realtime API
 *  3. Audio streams browser ↔ OpenAI — WordPress is NOT in the audio path
 *  4. OpenAI triggers function calls (search_kb, capture_lead) via the data channel
 *  5. Function calls are dispatched to WP REST endpoints and results fed back in
 *  6. Session ends after max_duration_min or when stop() is called
 *
 * States: idle → connecting → active → ended
 *                                    ↘ error
 *
 * @param {Object}   config
 * @param {string}   config.restUrl         WP REST base URL (e.g. /wp-json)
 * @param {string}   config.restNonce       X-WP-Nonce for WP REST API authentication
 * @param {Function} [config.onStateChange] Called with state string on every transition
 * @param {Function} [config.onError]       Called with a human-readable error message
 */
function VoiceClient( config ) {
	this.restUrl       = config.restUrl;
	this.restNonce     = config.restNonce;
	this.onStateChange = config.onStateChange || function () {};
	this.onError       = config.onError       || function () {};
	this.onAiSpeaking  = config.onAiSpeaking  || function () {};

	this._pc             = null;  // RTCPeerConnection
	this._stream         = null;  // local MediaStream (microphone)
	this._audioEl        = null;  // <audio> element for remote playback
	this._dc             = null;  // RTCDataChannel 'oai-events'
	this._sessionData    = null;  // response from /voice/session
	this._durationTimer  = null;
	this._hangUpTimer    = null;  // safety fallback for hang_up
	this._hangUpPending  = false; // true after AI calls hang_up
	this._audioCtx       = null;  // AudioContext for remote volume monitoring
	this._ringCtx        = null;  // AudioContext for connecting ring tone
	this._ringTimer      = null;  // setTimeout handle for ring repeat
	this._transcript     = [];    // collected {role, content} messages
	this._greetingSent   = false; // response.create sent after session.updated
	this._micUnlocked    = false; // mic unmuted after first AI utterance ends
	this._state          = 'idle';
}

// ─── Public API ───────────────────────────────────────────────────────────────

/**
 * Start a voice session.
 * Requests microphone access, fetches an ephemeral token, and opens WebRTC.
 */
VoiceClient.prototype.start = async function () {
	if ( this._state !== 'idle' ) return;

	if ( ! this._isSupported() ) {
		this._fail( 'Your browser does not support WebRTC. Please try Chrome or Firefox.' );
		return;
	}

	this._setState( 'connecting' );
	this._startConnectingRing();

	try {
		// 1. Microphone access — muted until the opening greeting finishes.
		this._stream = await navigator.mediaDevices.getUserMedia( {
			audio: {
				echoCancellation: true,
				noiseSuppression: true,
				autoGainControl:  true,
			},
		} );
		this.setMuted( true );
	} catch ( err ) {
		if ( err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError' ) {
			this._fail( 'Microphone access was denied. Please allow microphone access and try again.' );
		} else {
			this._fail( 'Could not access microphone: ' + err.message );
		}
		return;
	}

	try {
		// 2. Ephemeral token from WordPress.
		const res = await fetch( this.restUrl + '/voice/session', {
			method:  'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce':   this.restNonce,
			},
		} );
		const data = await res.json();
		if ( ! res.ok ) {
			this._fail( data.message || 'Failed to start voice session.' );
			this._releaseStream();
			return;
		}
		this._sessionData = data;
	} catch ( err ) {
		this._fail( 'Network error while starting voice session.' );
		this._releaseStream();
		return;
	}

	try {
		// 3. WebRTC connection to OpenAI Realtime API.
		await this._openPeerConnection();
	} catch ( err ) {
		this._fail( 'Failed to connect to voice service: ' + err.message );
		this._releaseStream();
		return;
	}

	this._setState( 'active' );

	// 4. Auto-end after max_duration_min.
	const maxMs = ( this._sessionData.max_duration_min || 5 ) * 60 * 1000;
	this._durationTimer = setTimeout( () => this.stop(), maxMs );
};

/**
 * Begin the disconnection sequence: mute the mic, signal the UI, then
 * wait for silence before calling stop(). For user-initiated disconnects
 * (no farewell audio) a short fixed delay is used instead.
 *
 * @param {boolean} waitForSilence  true = audio-monitor-driven, false = 1.5 s fixed
 */
VoiceClient.prototype.disconnect = function ( waitForSilence ) {
	if ( this._state !== 'active' ) {
		this.stop();
		return;
	}

	this.setMuted( true );
	this._setState( 'disconnecting' );

	if ( waitForSilence ) {
		this._hangUpPending = true;
		this._hangUpTimer   = setTimeout( () => this.stop(), 6000 );
	} else {
		this._hangUpTimer = setTimeout( () => this.stop(), 1500 );
	}
};

/**
 * End the voice session and release all resources.
 */
VoiceClient.prototype.stop = function () {
	if ( this._state === 'idle' || this._state === 'ended' ) return;

	clearTimeout( this._durationTimer );
	clearTimeout( this._hangUpTimer );
	this._durationTimer  = null;
	this._hangUpTimer    = null;
	this._hangUpPending  = false;
	this._greetingSent   = false;
	this._micUnlocked    = false;
	this._stopConnectingRing();
	this._stopAudioMonitor();
	this._saveTranscript();

	if ( this._dc ) {
		this._dc.close();
		this._dc = null;
	}
	if ( this._pc ) {
		this._pc.close();
		this._pc = null;
	}
	if ( this._audioEl ) {
		this._audioEl.srcObject = null;
		this._audioEl.remove();
		this._audioEl = null;
	}
	this._releaseStream();
	this._sessionData = null;
	this._playDisconnectSound();
	this._setState( 'ended' );
};

/**
 * Mute or unmute the local microphone track.
 *
 * @param {boolean} muted
 */
VoiceClient.prototype.setMuted = function ( muted ) {
	if ( ! this._stream ) return;
	this._stream.getAudioTracks().forEach( function ( track ) {
		track.enabled = ! muted;
	} );
};

/**
 * Return the current state string.
 *
 * @return {string}
 */
VoiceClient.prototype.getState = function () {
	return this._state;
};

// ─── Private ─────────────────────────────────────────────────────────────────

/**
 * Open the RTCPeerConnection, add tracks, wire the data channel, negotiate SDP.
 */
VoiceClient.prototype._openPeerConnection = async function () {
	const self  = this;
	const model = this._sessionData.model || 'gpt-4o-realtime-preview';
	const token = this._sessionData.client_secret;

	this._pc = new RTCPeerConnection();

	// Remote audio → <audio> element.
	this._audioEl = document.createElement( 'audio' );
	this._audioEl.autoplay = true;
	document.body.appendChild( this._audioEl );

	this._pc.ontrack = function ( event ) {
		if ( event.streams && event.streams[ 0 ] ) {
			self._audioEl.srcObject = event.streams[ 0 ];
			self._startAudioMonitor( event.streams[ 0 ] );
		}
	};

	// Local microphone track.
	this._stream.getAudioTracks().forEach( function ( track ) {
		self._pc.addTrack( track, self._stream );
	} );

	// Data channel for JSON events (must be created before the offer).
	this._dc = this._pc.createDataChannel( 'oai-events' );
	this._dc.onmessage = function ( event ) {
		self._handleDataChannelMessage( event );
	};
	this._dc.onopen = function () {};

	// SDP offer.
	const offer = await this._pc.createOffer();
	await this._pc.setLocalDescription( offer );

	// Exchange SDP with OpenAI Realtime API.
	// Uses FormData — browser sets Content-Type + multipart boundary automatically.
	const fd = new FormData();
	fd.set( 'sdp', offer.sdp );
	fd.set( 'session', JSON.stringify( { type: 'realtime', model: model } ) );

	const sdpRes = await fetch(
		'https://api.openai.com/v1/realtime/calls',
		{
			method:  'POST',
			headers: { 'Authorization': 'Bearer ' + token },
			body:    fd,
		}
	);

	if ( ! sdpRes.ok ) {
		throw new Error( 'OpenAI SDP exchange failed: ' + sdpRes.status );
	}

	const answerSdp = await sdpRes.text();
	await this._pc.setRemoteDescription( { type: 'answer', sdp: answerSdp } );
};

/**
 * Handle an incoming data channel message from OpenAI.
 *
 * @param {MessageEvent} event
 */
VoiceClient.prototype._handleDataChannelMessage = function ( event ) {
	var msg;
	try {
		msg = JSON.parse( event.data );
	} catch {
		return;
	}

	// Session created — fire greeting immediately and configure transcription in parallel.
	if ( msg.type === 'session.created' ) {
		if ( ! this._greetingSent ) {
			this._greetingSent = true;
			if ( this._dc && this._dc.readyState === 'open' ) {
				this._dc.send( JSON.stringify( { type: 'response.create' } ) );
			}
		}
		if ( this._dc && this._dc.readyState === 'open' ) {
			this._dc.send( JSON.stringify( {
				type: 'session.update',
				session: {
					type: 'realtime',
					input_audio_transcription: { model: 'gpt-4o-transcribe' },
				},
			} ) );
		}
	}

	// OpenAI signals a fully-assembled function call with this event type.
	if ( msg.type === 'response.function_call_arguments.done' ) {
		this._dispatchFunctionCall( msg );
	}

	// Collect transcript: AI side.
	if ( msg.type === 'response.output_audio_transcript.done' && msg.transcript ) {
		this._transcript.push( { role: 'assistant', content: msg.transcript } );
	}

	// Collect transcript: user side (what the agent heard via Whisper).
	if ( msg.type === 'conversation.item.input_audio_transcription.completed' ) {
		console.log( '[GrayFox Voice] user transcript event:', msg );
		if ( msg.transcript ) {
			this._transcript.push( { role: 'user', content: msg.transcript } );
		}
	}


	// Speaking/Listening state is driven entirely by the audio volume monitor
	// in _startAudioMonitor. No data channel events are used for that — they
	// fire at generation time, not playback time, causing sync issues.
};

/**
 * Dispatch an OpenAI function call to the appropriate WP REST endpoint,
 * then feed the result back into the Realtime session.
 *
 * @param {Object} msg  Data channel event with name, arguments, call_id.
 */
VoiceClient.prototype._dispatchFunctionCall = async function ( msg ) {
	const self   = this;
	const name   = msg.name;
	const callId = msg.call_id;
	var   args;

	try {
		args = JSON.parse( msg.arguments || '{}' );
	} catch {
		args = {};
	}

	if ( name === 'hang_up' ) {
		self._sendFunctionOutput( callId, JSON.stringify( { ok: true } ) );
		self.disconnect( true );
		return;
	}

	var result;
	try {
		if ( name === 'search_kb' ) {
			result = await self._callKb( args.query || '' );
		} else if ( name === 'capture_lead' ) {
			result = await self._callLead( args );
		} else {
			result = JSON.stringify( { error: 'Unknown function: ' + name } );
		}
	} catch ( err ) {
		result = JSON.stringify( { error: err.message || 'Function call failed.' } );
	}

	self._sendFunctionResult( callId, result );
};

/**
 * POST to /grayfox/v1/voice/kb and return the raw JSON string result.
 *
 * @param  {string} query
 * @return {Promise<string>}
 */
VoiceClient.prototype._callKb = async function ( query ) {
	const res = await fetch( this.restUrl + '/voice/kb', {
		method:  'POST',
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce':   this.restNonce,
		},
		body: JSON.stringify( { query: query } ),
	} );
	const data = await res.json();
	return JSON.stringify( data );
};

/**
 * POST to /grayfox/v1/voice/lead and return a result string.
 *
 * @param  {Object} args  { name, email, interest }
 * @return {Promise<string>}
 */
VoiceClient.prototype._callLead = async function ( args ) {
	const res = await fetch( this.restUrl + '/voice/lead', {
		method:  'POST',
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce':   this.restNonce,
		},
		body: JSON.stringify( {
			name:            args.name     || '',
			email:           args.email    || '',
			phone:           args.phone    || '',
			interest:        args.interest || '',
			conversation_id: ( this._sessionData && this._sessionData.conversation_id ) || 0,
		} ),
	} );
	const data = await res.json();
	if ( data.success ) {
		return JSON.stringify( {
			success: true,
			message: 'Contact details saved. Do not ask for contact information again.',
		} );
	}
	return JSON.stringify( { error: data.message || 'Lead capture failed.' } );
};

/**
 * Send a function call result back into the OpenAI Realtime session via the
 * data channel, then trigger a new response turn.
 *
 * @param {string} callId  The call_id from the function_call event.
 * @param {string} output  JSON string result to return to the model.
 */
VoiceClient.prototype._sendFunctionOutput = function ( callId, output ) {
	if ( ! this._dc || this._dc.readyState !== 'open' ) return;
	this._dc.send( JSON.stringify( {
		type: 'conversation.item.create',
		item: { type: 'function_call_output', call_id: callId, output: output },
	} ) );
};

VoiceClient.prototype._sendFunctionResult = function ( callId, output ) {
	this._sendFunctionOutput( callId, output );
	// Tell OpenAI to continue generating a response after the tool result.
	if ( this._dc && this._dc.readyState === 'open' ) {
		this._dc.send( JSON.stringify( { type: 'response.create' } ) );
	}
};

/**
 * POST the collected transcript to WordPress after the session ends.
 * Fire-and-forget — we don't block stop() on it.
 */
VoiceClient.prototype._saveTranscript = function () {
	const conversationId = this._sessionData && this._sessionData.conversation_id;
	console.log( '[GrayFox Voice] _saveTranscript:', conversationId, this._transcript );
	if ( ! conversationId || this._transcript.length === 0 ) return;

	const messages = this._transcript.slice();
	this._transcript = [];

	fetch( this.restUrl + '/voice/transcript', {
		method:  'POST',
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce':   this.restNonce,
		},
		body: JSON.stringify( {
			conversation_id: conversationId,
			messages:        messages,
		} ),
	} ).catch( function () {} ); // best-effort, ignore errors
};

/**
 * Analyse the remote audio stream's volume in real time.
 * Switches onAiSpeaking(false) only when the track actually goes silent,
 * avoiding the mismatch between response.done (generation end) and audio end.
 *
 * @param {MediaStream} stream  The remote MediaStream from the RTC track.
 */
VoiceClient.prototype._startAudioMonitor = function ( stream ) {
	const self = this;
	try {
		this._audioCtx         = new AudioContext();
		const source           = this._audioCtx.createMediaStreamSource( stream );
		const analyser         = this._audioCtx.createAnalyser();
		analyser.fftSize       = 256;
		analyser.smoothingTimeConstant = 0.6;
		source.connect( analyser );

		const data = new Uint8Array( analyser.frequencyBinCount );
		let silenceTimer = null;
		let speaking     = false;

		function tick() {
			if ( ! self._audioCtx ) return;
			analyser.getByteFrequencyData( data );
			const volume = data.reduce( function ( s, v ) { return s + v; }, 0 ) / data.length;

			if ( volume > 8 ) {
				// Audio present — cancel silence timer, switch to Speaking if needed.
				if ( silenceTimer ) {
					clearTimeout( silenceTimer );
					silenceTimer = null;
				}
				if ( ! speaking ) {
					speaking = true;
					self._stopConnectingRing();
					self.onAiSpeaking( true );
				}
			} else {
				// Silence — debounce before switching to Listening.
				if ( speaking && ! silenceTimer ) {
					silenceTimer = setTimeout( function () {
						silenceTimer = null;
						speaking     = false;
						// Unlock mic after the opening greeting finishes.
						if ( ! self._micUnlocked ) {
							self._micUnlocked = true;
							self.setMuted( false );
						}
						self.onAiSpeaking( false );
						// If hang_up was called, stop now that audio has finished.
						if ( self._hangUpPending ) {
							clearTimeout( self._hangUpTimer );
							self._hangUpTimer  = null;
							self._hangUpPending = false;
							self.stop();
						}
					}, 400 );
				}
			}

			requestAnimationFrame( tick );
		}

		tick();
	} catch ( e ) {
		// AudioContext unavailable — fall back: response.created already set Speaking,
		// it will stay until the next user turn resets it.
	}
};

/**
 * Play a US-style telephone ring tone (dual 440+480 Hz) that repeats until stopped.
 * Called when entering the connecting state.
 */
VoiceClient.prototype._startConnectingRing = function () {
	const self = this;
	try {
		const ctx = new AudioContext();
		this._ringCtx = ctx;

		function ring() {
			if ( ! self._ringCtx ) return;
			var osc1  = ctx.createOscillator();
			var osc2  = ctx.createOscillator();
			var gain  = ctx.createGain();
			osc1.type = 'sine';
			osc2.type = 'sine';
			osc1.frequency.value = 440;
			osc2.frequency.value = 480;
			gain.gain.setValueAtTime( 0, ctx.currentTime );
			gain.gain.linearRampToValueAtTime( 0.09, ctx.currentTime + 0.04 );
			gain.gain.setValueAtTime( 0.09, ctx.currentTime + 1.4 );
			gain.gain.linearRampToValueAtTime( 0, ctx.currentTime + 1.5 );
			osc1.connect( gain );
			osc2.connect( gain );
			gain.connect( ctx.destination );
			osc1.start( ctx.currentTime );
			osc2.start( ctx.currentTime );
			osc1.stop( ctx.currentTime + 1.55 );
			osc2.stop( ctx.currentTime + 1.55 );
			// Ring again after 1.5 s silence (total cycle: ~3 s)
			self._ringTimer = setTimeout( ring, 3000 );
		}

		ring();
	} catch ( e ) {}
};

/**
 * Stop and tear down the connecting ring tone.
 */
VoiceClient.prototype._stopConnectingRing = function () {
	clearTimeout( this._ringTimer );
	this._ringTimer = null;
	if ( this._ringCtx ) {
		try { this._ringCtx.close(); } catch ( e ) {}
		this._ringCtx = null;
	}
};

/**
 * Play a three-note descending end-call tone (Skype-style).
 * Called only on user-initiated disconnect so it doesn't overlap AI farewell audio.
 */
VoiceClient.prototype._playDisconnectSound = function () {
	try {
		var ctx    = new AudioContext();
		var notes  = [ [ 880, 0 ], [ 659, 0.18 ], [ 494, 0.36 ] ];
		notes.forEach( function ( note ) {
			var freq  = note[ 0 ];
			var delay = note[ 1 ];
			var osc   = ctx.createOscillator();
			var gain  = ctx.createGain();
			osc.type  = 'sine';
			osc.frequency.value = freq;
			gain.gain.setValueAtTime( 0, ctx.currentTime + delay );
			gain.gain.linearRampToValueAtTime( 0.12, ctx.currentTime + delay + 0.01 );
			gain.gain.setValueAtTime( 0.12, ctx.currentTime + delay + 0.12 );
			gain.gain.linearRampToValueAtTime( 0, ctx.currentTime + delay + 0.16 );
			osc.connect( gain );
			gain.connect( ctx.destination );
			osc.start( ctx.currentTime + delay );
			osc.stop( ctx.currentTime + delay + 0.2 );
		} );
		setTimeout( function () { try { ctx.close(); } catch ( e ) {} }, 1200 );
	} catch ( e ) {}
};

/**
 * Stop the audio monitor and close the AudioContext.
 */
VoiceClient.prototype._stopAudioMonitor = function () {
	if ( this._audioCtx ) {
		this._audioCtx.close();
		this._audioCtx = null;
	}
};

/**
 * Check for browser WebRTC support.
 *
 * @return {boolean}
 */
VoiceClient.prototype._isSupported = function () {
	return (
		typeof RTCPeerConnection !== 'undefined' &&
		!! ( navigator.mediaDevices && navigator.mediaDevices.getUserMedia )
	);
};

/**
 * Release the local microphone stream.
 */
VoiceClient.prototype._releaseStream = function () {
	if ( this._stream ) {
		this._stream.getTracks().forEach( function ( t ) { t.stop(); } );
		this._stream = null;
	}
};

/**
 * Transition to a new state and notify the caller.
 *
 * @param {string} state
 */
VoiceClient.prototype._setState = function ( state ) {
	this._state = state;
	this.onStateChange( state );
};

/**
 * Transition to error state and notify the caller.
 *
 * @param {string} message
 */
VoiceClient.prototype._fail = function ( message ) {
	this._state = 'error';
	this.onStateChange( 'error' );
	this.onError( message );
};

module.exports = { VoiceClient };
