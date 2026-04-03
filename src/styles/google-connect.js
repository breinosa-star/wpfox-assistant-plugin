/**
 * GrayFox — Google Connect admin page JS.
 *
 * Handles:
 *   - AJAX disconnect with confirmation dialog
 *   - AJAX save credentials
 *   - Auto-dismiss success notice when ?connected=1 is present
 *
 * Vanilla JS, no framework dependencies.
 * Localised strings are provided via GrayFoxGoogleL10n (wp_localize_script).
 */
(function () {
	'use strict';

	/**
	 * Auto-dismiss the success notice after 5 seconds.
	 */
	function initSuccessNoticeDismiss() {
		var params = new URLSearchParams(window.location.search);
		if (params.get('connected') !== '1') {
			return;
		}
		var notice = document.getElementById('grayfox-google-success-notice');
		if (!notice) {
			return;
		}
		setTimeout(function () {
			notice.style.transition = 'opacity 0.4s';
			notice.style.opacity = '0';
			setTimeout(function () {
				notice.remove();
			}, 400);
		}, 5000);
	}

	/**
	 * AJAX save credentials handler.
	 */
	function initSaveCredentials() {
		var saveBtn = document.getElementById('grayfox-save-credentials');
		if (!saveBtn) {
			return;
		}

		saveBtn.addEventListener('click', function () {
			var clientIdInput     = document.getElementById('grayfox-client-id');
			var clientSecretInput = document.getElementById('grayfox-client-secret');
			var resultSpan        = document.getElementById('grayfox-credentials-result');
			var nonce             = (window.GrayFoxGoogleL10n && window.GrayFoxGoogleL10n.saveNonce)
				? window.GrayFoxGoogleL10n.saveNonce
				: '';

			if (!resultSpan) {
				return;
			}

			resultSpan.textContent = window.GrayFoxGoogleL10n ? window.GrayFoxGoogleL10n.saving : '';
			resultSpan.style.color = '#666';

			var data = new FormData();
			data.append('action', 'grayfox_save_google_credentials');
			data.append('client_id', clientIdInput ? clientIdInput.value : '');
			data.append('client_secret', clientSecretInput ? clientSecretInput.value : '');
			data.append('_wpnonce', nonce);

			fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', { method: 'POST', body: data })
				.then(function (r) { return r.json(); })
				.then(function (resp) {
					if (resp.success) {
						resultSpan.textContent = (resp.data && resp.data.message)
							? resp.data.message
							: (window.GrayFoxGoogleL10n ? window.GrayFoxGoogleL10n.saved : '');
						resultSpan.style.color = 'green';
						setTimeout(function () {
							window.location.reload();
						}, 800);
					} else {
						resultSpan.textContent = resp.data
							|| (window.GrayFoxGoogleL10n ? window.GrayFoxGoogleL10n.saveFailed : '');
						resultSpan.style.color = 'red';
					}
				})
				.catch(function () {
					resultSpan.textContent = window.GrayFoxGoogleL10n
						? window.GrayFoxGoogleL10n.networkError
						: '';
					resultSpan.style.color = 'red';
				});
		});
	}

	/**
	 * AJAX disconnect handler.
	 */
	function initDisconnect() {
		var disconnectBtn = document.getElementById('grayfox-disconnect-google');
		if (!disconnectBtn) {
			return;
		}

		disconnectBtn.addEventListener('click', function () {
			var confirmMsg = window.GrayFoxGoogleL10n
				? window.GrayFoxGoogleL10n.confirmDisconnect
				: '';

			if (!window.confirm(confirmMsg)) {
				return;
			}

			var nonce = disconnectBtn.getAttribute('data-nonce') || '';
			disconnectBtn.disabled = true;
			disconnectBtn.textContent = window.GrayFoxGoogleL10n
				? window.GrayFoxGoogleL10n.disconnecting
				: '';

			var data = new FormData();
			data.append('action', 'grayfox_google_disconnect');
			data.append('_wpnonce', nonce);

			fetch(window.ajaxurl || '/wp-admin/admin-ajax.php', { method: 'POST', body: data })
				.then(function (r) { return r.json(); })
				.then(function (resp) {
					if (resp.success) {
						window.location.reload();
					} else {
						disconnectBtn.disabled = false;
						disconnectBtn.textContent = window.GrayFoxGoogleL10n
							? window.GrayFoxGoogleL10n.disconnectLabel
							: '';
						window.alert(resp.data
							|| (window.GrayFoxGoogleL10n ? window.GrayFoxGoogleL10n.disconnectFailed : ''));
					}
				})
				.catch(function () {
					disconnectBtn.disabled = false;
					disconnectBtn.textContent = window.GrayFoxGoogleL10n
						? window.GrayFoxGoogleL10n.disconnectLabel
						: '';
					window.alert(window.GrayFoxGoogleL10n
						? window.GrayFoxGoogleL10n.networkError
						: '');
				});
		});
	}

	// Initialise all handlers on DOM ready.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () {
			initSuccessNoticeDismiss();
			initSaveCredentials();
			initDisconnect();
		});
	} else {
		initSuccessNoticeDismiss();
		initSaveCredentials();
		initDisconnect();
	}
})();
