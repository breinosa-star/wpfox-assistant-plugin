/**
 * GrayFox Site Builder — 5-step wizard JS.
 *
 * Consumed via wp_localize_script as window.GrayFoxSiteBuilderL10n:
 *   { ajaxUrl: string, nonces: { ... } }
 */

(function () {
	'use strict';

	const cfg      = window.GrayFoxSiteBuilderL10n || {};
	const ajaxUrl  = cfg.ajaxUrl || '';
	const nonces   = cfg.nonces  || {};

	let currentStep = 1;
	let envData     = null;      // populated after environment detection
	let pollTimer   = null;

	/* ------------------------------------------------------------------
	 * AJAX helper
	 * ------------------------------------------------------------------ */

	function post(action, nonce, extraData) {
		const data = new FormData();
		data.append('action', action);
		data.append('_ajax_nonce', nonce);
		if (extraData) {
			Object.keys(extraData).forEach(function (k) {
				data.append(k, extraData[k]);
			});
		}
		return fetch(ajaxUrl, { method: 'POST', body: data }).then(function (r) {
			return r.json();
		});
	}

	/* ------------------------------------------------------------------
	 * Step navigation
	 * ------------------------------------------------------------------ */

	function showStep(n) {
		currentStep = n;
		document.querySelectorAll('.grayfox-step').forEach(function (el) {
			el.style.display = parseInt(el.dataset.step, 10) === n ? '' : 'none';
		});
		document.querySelectorAll('.grayfox-step-tab').forEach(function (tab) {
			const active = parseInt(tab.dataset.step, 10) === n;
			tab.style.borderBottomColor = active ? '#2271b1' : 'transparent';
			tab.style.color             = active ? '#2271b1' : '#555';
		});
		// Auto-run step-specific init.
		if (n === 2) runEnvironmentDetection();
	}

	// Tab clicks
	document.querySelectorAll('.grayfox-step-tab').forEach(function (tab) {
		tab.addEventListener('click', function () {
			showStep(parseInt(this.dataset.step, 10));
		});
	});

	// Initial step.
	const initialStep = parseInt(
		document.querySelector('.grayfox-step-tab') ? 1 : 1,
		10
	);
	// Read PHP-set initial step from data attribute if present.
	const wrapEl = document.querySelector('.grayfox-admin-wrap');
	const phpInitial = wrapEl ? parseInt(wrapEl.dataset.initialStep || '1', 10) : 1;
	showStep(phpInitial || 1);

	/* ------------------------------------------------------------------
	 * Step 1 — Sitemap preview
	 * ------------------------------------------------------------------ */

	function renderSitemapEditor(pages, container) {
		container.innerHTML = '';
		const ul = buildPageList(pages);
		container.appendChild(ul);
	}

	function buildPageList(pages) {
		const ul = document.createElement('ul');
		ul.style.cssText = 'list-style:disc;padding-left:24px;margin:0;';
		(pages || []).forEach(function (page) {
			const li   = document.createElement('li');
			const inp  = document.createElement('input');
			inp.type   = 'text';
			inp.value  = page.title || '';
			inp.className = 'regular-text';
			inp.dataset.page = 'title';
			inp.style.marginBottom = '6px';
			inp.addEventListener('input', function () { page.title = this.value; });
			li.appendChild(inp);
			if (page.children && page.children.length) {
				li.appendChild(buildPageList(page.children));
			}
			ul.appendChild(li);
		});
		return ul;
	}

	const btnGenerate = document.getElementById('grayfox-generate-sitemap');
	if (btnGenerate) {
		btnGenerate.addEventListener('click', function () {
			const status  = document.getElementById('grayfox-sitemap-status');
			const preview = document.getElementById('grayfox-sitemap-preview');
			status.textContent = 'Generating preview…';
			btnGenerate.disabled = true;

			post('grayfox_generate_sitemap_preview', nonces.generateSitemapPreview)
				.then(function (resp) {
					btnGenerate.disabled = false;
					if (resp.success && resp.data) {
						status.textContent = '';
						preview.style.display = '';
						const noticeEl = document.getElementById('grayfox-sitemap-notice');
						if (noticeEl) noticeEl.textContent = resp.data.notice || '';
						const editor = document.getElementById('grayfox-sitemap-editor');
						window._grayfoxSitemapPages = resp.data.sitemap;
						renderSitemapEditor(resp.data.sitemap, editor);
					} else {
						status.textContent = (resp.data && typeof resp.data === 'string') ? resp.data : 'Failed to generate preview.';
					}
				})
				.catch(function () {
					btnGenerate.disabled = false;
					status.textContent = 'Network error.';
				});
		});
	}

	const btnSaveSitemap = document.getElementById('grayfox-save-sitemap');
	if (btnSaveSitemap) {
		btnSaveSitemap.addEventListener('click', function () {
			const pages = window._grayfoxSitemapPages;
			if (!pages) return;
			btnSaveSitemap.disabled = true;

			post('grayfox_save_sitemap', nonces.saveSitemap, {
				pages: JSON.stringify(pages),
			}).then(function (resp) {
				btnSaveSitemap.disabled = false;
				if (resp.success) {
					showStep(2);
				} else {
					alert((resp.data && typeof resp.data === 'string') ? resp.data : 'Save failed.');
				}
			});
		});
	}

	const btnUseSaved = document.getElementById('grayfox-use-saved-sitemap');
	if (btnUseSaved) {
		btnUseSaved.addEventListener('click', function () {
			showStep(2);
		});
	}

	/* ------------------------------------------------------------------
	 * Step 2 — Environment detection
	 * ------------------------------------------------------------------ */

	function runEnvironmentDetection() {
		const result = document.getElementById('grayfox-env-result');
		const btn    = document.getElementById('grayfox-env-continue');
		if (!result) return;
		result.innerHTML = '<em>Detecting environment…</em>';

		post('grayfox_detect_environment', nonces.detectEnvironment)
			.then(function (resp) {
				if (resp.success && resp.data) {
					envData = resp.data;

					// Build table with DOM APIs — no innerHTML with server values.
					const table = document.createElement('table');
					table.className = 'widefat';
					table.style.maxWidth = '500px';
					const tbody = document.createElement('tbody');

					function addRow(label, value) {
						const tr = document.createElement('tr');
						const tdL = document.createElement('td');
						tdL.textContent = label;
						const tdV = document.createElement('td');
						tdV.textContent = value;
						tr.appendChild(tdL);
						tr.appendChild(tdV);
						tbody.appendChild(tr);
					}

					addRow('Block Theme', envData.is_block_theme ? '✓ Yes' : 'No');
					addRow(
						'Elementor',
						envData.has_elementor
							? ('✓ Active (v' + envData.elementor_version + ')')
							: 'Not active'
					);
					if (envData.has_other_builder) {
						addRow('Other Builder', envData.other_builder_name + ' detected');
					}

					table.appendChild(tbody);
					result.innerHTML = '';
					result.appendChild(table);
					if (btn) btn.style.display = '';

					// Disable Elementor option if not available.
					const elementorInput = document.querySelector('input[value="elementor"]');
					const elementorLabel = document.getElementById('grayfox-elementor-label');
					if (elementorInput && elementorLabel && (!envData.has_elementor || !envData.elementor_version_ok)) {
						elementorInput.disabled = true;
						elementorLabel.style.opacity = '0.5';
						elementorLabel.title = 'Elementor 3.0.0+ is required';
					}
				} else {
					result.textContent = 'Could not detect environment.';
					if (btn) btn.style.display = '';
				}
			})
			.catch(function () {
				result.textContent = 'Network error.';
				if (btn) btn.style.display = '';
			});
	}

	const btnEnvContinue = document.getElementById('grayfox-env-continue');
	if (btnEnvContinue) {
		btnEnvContinue.addEventListener('click', function () {
			// Skip step 3 if neither Elementor nor a reason to choose — but keep it for user awareness.
			showStep(3);
		});
	}

	/* ------------------------------------------------------------------
	 * Step 3 — Format choice
	 * ------------------------------------------------------------------ */

	const btnSaveFormat = document.getElementById('grayfox-save-format');
	if (btnSaveFormat) {
		btnSaveFormat.addEventListener('click', function () {
			const selected = document.querySelector('input[name="grayfox_build_format"]:checked');
			const notice   = document.getElementById('grayfox-format-notice');
			if (!selected) {
				notice.textContent = 'Please select a format.';
				return;
			}
			btnSaveFormat.disabled = true;
			notice.textContent     = '';

			post('grayfox_set_build_format', nonces.setBuildFormat, { format: selected.value })
				.then(function (resp) {
					btnSaveFormat.disabled = false;
					if (resp.success) {
						showStep(4);
					} else {
						notice.textContent = (resp.data && typeof resp.data === 'string') ? resp.data : 'Could not save format.';
					}
				})
				.catch(function () {
					btnSaveFormat.disabled = false;
					notice.textContent = 'Network error.';
				});
		});
	}

	/* ------------------------------------------------------------------
	 * Step 4 — Generate
	 * ------------------------------------------------------------------ */

	const btnEstimate = document.getElementById('grayfox-estimate-cost');
	if (btnEstimate) {
		btnEstimate.addEventListener('click', function () {
			const result = document.getElementById('grayfox-estimate-result');
			btnEstimate.disabled = true;
			result.textContent   = 'Estimating…';

			post('grayfox_estimate_generation_cost', nonces.estimateGenerationCost)
				.then(function (resp) {
					btnEstimate.disabled = false;
					if (resp.success && resp.data) {
						const d = resp.data;
						// Use textContent to avoid XSS — server values are numbers/strings.
						result.textContent = d.page_count + ' pages · ~' +
							d.total_tokens.toLocaleString() + ' tokens · est. cost: ' + d.estimated_cost;
					} else {
						result.textContent = (resp.data && typeof resp.data === 'string') ? resp.data : 'Estimation failed.';
					}
				})
				.catch(function () {
					btnEstimate.disabled = false;
					result.textContent = 'Network error.';
				});
		});
	}

	const btnSaveUnsplash = document.getElementById('grayfox-save-unsplash');
	if (btnSaveUnsplash) {
		btnSaveUnsplash.addEventListener('click', function () {
			const key    = document.getElementById('grayfox-unsplash-key').value.trim();
			const status = document.getElementById('grayfox-unsplash-status');
			if (!key) { status.textContent = 'Enter a key first.'; return; }
			btnSaveUnsplash.disabled = true;
			status.textContent       = 'Saving…';

			post('grayfox_save_unsplash_key', nonces.saveUnsplashKey, { key: key })
				.then(function (resp) {
					btnSaveUnsplash.disabled = false;
					status.textContent = resp.success ? 'Saved!' : ((resp.data && typeof resp.data === 'string') ? resp.data : 'Failed.');
				})
				.catch(function () {
					btnSaveUnsplash.disabled = false;
					status.textContent = 'Network error.';
				});
		});
	}

	const btnStart = document.getElementById('grayfox-start-generation');
	if (btnStart) {
		btnStart.addEventListener('click', function () {
			if (!confirm('Start generating site pages? This will run in the background and may take a few minutes.')) return;
			btnStart.disabled = true;

			post('grayfox_start_site_generation', nonces.startSiteGeneration)
				.then(function (resp) {
					if (resp.success) {
						document.getElementById('grayfox-progress-block').style.display = '';
						startProgressPolling();
					} else {
						btnStart.disabled = false;
						alert((resp.data && typeof resp.data === 'string') ? resp.data : 'Could not start generation.');
					}
				})
				.catch(function () {
					btnStart.disabled = false;
					alert('Network error.');
				});
		});
	}

	function startProgressPolling() {
		if (pollTimer) clearInterval(pollTimer);
		pollTimer = setInterval(pollProgress, 2000);
	}

	function stopProgressPolling() {
		if (pollTimer) {
			clearInterval(pollTimer);
			pollTimer = null;
		}
	}

	function pollProgress() {
		post('grayfox_get_build_progress', nonces.getBuildProgress)
			.then(function (resp) {
				if (!resp.success || !resp.data) return;
				const d = resp.data;
				updateProgressUI(d);
				if (d.status === 'complete') {
					stopProgressPolling();
					setTimeout(function () { showStep(5); window.location.reload(); }, 1500);
				}
			})
			.catch(function () { /* silent */ });
	}

	function updateProgressUI(d) {
		const bar   = document.getElementById('grayfox-progress-bar');
		const text  = document.getElementById('grayfox-progress-text');
		const list  = document.getElementById('grayfox-progress-list');
		if (!bar || !text) return;

		const pct   = d.total > 0 ? Math.round((d.completed / d.total) * 100) : 0;
		bar.value   = pct;
		bar.max     = 100;
		text.textContent = d.completed + ' / ' + d.total + ' pages (' + pct + '%)';

		if (list && d.pages) {
			list.innerHTML = '';
			d.pages.forEach(function (page) {
				const li     = document.createElement('li');
				li.textContent = page.title + (page.status === 'complete' ? ' ✓' : ' ✗');
				if (page.status !== 'complete') li.style.color = '#d63638';
				list.appendChild(li);
			});
		}
	}

	/* ------------------------------------------------------------------
	 * Step 5 — Results / Undo
	 * ------------------------------------------------------------------ */

	const btnUndo = document.getElementById('grayfox-undo-build');
	if (btnUndo) {
		btnUndo.addEventListener('click', function () {
			if (!confirm('This will move all GrayFox-generated pages to the Trash. Are you sure?')) return;
			if (!confirm('Please confirm again: remove all GrayFox-generated pages?')) return;

			const status = document.getElementById('grayfox-undo-status');
			btnUndo.disabled   = true;
			status.textContent = 'Removing…';

			post('grayfox_undo_site_build', nonces.undoSiteBuild, { confirmed: '1' })
				.then(function (resp) {
					if (resp.success && resp.data) {
						status.textContent = resp.data.trashed_count + ' page(s) moved to Trash.';
						setTimeout(function () { window.location.reload(); }, 1500);
					} else {
						btnUndo.disabled   = false;
						status.textContent = (resp.data && typeof resp.data === 'string') ? resp.data : 'Failed.';
					}
				})
				.catch(function () {
					btnUndo.disabled   = false;
					status.textContent = 'Network error.';
				});
		});
	}

	// If a build is currently running when the page loads, start polling immediately.
	post('grayfox_get_build_progress', nonces.getBuildProgress)
		.then(function (resp) {
			if (resp.success && resp.data && resp.data.status === 'running') {
				showStep(4);
				document.getElementById('grayfox-progress-block').style.display = '';
				updateProgressUI(resp.data);
				startProgressPolling();
			}
		})
		.catch(function () { /* silent */ });
})();
