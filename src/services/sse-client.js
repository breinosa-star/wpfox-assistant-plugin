'use strict';

const CONNECT_TIMEOUT_MS = 15000;
const MAX_RETRIES        = 3;

/**
 * Server-Sent Events client for streaming chat responses.
 *
 * @param {string} sessionId
 * @param {string} streamToken
 * @param {string} streamUrl
 */
function SSEClient( sessionId, streamToken, streamUrl ) {
	this.sessionId            = sessionId;
	this.streamToken          = streamToken;
	this.streamUrl            = streamUrl;
	this.eventSource          = null;
	this.messageCallback      = null;
	this.errorCallback        = null;
	this.retryCount           = 0;
	this.maxRetries           = MAX_RETRIES;
	this.closed               = false;
	this.receivedFirstMessage = false;
	this.connectTimer         = null;
	this._tokenBuffer         = null; // non-null while buffering during thinking delay
}

SSEClient.prototype.connect = function () {
	const self = this;
	this.closed               = false;
	this.receivedFirstMessage = false;
	this.eventSource          = new EventSource( this.streamUrl );

	// Bail if no message arrives within the timeout.
	this.connectTimer = setTimeout( function () {
		if ( ! self.receivedFirstMessage && ! self.closed ) {
			self.errorCallback && self.errorCallback( { type: 'error', message: 'Connection failed. Please try again.' } );
			self.close();
		}
	}, CONNECT_TIMEOUT_MS );

	this.eventSource.onmessage = function ( event ) {
		self.receivedFirstMessage = true;
		clearTimeout( self.connectTimer );
		self.connectTimer = null;
		self.retryCount   = 0;

		const raw = event.data;

		// Sentinel values that indicate stream failure.
		if ( raw === '0' || raw === '-1' || raw === '' ) {
			self.errorCallback && self.errorCallback( { type: 'error', message: 'Stream connection failed' } );
			self.close();
			return;
		}

		if ( raw === '[DONE]' ) {
			self.close();
			return;
		}

		try {
			const parsed = JSON.parse( raw );

			if ( parsed.type === 'error' ) {
				self.errorCallback && self.errorCallback( parsed );
				self.close();
				return;
			}

			// thinking_ms: hold typing indicator, buffer tokens, flush after delay.
			if ( parsed.thinking_ms ) {
				self._tokenBuffer = [];
				setTimeout( function () {
					const buf = self._tokenBuffer;
					self._tokenBuffer = null;
					if ( buf ) {
						buf.forEach( function ( msg ) {
							self.messageCallback && self.messageCallback( msg );
						} );
					}
				}, parsed.thinking_ms );
				return;
			}

			if ( self._tokenBuffer !== null ) {
				self._tokenBuffer.push( parsed );
				return;
			}

			self.messageCallback && self.messageCallback( parsed );
		} catch {
			// Plain token string, not JSON.
			const msg = { type: 'token', token: raw };
			if ( self._tokenBuffer !== null ) {
				self._tokenBuffer.push( msg );
			} else {
				self.messageCallback && self.messageCallback( msg );
			}
		}
	};

	this.eventSource.onerror = function () {
		if ( self.closed ) return;
		self.eventSource.close();

		if ( ! self.receivedFirstMessage ) {
			self.errorCallback && self.errorCallback( { type: 'error', message: 'Connection failed. Please try again.' } );
			self.closed = true;
			return;
		}

		if ( self.retryCount < self.maxRetries ) {
			self.retryCount++;
			const delay = Math.pow( 2, self.retryCount ) * 1000;
			setTimeout( function () {
				if ( ! self.closed ) self.connect();
			}, delay );
		} else {
			self.errorCallback && self.errorCallback( { type: 'error', message: 'Connection lost. Please try again.' } );
			self.closed = true;
		}
	};
};

SSEClient.prototype.onMessage = function ( callback ) {
	this.messageCallback = callback;
};

SSEClient.prototype.onError = function ( callback ) {
	this.errorCallback = callback;
};

SSEClient.prototype.close = function () {
	this.closed = true;
	if ( this.connectTimer ) {
		clearTimeout( this.connectTimer );
		this.connectTimer = null;
	}
	if ( this.eventSource ) {
		this.eventSource.close();
		this.eventSource = null;
	}
};

module.exports = SSEClient;
