(function () {
	'use strict';

	var cfg = window.aiProductDescAuto || {};
	var i18n = cfg.i18n || {};

	var root = document.getElementById('ai-auto-submit-root');
	var brandSelect = document.getElementById('ai-auto-brand');
	var startBtn = document.getElementById('ai-auto-start');
	var cancelBtn = document.getElementById('ai-auto-cancel');
	var startRowInput = document.getElementById('ai-auto-start-row');
	var countModeSelect = document.getElementById('ai-auto-count-mode');
	var countProcessInput = document.getElementById('ai-auto-count-process');
	var noticeEl = document.getElementById('ai-auto-notice');
	var productsJson = document.getElementById('ai-auto-products-json');

	var products = [];
	if (productsJson) {
		try {
			products = JSON.parse(productsJson.textContent || '[]');
			if (!Array.isArray(products)) {
				products = [];
			}
		} catch (e) {
			products = [];
		}
	}

	var running = false;
	var cancelled = false;
	var currentProductId = null;
	var activeBatchIds = [];

	function showNotice(message, type) {
		if (!noticeEl) {
			return;
		}
		noticeEl.textContent = message;
		noticeEl.className =
			'ai-auto-submit__notice' +
			(type ? ' ai-auto-submit__notice--' + type : '');
		noticeEl.hidden = false;
	}

	function hideNotice() {
		if (noticeEl) {
			noticeEl.hidden = true;
			noticeEl.textContent = '';
		}
	}

	function getRowEl(productId) {
		return document.querySelector(
			'#ai-auto-preview-body tr[data-product-id="' + productId + '"]'
		);
	}

	function getRowStatusEl(productId) {
		var row = getRowEl(productId);
		return row ? row.querySelector('.ai-auto-submit__row-status') : null;
	}

	function scrollRowIntoView(productId) {
		var row = getRowEl(productId);
		if (!row || typeof row.scrollIntoView !== 'function') {
			return;
		}
		row.scrollIntoView({
			behavior: 'smooth',
			block: 'nearest',
			inline: 'nearest',
		});
	}

	function setRowState(productId, state) {
		var cell = getRowStatusEl(productId);
		if (!cell) {
			return;
		}
		cell.setAttribute('data-state', state);

		if (state === 'processing') {
			scrollRowIntoView(productId);
		}

		var fill = cell.querySelector('.ai-auto-submit__row-fill');
		var bar = cell.querySelector('.ai-auto-submit__row-bar');
		var icon = cell.querySelector('.ai-auto-submit__row-icon');

		if (icon) {
			icon.className = 'ai-auto-submit__row-icon';
			if (state === 'success') {
				icon.className += ' dashicons dashicons-yes-alt';
			} else if (state === 'error') {
				icon.className += ' dashicons dashicons-dismiss';
			} else if (state === 'canceled') {
				icon.className += ' dashicons dashicons-marker';
			}
		}

		var pct = 0;
		if (state === 'processing') {
			pct = 50;
		} else if (state === 'success' || state === 'error') {
			pct = 100;
		}

		if (fill) {
			fill.style.width = pct + '%';
		}
		if (bar) {
			bar.setAttribute('aria-valuenow', String(pct));
		}
	}

	function resetBatchRows(batchIds) {
		batchIds.forEach(function (id) {
			setRowState(id, 'idle');
		});
	}

	function markRemainingCanceled(fromIndex, list) {
		for (var i = fromIndex; i < list.length; i += 1) {
			var id = list[i].id;
			var cell = getRowStatusEl(id);
			if (!cell) {
				continue;
			}
			var state = cell.getAttribute('data-state');
			if (state === 'idle' || state === 'processing') {
				setRowState(id, 'canceled');
			}
		}
	}

	function updateCountInputVisibility() {
		if (!countModeSelect || !countProcessInput) {
			return;
		}
		var isCustom = countModeSelect.value === 'custom';
		countProcessInput.hidden = !isCustom;
	}

	function getBatchProducts() {
		var total = products.length;
		if (!total) {
			return null;
		}

		var start = parseInt(startRowInput && startRowInput.value, 10) || 1;
		if (start < 1 || start > total) {
			showNotice(i18n.invalidStart || 'شماره محصول نامعتبر است.', 'error');
			return null;
		}

		var fromIndex = start - 1;
		var mode = countModeSelect ? countModeSelect.value : 'all';
		var list;

		if (mode === 'all') {
			list = products.slice(fromIndex);
		} else {
			var count = parseInt(countProcessInput && countProcessInput.value, 10) || 0;
			if (count < 1) {
				showNotice(i18n.invalidCount || 'تعداد محصول نامعتبر است.', 'error');
				return null;
			}
			var toIndex = Math.min(fromIndex + count, total);
			list = products.slice(fromIndex, toIndex);
		}

		if (!list.length) {
			showNotice(i18n.invalidCount || 'تعداد محصول نامعتبر است.', 'error');
			return null;
		}

		return list;
	}

	function setUiIdle() {
		running = false;
		cancelled = false;
		currentProductId = null;
		activeBatchIds = [];

		if (brandSelect) {
			brandSelect.disabled = false;
		}
		var showBtn = document.getElementById('ai-auto-show-btn');
		if (showBtn) {
			showBtn.disabled = false;
		}
		if (startBtn) {
			startBtn.disabled = products.length === 0;
		}
		if (startRowInput) {
			startRowInput.disabled = false;
		}
		if (countModeSelect) {
			countModeSelect.disabled = false;
		}
		if (countProcessInput) {
			countProcessInput.disabled = false;
		}
		if (cancelBtn) {
			cancelBtn.hidden = true;
		}
	}

	function setUiRunning() {
		running = true;
		cancelled = false;

		if (brandSelect) {
			brandSelect.disabled = true;
		}
		var showBtn = document.getElementById('ai-auto-show-btn');
		if (showBtn) {
			showBtn.disabled = true;
		}
		if (startBtn) {
			startBtn.disabled = true;
		}
		if (startRowInput) {
			startRowInput.disabled = true;
		}
		if (countModeSelect) {
			countModeSelect.disabled = true;
		}
		if (countProcessInput) {
			countProcessInput.disabled = true;
		}
		if (cancelBtn) {
			cancelBtn.hidden = false;
		}
	}

	function post(action, data) {
		var body = new FormData();
		body.append('action', action);
		body.append('nonce', cfg.nonce || '');
		Object.keys(data || {}).forEach(function (key) {
			body.append(key, data[key]);
		});
		return fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body,
		}).then(function (res) {
			return res.json();
		});
	}

	function finishRun(list, index) {
		if (cancelled) {
			markRemainingCanceled(index, list);
		}
		setUiIdle();
	}

	function processQueue(list) {
		var total = list.length;
		var index = 0;

		activeBatchIds = list.map(function (item) {
			return item.id;
		});
		resetBatchRows(activeBatchIds);
		setUiRunning();

		function next() {
			if (cancelled) {
				finishRun(list, index);
				return;
			}
			if (index >= total) {
				setUiIdle();
				return;
			}

			var item = list[index];
			currentProductId = item.id;
			setRowState(item.id, 'processing');

			post(cfg.actions.processOne, { product_id: String(item.id) })
				.then(function (json) {
					if (cancelled) {
						setRowState(item.id, 'canceled');
						finishRun(list, index + 1);
						return;
					}
					if (!json || !json.success) {
						setRowState(item.id, 'error');
					} else {
						setRowState(item.id, 'success');
					}
					currentProductId = null;
					index += 1;
					next();
				})
				.catch(function () {
					if (cancelled) {
						setRowState(item.id, 'canceled');
						finishRun(list, index + 1);
						return;
					}
					setRowState(item.id, 'error');
					currentProductId = null;
					index += 1;
					next();
				});
		}

		next();
	}

	if (countModeSelect) {
		countModeSelect.addEventListener('change', updateCountInputVisibility);
		updateCountInputVisibility();
	}

	if (startBtn) {
		startBtn.addEventListener('click', function () {
			if (running) {
				return;
			}
			hideNotice();

			if (!cfg.isConfigured) {
				showNotice(
					i18n.notConfigured || 'تنظیمات AI کامل نیست.',
					'error'
				);
				return;
			}
			if (!products.length) {
				showNotice(i18n.pickBrand || 'برند انتخاب کنید.', 'warn');
				return;
			}

			var batch = getBatchProducts();
			if (!batch) {
				return;
			}

			processQueue(batch);
		});
	}

	if (cancelBtn) {
		cancelBtn.addEventListener('click', function () {
			if (!running) {
				return;
			}
			cancelled = true;
			if (currentProductId) {
				setRowState(currentProductId, 'canceled');
			}
			cancelBtn.disabled = true;
			window.setTimeout(function () {
				cancelBtn.disabled = false;
			}, 500);
		});
	}
})();
