/**
 * GrayFox Sheets Analytics — admin JS.
 *
 * Vanilla JS only. No jQuery. Uses fetch() + FormData.
 * All user-facing strings come from GrayFoxSheetsL10n (never hard-coded).
 */
( function () {
	'use strict';

	var L10n = window.GrayFoxSheetsL10n || {};

	/**
	 * Post data to admin-ajax.php.
	 *
	 * @param {string} action   WordPress AJAX action name.
	 * @param {Object} params   Key/value pairs to include in the request body.
	 * @returns {Promise<Object>} Parsed JSON response.
	 */
	function ajaxPost( action, params ) {
		var data = new FormData();
		data.append( 'action', action );
		data.append( 'nonce', L10n.nonce || '' );
		Object.keys( params ).forEach( function ( key ) {
			data.append( key, params[ key ] );
		} );
		return fetch( L10n.ajaxUrl || '', {
			method: 'POST',
			body: data,
			credentials: 'same-origin',
		} ).then( function ( res ) {
			if ( ! res.ok ) {
				throw new Error( L10n.networkError || 'Network error.' );
			}
			return res.json();
		} );
	}

	/**
	 * Set the text content (never innerHTML) of an element by ID.
	 *
	 * @param {string} id   Element ID.
	 * @param {string} text Text to display.
	 */
	function setText( id, text ) {
		var el = document.getElementById( id );
		if ( el ) {
			el.textContent = text;
		}
	}

	/**
	 * Set a button's text and disabled state.
	 *
	 * @param {HTMLButtonElement} btn      The button element.
	 * @param {string}            label    Label to show.
	 * @param {boolean}           disabled Whether to disable the button.
	 */
	function setBtn( btn, label, disabled ) {
		btn.textContent = label;
		btn.disabled    = !! disabled;
	}

	// -------------------------------------------------------------------------
	// Load Sheets
	// -------------------------------------------------------------------------
	var loadBtn = document.getElementById( 'gf-sheets-load-btn' );
	if ( loadBtn ) {
		loadBtn.addEventListener( 'click', function () {
			var spreadsheetId = ( document.getElementById( 'gf-sheets-spreadsheet-id' ) || {} ).value || '';
			if ( ! spreadsheetId ) {
				return;
			}
			setBtn( loadBtn, L10n.loading || 'Loading\u2026', true );

			ajaxPost( 'grayfox_sheets_list', { spreadsheet_id: spreadsheetId } )
				.then( function ( response ) {
					setBtn( loadBtn, 'Load Sheets', false );
					if ( ! response.success ) {
						var msg = ( response.data && response.data.message ) ? response.data.message : ( L10n.networkError || 'Error.' );
						setText( 'gf-sheets-settings-msg', msg );
						return;
					}
					var select = document.getElementById( 'gf-sheets-sheet-select' );
					if ( ! select ) {
						return;
					}
					// Clear existing options (keep placeholder).
					while ( select.options.length > 1 ) {
						select.remove( 1 );
					}
					var sheets = ( response.data && response.data.sheets ) ? response.data.sheets : [];
					sheets.forEach( function ( sheet ) {
						var opt       = document.createElement( 'option' );
						opt.value     = sheet.title || '';
						opt.textContent = sheet.title || '';
						select.appendChild( opt );
					} );
					setText( 'gf-sheets-settings-msg', '' );
				} )
				.catch( function () {
					setBtn( loadBtn, 'Load Sheets', false );
					setText( 'gf-sheets-settings-msg', L10n.networkError || 'Network error.' );
				} );
		} );
	}

	// -------------------------------------------------------------------------
	// Save Settings
	// -------------------------------------------------------------------------
	var saveSettingsBtn = document.getElementById( 'gf-sheets-save-settings-btn' );
	if ( saveSettingsBtn ) {
		saveSettingsBtn.addEventListener( 'click', function () {
			var spreadsheetId = ( document.getElementById( 'gf-sheets-spreadsheet-id' ) || {} ).value || '';
			var defaultRange  = ( document.getElementById( 'gf-sheets-default-range' ) || {} ).value || '';

			setBtn( saveSettingsBtn, L10n.saving || 'Saving\u2026', true );
			setText( 'gf-sheets-settings-msg', '' );

			ajaxPost( 'grayfox_sheets_save_settings', {
				spreadsheet_id: spreadsheetId,
				default_range:  defaultRange,
			} )
				.then( function ( response ) {
					setBtn( saveSettingsBtn, L10n.saved || 'Saved!', false );
					if ( ! response.success ) {
						var msg = ( response.data && response.data.message ) ? response.data.message : ( L10n.networkError || 'Error.' );
						setText( 'gf-sheets-settings-msg', msg );
					} else {
						setText( 'gf-sheets-settings-msg', L10n.saved || 'Saved!' );
					}
				} )
				.catch( function () {
					setBtn( saveSettingsBtn, 'Save Settings', false );
					setText( 'gf-sheets-settings-msg', L10n.networkError || 'Network error.' );
				} );
		} );
	}

	// -------------------------------------------------------------------------
	// Analyze
	// -------------------------------------------------------------------------
	var analyzeBtn = document.getElementById( 'gf-sheets-analyze-btn' );
	if ( analyzeBtn ) {
		analyzeBtn.addEventListener( 'click', function () {
			var spreadsheetId = ( document.getElementById( 'gf-sheets-query-spreadsheet-id' ) || {} ).value || '';
			var range         = ( document.getElementById( 'gf-sheets-query-range' ) || {} ).value || '';
			var question      = ( document.getElementById( 'gf-sheets-question' ) || {} ).value || '';
			var answerEl      = document.getElementById( 'gf-sheets-answer' );

			if ( ! spreadsheetId || ! range || ! question ) {
				return;
			}

			setBtn( analyzeBtn, L10n.analyzing || 'Analyzing\u2026', true );
			if ( answerEl ) {
				answerEl.textContent = '';
			}

			ajaxPost( 'grayfox_sheets_query', {
				spreadsheet_id: spreadsheetId,
				range:          range,
				question:       question,
			} )
				.then( function ( response ) {
					setBtn( analyzeBtn, 'Analyze', false );
					if ( ! response.success ) {
						var msg = ( response.data && response.data.message ) ? response.data.message : ( L10n.networkError || 'Error.' );
						if ( answerEl ) {
							answerEl.textContent = msg;
						}
						return;
					}
					if ( answerEl ) {
						// Never use innerHTML for server data — textContent only.
						answerEl.textContent = ( response.data && response.data.answer ) ? response.data.answer : '';
					}
				} )
				.catch( function () {
					setBtn( analyzeBtn, 'Analyze', false );
					if ( answerEl ) {
						answerEl.textContent = L10n.networkError || 'Network error.';
					}
				} );
		} );
	}

	// -------------------------------------------------------------------------
	// Schedule Report
	// -------------------------------------------------------------------------
	var schedSubmitBtn = document.getElementById( 'gf-sched-submit-btn' );
	if ( schedSubmitBtn ) {
		schedSubmitBtn.addEventListener( 'click', function () {
			var spreadsheetId = ( document.getElementById( 'gf-sched-spreadsheet-id' ) || {} ).value || '';
			var range         = ( document.getElementById( 'gf-sched-range' ) || {} ).value || '';
			var question      = ( document.getElementById( 'gf-sched-question' ) || {} ).value || '';
			var reportSheet   = ( document.getElementById( 'gf-sched-report-sheet' ) || {} ).value || 'GrayFox Report';
			var frequency     = ( document.getElementById( 'gf-sched-frequency' ) || {} ).value || 'daily';

			if ( ! spreadsheetId || ! range || ! question ) {
				return;
			}

			setBtn( schedSubmitBtn, L10n.scheduling || 'Scheduling\u2026', true );
			setText( 'gf-sched-msg', '' );

			ajaxPost( 'grayfox_sheets_schedule_report', {
				spreadsheet_id: spreadsheetId,
				range:          range,
				question:       question,
				report_sheet:   reportSheet,
				frequency:      frequency,
			} )
				.then( function ( response ) {
					setBtn( schedSubmitBtn, 'Schedule', false );
					if ( ! response.success ) {
						var errMsg = ( response.data && response.data.message ) ? response.data.message : ( L10n.networkError || 'Error.' );
						setText( 'gf-sched-msg', errMsg );
						return;
					}
					setText( 'gf-sched-msg', L10n.saved || 'Scheduled!' );

					// Reload the page to refresh the scheduled reports table.
					setTimeout( function () {
						window.location.reload();
					}, 800 );
				} )
				.catch( function () {
					setBtn( schedSubmitBtn, 'Schedule', false );
					setText( 'gf-sched-msg', L10n.networkError || 'Network error.' );
				} );
		} );
	}

	// -------------------------------------------------------------------------
	// Delete Report
	// -------------------------------------------------------------------------
	var schedTable = document.getElementById( 'gf-sched-table' );
	if ( schedTable ) {
		schedTable.addEventListener( 'click', function ( e ) {
			var target = e.target;
			if ( ! target || ! target.classList.contains( 'gf-delete-report-btn' ) ) {
				return;
			}

			var reportId = target.getAttribute( 'data-report-id' ) || '';
			if ( ! reportId ) {
				return;
			}

			setBtn( target, L10n.loading || 'Deleting\u2026', true );

			ajaxPost( 'grayfox_sheets_delete_report', { report_id: reportId } )
				.then( function ( response ) {
					if ( ! response.success ) {
						setBtn( target, 'Delete', false );
						return;
					}
					// Remove the row from the DOM on success.
					var row = target.closest( 'tr' );
					if ( row ) {
						row.parentNode.removeChild( row );
					}
					// If no rows remain, show empty notice.
					var tbody = schedTable.querySelector( 'tbody' );
					if ( tbody && tbody.querySelectorAll( 'tr' ).length === 0 ) {
						var emptyRow = document.createElement( 'tr' );
						emptyRow.id  = 'gf-sched-empty-row';
						var emptyTd  = document.createElement( 'td' );
						emptyTd.setAttribute( 'colspan', '6' );
						emptyTd.textContent = L10n.noReports || 'No scheduled reports yet.';
						emptyRow.appendChild( emptyTd );
						tbody.appendChild( emptyRow );
					}
				} )
				.catch( function () {
					setBtn( target, 'Delete', false );
				} );
		} );
	}
}() );
