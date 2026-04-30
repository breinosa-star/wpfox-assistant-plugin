/**
 * GrayFox Theme Builder — admin JS
 *
 * Handles the 4-step theme builder UI:
 *   Step 1 — Logo upload + brand guidelines
 *   Step 2 — AI-generated brand profile review/edit
 *   Step 3 — Create theme
 *   Step 4 — Done / theme manager
 *
 * Built with: npm run build:theme-builder
 * Output:     assets/dist/grayfox-theme-builder.min.js
 */

( () => {
	( function () {
		'use strict';

		const l10n     = window.GrayFoxThemeBuilderL10n || {};
		const ajaxUrl  = l10n.ajaxUrl  || '/wp-admin/admin-ajax.php';
		const nonces   = l10n.nonces   || {};
		const maxThemes = l10n.maxThemes || 3;
		const supportsVision = !! l10n.providerSupportsVision;

		let logoAttachmentId = 0;
		let logoAnalysis     = null;
		let savedProfile     = null;

		const qs = ( selector, ctx = document ) => ctx.querySelector( selector );

		// ── Bootstrap ────────────────────────────────────────────────────────

		document.addEventListener( 'DOMContentLoaded', function () {
			const root = qs( '#grayfox-theme-builder' );
			if ( ! root ) return;

			const initialStep = parseInt( root.dataset.initialStep || '1', 10 );

			initStepTabs();
			initStep1();
			initStep2();
			initStep3();
			initStep4();
			goToStep( initialStep );

			if ( initialStep >= 3 ) {
				initStep3Preview();
			}
		} );

		// ── Step navigation ───────────────────────────────────────────────────

		function goToStep( step ) {
			document.querySelectorAll( '.grayfox-tb-step' ).forEach( el => {
				el.style.display = parseInt( el.dataset.step, 10 ) === step ? '' : 'none';
			} );
			document.querySelectorAll( '.grayfox-tb-step-tab' ).forEach( el => {
				const active = parseInt( el.dataset.step, 10 ) === step;
				el.style.borderBottomColor = active ? '#2271b1' : 'transparent';
				el.style.color             = active ? '#2271b1' : '#555';
			} );
		}

		function initStepTabs() {
			document.querySelectorAll( '.grayfox-tb-step-tab' ).forEach( tab => {
				tab.addEventListener( 'click', () => goToStep( parseInt( tab.dataset.step, 10 ) ) );
			} );
		}

		// ── Step 1: Logo + guidelines ─────────────────────────────────────────

		function initStep1() {
			const btnSelect   = qs( '#grayfox-tb-logo-select' );
			const btnRemove   = qs( '#grayfox-tb-logo-remove' );
			const inputLogoId = qs( '#grayfox-tb-logo-id' );
			const imgEl       = qs( '#grayfox-tb-logo-img' );
			const preview     = qs( '#grayfox-tb-logo-preview' );
			const skipToggle  = qs( '#grayfox-tb-skip-branding' );
			const btnContinue = qs( '#grayfox-tb-step1-continue' );
			const analyzing   = qs( '#grayfox-tb-analyzing-status' );

			if ( btnSelect && typeof wp !== 'undefined' && wp.media ) {
				btnSelect.addEventListener( 'click', () => {
					const frame = wp.media( {
						title:    'Select Logo',
						button:   { text: 'Use this logo' },
						multiple: false,
						library:  { type: 'image' },
					} );

					frame.on( 'select', function () {
						const attachment = frame.state().get( 'selection' ).first().toJSON();
						logoAttachmentId = attachment.id;
						if ( inputLogoId ) inputLogoId.value = attachment.id;
						if ( imgEl ) imgEl.src = attachment.url;
						if ( preview ) preview.style.display = '';
						if ( btnRemove ) btnRemove.style.display = '';

						const analysisStatus = qs( '#grayfox-tb-logo-analysis-status' );
						if ( analysisStatus ) {
							analysisStatus.style.display = 'none';
							analysisStatus.innerHTML     = '';
						}
					} );

					frame.open();
				} );
			}

			if ( btnRemove ) {
				btnRemove.addEventListener( 'click', () => {
					logoAttachmentId = 0;
					logoAnalysis     = null;
					if ( inputLogoId ) inputLogoId.value = '';
					if ( imgEl ) imgEl.src = '';
					if ( preview ) preview.style.display = 'none';
					btnRemove.style.display = 'none';

					const analysisStatus = qs( '#grayfox-tb-logo-analysis-status' );
					if ( analysisStatus ) {
						analysisStatus.style.display = 'none';
						analysisStatus.innerHTML     = '';
					}
				} );
			}

			if ( skipToggle ) {
				skipToggle.addEventListener( 'change', () => {
					const skip = skipToggle.checked;
					if ( btnSelect ) btnSelect.disabled = skip || ! supportsVision;
					if ( btnRemove ) btnRemove.disabled = skip;
					const guidelines = qs( '#grayfox-tb-guidelines' );
					if ( guidelines ) guidelines.disabled = skip;
				} );
			}

			if ( btnContinue ) {
				btnContinue.addEventListener( 'click', async () => {
					const skip = skipToggle && skipToggle.checked;

					if ( logoAttachmentId && supportsVision && ! skip ) {
						if ( analyzing ) analyzing.style.display = '';
						btnContinue.disabled = true;

						logoAnalysis = await analyzeLogo( logoAttachmentId );

						if ( analyzing ) analyzing.style.display = 'none';
						btnContinue.disabled = false;

						const analysisStatus = qs( '#grayfox-tb-logo-analysis-status' );
						if ( analysisStatus ) {
							analysisStatus.style.display = '';
							if ( logoAnalysis && ! logoAnalysis.skipped ) {
								analysisStatus.innerHTML = '<span style="color:#3c763d;">✓ Logo analyzed successfully.</span>';
							} else {
								const reason = logoAnalysis && logoAnalysis.reason ? logoAnalysis.reason : 'unsupported';
								analysisStatus.innerHTML = `<span style="color:#8a6d3b;">Logo analysis skipped (${ reason }). Theme will be generated from your knowledge base.</span>`;
							}
						}
					} else {
						logoAnalysis = null;
					}

					goToStep( 2 );
					generateProfile();
				} );
			}
		}

		// ── Step 2: Profile review ────────────────────────────────────────────

		function initStep2() {
			// Swatch click → open color picker.
			document.addEventListener( 'click', e => {
				const circle = e.target.closest( '.grayfox-tb-swatch-circle' );
				if ( ! circle ) return;
				const swatch = circle.closest( '.grayfox-tb-swatch' );
				if ( ! swatch ) return;
				const input = swatch.querySelector( '.grayfox-tb-color-input' );
				if ( input ) input.click();
			} );

			// Color input change → update swatch display.
			document.addEventListener( 'input', e => {
				if ( ! e.target.classList.contains( 'grayfox-tb-color-input' ) ) return;
				const key    = e.target.dataset.colorKey;
				const value  = e.target.value;
				const swatch = document.querySelector( `.grayfox-tb-swatch[data-color-key="${ key }"]` );
				if ( ! swatch ) return;
				const circle = swatch.querySelector( '.grayfox-tb-swatch-circle' );
				const label  = swatch.querySelector( '.grayfox-tb-hex-label' );
				if ( circle ) circle.style.background = value;
				if ( label )  label.textContent = value;
				updateLivePreview();
			} );

			// Style option selection.
			document.addEventListener( 'click', e => {
				const option = e.target.closest( '.grayfox-tb-style-option' );
				if ( ! option ) return;
				const param = option.dataset.param;
				const value = option.dataset.value;
				const group = document.querySelector( `.grayfox-tb-style-group[data-param="${ param }"]` );
				if ( ! group ) return;

				group.querySelectorAll( '.grayfox-tb-style-option' ).forEach( opt => {
					opt.style.border     = '2px solid #ddd';
					opt.style.background = '#fff';
					const div = opt.querySelector( 'div' );
					if ( div ) { div.style.fontWeight = '500'; div.style.color = '#333'; }
				} );

				option.style.border     = '2px solid #2271b1';
				option.style.background = '#f0f6ff';
				const activeDiv = option.querySelector( 'div' );
				if ( activeDiv ) { activeDiv.style.fontWeight = '700'; activeDiv.style.color = '#2271b1'; }

				const hiddenInput = group.querySelector( '.grayfox-tb-style-value' );
				if ( hiddenInput ) hiddenInput.value = value;
			} );

			// Font change panels.
			[ 'heading', 'body' ].forEach( type => {
				const link  = qs( `#grayfox-tb-${ type }-change-link` );
				const panel = qs( `#grayfox-tb-${ type }-change-panel` );
				if ( link && panel ) {
					link.addEventListener( 'click', e => {
						e.preventDefault();
						panel.style.display = panel.style.display === 'none' ? '' : 'none';
					} );
				}

				const applyBtn = qs( `#grayfox-tb-${ type }-font-apply` );
				if ( applyBtn ) {
					applyBtn.addEventListener( 'click', () => {
						const input = qs( `#grayfox-tb-${ type }-font-input` );
						if ( ! input ) return;
						const font = input.value.trim();
						if ( font ) {
							applyFont( type, font );
							if ( panel ) panel.style.display = 'none';
						}
					} );
				}

				const fontInput = qs( `#grayfox-tb-${ type }-font-input` );
				if ( fontInput ) {
					fontInput.addEventListener( 'keydown', e => {
						if ( e.key === 'Enter' ) {
							e.preventDefault();
							const apply = qs( `#grayfox-tb-${ type }-font-apply` );
							if ( apply ) apply.click();
						}
					} );
				}
			} );

			// Regenerate + retry buttons.
			const regenerateBtn = qs( '#grayfox-tb-regenerate' );
			if ( regenerateBtn ) regenerateBtn.addEventListener( 'click', () => generateProfile() );

			const retryBtn = qs( '#grayfox-tb-retry' );
			if ( retryBtn ) retryBtn.addEventListener( 'click', () => generateProfile() );

			// Save profile button.
			const saveBtn = qs( '#grayfox-tb-save-profile' );
			if ( saveBtn ) saveBtn.addEventListener( 'click', () => saveProfile() );
		}

		// ── Step 3: Create theme ──────────────────────────────────────────────

		function initStep3() {
			const applyBtn = qs( '#grayfox-tb-apply-btn' );
			if ( applyBtn ) applyBtn.addEventListener( 'click', () => applyTheme() );

			document.addEventListener( 'click', e => {
				const deleteBtn = e.target.closest( '.grayfox-tb-delete-theme' );
				if ( ! deleteBtn ) return;
				const slug = deleteBtn.dataset.slug;
				const name = deleteBtn.dataset.name || slug;
				if ( slug ) deleteTheme( slug, name );
			} );
		}

		function initStep3Preview() {
			const generatingState = qs( '#grayfox-tb-generating-state' );
			const resultsEl       = qs( '#grayfox-tb-results' );
			if ( generatingState ) generatingState.style.display = 'none';
			if ( resultsEl )       resultsEl.style.display       = '';

			const headingFont = ( qs( '#grayfox-tb-heading-font' ) || {} ).value;
			const bodyFont    = ( qs( '#grayfox-tb-body-font' )    || {} ).value;

			if ( headingFont ) {
				const label   = qs( '#grayfox-tb-heading-font-label' );
				if ( label ) label.textContent = headingFont;
				loadGoogleFont( headingFont, '400;600;700' );
				const preview = qs( '#grayfox-tb-heading-preview' );
				if ( preview ) preview.style.fontFamily = `"${ headingFont }", sans-serif`;
			}
			if ( bodyFont ) {
				const label   = qs( '#grayfox-tb-body-font-label' );
				if ( label ) label.textContent = bodyFont;
				loadGoogleFont( bodyFont, '400;500' );
				const preview = qs( '#grayfox-tb-body-preview' );
				if ( preview ) preview.style.fontFamily = `"${ bodyFont }", sans-serif`;
			}
		}

		// ── Step 4: Done ──────────────────────────────────────────────────────

		function initStep4() {
			const backBtn = qs( '#grayfox-tb-back-to-manager' );
			if ( backBtn ) backBtn.addEventListener( 'click', () => goToStep( 3 ) );
		}

		// ── AJAX: analyze logo ────────────────────────────────────────────────

		async function analyzeLogo( attachmentId ) {
			try {
				const data = new FormData();
				data.append( 'action',        'grayfox_tb_analyze_logo' );
				data.append( '_ajax_nonce',   nonces.analyzeLogo || '' );
				data.append( 'logo_attachment_id', attachmentId );

				const response = await fetch( ajaxUrl, { method: 'POST', body: data } );
				const json     = await response.json();
				return ( json.success && json.data.analysis ) ? json.data.analysis : null;
			} catch {
				return null;
			}
		}

		// ── AJAX: generate profile ────────────────────────────────────────────

		async function generateProfile() {
			const generatingState = qs( '#grayfox-tb-generating-state' );
			const resultsEl       = qs( '#grayfox-tb-results' );
			const errorEl         = qs( '#grayfox-tb-generate-error' );

			if ( generatingState ) generatingState.style.display = '';
			if ( resultsEl )       resultsEl.style.display       = 'none';
			if ( errorEl )         errorEl.style.display         = 'none';

			try {
				const guidelines = ( qs( '#grayfox-tb-guidelines' ) || {} ).value || '';
				const data = new FormData();
				data.append( 'action',          'grayfox_tb_generate_theme' );
				data.append( '_ajax_nonce',     nonces.generateTheme || '' );
				data.append( 'logo_analysis',   JSON.stringify( logoAnalysis || {} ) );
				data.append( 'brand_guidelines', guidelines );

				const response = await fetch( ajaxUrl, { method: 'POST', body: data } );
				const json     = await response.json();

				if ( ! json.success ) throw new Error( json.data || 'Generation failed.' );

				savedProfile = json.data.profile;
				renderProfile( savedProfile );

				if ( generatingState ) generatingState.style.display = 'none';
				if ( resultsEl )       resultsEl.style.display       = '';
			} catch ( err ) {
				if ( generatingState ) generatingState.style.display = 'none';
				if ( errorEl ) {
					const errText = qs( '#grayfox-tb-error-text' );
					if ( errText ) errText.textContent = err.message;
					errorEl.style.display = '';
				}
			}
		}

		// ── AJAX: save profile ────────────────────────────────────────────────

		async function saveProfile() {
			const saveBtn    = qs( '#grayfox-tb-save-profile' );
			const saveStatus = qs( '#grayfox-tb-save-status' );

			if ( saveBtn ) saveBtn.disabled = true;
			if ( saveStatus ) { saveStatus.style.display = ''; saveStatus.textContent = 'Saving…'; }

			const profile = collectProfile();

			try {
				const data = new FormData();
				data.append( 'action',      'grayfox_tb_save_brand_profile' );
				data.append( '_ajax_nonce', nonces.saveBrandProfile || '' );
				data.append( 'profile',     JSON.stringify( profile ) );

				const response = await fetch( ajaxUrl, { method: 'POST', body: data } );
				const json     = await response.json();

				if ( ! json.success ) throw new Error( json.data || 'Save failed.' );

				if ( saveStatus ) {
					saveStatus.textContent = '✓ Saved';
					setTimeout( () => { saveStatus.style.display = 'none'; }, 2000 );
				}

				updateStep3Summary( profile );
				goToStep( 3 );

				// Enable Create Theme button — it was PHP-disabled on load since no profile existed yet.
				const applyBtn = qs( '#grayfox-tb-apply-btn' );
				if ( applyBtn ) applyBtn.disabled = false;

			} catch ( err ) {
				if ( saveStatus ) saveStatus.textContent = '✗ ' + err.message;
			} finally {
				if ( saveBtn ) saveBtn.disabled = false;
			}
		}

		// ── AJAX: apply theme ─────────────────────────────────────────────────

		async function applyTheme() {
			const applyBtn    = qs( '#grayfox-tb-apply-btn' );
			const applyStatus = qs( '#grayfox-tb-apply-status' );
			const applyError  = qs( '#grayfox-tb-apply-error' );
			const elementorCb = qs( '#grayfox-tb-apply-elementor' );

			if ( applyBtn )    applyBtn.disabled = true;
			if ( applyStatus ) applyStatus.style.display = '';
			if ( applyError )  applyError.style.display  = 'none';

			try {
				const data = new FormData();
				data.append( 'action',          'grayfox_tb_apply_theme' );
				data.append( '_ajax_nonce',     nonces.applyTheme || '' );
				data.append( 'apply_elementor', elementorCb && ( elementorCb.checked || elementorCb.value === '1' ) ? '1' : '0' );

				const response = await fetch( ajaxUrl, { method: 'POST', body: data } );
				const json     = await response.json();

				if ( ! json.success ) {
					const d = json.data;
					throw new Error( d && d.message ? d.message : d || 'Theme creation failed.' );
				}

				const result      = json.data || {};
				const slug        = result.slug         || '';
				const displayName = result.display_name || slug;
				const visualStyle = result.visual_style || '';
				const generatedAt = result.generated_at || 0;
				const activateUrl = result.activate_url || result.themes_url || '';
				const remaining   = typeof result.remaining_slots === 'number' ? result.remaining_slots : 0;

				const doneDetail = qs( '#grayfox-tb-done-detail' );
				if ( doneDetail ) doneDetail.textContent = `"${ displayName }" has been written to your themes directory.`;

				const activateLink = qs( '#grayfox-tb-activate-link' );
				if ( activateLink && activateUrl ) activateLink.href = activateUrl;

				addThemeCard( slug, displayName, visualStyle, generatedAt, activateUrl, remaining );
				goToStep( 4 );

			} catch ( err ) {
				if ( applyError ) { applyError.textContent = err.message; applyError.style.display = ''; }
			} finally {
				if ( applyBtn )    applyBtn.disabled    = false;
				if ( applyStatus ) applyStatus.style.display = 'none';
			}
		}

		// ── AJAX: delete theme ────────────────────────────────────────────────

		async function deleteTheme( slug, name ) {
			if ( ! window.confirm( `Delete "${ name }"?\nThis permanently removes the theme files. This cannot be undone.` ) ) return;

			const deleteBtn = document.querySelector( `.grayfox-tb-delete-theme[data-slug="${ slug }"]` );
			if ( deleteBtn ) { deleteBtn.disabled = true; deleteBtn.textContent = 'Deleting…'; }

			try {
				const data = new FormData();
				data.append( 'action',      'grayfox_tb_delete_theme' );
				data.append( '_ajax_nonce', nonces.deleteTheme || '' );
				data.append( 'theme_slug',  slug );

				const response = await fetch( ajaxUrl, { method: 'POST', body: data } );
				const json     = await response.json();

				if ( ! json.success ) throw new Error( json.data || 'Could not delete theme.' );

				const card = document.querySelector( `.grayfox-tb-theme-card[data-slug="${ slug }"]` );
				if ( card ) card.remove();
				updateThemeCount();

			} catch ( err ) {
				if ( deleteBtn ) { deleteBtn.disabled = false; deleteBtn.textContent = 'Delete'; }
				window.alert( 'Error: ' + err.message );
			}
		}

		// ── Render: profile into Step 2 form ──────────────────────────────────

		function renderProfile( profile ) {
			const theme = profile.theme || profile;

			const rationaleEl = qs( '#grayfox-tb-rationale-text' );
			if ( rationaleEl ) rationaleEl.textContent = theme.rationale || profile.rationale || '';

			const colors = theme.colors || {};
			document.querySelectorAll( '.grayfox-tb-swatch' ).forEach( swatch => {
				const key   = swatch.dataset.colorKey;
				const value = colors[ key ];
				if ( ! value ) return;
				const circle = swatch.querySelector( '.grayfox-tb-swatch-circle' );
				const label  = swatch.querySelector( '.grayfox-tb-hex-label' );
				const input  = swatch.querySelector( '.grayfox-tb-color-input' );
				if ( circle ) circle.style.background = value;
				if ( label )  label.textContent       = value;
				if ( input )  input.value             = value;
			} );

			const typography = theme.typography || {};
			if ( typography.heading_font ) applyFont( 'heading', typography.heading_font );
			if ( typography.body_font )    applyFont( 'body',    typography.body_font );

			updateLivePreview();

			const styleOptions = {
				visual_style:  theme.style_archetype || profile.visual_style,
				spacing_style: theme.spacing_style   || profile.spacing_style,
			};
			Object.entries( styleOptions ).forEach( ( [ param, value ] ) => {
				if ( ! value ) return;
				const group = document.querySelector( `.grayfox-tb-style-group[data-param="${ param }"]` );
				if ( ! group ) return;

				group.querySelectorAll( '.grayfox-tb-style-option' ).forEach( opt => {
					const active = opt.dataset.value === value;
					opt.style.border     = active ? '2px solid #2271b1' : '2px solid #ddd';
					opt.style.background = active ? '#f0f6ff' : '#fff';
					opt.classList.toggle( 'is-active', active );
					const div = opt.querySelector( 'div' );
					if ( div ) { div.style.fontWeight = active ? '700' : '500'; div.style.color = active ? '#2271b1' : '#333'; }
				} );

				const hidden = group.querySelector( '.grayfox-tb-style-value' );
				if ( hidden ) hidden.value = value;
			} );
		}

		// ── Render: step 3 summary bar ────────────────────────────────────────

		function updateStep3Summary( profile ) {
			const colors     = profile.colors     || {};
			const typography = profile.typography || {};
			const style      = profile.visual_style || '';

			document.querySelectorAll( '.grayfox-tb-s3-dot' ).forEach( dot => {
				const color = colors[ dot.dataset.colorKey ];
				if ( color ) dot.style.background = color;
			} );

			const styleEl   = qs( '#grayfox-tb-s3-style' );
			const headingEl = qs( '#grayfox-tb-s3-heading-font' );
			const bodyEl    = qs( '#grayfox-tb-s3-body-font' );
			if ( styleEl )   styleEl.textContent   = style ? style.charAt( 0 ).toUpperCase() + style.slice( 1 ) : '—';
			if ( headingEl ) headingEl.textContent  = typography.heading_font || '—';
			if ( bodyEl )    bodyEl.textContent     = typography.body_font    || '—';

			const colorsRow = qs( '#grayfox-tb-s3-colors' );
			const metaRow   = qs( '#grayfox-tb-s3-meta' );
			if ( colorsRow ) colorsRow.style.display = '';
			if ( metaRow )   metaRow.style.display   = '';
		}

		// ── Render: add theme card to Step 3 list ─────────────────────────────

		function addThemeCard( slug, displayName, visualStyle, generatedAt, activateUrl ) {
			let list = qs( '#grayfox-tb-themes-list' );

			if ( ! list ) {
				const noThemes = qs( '#grayfox-tb-no-themes' );
				if ( noThemes ) noThemes.remove();

				list = document.createElement( 'div' );
				list.id        = 'grayfox-tb-themes-list';
				list.style.cssText = 'max-width:720px;margin:20px 0 28px;';

				const heading = document.createElement( 'div' );
				heading.style.cssText = 'font-size:13px;font-weight:600;color:#23282d;margin-bottom:10px;';
				heading.innerHTML = 'Generated Themes <span id="grayfox-tb-themes-count" style="font-weight:400;color:#888;margin-left:6px;"></span>';
				list.appendChild( heading );

				const createSection = qs( '#grayfox-tb-create-section' );
				if ( createSection ) createSection.parentNode.insertBefore( list, createSection );
			}

			const date = generatedAt ? new Date( generatedAt * 1000 ).toLocaleDateString() : new Date().toLocaleDateString();
			const card = document.createElement( 'div' );
			card.className    = 'grayfox-tb-theme-card';
			card.dataset.slug = slug;
			card.style.cssText = 'display:flex;align-items:center;gap:16px;padding:14px 18px;margin-bottom:8px;border:2px solid #ddd;border-radius:6px;background:#fff;';
			card.innerHTML = `
				<div style="flex:1;min-width:0;">
					<div style="font-weight:600;font-size:13px;color:#1e1e1e;">${ escHtml( displayName ) }</div>
					<div style="font-size:11px;color:#888;margin-top:2px;">
						${ visualStyle ? `<span style="background:#f0f0f0;border-radius:3px;padding:1px 6px;margin-right:6px;">${ escHtml( visualStyle.charAt( 0 ).toUpperCase() + visualStyle.slice( 1 ) ) }</span>` : '' }
						${ escHtml( date ) }
					</div>
				</div>
				<a href="${ activateUrl }" class="button button-small" style="flex-shrink:0;">Activate</a>
				<button type="button" class="grayfox-tb-delete-theme button button-small" data-slug="${ escHtml( slug ) }" data-name="${ escHtml( displayName ) }" style="flex-shrink:0;color:#d63638;border-color:#d63638;">Delete</button>
			`;
			list.appendChild( card );
			updateThemeCount();
		}

		function updateThemeCount() {
			const count     = document.querySelectorAll( '.grayfox-tb-theme-card' ).length;
			const countEl   = qs( '#grayfox-tb-themes-count' );
			if ( countEl ) countEl.textContent = `${ count } / ${ maxThemes }`;

			const atLimit       = count >= maxThemes;
			const createSection = qs( '#grayfox-tb-create-section' );
			const limitNotice   = qs( '#grayfox-tb-limit-notice' );
			if ( createSection ) createSection.style.display = atLimit ? 'none' : '';
			if ( limitNotice )   limitNotice.style.display   = atLimit ? '' : 'none';
		}

		// ── Live preview ──────────────────────────────────────────────────────

		function updateLivePreview() {
			const preview = document.getElementById( 'grayfox-tb-live-preview' );
			if ( ! preview ) return;

			const colors = {};
			document.querySelectorAll( '.grayfox-tb-color-input' ).forEach( input => {
				colors[ input.dataset.colorKey ] = input.value;
			} );

			const headingFont = ( qs( '#grayfox-tb-heading-font' ) || {} ).value || 'Inter';
			const bodyFont    = ( qs( '#grayfox-tb-body-font' )    || {} ).value || 'Inter';

			preview.style.setProperty( '--gf-p',  colors.primary    || '#1a2e4a' );
			preview.style.setProperty( '--gf-s',  colors.secondary  || '#2d6a8f' );
			preview.style.setProperty( '--gf-a',  colors.accent     || '#f4a723' );
			preview.style.setProperty( '--gf-bg', colors.background || '#ffffff' );
			preview.style.setProperty( '--gf-t',  colors.text       || '#1e1e1e' );
			preview.style.setProperty( '--gf-m',  colors.muted      || '#6b7280' );
			preview.style.setProperty( '--gf-hf', `"${ headingFont }", sans-serif` );
			preview.style.setProperty( '--gf-bf', `"${ bodyFont }", sans-serif` );

			const prevHeading = qs( '#grayfox-tb-prev-heading-font' );
			const prevBody    = qs( '#grayfox-tb-prev-body-font' );
			if ( prevHeading ) prevHeading.textContent = headingFont;
			if ( prevBody )    prevBody.textContent    = bodyFont;
		}

		// ── Font helpers ──────────────────────────────────────────────────────

		function applyFont( type, fontName ) {
			const fontInput   = qs( `#grayfox-tb-${ type }-font` );
			const previewEl   = qs( `#grayfox-tb-${ type }-preview` );
			const labelEl     = qs( `#grayfox-tb-${ type }-font-label` );
			const panelInput  = qs( `#grayfox-tb-${ type }-font-input` );

			if ( fontInput )  fontInput.value              = fontName;
			if ( previewEl )  previewEl.style.fontFamily   = `"${ fontName }", sans-serif`;
			if ( labelEl )    labelEl.textContent           = fontName;
			if ( panelInput ) panelInput.value              = fontName;

			const weights = type === 'heading' ? '400;600;700' : '400;500';
			loadGoogleFont( fontName, weights );
			updateLivePreview();
		}

		const loadedFonts = new Set();

		function loadGoogleFont( fontName, weights = '400;700' ) {
			if ( ! fontName ) return;
			const key = fontName + ':' + weights;
			if ( loadedFonts.has( key ) ) return;
			loadedFonts.add( key );

			const link = document.createElement( 'link' );
			link.rel  = 'stylesheet';
			link.href = `https://fonts.googleapis.com/css2?family=${ encodeURIComponent( fontName ) }:wght@${ weights }&display=swap`;
			document.head.appendChild( link );
		}

		// ── Collect profile from form ─────────────────────────────────────────

		function collectProfile() {
			const colors = {};
			document.querySelectorAll( '.grayfox-tb-color-input' ).forEach( input => {
				colors[ input.dataset.colorKey ] = input.value;
			} );

			return Object.assign( {}, savedProfile || {}, {
				colors,
				typography: {
					heading_font:   ( qs( '#grayfox-tb-heading-font' ) || {} ).value || '',
					body_font:      ( qs( '#grayfox-tb-body-font' )    || {} ).value || '',
					heading_weight: '700',
					body_weight:    '400',
				},
				visual_style:        ( qs( '#grayfox-tb-visual-style' )  || {} ).value || 'clean',
				spacing_style:       ( qs( '#grayfox-tb-spacing-style' ) || {} ).value || 'comfortable',
				logo_attachment_id:  logoAttachmentId || 0,
				logo_analysis:       logoAnalysis || {},
			} );
		}

		// ── Utilities ─────────────────────────────────────────────────────────

		function escHtml( str ) {
			return String( str )
				.replace( /&/g,  '&amp;' )
				.replace( /</g,  '&lt;' )
				.replace( />/g,  '&gt;' )
				.replace( /"/g,  '&quot;' );
		}

	} )();
} )();
