(function () {
	'use strict';

	if (window.aiProductDescBound) {
		return;
	}
	window.aiProductDescBound = true;

	var lastResult = {
		productId: '',
		description: '',
		categoryUrl: 'https://lookazma.com/',
	};

	function getConfig() {
		var root = document.getElementById('ai-product-desc-root');
		var fromWindow = window.aiProductDesc || {};

		if (root && root.getAttribute('data-config')) {
			try {
				var fromDom = JSON.parse(root.getAttribute('data-config'));
				return Object.assign({}, fromWindow, fromDom);
			} catch (e) {
				// Fall through to window config.
			}
		}

		return fromWindow;
	}

	function isConfigured(cfg) {
		return cfg.isConfigured === 1 || cfg.isConfigured === '1' || cfg.isConfigured === true;
	}

	function escapeHtml(text) {
		return String(text)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;');
	}

	function lookazmaLink(href, label) {
		var url = href || 'https://lookazma.com/';
		return (
			'<a href="' +
			escapeHtml(url) +
			'" target="_blank" rel="noopener noreferrer">' +
			(label || escapeHtml(url)) +
			'</a>'
		);
	}

	/**
	 * Escape text, keep line breaks, and turn Lookazma mentions into links.
	 * Preserves product-category archive URLs; only rewrites bare homepage.
	 */
	function formatDescriptionHtml(text, categoryUrl) {
		var value = String(text || '').trim();
		var storeUrl = categoryUrl || lastResult.categoryUrl || 'https://lookazma.com/';
		if (!value) {
			return '';
		}

		// Structured HTML from the product prompt template — keep as-is if it already has links.
		if (/<[a-z][\s\S]*>/i.test(value)) {
			return value;
		}

		var escaped = escapeHtml(value).replace(/\r\n|\r|\n/g, '<br>');

		// Only bare homepage (not /product-category/...).
		escaped = escaped.replace(
			/https?:\/\/(?:www\.)?lookazma\.com\/?(?=[\s<]|$)/gi,
			function () {
				return lookazmaLink(storeUrl, storeUrl);
			}
		);

		if (escaped.indexOf('href="') === -1) {
			if (/خرید\s+از\s+لوک\s*آزما/.test(escaped)) {
				escaped = escaped.replace(
					/خرید\s+از\s+لوک\s*آزما/,
					lookazmaLink(storeUrl, 'خرید از لوک آزما')
				);
			} else if (/لوک\s*آزما/.test(escaped)) {
				escaped = escaped.replace(
					/لوک\s*آزما/,
					lookazmaLink(storeUrl, 'لوک آزما')
				);
			}
		}

		return escaped;
	}

	function setSaveStatus(message, isError) {
		var status = document.getElementById('ai-product-desc-save-status');
		if (!status) {
			return;
		}

		if (!message) {
			status.hidden = true;
			status.textContent = '';
			status.classList.remove('is-error', 'is-success');
			return;
		}

		status.hidden = false;
		status.textContent = message;
		status.classList.toggle('is-error', !!isError);
		status.classList.toggle('is-success', !isError);
	}

	function renderCurrentDescription(currentDescription) {
		var body = document.getElementById('ai-product-desc-current-body');
		var cfg = getConfig();

		if (!body) {
			return;
		}

		var payload = currentDescription || {};
		var isEmpty =
			payload.is_empty === true ||
			payload.is_empty === 1 ||
			payload.is_empty === '1' ||
			!payload.html;

		body.classList.toggle('is-empty', isEmpty);

		if (isEmpty) {
			body.textContent =
				(cfg.i18n && cfg.i18n.emptyDesc) ||
				'این محصول هنوز توضیحات ندارد.';
			return;
		}

		body.innerHTML = payload.html;
	}

	function showResult(productName, text, isError, productId, currentDescription, categoryUrl) {
		var resultWrap = document.getElementById('ai-product-desc-result');
		var resultTitle = document.getElementById('ai-product-desc-result-title');
		var resultBody = document.getElementById('ai-product-desc-result-body');
		var saveWrap = document.getElementById('ai-product-desc-save-wrap');
		var saveBtn = document.getElementById('ai-product-desc-save-btn');

		if (!resultWrap || !resultBody) {
			window.alert(text);
			return;
		}

		resultWrap.hidden = false;

		if (resultTitle) {
			if (productName && !isError) {
				resultTitle.textContent = productName;
				resultTitle.hidden = false;
			} else {
				resultTitle.textContent = '';
				resultTitle.hidden = true;
			}
		}

		if (isError) {
			resultBody.textContent = text;
			lastResult.productId = '';
			lastResult.description = '';
			lastResult.categoryUrl = 'https://lookazma.com/';
			if (saveWrap) {
				saveWrap.hidden = true;
			}
		} else {
			lastResult.categoryUrl = categoryUrl || 'https://lookazma.com/';
			resultBody.innerHTML = formatDescriptionHtml(text, lastResult.categoryUrl);
			lastResult.productId = productId ? String(productId) : '';
			lastResult.description = text || '';
			if (saveWrap) {
				saveWrap.hidden = !lastResult.productId || !lastResult.description;
			}
			if (saveBtn) {
				saveBtn.disabled = false;
				saveBtn.classList.remove('is-loading', 'is-error');
			}
			setSaveStatus('');
			renderCurrentDescription(currentDescription);
		}

		resultBody.classList.toggle('is-error', !!isError);
		resultWrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
	}

	function initSearch() {
		var searchInput = document.getElementById('ai-product-desc-search');
		var table = document.getElementById('ai-product-desc-table');

		if (!searchInput || !table || searchInput.dataset.aiBound === '1') {
			return;
		}

		searchInput.dataset.aiBound = '1';
		searchInput.addEventListener('input', function () {
			var filter = this.value.toLowerCase();
			var rows = table.querySelectorAll('tbody tr');

			rows.forEach(function (row) {
				var nameCell = row.querySelector('.ai-product-desc__product-name');
				var name = nameCell ? nameCell.textContent.toLowerCase() : '';
				row.style.display = name.indexOf(filter) !== -1 ? '' : 'none';
			});
		});
	}

	function handleGenerateClick(event) {
		var btn = event.target.closest
			? event.target.closest('.ai-product-desc__generate-btn')
			: null;

		if (!btn) {
			return;
		}

		event.preventDefault();
		event.stopPropagation();

		var cfg = getConfig();

		if (!cfg.ajaxUrl || !cfg.action || !cfg.nonce) {
			window.alert(
				(cfg.i18n && cfg.i18n.error) ||
					'پیکربندی اسکریپت بارگذاری نشده است. صفحه را رفرش کنید.'
			);
			return;
		}

		if (!isConfigured(cfg)) {
			window.alert(
				(cfg.i18n && cfg.i18n.notConfigured) ||
					'تنظیمات ارائه‌دهنده هوش مصنوعی کامل نیست.'
			);
			return;
		}

		var productId = btn.getAttribute('data-product-id');
		if (!productId) {
			return;
		}

		if (btn.disabled) {
			return;
		}

		var originalText = btn.getAttribute('data-original-text') || btn.textContent;
		btn.setAttribute('data-original-text', originalText);
		btn.disabled = true;
		btn.classList.add('is-loading');
		btn.classList.remove('is-error');
		btn.textContent = (cfg.i18n && cfg.i18n.loading) || 'در حال ساخت...';

		var formData = new FormData();
		formData.append('action', cfg.action);
		formData.append('nonce', cfg.nonce);
		formData.append('product_id', productId);

		fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData,
		})
			.then(function (response) {
				return response.json().then(function (payload) {
					return { ok: response.ok, payload: payload };
				});
			})
			.then(function (result) {
				btn.disabled = false;
				btn.classList.remove('is-loading');
				btn.textContent = originalText;

				if (!result.payload || !result.payload.success) {
					btn.classList.add('is-error');
					var message =
						(result.payload &&
							result.payload.data &&
							result.payload.data.message) ||
						(cfg.i18n && cfg.i18n.error) ||
						'خطا در ساخت توضیحات.';
					showResult('', message, true, '', null);
					return;
				}

				var data = result.payload.data || {};
				showResult(
					data.product_name || '',
					data.description || '',
					false,
					data.product_id || productId,
					data.current_description || null,
					data.category_url || ''
				);
			})
			.catch(function () {
				btn.disabled = false;
				btn.classList.remove('is-loading');
				btn.classList.add('is-error');
				btn.textContent = originalText;
				showResult(
					'',
					(cfg.i18n && cfg.i18n.error) || 'خطا در ساخت توضیحات.',
					true,
					'',
					null
				);
			});
	}

	function handleSaveClick(event) {
		var btn = event.target.closest
			? event.target.closest('#ai-product-desc-save-btn')
			: null;

		if (!btn) {
			return;
		}

		event.preventDefault();
		event.stopPropagation();

		var cfg = getConfig();

		if (!lastResult.productId || !lastResult.description) {
			window.alert(
				(cfg.i18n && cfg.i18n.saveError) || 'ابتدا توضیحات را بسازید.'
			);
			return;
		}

		if (!cfg.ajaxUrl || !cfg.saveAction || !cfg.nonce) {
			window.alert(
				(cfg.i18n && cfg.i18n.saveError) || 'خطا در ذخیره توضیحات محصول.'
			);
			return;
		}

		if (btn.disabled) {
			return;
		}

		var originalText = btn.getAttribute('data-original-text') || btn.textContent;
		btn.setAttribute('data-original-text', originalText);
		btn.disabled = true;
		btn.classList.add('is-loading');
		btn.classList.remove('is-error');
		btn.textContent = (cfg.i18n && cfg.i18n.saving) || 'در حال ذخیره...';
		setSaveStatus('');

		var formData = new FormData();
		formData.append('action', cfg.saveAction);
		formData.append('nonce', cfg.nonce);
		formData.append('product_id', lastResult.productId);
		formData.append('description', lastResult.description);

		fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData,
		})
			.then(function (response) {
				return response.json().then(function (payload) {
					return { ok: response.ok, payload: payload };
				});
			})
			.then(function (result) {
				btn.disabled = false;
				btn.classList.remove('is-loading');
				btn.textContent = originalText;

				if (!result.payload || !result.payload.success) {
					btn.classList.add('is-error');
					var message =
						(result.payload &&
							result.payload.data &&
							result.payload.data.message) ||
						(cfg.i18n && cfg.i18n.saveError) ||
						'خطا در ذخیره توضیحات محصول.';
					setSaveStatus(message, true);
					return;
				}

				var data = result.payload.data || {};
				setSaveStatus(
					data.message || 'توضیحات ذخیره شد.',
					false
				);
				if (data.current_description) {
					renderCurrentDescription(data.current_description);
				}
			})
			.catch(function () {
				btn.disabled = false;
				btn.classList.remove('is-loading');
				btn.classList.add('is-error');
				btn.textContent = originalText;
				setSaveStatus(
					(cfg.i18n && cfg.i18n.saveError) ||
						'خطا در ذخیره توضیحات محصول.',
					true
				);
			});
	}

	function boot() {
		initSearch();
	}

	document.addEventListener('click', handleGenerateClick, true);
	document.addEventListener('click', handleSaveClick, true);

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}

	window.addEventListener('load', boot);
})();
