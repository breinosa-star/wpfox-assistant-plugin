(function () {

	// ---- API key show/hide toggle ----
	var toggleBtn = document.getElementById('grayfox-toggle-key');
	var keyField  = document.getElementById('grayfox_llm_api_key');
	if (toggleBtn && keyField) {
		toggleBtn.addEventListener('click', function () {
			if (keyField.type === 'password') {
				keyField.type      = 'text';
				toggleBtn.textContent = grayfoxSettings.i18n.hide;
			} else {
				keyField.type      = 'password';
				toggleBtn.textContent = grayfoxSettings.i18n.show;
			}
		});
	}

	// ---- Provider → model dropdown sync ----
	var provSelect = document.getElementById('grayfox_llm_provider');
	var mdlSelect  = document.getElementById('grayfox_llm_model');
	var savedModel = grayfoxSettings.savedModel;
	var allModels  = grayfoxSettings.allModels;

	function populateModels(provider) {
		var models = allModels[provider] || {};
		mdlSelect.innerHTML = '';
		Object.keys(models).forEach(function (id) {
			var opt = document.createElement('option');
			opt.value       = id;
			opt.textContent = models[id];
			if (id === savedModel) opt.selected = true;
			mdlSelect.appendChild(opt);
		});
	}

	if (provSelect && mdlSelect) {
		provSelect.addEventListener('change', function () {
			savedModel = '';
			populateModels(this.value);
		});
	}

	// ---- Test Connection ----
	var testBtn = document.getElementById('grayfox-test-llm');
	if (testBtn) {
		testBtn.addEventListener('click', function () {
			var result = document.getElementById('grayfox-test-llm-result');
			result.textContent = grayfoxSettings.i18n.testing;
			result.style.color = '#666';
			testBtn.disabled   = true;

			var data = new FormData();
			data.append('action', 'grayfox_test_llm');
			data.append('_wpnonce', testBtn.dataset.nonce);

			fetch(ajaxurl, { method: 'POST', body: data })
				.then(function (r) { return r.json(); })
				.then(function (resp) {
					testBtn.disabled = false;
					if (resp.success) {
						result.textContent = resp.data.message || grayfoxSettings.i18n.connected;
						result.style.color = 'green';
					} else {
						result.textContent = resp.data || grayfoxSettings.i18n.connectionFailed;
						result.style.color = 'red';
					}
				})
				.catch(function () {
					testBtn.disabled   = false;
					result.textContent = grayfoxSettings.i18n.networkError;
					result.style.color = 'red';
				});
		});
	}

	// ---- Widget color live preview ----
	var colorInput   = document.getElementById('grayfox_widget_color');
	var colorDisplay = document.getElementById('grayfox-color-display');
	if (colorInput && colorDisplay) {
		colorInput.addEventListener('input', function () {
			colorDisplay.textContent = colorInput.value;
		});
	}

	// ---- Restore default links ----
	document.querySelectorAll('.grayfox-restore-default').forEach(function (link) {
		link.addEventListener('click', function (e) {
			e.preventDefault();
			var input = document.getElementById(this.dataset.target);
			if (input) input.value = this.dataset['default'];
		});
	});

	// ---- Copy URL buttons ----
	document.querySelectorAll('.grayfox-copy-url').forEach(function (btn) {
		btn.addEventListener('click', function () {
			navigator.clipboard.writeText(this.dataset.url).then(function () {
				btn.textContent = grayfoxSettings.i18n.copied;
				setTimeout(function () {
					btn.textContent = grayfoxSettings.i18n.copy;
				}, 2000);
			});
		});
	});

})();
