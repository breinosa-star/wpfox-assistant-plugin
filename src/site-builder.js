(function () {
	'use strict';

	const L10n    = window.GrayFoxSiteBuilderL10n || {};
	const ajaxUrl = L10n.ajaxUrl || '';
	const nonces  = L10n.nonces  || {};

	let currentStep    = 1;
	let envData        = null;
	let genPollTimer   = null;
	let genStartTime   = null;

	// -------------------------------------------------------------------------
	// AJAX helper
	// -------------------------------------------------------------------------

	function ajax( action, nonce, data ) {
		const form = new FormData();
		form.append( 'action', action );
		form.append( '_ajax_nonce', nonce );
		if ( data ) {
			Object.keys( data ).forEach( function ( key ) {
				form.append( key, data[ key ] );
			} );
		}
		return fetch( ajaxUrl, { method: 'POST', body: form } ).then( function ( r ) {
			return r.json();
		} );
	}

	// -------------------------------------------------------------------------
	// Step navigation
	// -------------------------------------------------------------------------

	function goToStep( step ) {
		currentStep = step;

		document.querySelectorAll( '.grayfox-step' ).forEach( function ( el ) {
			el.style.display = parseInt( el.dataset.step, 10 ) === step ? '' : 'none';
		} );

		document.querySelectorAll( '.grayfox-step-tab' ).forEach( function ( el ) {
			const active = parseInt( el.dataset.step, 10 ) === step;
			el.style.borderBottomColor = active ? '#2271b1' : 'transparent';
			el.style.color             = active ? '#2271b1' : '#555';
		} );

		if ( step === 2 ) detectEnvironment();
		if ( step === 6 ) initFooterStep();
		if ( step === 7 ) loadAuditResults();
	}

	document.querySelectorAll( '.grayfox-step-tab' ).forEach( function ( tab ) {
		tab.addEventListener( 'click', function () {
			goToStep( parseInt( this.dataset.step, 10 ) );
		} );
	} );

	const adminWrap   = document.querySelector( '.grayfox-admin-wrap' );
	const initialStep = adminWrap ? parseInt( adminWrap.dataset.initialStep || '1', 10 ) : 1;
	goToStep( initialStep || 1 );

	// -------------------------------------------------------------------------
	// Step 1 — Sitemap
	// -------------------------------------------------------------------------

	function renderSitemapList( pages, containerEl ) {
		containerEl.innerHTML = '';
		containerEl.appendChild( buildSitemapList( pages ) );
	}

	function buildSitemapList( pages ) {
		const ul = document.createElement( 'ul' );
		ul.style.cssText = 'list-style:disc;padding-left:24px;margin:0;';

		( pages || [] ).forEach( function ( page ) {
			const li    = document.createElement( 'li' );
			const input = document.createElement( 'input' );
			input.type          = 'text';
			input.value         = page.title || '';
			input.className     = 'regular-text';
			input.dataset.page  = 'title';
			input.style.marginBottom = '6px';
			input.addEventListener( 'input', function () {
				page.title = this.value;
			} );
			li.appendChild( input );

			if ( page.children && page.children.length ) {
				li.appendChild( buildSitemapList( page.children ) );
			}
			ul.appendChild( li );
		} );

		return ul;
	}

	const generateSitemapBtn = document.getElementById( 'grayfox-generate-sitemap' );
	if ( generateSitemapBtn ) {
		generateSitemapBtn.addEventListener( 'click', function () {
			const statusEl  = document.getElementById( 'grayfox-sitemap-status' );
			const previewEl = document.getElementById( 'grayfox-sitemap-preview' );
			statusEl.textContent       = 'Generating preview…';
			generateSitemapBtn.disabled = true;

			ajax( 'grayfox_generate_sitemap_preview', nonces.generateSitemapPreview )
				.then( function ( res ) {
					generateSitemapBtn.disabled = false;
					if ( res.success && res.data ) {
						statusEl.textContent     = '';
						previewEl.style.display  = '';

						const noticeEl = document.getElementById( 'grayfox-sitemap-notice' );
						if ( noticeEl ) noticeEl.textContent = res.data.notice || '';

						const editorEl = document.getElementById( 'grayfox-sitemap-editor' );
						window._grayfoxSitemapPages = res.data.sitemap;
						renderSitemapList( res.data.sitemap, editorEl );
					} else {
						statusEl.textContent = res.data && typeof res.data === 'string'
							? res.data
							: 'Failed to generate preview.';
					}
				} )
				.catch( function () {
					generateSitemapBtn.disabled = false;
					statusEl.textContent        = 'Network error.';
				} );
		} );
	}

	const saveSitemapBtn = document.getElementById( 'grayfox-save-sitemap' );
	if ( saveSitemapBtn ) {
		saveSitemapBtn.addEventListener( 'click', function () {
			const pages = window._grayfoxSitemapPages;
			if ( ! pages ) return;
			saveSitemapBtn.disabled = true;
			ajax( 'grayfox_save_sitemap', nonces.saveSitemap, { pages: JSON.stringify( pages ) } )
				.then( function ( res ) {
					saveSitemapBtn.disabled = false;
					if ( res.success ) {
						goToStep( 2 );
					} else {
						alert( res.data && typeof res.data === 'string' ? res.data : 'Save failed.' );
					}
				} );
		} );
	}

	const useSavedSitemapBtn = document.getElementById( 'grayfox-use-saved-sitemap' );
	if ( useSavedSitemapBtn ) {
		useSavedSitemapBtn.addEventListener( 'click', function () {
			goToStep( 2 );
		} );
	}

	// -------------------------------------------------------------------------
	// Step 2 — Environment detection
	// -------------------------------------------------------------------------

	function detectEnvironment() {
		const resultEl   = document.getElementById( 'grayfox-env-result' );
		const continueEl = document.getElementById( 'grayfox-env-continue' );
		if ( ! resultEl ) return;

		resultEl.innerHTML = '<em>Detecting environment…</em>';

		ajax( 'grayfox_detect_environment', nonces.detectEnvironment )
			.then( function ( res ) {
				if ( res.success && res.data ) {
					envData = res.data;

					const table = document.createElement( 'table' );
					table.className    = 'widefat';
					table.style.maxWidth = '500px';
					const tbody = document.createElement( 'tbody' );

					function addRow( label, value ) {
						const tr = document.createElement( 'tr' );
						const td1 = document.createElement( 'td' );
						td1.textContent = label;
						const td2 = document.createElement( 'td' );
						td2.textContent = value;
						tr.appendChild( td1 );
						tr.appendChild( td2 );
						tbody.appendChild( tr );
					}

					addRow( 'Block Theme', envData.is_block_theme ? '✓ Yes' : 'No' );
					addRow( 'Elementor', envData.has_elementor
						? '✓ Active (v' + envData.elementor_version + ')'
						: 'Not active' );
					if ( envData.has_other_builder ) {
						addRow( 'Other Builder', envData.other_builder_name + ' detected' );
					}

					table.appendChild( tbody );
					resultEl.innerHTML = '';
					resultEl.appendChild( table );

					if ( continueEl ) continueEl.style.display = '';

					const elementorRadio = document.querySelector( 'input[value="elementor"]' );
					const elementorLabel = document.getElementById( 'grayfox-elementor-label' );
					if ( elementorRadio && elementorLabel ) {
						if ( ! envData.has_elementor || ! envData.elementor_version_ok ) {
							elementorRadio.disabled    = true;
							elementorLabel.style.opacity = '0.5';
							elementorLabel.title         = 'Elementor 3.0.0+ is required';
						}
					}
				} else {
					resultEl.textContent = 'Could not detect environment.';
					if ( continueEl ) continueEl.style.display = '';
				}
			} )
			.catch( function () {
				resultEl.textContent = 'Network error.';
				if ( continueEl ) continueEl.style.display = '';
			} );
	}

	const envContinueBtn = document.getElementById( 'grayfox-env-continue' );
	if ( envContinueBtn ) {
		envContinueBtn.addEventListener( 'click', function () {
			goToStep( 3 );
		} );
	}

	// -------------------------------------------------------------------------
	// Step 3 — Format selection
	// -------------------------------------------------------------------------

	const saveFormatBtn = document.getElementById( 'grayfox-save-format' );
	if ( saveFormatBtn ) {
		saveFormatBtn.addEventListener( 'click', function () {
			const selected   = document.querySelector( 'input[name="grayfox_build_format"]:checked' );
			const noticeEl   = document.getElementById( 'grayfox-format-notice' );
			if ( ! selected ) {
				noticeEl.textContent = 'Please select a format.';
				return;
			}
			saveFormatBtn.disabled = true;
			noticeEl.textContent   = '';
			ajax( 'grayfox_set_build_format', nonces.setBuildFormat, { format: selected.value } )
				.then( function ( res ) {
					saveFormatBtn.disabled = false;
					if ( res.success ) {
						goToStep( 4 );
					} else {
						noticeEl.textContent = res.data && typeof res.data === 'string'
							? res.data
							: 'Could not save format.';
					}
				} )
				.catch( function () {
					saveFormatBtn.disabled = false;
					noticeEl.textContent   = 'Network error.';
				} );
		} );
	}

	// -------------------------------------------------------------------------
	// Step 4 — Cost estimation, Unsplash key, generation
	// -------------------------------------------------------------------------

	const estimateCostBtn = document.getElementById( 'grayfox-estimate-cost' );
	if ( estimateCostBtn ) {
		estimateCostBtn.addEventListener( 'click', function () {
			const resultEl         = document.getElementById( 'grayfox-estimate-result' );
			estimateCostBtn.disabled = true;
			resultEl.textContent   = 'Estimating…';

			ajax( 'grayfox_estimate_generation_cost', nonces.estimateGenerationCost )
				.then( function ( res ) {
					estimateCostBtn.disabled = false;
					if ( res.success && res.data ) {
						const d = res.data;
						resultEl.textContent = d.page_count + ' pages \xB7 ~' +
							d.total_tokens.toLocaleString() + ' tokens \xB7 est. cost: ' +
							d.estimated_cost;
					} else {
						resultEl.textContent = res.data && typeof res.data === 'string'
							? res.data
							: 'Estimation failed.';
					}
				} )
				.catch( function () {
					estimateCostBtn.disabled = false;
					resultEl.textContent     = 'Network error.';
				} );
		} );
	}

	const saveUnsplashBtn = document.getElementById( 'grayfox-save-unsplash' );
	if ( saveUnsplashBtn ) {
		saveUnsplashBtn.addEventListener( 'click', function () {
			const key      = document.getElementById( 'grayfox-unsplash-key' ).value.trim();
			const statusEl = document.getElementById( 'grayfox-unsplash-status' );
			if ( ! key ) {
				statusEl.textContent = 'Enter a key first.';
				return;
			}
			saveUnsplashBtn.disabled = true;
			statusEl.textContent     = 'Saving…';

			ajax( 'grayfox_save_unsplash_key', nonces.saveUnsplashKey, { key } )
				.then( function ( res ) {
					saveUnsplashBtn.disabled = false;
					statusEl.textContent     = res.success
						? 'Saved!'
						: ( res.data && typeof res.data === 'string' ? res.data : 'Failed.' );
				} )
				.catch( function () {
					saveUnsplashBtn.disabled = false;
					statusEl.textContent     = 'Network error.';
				} );
		} );
	}

	const startGenerationBtn = document.getElementById( 'grayfox-start-generation' );
	if ( startGenerationBtn ) {
		startGenerationBtn.addEventListener( 'click', function () {
			if ( ! confirm( 'Start generating site pages? This will run in the background and may take a few minutes.' ) ) return;
			startGenerationBtn.disabled = true;

			ajax( 'grayfox_start_site_generation', nonces.startSiteGeneration )
				.then( function ( res ) {
					if ( res.success ) {
						document.getElementById( 'grayfox-progress-block' ).style.display = '';
						startProgressPolling();
					} else {
						startGenerationBtn.disabled = false;
						alert( res.data && typeof res.data === 'string' ? res.data : 'Could not start generation.' );
					}
				} )
				.catch( function () {
					startGenerationBtn.disabled = false;
					alert( 'Network error.' );
				} );
		} );
	}

	function startProgressPolling() {
		if ( genPollTimer ) clearInterval( genPollTimer );
		genStartTime  = Date.now();
		genPollTimer  = setInterval( pollGenProgress, 2000 );
	}

	function stopProgressPolling() {
		if ( genPollTimer ) {
			clearInterval( genPollTimer );
			genPollTimer = null;
		}
	}

	function pollGenProgress() {
		ajax( 'grayfox_get_build_progress', nonces.getBuildProgress )
			.then( function ( res ) {
				if ( ! res.success || ! res.data ) return;
				renderProgress( res.data );
				if ( res.data.status === 'complete' ) {
					stopProgressPolling();
					setTimeout( function () {
						goToStep( 5 );
						window.location.reload();
					}, 1500 );
				}
			} )
			.catch( function () {} );
	}

	function renderProgress( data ) {
		const barEl      = document.getElementById( 'grayfox-progress-bar' );
		const textEl     = document.getElementById( 'grayfox-progress-text' );
		const listEl     = document.getElementById( 'grayfox-progress-list' );
		if ( ! barEl || ! textEl ) return;

		if ( ! ( data.completed > 0 || data.status === 'complete' ) ) {
			barEl.style.display = 'none';

			const waited = genStartTime ? Math.round( ( Date.now() - genStartTime ) / 1000 ) : 0;
			while ( textEl.firstChild ) textEl.removeChild( textEl.firstChild );
			textEl.appendChild( document.createTextNode( 'Waiting for background worker to start…' ) );

			if ( waited > 20 ) {
				const hint = document.createElement( 'span' );
				hint.style.cssText = 'display:block;margin-top:6px;font-size:12px;font-weight:normal;color:#d63638;';
				hint.textContent   = 'Taking longer than usual. You can run the job manually: ';
				const link = document.createElement( 'a' );
				link.href        = 'tools.php?page=action-scheduler&status=pending&s=grayfox';
				link.textContent = 'Tools → Scheduled Actions';
				hint.appendChild( link );
				textEl.appendChild( hint );
			}
			return;
		}

		genStartTime        = null;
		barEl.style.display = '';

		const pct = data.total > 0 ? Math.round( data.completed / data.total * 100 ) : 0;
		barEl.value    = pct;
		barEl.max      = 100;
		textEl.textContent = data.completed + ' / ' + data.total + ' pages (' + pct + '%)';

		if ( listEl && data.pages ) {
			listEl.innerHTML = '';
			data.pages.forEach( function ( page ) {
				const li = document.createElement( 'li' );
				li.textContent = page.title + ( page.status === 'complete' ? ' ✓' : ' ✗' );
				if ( page.status !== 'complete' ) li.style.color = '#d63638';
				listEl.appendChild( li );
			} );
		}
	}

	// -------------------------------------------------------------------------
	// Step 5 — Results & revisions
	// -------------------------------------------------------------------------

	const revisionStatusMap = {
		pending:    '<span style="color:#996800;">&#9679; Queued</span>',
		processing: '<span style="color:#2271b1;">&#8635; Processing</span>',
		done:       '<span style="color:#46b450;">&#10003; Done</span>',
		error:      '<span style="color:#d63638;">&#10007; Error</span>',
	};

	document.querySelectorAll( '.grayfox-revision-action' ).forEach( function ( select ) {
		select.addEventListener( 'change', function () {
			const hintEl = this.closest( 'tr' ).querySelector( '.grayfox-revision-hint' );
			if ( hintEl ) hintEl.style.display = this.value ? '' : 'none';
			updateSubmitButton();
		} );
	} );

	function updateSubmitButton() {
		const submitBtn = document.getElementById( 'grayfox-submit-revisions' );
		if ( ! submitBtn ) return;
		const hasAny = Array.from( document.querySelectorAll( '.grayfox-revision-action' ) )
			.some( function ( el ) { return el.value !== ''; } );
		submitBtn.disabled = ! hasAny;
	}

	const submitRevisionsBtn = document.getElementById( 'grayfox-submit-revisions' );
	if ( submitRevisionsBtn ) {
		submitRevisionsBtn.addEventListener( 'click', function () {
			const revisions = [];
			document.querySelectorAll( '#grayfox-results-table tbody tr' ).forEach( function ( row ) {
				const actionEl = row.querySelector( '.grayfox-revision-action' );
				const hintEl   = row.querySelector( '.grayfox-revision-hint' );
				if ( ! actionEl || ! actionEl.value ) return;
				const parts = actionEl.value.split( '|' );
				revisions.push( {
					post_id:            parseInt( row.dataset.postId, 10 ),
					action_type:        parts[ 0 ],
					dropdown_selection: parts[ 1 ] || '',
					user_hint:          hintEl ? hintEl.value.trim().slice( 0, 50 ) : '',
				} );
			} );
			if ( ! revisions.length ) return;

			submitRevisionsBtn.disabled = true;
			const statusEl = document.getElementById( 'grayfox-revisions-status' );
			if ( statusEl ) statusEl.textContent = 'Submitting…';

			ajax( 'grayfox_submit_page_revisions', nonces.submitPageRevisions, {
				revisions: JSON.stringify( revisions ),
			} )
				.then( function ( res ) {
					if ( res.success && res.data ) {
						if ( statusEl ) {
							statusEl.textContent = res.data.queued + ' job(s) queued. Watching for updates…';
						}
						document.querySelectorAll( '#grayfox-results-table tbody tr' ).forEach( function ( row ) {
							const actionEl = row.querySelector( '.grayfox-revision-action' );
							const hintEl   = row.querySelector( '.grayfox-revision-hint' );
							if ( actionEl && actionEl.value ) {
								const postId = parseInt( row.dataset.postId, 10 );
								const matched = revisions.find( function ( r ) { return r.post_id === postId; } );
								if ( matched ) {
									setRowStatus( row, 'pending' );
									actionEl.value = '';
									if ( hintEl ) { hintEl.value = ''; hintEl.style.display = 'none'; }
								}
							}
						} );
						startRevisionPolling();
					} else {
						submitRevisionsBtn.disabled = false;
						if ( statusEl ) {
							statusEl.textContent = res.data && typeof res.data === 'string'
								? res.data
								: 'Submission failed.';
						}
					}
				} )
				.catch( function () {
					submitRevisionsBtn.disabled = false;
					if ( statusEl ) statusEl.textContent = 'Network error.';
				} );
		} );
	}

	function setRowStatus( rowEl, status ) {
		const statusCell = rowEl.querySelector( '.grayfox-revision-status' );
		if ( statusCell ) {
			statusCell.dataset.status = status;
			statusCell.innerHTML = revisionStatusMap[ status ] || '&mdash;';
		}
	}

	let revisionPollTimer = null;

	function startRevisionPolling() {
		if ( ! revisionPollTimer ) {
			revisionPollTimer = setInterval( pollRevisionStatus, 3000 );
		}
	}

	function stopRevisionPolling() {
		if ( revisionPollTimer ) {
			clearInterval( revisionPollTimer );
			revisionPollTimer = null;
		}
	}

	function pollRevisionStatus() {
		ajax( 'grayfox_get_build_progress', nonces.getBuildProgress )
			.then( function ( res ) {
				if ( ! res.success || ! res.data || ! res.data.pages ) return;
				let stillActive = false;
				res.data.pages.forEach( function ( page ) {
					if ( ! page.post_id ) return;
					const row = document.querySelector(
						'#grayfox-results-table tr[data-post-id="' + page.post_id + '"]'
					);
					if ( ! row ) return;
					const st = page.revision_status || '';
					if ( st ) setRowStatus( row, st );
					if ( st === 'pending' || st === 'processing' ) stillActive = true;
				} );
				if ( ! stillActive ) {
					stopRevisionPolling();
					const statusEl = document.getElementById( 'grayfox-revisions-status' );
					if ( statusEl && statusEl.textContent.indexOf( 'Watching' ) !== -1 ) {
						statusEl.textContent = 'All revision jobs complete.';
					}
					updateSubmitButton();
				}
			} )
			.catch( function () {} );
	}

	// Start revision polling if any rows are already in progress (page reload case).
	document.querySelectorAll( '.grayfox-revision-status' ).forEach( function ( el ) {
		const st = el.dataset.status;
		if ( st === 'pending' || st === 'processing' ) startRevisionPolling();
	} );

	const undoBuildBtn = document.getElementById( 'grayfox-undo-build' );
	if ( undoBuildBtn ) {
		undoBuildBtn.addEventListener( 'click', function () {
			if ( ! confirm( 'This will move all GrayFox-generated pages to the Trash. Are you sure?' ) ) return;
			if ( ! confirm( 'Please confirm again: remove all GrayFox-generated pages?' ) ) return;

			const statusEl      = document.getElementById( 'grayfox-undo-status' );
			undoBuildBtn.disabled = true;
			statusEl.textContent  = 'Removing…';

			ajax( 'grayfox_undo_site_build', nonces.undoSiteBuild, { confirmed: '1' } )
				.then( function ( res ) {
					if ( res.success && res.data ) {
						statusEl.textContent = res.data.trashed_count + ' page(s) moved to Trash.';
						setTimeout( function () { window.location.reload(); }, 1500 );
					} else {
						undoBuildBtn.disabled = false;
						statusEl.textContent  = res.data && typeof res.data === 'string'
							? res.data
							: 'Failed.';
					}
				} )
				.catch( function () {
					undoBuildBtn.disabled = false;
					statusEl.textContent  = 'Network error.';
				} );
		} );
	}

	// -------------------------------------------------------------------------
	// Step 6 — Footer / navigation
	// -------------------------------------------------------------------------

	let allPages = [];

	function initFooterStep() {
		const loadingEl    = document.getElementById( 'grayfox-footer-loading' );
		const columnsEl    = document.getElementById( 'grayfox-footer-columns' );
		const saveFooterEl = document.getElementById( 'grayfox-save-footer' );
		const resetEl      = document.getElementById( 'grayfox-reset-footer' );
		const suggestEl    = document.getElementById( 'grayfox-suggest-footer' );
		const hintEl       = document.getElementById( 'grayfox-footer-hint' );
		const overrideEl   = document.getElementById( 'grayfox-footer-override-note' );

		if ( ! loadingEl ) return;

		loadingEl.style.display    = '';
		if ( columnsEl )    columnsEl.style.display    = 'none';
		if ( saveFooterEl ) saveFooterEl.style.display = 'none';
		if ( resetEl )      resetEl.style.display      = 'none';
		if ( suggestEl )    suggestEl.style.display    = 'none';
		if ( hintEl )       hintEl.style.display       = 'none';
		if ( overrideEl )   overrideEl.style.display   = 'none';

		ajax( 'grayfox_get_footer_config', nonces.getFooterConfig )
			.then( function ( res ) {
				loadingEl.style.display = 'none';
				if ( ! res.success || ! res.data ) return;

				const data = res.data;
				allPages   = data.all_pages || [];

				if ( hintEl ) {
					let msg = 'GrayFox creates a <strong>GrayFox Header Menu</strong> and a ' +
						'<strong>GrayFox Footer Menu</strong> in <a href="' +
						( L10n.adminUrl || '' ) + 'nav-menus.php" target="_blank">' +
						'Appearance → Menus</a>.';
					if ( data.is_block_theme ) {
						msg += ' On your block theme, GrayFox will also wire these menus into ' +
							'the header and footer template parts automatically.';
					}
					hintEl.innerHTML     = msg;
					hintEl.style.display = '';
				}

				renderFooterColumns( data );
				if ( columnsEl )    columnsEl.style.display    = 'flex';
				if ( saveFooterEl ) saveFooterEl.style.display = '';
				if ( suggestEl )    suggestEl.style.display    = '';
				if ( ( data.header_menu_exists || data.footer_menu_exists ) && resetEl ) {
					resetEl.style.display = '';
				}
			} )
			.catch( function () {
				if ( loadingEl ) loadingEl.textContent = 'Failed to load menu configuration.';
			} );
	}

	function renderFooterColumns( data ) {
		const columnsEl = document.getElementById( 'grayfox-footer-columns' );
		if ( ! columnsEl ) return;
		columnsEl.innerHTML = '';
		columnsEl.appendChild( buildColumn( 'header', 'Header Navigation', data.header_items || [] ) );
		columnsEl.appendChild( buildColumn( 'footer', 'Footer Navigation', data.footer_items || [] ) );
	}

	function buildColumn( location, title, items ) {
		const wrap = document.createElement( 'div' );
		wrap.dataset.location = location;
		wrap.style.cssText = 'flex:1;min-width:200px;max-width:340px;border:1px solid #ddd;' +
			'border-radius:4px;padding:12px;';

		const h4 = document.createElement( 'h4' );
		h4.style.margin  = '0 0 10px';
		h4.textContent   = title;
		wrap.appendChild( h4 );

		const listEl = document.createElement( 'div' );
		listEl.className      = 'grayfox-footer-list';
		listEl.style.marginBottom = '10px';
		items.forEach( function ( item ) { addItemToList( listEl, item ); } );
		wrap.appendChild( listEl );

		// Add-page dropdown.
		const addWrap  = document.createElement( 'div' );
		addWrap.style.marginTop = '8px';
		const select   = document.createElement( 'select' );
		select.className   = 'grayfox-footer-add-select';
		select.style.width = '100%';
		const placeholder  = document.createElement( 'option' );
		placeholder.value       = '';
		placeholder.textContent = '— Add a page —';
		select.appendChild( placeholder );

		allPages.forEach( function ( page ) {
			const opt = document.createElement( 'option' );
			opt.value          = page.post_id;
			opt.textContent    = page.title;
			opt.dataset.url    = page.url;
			opt.dataset.title  = page.title;
			select.appendChild( opt );
		} );

		select.addEventListener( 'change', function () {
			if ( ! this.value ) return;
			const chosen = this.options[ this.selectedIndex ];
			addItemToList( listEl, {
				post_id:  parseInt( this.value, 10 ),
				title:    chosen.dataset.title,
				url:      chosen.dataset.url,
				children: [],
			} );
			this.value = '';
		} );

		addWrap.appendChild( select );
		wrap.appendChild( addWrap );
		return wrap;
	}

	function buildFooterItem( item, isChild ) {
		const row = document.createElement( 'div' );
		row.className           = 'grayfox-footer-item';
		row.dataset.menuItemId  = item.menu_item_id || 0;
		row.dataset.postId      = item.post_id       || 0;
		row.dataset.url         = item.url           || '';
		row.dataset.title       = item.title         || '';
		row.dataset.indent      = isChild ? '1' : '0';
		row.style.cssText = 'display:flex;align-items:center;gap:6px;padding:4px 0;' +
			'border-bottom:1px solid #eee;' + ( isChild ? 'margin-left:20px;' : '' );

		// Up/down arrows.
		const arrows = document.createElement( 'span' );
		arrows.style.cssText = 'display:flex;flex-direction:column;gap:1px;';

		const upBtn = document.createElement( 'button' );
		upBtn.type      = 'button';
		upBtn.textContent = '▲';
		upBtn.style.cssText = 'font-size:10px;padding:0 3px;cursor:pointer;line-height:1.2;';
		upBtn.addEventListener( 'click', function () {
			const prev = row.previousElementSibling;
			if ( prev && prev.classList.contains( 'grayfox-footer-item' ) ) {
				row.parentNode.insertBefore( row, prev );
			}
		} );

		const downBtn = document.createElement( 'button' );
		downBtn.type      = 'button';
		downBtn.textContent = '▼';
		downBtn.style.cssText = 'font-size:10px;padding:0 3px;cursor:pointer;line-height:1.2;';
		downBtn.addEventListener( 'click', function () {
			const next = row.nextElementSibling;
			if ( next && next.classList.contains( 'grayfox-footer-item' ) ) {
				row.parentNode.insertBefore( next, row );
			}
		} );

		arrows.appendChild( upBtn );
		arrows.appendChild( downBtn );

		const label = document.createElement( 'span' );
		label.style.cssText = 'flex:1;font-size:13px;' + ( isChild ? 'color:#555;' : 'font-weight:600;' );
		label.textContent   = ( isChild ? '↳ ' : '' ) + item.title;

		const removeBtn = document.createElement( 'button' );
		removeBtn.type        = 'button';
		removeBtn.textContent = '\xD7';
		removeBtn.title       = 'Remove';
		removeBtn.style.cssText = 'cursor:pointer;color:#d63638;font-size:16px;' +
			'padding:0 4px;background:none;border:none;';
		removeBtn.addEventListener( 'click', function () { row.remove(); } );

		row.appendChild( arrows );
		row.appendChild( label );
		row.appendChild( removeBtn );
		return row;
	}

	function addItemToList( listEl, item ) {
		listEl.appendChild( buildFooterItem( item, false ) );
		( item.children || [] ).forEach( function ( child ) {
			listEl.appendChild( buildFooterItem( child, true ) );
		} );
	}

	function collectColumn( location ) {
		const colEl = document.querySelector(
			'#grayfox-footer-columns [data-location="' + location + '"]'
		);
		if ( ! colEl ) return [];

		const items = [];
		let   parent = null;

		colEl.querySelectorAll( '.grayfox-footer-item' ).forEach( function ( el ) {
			const item = {
				post_id:  parseInt( el.dataset.postId, 10 ) || 0,
				title:    el.dataset.title,
				url:      el.dataset.url,
				children: [],
			};
			if ( el.dataset.indent === '1' ) {
				if ( parent ) parent.children.push( item );
			} else {
				parent = item;
				items.push( item );
			}
		} );

		return items;
	}

	const saveFooterBtn = document.getElementById( 'grayfox-save-footer' );
	if ( saveFooterBtn ) {
		saveFooterBtn.addEventListener( 'click', function () {
			const statusEl = document.getElementById( 'grayfox-footer-status' );
			const payload  = {
				header_items: collectColumn( 'header' ),
				footer_items: collectColumn( 'footer' ),
			};
			saveFooterBtn.disabled = true;
			if ( statusEl ) statusEl.textContent = 'Saving…';

			ajax( 'grayfox_save_footer_config', nonces.saveFooterConfig, {
				config: JSON.stringify( payload ),
			} )
				.then( function ( res ) {
					saveFooterBtn.disabled = false;
					if ( res.success && res.data ) {
						const d       = res.data;
						const hSum    = d.header_summary || {};
						const fSum    = d.footer_summary || {};
						const summary = 'Saved: Header (' + ( hSum.item_count || 0 ) +
							' items), Footer (' + ( fSum.item_count || 0 ) + ' items).';
						if ( statusEl ) {
							statusEl.innerHTML = summary + ( d.verify_url
								? ' <a href="' + d.verify_url + '" target="_blank">' +
									'View in Appearance → Menus</a>'
								: '' );
						}
						const resetEl = document.getElementById( 'grayfox-reset-footer' );
						if ( resetEl ) resetEl.style.display = '';
					} else {
						if ( statusEl ) {
							statusEl.textContent = res.data?.message || 'Save failed.';
						}
					}
				} )
				.catch( function () {
					saveFooterBtn.disabled = false;
					if ( statusEl ) statusEl.textContent = 'Network error.';
				} );
		} );
	}

	const resetFooterBtn = document.getElementById( 'grayfox-reset-footer' );
	if ( resetFooterBtn ) {
		resetFooterBtn.addEventListener( 'click', function () {
			if ( ! confirm( 'Remove the GrayFox Header and Footer menus?' ) ) return;
			const statusEl        = document.getElementById( 'grayfox-footer-status' );
			resetFooterBtn.disabled = true;
			if ( statusEl ) statusEl.textContent = 'Resetting…';

			ajax( 'grayfox_reset_footer', nonces.resetFooter )
				.then( function ( res ) {
					resetFooterBtn.disabled = false;
					if ( res.success ) {
						if ( statusEl ) statusEl.textContent = 'Reset to theme default.';
						initFooterStep();
					} else {
						if ( statusEl ) statusEl.textContent = 'Reset failed.';
					}
				} )
				.catch( function () {
					resetFooterBtn.disabled = false;
					if ( statusEl ) statusEl.textContent = 'Network error.';
				} );
		} );
	}

	const suggestFooterBtn = document.getElementById( 'grayfox-suggest-footer' );
	if ( suggestFooterBtn ) {
		suggestFooterBtn.addEventListener( 'click', function () {
			const statusEl         = document.getElementById( 'grayfox-suggest-footer-status' );
			suggestFooterBtn.disabled = true;
			if ( statusEl ) statusEl.textContent = 'Generating suggestions…';

			ajax( 'grayfox_suggest_footer_links', nonces.suggestFooterLinks )
				.then( function ( res ) {
					suggestFooterBtn.disabled = false;
					if ( statusEl ) statusEl.textContent = '';
					if ( res.success && res.data ) {
						applyFooterSuggestions( 'header', res.data.header_nav || [] );
						applyFooterSuggestions( 'footer', res.data.footer_nav || [] );
					} else {
						if ( statusEl ) statusEl.textContent = 'Could not generate suggestions.';
					}
				} )
				.catch( function () {
					suggestFooterBtn.disabled = false;
					if ( statusEl ) statusEl.textContent = 'Network error.';
				} );
		} );
	}

	function applyFooterSuggestions( location, items ) {
		const colEl = document.querySelector(
			'#grayfox-footer-columns [data-location="' + location + '"]'
		);
		if ( ! colEl ) return;
		const listEl = colEl.querySelector( '.grayfox-footer-list' );
		if ( ! listEl ) return;
		listEl.innerHTML = '';
		items.forEach( function ( item ) { addItemToList( listEl, item ); } );
	}

	// -------------------------------------------------------------------------
	// Step 7 — Site audit
	// -------------------------------------------------------------------------

	const badgeStates = {
		idle:   { bg: '#ddd',     color: '#555',    text: 'Not scanned' },
		pass:   { bg: '#d4edda',  color: '#1e6c38', text: '✓ All clear' },
		issues: { bg: '#fff3cd',  color: '#856404', text: 'Issues found' },
	};

	function setBadge( sectionEl, status, issueCount ) {
		const badge = sectionEl.querySelector( '.grayfox-audit-badge' );
		if ( ! badge ) return;
		const cfg = badgeStates[ status ] || badgeStates.idle;
		badge.dataset.status    = status;
		badge.style.background  = cfg.bg;
		badge.style.color       = cfg.color;
		badge.textContent = ( status === 'issues' && issueCount > 0 )
			? issueCount + ' issue' + ( issueCount === 1 ? '' : 's' ) + ' found'
			: cfg.text;
	}

	let auditAllPages = [];

	function renderAuditSection( sectionEl, data ) {
		if ( ! sectionEl || ! data ) return;

		const issues  = data.issues || [];
		const section = sectionEl.dataset.section;

		setBadge( sectionEl, data.status || 'idle', issues.length );

		const headRow = sectionEl.querySelector( 'thead tr' );
		if ( headRow ) {
			headRow.innerHTML =
				'<th style="width:25%;">Page</th>' +
				'<th>Issue</th>' +
				'<th style="width:12%;text-align:center;">Severity</th>';
		}

		const tbody = sectionEl.querySelector( 'tbody' );
		if ( tbody ) {
			tbody.innerHTML = '';
			if ( issues.length === 0 ) {
				const emptyRow = document.createElement( 'tr' );
				emptyRow.innerHTML = '<td colspan="3" style="color:#46b450;text-align:center;">No issues found.</td>';
				tbody.appendChild( emptyRow );
			} else {
				issues.forEach( function ( issue ) {
					const row       = document.createElement( 'tr' );
					const severity  = issue.severity === 'error'
						? '<span style="color:#d63638;font-weight:600;">Error</span>'
						: '<span style="color:#996800;">Warning</span>';
					const postId    = issue.post_id || 0;
					const editUrl   = postId ? '/wp-admin/post.php?post=' + postId + '&action=edit' : '';
					const pageCell  = postId
						? '<a href="' + editUrl + '" target="_blank">' + escapeHtml( issue.title || '' ) + '</a>'
						: escapeHtml( issue.title || 'Site' );

					let issueCell = escapeHtml( issue.issue || '' );

					if ( section === 'broken_links' && issue.button_label && issue.fix_type === 'llm' ) {
						const pageOptions = ( auditAllPages || [] ).map( function ( p ) {
							return '<option value="' + escapeHtml( p.url ) + '">' + escapeHtml( p.title ) + '</option>';
						} ).join( '' );
						issueCell += '<div style="margin-top:6px;display:flex;align-items:center;gap:6px;flex-wrap:wrap;">' +
							'<span style="font-size:11px;color:#888;">Set URL:</span>' +
							'<select class="grayfox-manual-link-select" style="font-size:12px;">' +
							'<option value="">— select a page —</option>' + pageOptions + '</select>' +
							'<span style="font-size:11px;color:#888;">or</span>' +
							'<input type="text" class="grayfox-manual-link-input" placeholder="https://..." style="font-size:12px;width:160px;">' +
							'<button class="grayfox-apply-manual-link button button-small"' +
							' data-post-id="' + postId + '"' +
							' data-label="' + escapeHtml( issue.button_label ) + '"' +
							' data-section="broken_links">Apply</button>' +
							'<span class="grayfox-manual-link-status" style="font-size:11px;color:#666;"></span>' +
							'</div>';
					}

					row.innerHTML = '<td>' + pageCell + '</td><td>' + issueCell +
						'</td><td style="text-align:center;">' + severity + '</td>';
					tbody.appendChild( row );
				} );
			}
		}

		const bodyEl = sectionEl.querySelector( '.grayfox-audit-section-body' );
		if ( bodyEl ) bodyEl.style.display = issues.length > 0 ? '' : 'none';

		const fixBtn = sectionEl.querySelector( '.grayfox-fix-section' );
		if ( fixBtn ) {
			const hasAutoFix = issues.some( function ( i ) { return i.fixable && i.fix_type === 'auto'; } );
			fixBtn.style.display = ( issues.length > 0 && hasAutoFix ) ? '' : 'none';
			fixBtn.disabled      = false;
		}

		const llmBtn = sectionEl.querySelector( '.grayfox-llm-assist' );
		if ( llmBtn ) {
			const hasLlmFix = issues.some( function ( i ) { return i.fixable && i.fix_type === 'llm'; } );
			llmBtn.style.display = ( issues.length > 0 && hasLlmFix ) ? '' : 'none';
		}

		if ( section === 'seo' && issues.length > 0 ) {
			const configureEl = document.getElementById( 'grayfox-seo-configure' );
			const upgradeEl   = document.getElementById( 'grayfox-seo-upgrade' );
			if ( configureEl ) configureEl.style.display = '';
			if ( upgradeEl )   upgradeEl.style.display   = '';
		}
	}

	function renderAuditResults( data ) {
		if ( ! data ) return;
		if ( data.all_pages ) auditAllPages = data.all_pages;

		const sections = data.sections || data;
		Object.keys( sections ).forEach( function ( key ) {
			const sectionEl = document.querySelector( '.grayfox-audit-section[data-section="' + key + '"]' );
			renderAuditSection( sectionEl, sections[ key ] );
		} );
	}

	function loadAuditResults() {
		ajax( 'grayfox_get_audit_results', nonces.getAuditResults )
			.then( function ( res ) {
				if ( res.success && res.data && res.data.status === 'complete' ) {
					renderAuditResults( res.data );
				}
			} )
			.catch( function () {} );
	}

	const runAuditBtn = document.getElementById( 'grayfox-run-audit' );
	if ( runAuditBtn ) {
		runAuditBtn.addEventListener( 'click', function () {
			const statusEl       = document.getElementById( 'grayfox-audit-status' );
			runAuditBtn.disabled = true;
			if ( statusEl ) statusEl.textContent = 'Scanning…';

			document.querySelectorAll( '.grayfox-audit-badge' ).forEach( function ( badge ) {
				badge.style.background = '#e6f0fb';
				badge.style.color      = '#2271b1';
				badge.textContent      = '⟳ Scanning…';
			} );

			ajax( 'grayfox_run_site_audit', nonces.runSiteAudit )
				.then( function ( res ) {
					runAuditBtn.disabled = false;
					if ( statusEl ) statusEl.textContent = 'Scan complete.';
					if ( res.success && res.data ) {
						renderAuditResults( res.data );
					} else {
						if ( statusEl ) {
							statusEl.textContent = res.data && typeof res.data === 'string'
								? res.data
								: 'Scan failed.';
						}
					}
				} )
				.catch( function () {
					runAuditBtn.disabled = false;
					if ( statusEl ) statusEl.textContent = 'Network error.';
				} );
		} );
	}

	document.querySelectorAll( '.grayfox-fix-section' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			const section   = this.dataset.section;
			const sectionEl = document.querySelector( '.grayfox-audit-section[data-section="' + section + '"]' );
			const statusEl  = sectionEl ? sectionEl.querySelector( '.grayfox-fix-status' ) : null;
			btn.disabled = true;
			if ( statusEl ) statusEl.textContent = 'Fixing…';

			ajax( 'grayfox_fix_audit_section', nonces.fixAuditSection, { section } )
				.then( function ( res ) {
					btn.disabled = false;
					if ( statusEl ) statusEl.textContent = '';
					if ( res.success && res.data ) {
						renderAuditSection( sectionEl, res.data.result );
						const undoBtn = sectionEl ? sectionEl.querySelector( '.grayfox-undo-fix' ) : null;
						if ( undoBtn ) undoBtn.style.display = '';
					} else {
						if ( statusEl ) statusEl.textContent = 'Fix failed.';
					}
				} )
				.catch( function () {
					btn.disabled = false;
					if ( statusEl ) statusEl.textContent = 'Network error.';
				} );
		} );
	} );

	document.querySelectorAll( '.grayfox-undo-fix' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			const section   = this.dataset.section;
			const sectionEl = document.querySelector( '.grayfox-audit-section[data-section="' + section + '"]' );
			const statusEl  = sectionEl ? sectionEl.querySelector( '.grayfox-fix-status' ) : null;
			const label     = section.replace( /_/g, ' ' );

			if ( ! confirm( 'Restore the original content for the ' + label + ' section? This will undo the last fix.' ) ) return;

			btn.disabled = true;
			if ( statusEl ) statusEl.textContent = 'Restoring…';

			ajax( 'grayfox_undo_audit_fix', nonces.undoAuditFix, { section } )
				.then( function ( res ) {
					btn.disabled = false;
					if ( statusEl ) statusEl.textContent = '';
					if ( res.success && res.data ) {
						renderAuditSection( sectionEl, res.data.result );
						btn.style.display = 'none';
					} else {
						if ( statusEl ) statusEl.textContent = 'Restore failed.';
					}
				} )
				.catch( function () {
					btn.disabled = false;
					if ( statusEl ) statusEl.textContent = 'Network error.';
				} );
		} );
	} );

	// LLM assist panel — open + load suggestions.
	document.querySelectorAll( '.grayfox-llm-assist' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			const section   = this.dataset.section;
			const sectionEl = document.querySelector( '.grayfox-audit-section[data-section="' + section + '"]' );
			if ( ! sectionEl ) return;
			const panelEl = sectionEl.querySelector( '.grayfox-llm-panel' );
			if ( ! panelEl ) return;

			const nonceMap = {
				accessibility:   nonces.llmGenerateAltText,
				broken_links:    nonces.llmSuggestLinkTargets,
				content_quality: nonces.llmReplacePlaceholders,
			};
			const actionMap = {
				accessibility:   'grayfox_llm_generate_alt_text',
				broken_links:    'grayfox_llm_suggest_link_targets',
				content_quality: 'grayfox_llm_replace_placeholders',
			};
			const nonce  = nonceMap[ section ];
			const action = actionMap[ section ];
			if ( ! nonce || ! action ) return;

			panelEl.style.display = '';
			const loadingEl = panelEl.querySelector( '.grayfox-llm-panel-loading' );
			const bodyEl    = panelEl.querySelector( '.grayfox-llm-panel-body' );
			if ( loadingEl ) loadingEl.style.display = '';
			if ( bodyEl )    bodyEl.style.display    = 'none';

			btn.disabled = true;
			ajax( action, nonce )
				.then( function ( res ) {
					btn.disabled = false;
					if ( loadingEl ) loadingEl.style.display = 'none';
					if ( res.success && res.data && res.data.suggestions ) {
						renderLlmSuggestions( panelEl, section, res.data.suggestions );
						if ( bodyEl ) bodyEl.style.display = '';
					} else {
						if ( loadingEl ) {
							loadingEl.textContent  = res.data && typeof res.data === 'string'
								? res.data
								: 'No suggestions available.';
							loadingEl.style.display = '';
						}
					}
				} )
				.catch( function () {
					btn.disabled = false;
					if ( loadingEl ) {
						loadingEl.textContent  = 'Network error.';
						loadingEl.style.display = '';
					}
				} );
		} );
	} );

	function renderLlmSuggestions( panelEl, section, suggestions ) {
		const tbody = panelEl.querySelector( '.grayfox-llm-suggestions-body' );
		if ( ! tbody ) return;

		tbody.innerHTML = '';

		if ( ! suggestions.length ) {
			const emptyRow = document.createElement( 'tr' );
			emptyRow.innerHTML = '<td colspan="4" style="text-align:center;color:#46b450;">' +
				'No suggestions — nothing to fix.</td>';
			tbody.appendChild( emptyRow );
			return;
		}

		suggestions.forEach( function ( suggestion, idx ) {
			const row        = document.createElement( 'tr' );
			const confidence = suggestion.confidence || '';
			const confColor  = confidence === 'high' ? '#1e6c38'
				: confidence === 'medium' ? '#856404'
				: '#555';
			const checked    = confidence === 'high' ? 'checked' : '';

			let pageLabel = escapeHtml( suggestion.page_title || '' );
			if ( suggestion.button_label ) pageLabel += ' — <em>Button: ' + escapeHtml( suggestion.button_label ) + '</em>';
			if ( suggestion.filename )     pageLabel += ' — <em>' + escapeHtml( suggestion.filename ) + '</em>';
			if ( suggestion.original ) {
				pageLabel += '<br><small style="color:#888;">' +
					escapeHtml( suggestion.original.substring( 0, 60 ) ) + '</small>';
			}

			let suggestionCell = '';
			if ( suggestion.alt )           suggestionCell = '<code>' + escapeHtml( suggestion.alt ) + '</code>';
			if ( suggestion.suggested_url ) {
				suggestionCell = '<a href="' + escapeHtml( suggestion.suggested_url ) +
					'" target="_blank" style="word-break:break-all;">' +
					escapeHtml( suggestion.suggested_url ) + '</a>';
			}
			if ( suggestion.replacement )   suggestionCell = escapeHtml( suggestion.replacement );
			if ( suggestion.reason ) {
				suggestionCell += '<br><small style="color:#888;">' + escapeHtml( suggestion.reason ) + '</small>';
			}

			row.innerHTML =
				'<td style="text-align:center;">' +
				'<input type="checkbox" class="grayfox-llm-item-check" data-idx="' + idx + '" ' + checked + '></td>' +
				'<td style="font-size:12px;">' + pageLabel + '</td>' +
				'<td style="font-size:12px;">' + suggestionCell + '</td>' +
				'<td style="color:' + confColor + ';font-size:12px;">' + escapeHtml( confidence ) + '</td>';
			tbody.appendChild( row );
		} );

		panelEl._llmSuggestions = suggestions;
	}

	document.querySelectorAll( '.grayfox-llm-select-all' ).forEach( function ( chk ) {
		chk.addEventListener( 'change', function () {
			const panelEl = this.closest( '.grayfox-llm-panel' );
			if ( panelEl ) {
				panelEl.querySelectorAll( '.grayfox-llm-item-check' ).forEach( function ( item ) {
					item.checked = chk.checked;
				} );
			}
		} );
	} );

	document.querySelectorAll( '.grayfox-apply-llm' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			const section   = this.dataset.section;
			const sectionEl = document.querySelector( '.grayfox-audit-section[data-section="' + section + '"]' );
			const panelEl   = sectionEl ? sectionEl.querySelector( '.grayfox-llm-panel' ) : null;
			const statusEl  = panelEl ? panelEl.querySelector( '.grayfox-llm-apply-status' ) : null;
			if ( ! panelEl ) return;

			const allSuggestions = panelEl._llmSuggestions || [];
			const selected = [];
			panelEl.querySelectorAll( '.grayfox-llm-item-check' ).forEach( function ( chk ) {
				if ( chk.checked ) {
					const idx = parseInt( chk.dataset.idx, 10 );
					if ( allSuggestions[ idx ] ) selected.push( allSuggestions[ idx ] );
				}
			} );

			if ( ! selected.length ) {
				if ( statusEl ) statusEl.textContent = 'Select at least one suggestion first.';
				return;
			}

			btn.disabled = true;
			if ( statusEl ) statusEl.textContent = 'Applying…';

			ajax( 'grayfox_apply_llm_fixes', nonces.applyLlmFixes, {
				section: section,
				fixes:   JSON.stringify( selected ),
			} )
				.then( function ( res ) {
					btn.disabled = false;
					if ( statusEl ) statusEl.textContent = '';
					if ( res.success && res.data ) {
						renderAuditSection( sectionEl, res.data.result );
						panelEl.style.display = 'none';
						const undoBtn = sectionEl ? sectionEl.querySelector( '.grayfox-undo-fix' ) : null;
						if ( undoBtn ) undoBtn.style.display = '';
					} else {
						if ( statusEl ) statusEl.textContent = 'Apply failed.';
					}
				} )
				.catch( function () {
					btn.disabled = false;
					if ( statusEl ) statusEl.textContent = 'Network error.';
				} );
		} );
	} );

	document.querySelectorAll( '.grayfox-close-llm-panel' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			const section = this.dataset.section;
			const sectionEl = document.querySelector( '.grayfox-audit-section[data-section="' + section + '"]' );
			const panelEl   = sectionEl ? sectionEl.querySelector( '.grayfox-llm-panel' ) : null;
			if ( panelEl ) panelEl.style.display = 'none';
		} );
	} );

	// Manual link apply (broken_links inline UI, delegated because rows are dynamic).
	document.addEventListener( 'click', function ( e ) {
		const btn = e.target.closest( '.grayfox-apply-manual-link' );
		if ( ! btn ) return;

		const row       = btn.closest( 'tr' );
		const statusEl  = row ? row.querySelector( '.grayfox-manual-link-status' ) : null;
		const selectEl  = row ? row.querySelector( '.grayfox-manual-link-select' ) : null;
		const inputEl   = row ? row.querySelector( '.grayfox-manual-link-input' ) : null;
		const postId    = parseInt( btn.dataset.postId, 10 ) || 0;
		const label     = btn.dataset.label || '';
		const section   = btn.dataset.section || 'broken_links';
		const url       = ( inputEl && inputEl.value.trim() ) || ( selectEl && selectEl.value ) || '';

		if ( ! url ) {
			if ( statusEl ) statusEl.textContent = 'Select a page or enter a URL first.';
			return;
		}

		btn.disabled = true;
		if ( statusEl ) statusEl.textContent = 'Applying…';

		const fix = { post_id: postId, button_label: label, suggested_url: url, page_title: '' };

		ajax( 'grayfox_apply_llm_fixes', nonces.applyLlmFixes, {
			section: section,
			fixes:   JSON.stringify( [ fix ] ),
		} )
			.then( function ( res ) {
				btn.disabled = false;
				if ( res.success && res.data ) {
					const sectionEl = document.querySelector( '.grayfox-audit-section[data-section="' + section + '"]' );
					if ( sectionEl ) renderAuditSection( sectionEl, res.data.result );
					const undoBtn = sectionEl ? sectionEl.querySelector( '.grayfox-undo-fix' ) : null;
					if ( undoBtn ) undoBtn.style.display = '';
				} else {
					if ( statusEl ) statusEl.textContent = 'Failed to apply.';
				}
			} )
			.catch( function () {
				btn.disabled = false;
				if ( statusEl ) statusEl.textContent = 'Network error.';
			} );
	} );

	// Collapsible audit section headers.
	document.querySelectorAll( '.grayfox-audit-section-header' ).forEach( function ( header ) {
		header.addEventListener( 'click', function () {
			const body = this.nextElementSibling;
			if ( body ) body.style.display = body.style.display === 'none' ? '' : 'none';
		} );
	} );

	// -------------------------------------------------------------------------
	// Utility
	// -------------------------------------------------------------------------

	function escapeHtml( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

	// -------------------------------------------------------------------------
	// On load — resume generation progress if a build is running.
	// -------------------------------------------------------------------------

	ajax( 'grayfox_get_build_progress', nonces.getBuildProgress )
		.then( function ( res ) {
			if ( res.success && res.data && res.data.status === 'running' ) {
				goToStep( 4 );
				document.getElementById( 'grayfox-progress-block' ).style.display = '';
				if ( ( res.data.completed || 0 ) === 0 ) genStartTime = Date.now();
				renderProgress( res.data );
				startProgressPolling();
			}
		} )
		.catch( function () {} );

} )();
