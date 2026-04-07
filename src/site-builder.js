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

	let currentStep      = 1;
	let envData          = null;      // populated after environment detection
	let pollTimer        = null;
	let pendingStartTime = null;      // set when job is scheduled, cleared once work begins

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
		if (n === 6) loadFooterConfig();
		if (n === 7) loadAuditResults();
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
		pendingStartTime = Date.now();
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
					// Reload to render the full results table from PHP.
					setTimeout(function () { showStep(5); window.location.reload(); }, 1500);
				}
			})
			.catch(function () { /* silent */ });
	}

	function updateProgressUI(d) {
		const bar  = document.getElementById('grayfox-progress-bar');
		const text = document.getElementById('grayfox-progress-text');
		const list = document.getElementById('grayfox-progress-list');
		if (!bar || !text) return;

		const hasStarted = d.completed > 0 || d.status === 'complete';

		if (!hasStarted) {
			// Job is queued but the background worker hasn't picked it up yet.
			bar.style.display = 'none';
			const elapsed = pendingStartTime ? Math.round((Date.now() - pendingStartTime) / 1000) : 0;

			// Build the pending message with DOM APIs to avoid innerHTML with any
			// server-sourced values.
			while (text.firstChild) text.removeChild(text.firstChild);

			const spinner = document.createTextNode('Waiting for background worker to start\u2026');
			text.appendChild(spinner);

			if (elapsed > 20) {
				const hint = document.createElement('span');
				hint.style.cssText = 'display:block;margin-top:6px;font-size:12px;font-weight:normal;color:#d63638;';
				hint.textContent   = 'Taking longer than usual. You can run the job manually: ';
				const link = document.createElement('a');
				link.href        = 'tools.php?page=action-scheduler&status=pending&s=grayfox';
				link.textContent = 'Tools \u2192 Scheduled Actions';
				hint.appendChild(link);
				text.appendChild(hint);
			}
			return;
		}

		// Job is running — show the progress bar.
		pendingStartTime  = null;
		bar.style.display = '';
		const pct = d.total > 0 ? Math.round((d.completed / d.total) * 100) : 0;
		bar.value = pct;
		bar.max   = 100;
		text.textContent = d.completed + ' / ' + d.total + ' pages (' + pct + '%)';

		if (list && d.pages) {
			list.innerHTML = '';
			d.pages.forEach(function (page) {
				const li = document.createElement('li');
				li.textContent = page.title + (page.status === 'complete' ? ' \u2713' : ' \u2717');
				if (page.status !== 'complete') li.style.color = '#d63638';
				list.appendChild(li);
			});
		}
	}

	/* ------------------------------------------------------------------
	 * Step 5 — Revision table
	 * ------------------------------------------------------------------ */

	const revisionActionLabels = {
		'pending':    '<span style="color:#996800;">&#9679; Queued</span>',
		'processing': '<span style="color:#2271b1;">&#8635; Processing</span>',
		'done':       '<span style="color:#46b450;">&#10003; Done</span>',
		'error':      '<span style="color:#d63638;">&#10007; Error</span>',
	};

	// Show/hide hint field when an action is selected; enable submit button.
	document.querySelectorAll('.grayfox-revision-action').forEach(function (sel) {
		sel.addEventListener('change', function () {
			const row  = this.closest('tr');
			const hint = row.querySelector('.grayfox-revision-hint');
			hint.style.display = this.value ? '' : 'none';
			updateSubmitButton();
		});
	});

	function updateSubmitButton() {
		const btn      = document.getElementById('grayfox-submit-revisions');
		if (!btn) return;
		const hasAny   = Array.from(document.querySelectorAll('.grayfox-revision-action'))
			.some(function (sel) { return sel.value !== ''; });
		btn.disabled   = !hasAny;
	}

	const btnSubmitRevisions = document.getElementById('grayfox-submit-revisions');
	if (btnSubmitRevisions) {
		btnSubmitRevisions.addEventListener('click', function () {
			const revisions = [];
			document.querySelectorAll('#grayfox-results-table tbody tr').forEach(function (row) {
				const sel    = row.querySelector('.grayfox-revision-action');
				const hint   = row.querySelector('.grayfox-revision-hint');
				if (!sel || !sel.value) return;
				const parts  = sel.value.split('|');
				revisions.push({
					post_id:            parseInt(row.dataset.postId, 10),
					action_type:        parts[0],
					dropdown_selection: parts[1] || '',
					user_hint:          hint ? hint.value.trim().slice(0, 50) : '',
				});
			});

			if (!revisions.length) return;

			btnSubmitRevisions.disabled = true;
			const statusEl = document.getElementById('grayfox-revisions-status');
			if (statusEl) statusEl.textContent = 'Submitting…';

			post('grayfox_submit_page_revisions', nonces.submitPageRevisions, {
				revisions: JSON.stringify(revisions),
			}).then(function (resp) {
				if (resp.success && resp.data) {
					if (statusEl) statusEl.textContent = resp.data.queued + ' job(s) queued. Watching for updates…';
					// Reset dropdowns and hints for submitted rows.
					document.querySelectorAll('#grayfox-results-table tbody tr').forEach(function (row) {
						const sel  = row.querySelector('.grayfox-revision-action');
						const hint = row.querySelector('.grayfox-revision-hint');
						if (sel && sel.value) {
							const postId = parseInt(row.dataset.postId, 10);
							const submitted = revisions.find(function (r) { return r.post_id === postId; });
							if (submitted) {
								setRevisionBadge(row, 'pending');
								sel.value = '';
								if (hint) { hint.value = ''; hint.style.display = 'none'; }
							}
						}
					});
					startRevisionPolling();
				} else {
					btnSubmitRevisions.disabled = false;
					if (statusEl) statusEl.textContent = (resp.data && typeof resp.data === 'string') ? resp.data : 'Submission failed.';
				}
			}).catch(function () {
				btnSubmitRevisions.disabled = false;
				if (statusEl) statusEl.textContent = 'Network error.';
			});
		});
	}

	function setRevisionBadge(row, status) {
		const badge = row.querySelector('.grayfox-revision-status');
		if (!badge) return;
		badge.dataset.status = status;
		badge.innerHTML = revisionActionLabels[status] || '&mdash;';
	}

	let revisionPollTimer = null;

	function startRevisionPolling() {
		if (revisionPollTimer) return; // already polling
		revisionPollTimer = setInterval(pollRevisionStatuses, 3000);
	}

	function stopRevisionPolling() {
		if (revisionPollTimer) {
			clearInterval(revisionPollTimer);
			revisionPollTimer = null;
		}
	}

	function pollRevisionStatuses() {
		post('grayfox_get_build_progress', nonces.getBuildProgress)
			.then(function (resp) {
				if (!resp.success || !resp.data || !resp.data.pages) return;
				let anyActive = false;
				resp.data.pages.forEach(function (page) {
					if (!page.post_id) return;
					const row = document.querySelector('#grayfox-results-table tr[data-post-id="' + page.post_id + '"]');
					if (!row) return;
					const revStatus = page.revision_status || '';
					if (revStatus) {
						setRevisionBadge(row, revStatus);
					}
					if (revStatus === 'pending' || revStatus === 'processing') {
						anyActive = true;
					}
				});
				if (!anyActive) {
					stopRevisionPolling();
					const statusEl = document.getElementById('grayfox-revisions-status');
					if (statusEl && statusEl.textContent.indexOf('Watching') !== -1) {
						statusEl.textContent = 'All revision jobs complete.';
					}
					// Re-enable submit button in case user wants to submit more.
					updateSubmitButton();
				}
			})
			.catch(function () { /* silent */ });
	}

	// Resume polling on page load if any rows have active revision statuses.
	document.querySelectorAll('.grayfox-revision-status').forEach(function (badge) {
		const s = badge.dataset.status;
		if (s === 'pending' || s === 'processing') {
			startRevisionPolling();
		}
	});

	/* ------------------------------------------------------------------
	 * Step 5 — Undo
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

	/* ------------------------------------------------------------------
	 * Step 6 — Footer Configuration
	 * ------------------------------------------------------------------ */

	const isPro          = cfg.isPro || false;
	const footerDisabled = (function () {
		const tab = document.querySelector('.grayfox-step-tab[data-step="6"]');
		return tab ? tab.dataset.footerDisabled === '1' : true;
	}());

	let footerAllPages = [];

	function loadFooterConfig() {
		if (footerDisabled) return;
		const loadingEl  = document.getElementById('grayfox-footer-loading');
		const columnsEl  = document.getElementById('grayfox-footer-columns');
		const saveBtn    = document.getElementById('grayfox-save-footer');
		if (!loadingEl) return;

		loadingEl.style.display = '';
		if (columnsEl) columnsEl.style.display = 'none';
		if (saveBtn)   saveBtn.style.display   = 'none';

		post('grayfox_get_footer_config', nonces.getFooterConfig)
			.then(function (resp) {
				loadingEl.style.display = 'none';
				if (!resp.success || !resp.data) return;
				const data = resp.data;
				footerAllPages = data.all_pages || [];

				if (!data.footer_locations || Object.keys(data.footer_locations).length === 0) {
					loadingEl.textContent = 'No footer menu locations found in this theme.';
					loadingEl.style.display = '';
					return;
				}

				renderFooterColumns(data.footer_locations, data.items || {});
				if (columnsEl) columnsEl.style.display = 'flex';
				if (saveBtn)   saveBtn.style.display   = '';
			})
			.catch(function () {
				if (loadingEl) loadingEl.textContent = 'Failed to load footer configuration.';
			});
	}

	function renderFooterColumns(locations, itemsByLocation) {
		const container = document.getElementById('grayfox-footer-columns');
		if (!container) return;
		container.innerHTML = '';

		Object.keys(locations).forEach(function (locationKey) {
			const label = locations[locationKey];
			const items = itemsByLocation[locationKey] || [];

			const col = document.createElement('div');
			col.dataset.location = locationKey;
			col.style.cssText = 'flex:1;min-width:200px;max-width:340px;border:1px solid #ddd;border-radius:4px;padding:12px;';

			const heading = document.createElement('h4');
			heading.style.margin = '0 0 10px';
			heading.textContent  = label;
			col.appendChild(heading);

			const list = document.createElement('div');
			list.className = 'grayfox-footer-list';
			list.style.marginBottom = '10px';

			items.forEach(function (item) {
				list.appendChild(createFooterItem(item));
			});
			col.appendChild(list);

			// "Add page" select.
			const addRow = document.createElement('div');
			addRow.style.marginTop = '8px';
			const sel = document.createElement('select');
			sel.className = 'grayfox-footer-add-select';
			sel.style.width = '100%';
			const defaultOpt = document.createElement('option');
			defaultOpt.value = '';
			defaultOpt.textContent = '— Add a page —';
			sel.appendChild(defaultOpt);
			footerAllPages.forEach(function (p) {
				const opt = document.createElement('option');
				opt.value = p.post_id;
				opt.textContent = p.title;
				opt.dataset.url   = p.url;
				opt.dataset.title = p.title;
				sel.appendChild(opt);
			});
			sel.addEventListener('change', function () {
				if (!this.value) return;
				const opt   = this.options[this.selectedIndex];
				const item  = { menu_item_id: 0, post_id: parseInt(this.value, 10), title: opt.dataset.title, url: opt.dataset.url, is_generated: false };
				list.appendChild(createFooterItem(item));
				this.value = '';
			});
			addRow.appendChild(sel);
			col.appendChild(addRow);

			container.appendChild(col);
		});
	}

	function createFooterItem(item) {
		const row = document.createElement('div');
		row.className = 'grayfox-footer-item';
		row.dataset.menuItemId = item.menu_item_id || 0;
		row.dataset.postId     = item.post_id || 0;
		row.dataset.url        = item.url || '';
		row.dataset.title      = item.title || '';
		row.style.cssText = 'display:flex;align-items:center;gap:6px;padding:4px 0;border-bottom:1px solid #eee;';

		const arrows = document.createElement('span');
		arrows.style.display = 'flex';
		arrows.style.flexDirection = 'column';
		arrows.style.gap = '1px';

		const btnUp = document.createElement('button');
		btnUp.type = 'button';
		btnUp.textContent = '▲';
		btnUp.style.cssText = 'font-size:10px;padding:0 3px;cursor:pointer;line-height:1.2;';
		btnUp.addEventListener('click', function () {
			const prev = row.previousElementSibling;
			if (prev && prev.classList.contains('grayfox-footer-item')) {
				row.parentNode.insertBefore(row, prev);
			}
		});

		const btnDown = document.createElement('button');
		btnDown.type = 'button';
		btnDown.textContent = '▼';
		btnDown.style.cssText = 'font-size:10px;padding:0 3px;cursor:pointer;line-height:1.2;';
		btnDown.addEventListener('click', function () {
			const next = row.nextElementSibling;
			if (next && next.classList.contains('grayfox-footer-item')) {
				row.parentNode.insertBefore(next, row);
			}
		});

		arrows.appendChild(btnUp);
		arrows.appendChild(btnDown);

		const label = document.createElement('span');
		label.style.flex = '1';
		label.style.fontSize = '13px';
		label.textContent = item.title;
		if (item.is_generated) {
			label.style.fontWeight = '600';
		}

		const btnRemove = document.createElement('button');
		btnRemove.type      = 'button';
		btnRemove.textContent = '×';
		btnRemove.title     = 'Remove';
		btnRemove.style.cssText = 'cursor:pointer;color:#d63638;font-size:16px;padding:0 4px;background:none;border:none;';
		btnRemove.addEventListener('click', function () {
			row.remove();
		});

		row.appendChild(arrows);
		row.appendChild(label);
		row.appendChild(btnRemove);
		return row;
	}

	const btnSaveFooter = document.getElementById('grayfox-save-footer');
	if (btnSaveFooter) {
		btnSaveFooter.addEventListener('click', function () {
			const statusEl = document.getElementById('grayfox-footer-status');
			const config   = {};

			document.querySelectorAll('#grayfox-footer-columns > [data-location]').forEach(function (col) {
				const loc   = col.dataset.location;
				config[loc] = [];
				col.querySelectorAll('.grayfox-footer-item').forEach(function (item) {
					config[loc].push({
						menu_item_id: parseInt(item.dataset.menuItemId, 10) || 0,
						post_id:      parseInt(item.dataset.postId, 10) || 0,
						title:        item.dataset.title,
						url:          item.dataset.url,
					});
				});
			});

			btnSaveFooter.disabled = true;
			if (statusEl) statusEl.textContent = 'Saving…';

			post('grayfox_save_footer_config', nonces.saveFooterConfig, { config: JSON.stringify(config) })
				.then(function (resp) {
					btnSaveFooter.disabled = false;
					if (resp.success) {
						if (statusEl) statusEl.textContent = 'Footer saved!';
					} else {
						if (statusEl) statusEl.textContent = 'Save failed.';
					}
				})
				.catch(function () {
					btnSaveFooter.disabled = false;
					if (statusEl) statusEl.textContent = 'Network error.';
				});
		});
	}

	/* ------------------------------------------------------------------
	 * Step 7 — Site Audit
	 * ------------------------------------------------------------------ */

	const auditBadgeStyles = {
		idle:   { bg: '#ddd',    color: '#555',    text: 'Not scanned' },
		pass:   { bg: '#d4edda', color: '#1e6c38', text: '✓ All clear' },
		issues: { bg: '#fff3cd', color: '#856404', text: 'Issues found' },
	};

	function setAuditBadge(sectionEl, status, issueCount) {
		const badge = sectionEl.querySelector('.grayfox-audit-badge');
		if (!badge) return;
		const s = auditBadgeStyles[status] || auditBadgeStyles.idle;
		badge.dataset.status    = status;
		badge.style.background  = s.bg;
		badge.style.color       = s.color;
		badge.textContent       = (status === 'issues' && issueCount > 0)
			? issueCount + ' issue' + (issueCount === 1 ? '' : 's') + ' found'
			: s.text;
	}

	// Pages list populated when audit runs — used for broken_links URL selectors.
	let auditAllPages = [];

	function buildUrlSelectorHtml(postId, buttonLabel) {
		const pid = escHtml(String(postId));
		const lbl = escHtml(buttonLabel);
		let opts = '<option value="">— select target —</option>';
		auditAllPages.forEach(function (p) {
			opts += '<option value="' + escHtml(p.url) + '">' + escHtml(p.title) + '</option>';
		});
		opts += '<option value="__external__">External URL…</option>';
		return '<select class="grayfox-url-assign" data-post-id="' + pid + '" data-button-label="' + lbl + '" style="max-width:180px;">'
			+ opts + '</select>'
			+ '<input type="text" class="grayfox-external-url" placeholder="https://…" style="display:none;width:160px;margin-top:4px;" />';
	}

	function renderAuditSection(sectionEl, result) {
		if (!sectionEl || !result) return;
		const issues         = result.issues || [];
		const section        = sectionEl.dataset.section;
		const showUrlSelectors = !!(result.show_url_selectors);
		setAuditBadge(sectionEl, result.status || 'idle', issues.length);

		// Update thead — URL selector columns only visible after a failed autofix.
		const thead = sectionEl.querySelector('thead tr');
		if (thead) {
			if (showUrlSelectors) {
				thead.innerHTML = '<th style="width:20%;">Page</th><th style="width:15%;">Issue</th><th style="width:15%;">Button</th><th>Target URL</th><th style="width:10%;text-align:center;">Severity</th>';
			} else {
				thead.innerHTML = '<th style="width:25%;">Page</th><th>Issue</th><th style="width:12%;text-align:center;">Severity</th>';
			}
		}

		const tbody = sectionEl.querySelector('tbody');
		if (tbody) {
			tbody.innerHTML = '';
			if (issues.length === 0) {
				const tr = document.createElement('tr');
				tr.innerHTML = '<td colspan="' + (showUrlSelectors ? 5 : 3) + '" style="color:#46b450;text-align:center;">No issues found.</td>';
				tbody.appendChild(tr);
			} else {
				issues.forEach(function (issue) {
					const tr  = document.createElement('tr');
					const sev = issue.severity === 'error'
						? '<span style="color:#d63638;font-weight:600;">Error</span>'
						: '<span style="color:#996800;">Warning</span>';
					const postId   = issue.post_id || 0;
					const pageLink = postId
						? '<a href="/wp-admin/post.php?post=' + postId + '&action=edit" target="_blank">' + escHtml(issue.title || '') + '</a>'
						: escHtml(issue.title || 'Site');

					if (showUrlSelectors && issue.input_type === 'url_selector') {
						tr.innerHTML = '<td>' + pageLink + '</td>'
							+ '<td style="color:#996800;font-size:12px;">Autofix could not match a link</td>'
							+ '<td><strong>' + escHtml(issue.button_label || '') + '</strong></td>'
							+ '<td>' + buildUrlSelectorHtml(postId, issue.button_label || '') + '</td>'
							+ '<td style="text-align:center;">' + sev + '</td>';
					} else if (showUrlSelectors) {
						tr.innerHTML = '<td>' + pageLink + '</td><td colspan="2">' + escHtml(issue.issue || '') + '</td><td></td><td style="text-align:center;">' + sev + '</td>';
					} else {
						tr.innerHTML = '<td>' + pageLink + '</td><td>' + escHtml(issue.issue || '') + '</td><td style="text-align:center;">' + sev + '</td>';
					}
					tbody.appendChild(tr);
				});

				// Wire up external URL toggle for url_selector rows.
				tbody.querySelectorAll('.grayfox-url-assign').forEach(function (sel) {
					sel.addEventListener('change', function () {
						const extInput = this.closest('td').querySelector('.grayfox-external-url');
						if (extInput) extInput.style.display = this.value === '__external__' ? '' : 'none';
					});
				});
			}
		}

		const body = sectionEl.querySelector('.grayfox-audit-section-body');
		if (body) body.style.display = issues.length > 0 ? '' : 'none';

		const fixBtn   = sectionEl.querySelector('.grayfox-fix-section');
		const applyBtn = sectionEl.querySelector('.grayfox-apply-links');

		const anyFixable = issues.some(function (i) { return i.fixable; });
		if (fixBtn) {
			fixBtn.style.display  = (issues.length > 0 && anyFixable) ? '' : 'none';
			fixBtn.disabled       = showUrlSelectors; // gray out after autofix if unmatched remain
		}
		if (applyBtn) {
			applyBtn.style.display = showUrlSelectors ? '' : 'none';
		}

		const seoConfigure = document.getElementById('grayfox-seo-configure');
		const seoUpgrade   = document.getElementById('grayfox-seo-upgrade');
		if (section === 'seo' && issues.length > 0) {
			if (seoConfigure) seoConfigure.style.display = '';
			if (seoUpgrade)   seoUpgrade.style.display   = '';
		}
	}

	function renderAuditResults(data) {
		if (!data) return;
		if (data.all_pages) auditAllPages = data.all_pages;
		const sections = data.sections || data; // accept both full audit object and sections map
		Object.keys(sections).forEach(function (key) {
			const sectionEl = document.querySelector('.grayfox-audit-section[data-section="' + key + '"]');
			renderAuditSection(sectionEl, sections[key]);
		});
	}

	function loadAuditResults() {
		post('grayfox_get_audit_results', nonces.getAuditResults)
			.then(function (resp) {
				if (resp.success && resp.data && resp.data.status === 'complete') {
					renderAuditResults(resp.data);
				}
			})
			.catch(function () { /* silent */ });
	}

	const btnRunAudit = document.getElementById('grayfox-run-audit');
	if (btnRunAudit) {
		btnRunAudit.addEventListener('click', function () {
			const statusEl = document.getElementById('grayfox-audit-status');
			btnRunAudit.disabled = true;
			if (statusEl) statusEl.textContent = 'Scanning…';

			document.querySelectorAll('.grayfox-audit-badge').forEach(function (badge) {
				badge.style.background = '#e6f0fb';
				badge.style.color      = '#2271b1';
				badge.textContent      = '⟳ Scanning…';
			});

			post('grayfox_run_site_audit', nonces.runSiteAudit)
				.then(function (resp) {
					btnRunAudit.disabled = false;
					if (statusEl) statusEl.textContent = 'Scan complete.';
					if (resp.success && resp.data) {
						renderAuditResults(resp.data);
					} else {
						if (statusEl) statusEl.textContent = (resp.data && typeof resp.data === 'string') ? resp.data : 'Scan failed.';
					}
				})
				.catch(function () {
					btnRunAudit.disabled = false;
					if (statusEl) statusEl.textContent = 'Network error.';
				});
		});
	}

	// Fix section buttons.
	document.querySelectorAll('.grayfox-fix-section').forEach(function (btn) {
		btn.addEventListener('click', function () {
			const section   = this.dataset.section;
			const sectionEl = document.querySelector('.grayfox-audit-section[data-section="' + section + '"]');
			const fixStatus = sectionEl ? sectionEl.querySelector('.grayfox-fix-status') : null;

			const extraData = { section: section };

			btn.disabled = true;
			if (fixStatus) fixStatus.textContent = 'Fixing…';

			post('grayfox_fix_audit_section', nonces.fixAuditSection, extraData)
				.then(function (resp) {
					btn.disabled = false;
					if (fixStatus) fixStatus.textContent = '';
					if (resp.success && resp.data) {
						renderAuditSection(sectionEl, resp.data.result);
					} else {
						if (fixStatus) fixStatus.textContent = 'Fix failed.';
					}
				})
				.catch(function () {
					btn.disabled = false;
					if (fixStatus) fixStatus.textContent = 'Network error.';
				});
		});
	});

	// Apply button — sends user-selected URLs for buttons autofix couldn't match.
	document.querySelectorAll('.grayfox-apply-links').forEach(function (btn) {
		btn.addEventListener('click', function () {
			const sectionEl = document.querySelector('.grayfox-audit-section[data-section="broken_links"]');
			const fixStatus = sectionEl ? sectionEl.querySelector('.grayfox-fix-status') : null;

			const assignments = [];
			sectionEl.querySelectorAll('.grayfox-url-assign').forEach(function (sel) {
				let url = sel.value;
				if (url === '__external__') {
					const ext = sel.closest('td').querySelector('.grayfox-external-url');
					url = ext ? ext.value.trim() : '';
				}
				if (url) {
					assignments.push({
						post_id:      parseInt(sel.dataset.postId, 10),
						button_label: sel.dataset.buttonLabel || '',
						url:          url,
					});
				}
			});

			if (!assignments.length) {
				if (fixStatus) fixStatus.textContent = 'Select a target URL for each button first.';
				return;
			}

			btn.disabled = true;
			if (fixStatus) fixStatus.textContent = 'Applying…';

			post('grayfox_fix_audit_section', nonces.fixAuditSection, {
				section:     'broken_links',
				assignments: JSON.stringify(assignments),
			}).then(function (resp) {
				btn.disabled = false;
				if (fixStatus) fixStatus.textContent = '';
				if (resp.success && resp.data) {
					renderAuditSection(sectionEl, resp.data.result);
				} else {
					if (fixStatus) fixStatus.textContent = 'Apply failed.';
				}
			}).catch(function () {
				btn.disabled = false;
				if (fixStatus) fixStatus.textContent = 'Network error.';
			});
		});
	});

	// Collapsible section headers.
	document.querySelectorAll('.grayfox-audit-section-header').forEach(function (header) {
		header.addEventListener('click', function () {
			const body = this.nextElementSibling;
			if (body) body.style.display = body.style.display === 'none' ? '' : 'none';
		});
	});

	// Utility: escape HTML for safe insertion.
	function escHtml(str) {
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	// If a build is currently running when the page loads, start polling immediately.
	post('grayfox_get_build_progress', nonces.getBuildProgress)
		.then(function (resp) {
			if (resp.success && resp.data && resp.data.status === 'running') {
				showStep(4);
				document.getElementById('grayfox-progress-block').style.display = '';
				// If work hasn't started yet we don't know the original schedule time,
				// so start the elapsed clock from now (conservative — hint appears after 20s).
				if ((resp.data.completed || 0) === 0) {
					pendingStartTime = Date.now();
				}
				updateProgressUI(resp.data);
				startProgressPolling();
			}
		})
		.catch(function () { /* silent */ });
})();
