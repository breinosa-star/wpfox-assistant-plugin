/**
 * GrayFox Drive Sync admin page interactions.
 *
 * Handles AJAX calls for folder listing, file selection saving,
 * sync-now trigger, and individual file re-sync.
 *
 * Expects GrayFoxDriveL10n to be localized via wp_localize_script().
 *
 * @package GrayFox
 */
( function () {
	'use strict';

	var cfg = ( typeof GrayFoxDriveL10n !== 'undefined' ) ? GrayFoxDriveL10n : {};
	var ajaxUrl = cfg.ajaxUrl || ( typeof window.ajaxurl !== 'undefined' ? window.ajaxurl : '/wp-admin/admin-ajax.php' );
	var nonce = cfg.nonce || '';

	/* ------------------------------------------------------------------ */
	/* Notice helper                                                       */
	/* ------------------------------------------------------------------ */

	function showNotice( message, isError ) {
		var el = document.getElementById( 'gf-drive-notice' );
		if ( ! el ) {
			return;
		}
		el.textContent = message;
		el.style.display = 'block';
		el.style.color = isError ? '#cc1818' : '#00a32a';
		setTimeout( function () {
			el.style.display = 'none';
		}, 5000 );
	}

	/* ------------------------------------------------------------------ */
	/* POST helper using fetch + FormData                                   */
	/* ------------------------------------------------------------------ */

	function post( action, data, callback ) {
		var body = new FormData();
		body.append( 'action', action );
		body.append( 'nonce', nonce );
		for ( var key in data ) {
			if ( ! data.hasOwnProperty( key ) ) {
				continue;
			}
			if ( Array.isArray( data[ key ] ) ) {
				data[ key ].forEach( function ( item ) {
					body.append( key + '[]', item );
				} );
			} else {
				body.append( key, data[ key ] );
			}
		}
		fetch( ajaxUrl, { method: 'POST', body: body } )
			.then( function ( r ) { return r.json(); } )
			.then( callback )
			.catch( function () {
				callback( { success: false, data: { message: cfg.networkError || 'Network error. Please try again.' } } );
			} );
	}

	/* ------------------------------------------------------------------ */
	/* XSS helpers                                                          */
	/* ------------------------------------------------------------------ */

	function escHtml( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#039;' );
	}

	function escAttr( str ) {
		return escHtml( str );
	}

	function mimeLabel( mime ) {
		var map = {
			'application/vnd.google-apps.document': 'Google Doc',
			'text/plain': 'Plain Text',
			'application/pdf': 'PDF',
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'Word (DOCX)'
		};
		return map[ mime ] || mime;
	}

	/* ------------------------------------------------------------------ */
	/* 1. Save folder settings                                              */
	/* ------------------------------------------------------------------ */

	var saveFolderBtn = document.getElementById( 'grayfox-drive-save-selection' );
	var folderInput = document.getElementById( 'grayfox-drive-folder-id' );
	var saveStatus = document.getElementById( 'grayfox-drive-save-status' );

	if ( saveFolderBtn ) {
		saveFolderBtn.addEventListener( 'click', function () {
			var checked = document.querySelectorAll( '.grayfox-drive-file-cb:checked' );
			var fileIds = Array.prototype.map.call( checked, function ( cb ) { return cb.value; } );
			var folderId = folderInput ? folderInput.value.trim() : '';

			saveFolderBtn.disabled = true;
			if ( saveStatus ) {
				saveStatus.textContent = cfg.saving || 'Saving\u2026';
				saveStatus.style.display = 'inline';
			}

			post( 'grayfox_drive_save_selection', { file_ids: fileIds, folder_id: folderId }, function ( res ) {
				saveFolderBtn.disabled = false;
				if ( saveStatus ) {
					saveStatus.textContent = res.success
						? ( cfg.saved || 'Saved!' )
						: ( res.data && res.data.message ? res.data.message : 'Save failed.' );
					setTimeout( function () { saveStatus.style.display = 'none'; }, 3000 );
				}
				showNotice(
					res.success ? ( cfg.saved || 'Saved!' ) : ( res.data && res.data.message ? res.data.message : 'Save failed.' ),
					! res.success
				);
			} );
		} );
	}

	/* ------------------------------------------------------------------ */
	/* 2. Sync Now                                                          */
	/* ------------------------------------------------------------------ */

	var syncNowBtn = document.getElementById( 'grayfox-drive-sync-now' );
	var syncResult = document.getElementById( 'grayfox-drive-sync-result' );

	if ( syncNowBtn ) {
		syncNowBtn.addEventListener( 'click', function () {
			syncNowBtn.disabled = true;
			syncNowBtn.textContent = cfg.syncing || 'Syncing\u2026';
			if ( syncResult ) {
				syncResult.style.display = 'none';
			}

			post( 'grayfox_drive_sync_now', {}, function ( res ) {
				syncNowBtn.disabled = false;
				syncNowBtn.textContent = cfg.syncNow || 'Sync Now';
				var msg = res.success && res.data && res.data.message
					? res.data.message
					: ( res.data && res.data.message ? res.data.message : 'Sync error.' );
				if ( syncResult ) {
					syncResult.textContent = msg;
					syncResult.style.display = 'inline';
				}
				showNotice( msg, ! res.success );
			} );
		} );
	}

	/* ------------------------------------------------------------------ */
	/* 3. List files on page load / Load Files button                       */
	/* ------------------------------------------------------------------ */

	var loadBtn = document.getElementById( 'grayfox-drive-load-files' );
	var fileList = document.getElementById( 'grayfox-drive-file-list' );
	var loadingMsg = document.getElementById( 'grayfox-drive-file-list-loading' );
	var emptyMsg = document.getElementById( 'grayfox-drive-file-list-empty' );
	var errorMsg = document.getElementById( 'grayfox-drive-file-list-error' );
	var filesTable = document.getElementById( 'grayfox-drive-files-table' );
	var filesTbody = document.getElementById( 'grayfox-drive-files-tbody' );
	var selectAll = document.getElementById( 'grayfox-drive-select-all' );

	function loadFiles() {
		var folderId = folderInput ? folderInput.value.trim() : '';
		if ( ! folderId ) {
			return;
		}

		if ( fileList ) { fileList.style.display = 'block'; }
		if ( loadingMsg ) { loadingMsg.style.display = 'block'; }
		if ( emptyMsg ) { emptyMsg.style.display = 'none'; }
		if ( errorMsg ) { errorMsg.style.display = 'none'; }
		if ( filesTable ) { filesTable.style.display = 'none'; }
		if ( loadBtn ) { loadBtn.disabled = true; }

		post( 'grayfox_drive_list_folder', { folder_id: folderId }, function ( res ) {
			if ( loadingMsg ) { loadingMsg.style.display = 'none'; }
			if ( loadBtn ) { loadBtn.disabled = false; }

			if ( ! res.success ) {
				if ( errorMsg ) {
					errorMsg.textContent = res.data && res.data.message ? res.data.message : 'Error loading files.';
					errorMsg.style.display = 'block';
				}
				return;
			}

			var files = ( res.data && res.data.files ) ? res.data.files : [];
			if ( files.length === 0 ) {
				if ( emptyMsg ) { emptyMsg.style.display = 'block'; }
				return;
			}

			if ( filesTbody ) {
				filesTbody.innerHTML = '';
				files.forEach( function ( f ) {
					var modified = f.modifiedTime ? new Date( f.modifiedTime ).toLocaleString() : '\u2014';
					var row = '<tr>' +
						'<td><input type="checkbox" name="grayfox_drive_file" class="grayfox-drive-file-cb" value="' + escAttr( f.id ) + '" /></td>' +
						'<td>' + escHtml( f.name ) + '</td>' +
						'<td>' + escHtml( mimeLabel( f.mimeType ) ) + '</td>' +
						'<td>' + escHtml( modified ) + '</td>' +
						'</tr>';
					filesTbody.insertAdjacentHTML( 'beforeend', row );
				} );
			}
			if ( filesTable ) { filesTable.style.display = ''; }
		} );
	}

	if ( loadBtn ) {
		loadBtn.addEventListener( 'click', loadFiles );
	}

	/* Select-all toggle */
	if ( selectAll ) {
		selectAll.addEventListener( 'change', function () {
			document.querySelectorAll( '.grayfox-drive-file-cb' ).forEach( function ( cb ) {
				cb.checked = selectAll.checked;
			} );
		} );
	}

	/* ------------------------------------------------------------------ */
	/* 4. Re-sync individual file                                           */
	/* ------------------------------------------------------------------ */

	document.addEventListener( 'click', function ( e ) {
		if ( ! e.target || ! e.target.classList.contains( 'grayfox-drive-resync-btn' ) ) {
			return;
		}
		var btn = e.target;
		var fileId = btn.dataset.fileId || '';
		if ( ! fileId ) { return; }

		btn.disabled = true;
		btn.textContent = 'Queuing\u2026';

		post( 'grayfox_drive_resync_file', { file_id: fileId }, function ( res ) {
			btn.disabled = false;
			btn.textContent = 'Re-sync';
			if ( res.success ) {
				showNotice( res.data && res.data.message ? res.data.message : 'File queued.', false );
			} else {
				showNotice( res.data && res.data.message ? res.data.message : 'Re-sync failed.', true );
			}
		} );
	} );
} )();
