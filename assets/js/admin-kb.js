(function () {
	var ajaxUrl = grayfoxKB.ajaxUrl;

	// ---- Delete document ----
	document.querySelectorAll('.grayfox-delete-doc').forEach(function (btn) {
		btn.addEventListener('click', function () {
			if (!confirm(grayfoxKB.i18n.confirmDelete)) return;
			var id    = this.dataset.id;
			var nonce = this.dataset.nonce;
			var row   = document.getElementById('grayfox-kb-row-' + id);
			btn.disabled = true;

			var data = new FormData();
			data.append('action', 'grayfox_delete_kb_document');
			data.append('id', id);
			data.append('_ajax_nonce', nonce);

			fetch(ajaxUrl, { method: 'POST', body: data })
				.then(function (r) { return r.json(); })
				.then(function (resp) {
					if (resp.success) {
						if (row) row.remove();
					} else {
						alert(resp.data || grayfoxKB.i18n.deleteFailed);
						btn.disabled = false;
					}
				})
				.catch(function () {
					alert(grayfoxKB.i18n.networkError);
					btn.disabled = false;
				});
		});
	});

	// ---- Retry document ----
	document.querySelectorAll('.grayfox-retry-doc').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var id    = this.dataset.id;
			var nonce = this.dataset.nonce;
			btn.disabled    = true;
			btn.textContent = grayfoxKB.i18n.queuingText;

			var data = new FormData();
			data.append('action', 'grayfox_retry_kb_document');
			data.append('id', id);
			data.append('_ajax_nonce', nonce);

			fetch(ajaxUrl, { method: 'POST', body: data })
				.then(function (r) { return r.json(); })
				.then(function (resp) {
					if (resp.success) {
						btn.textContent = grayfoxKB.i18n.queuedText;
					} else {
						alert(resp.data || grayfoxKB.i18n.retryFailed);
						btn.disabled    = false;
						btn.textContent = grayfoxKB.i18n.retryText;
					}
				});
		});
	});

	// ---- Resolve conflict ----
	document.querySelectorAll('.grayfox-resolve-conflict').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var newId      = this.dataset.newId;
			var oldId      = this.dataset.oldId;
			var resolution = this.dataset.resolution;
			var nonce      = this.dataset.nonce;
			var panel      = document.getElementById('grayfox-conflict-' + newId + '-' + oldId);

			if (!confirm(grayfoxKB.i18n.confirmResolve)) return;
			btn.disabled = true;

			var data = new FormData();
			data.append('action', 'grayfox_resolve_conflict');
			data.append('new_doc_id', newId);
			data.append('old_doc_id', oldId);
			data.append('resolution', resolution);
			data.append('_ajax_nonce', nonce);

			fetch(ajaxUrl, { method: 'POST', body: data })
				.then(function (r) { return r.json(); })
				.then(function (resp) {
					if (resp.success) {
						if (panel) panel.remove();
						window.location.reload();
					} else {
						alert(resp.data || grayfoxKB.i18n.resolveFailed);
						btn.disabled = false;
					}
				});
		});
	});

	// ---- Load AI diff ----
	document.querySelectorAll('.grayfox-get-diff').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var newId  = this.dataset.newId;
			var oldId  = this.dataset.oldId;
			var nonce  = this.dataset.nonce;
			var result = document.getElementById('grayfox-diff-' + newId + '-' + oldId);
			btn.disabled = true;
			if (result) result.textContent = grayfoxKB.i18n.loadingText;

			var data = new FormData();
			data.append('action', 'grayfox_get_conflict_diff');
			data.append('new_doc_id', newId);
			data.append('old_doc_id', oldId);
			data.append('_ajax_nonce', nonce);

			fetch(ajaxUrl, { method: 'POST', body: data })
				.then(function (r) { return r.json(); })
				.then(function (resp) {
					btn.disabled = false;
					if (resp.success && resp.data) {
						if (result) result.textContent = resp.data.diff || '';
					} else {
						if (result) result.textContent = resp.data || grayfoxKB.i18n.diffFailed;
					}
				})
				.catch(function () {
					btn.disabled = false;
					if (result) result.textContent = grayfoxKB.i18n.networkError;
				});
		});
	});
})();
