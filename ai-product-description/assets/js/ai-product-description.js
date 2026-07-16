(function () {
	'use strict';

	if (window.aiProductDescBound) {
		return;
	}
	window.aiProductDescBound = true;

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

	function lookazmaLink(label) {
		return (
			'<a href="https://lookazma.com/" target="_blank" rel="noopener noreferrer">' +
			label +
			'</a>'
		);
	}

	/**
	 * Escape text, keep line breaks, and turn Lookazma mentions into links.
	 */
	function formatDescriptionHtml(text) {
		var escaped = escapeHtml(text).replace(/\r\n|\r|\n/g, '<br>');
		var storeUrlPattern =
			/https?:\/\/(?:www\.)?lookazma\.com\/?/gi;
		var plainDomainPattern = /(?:^|[\s(])((?:www\.)?lookazma\.com\/?)/gi;

		escaped = escaped.replace(storeUrlPattern, function () {
			return lookazmaLink('https://lookazma.com/');
		});

		escaped = escaped.replace(plainDomainPattern, function (match, domain) {
			var prefix = match.slice(0, match.length - domain.length);
			return prefix + lookazmaLink('https://lookazma.com/');
		});

		// If no URL was linked, wrap "خرید از لوک آزما" / "لوک آزما" once.
		if (escaped.indexOf('href="https://lookazma.com/"') === -1) {
			if (/خرید\s+از\s+لوک\s*آزما/.test(escaped)) {
				escaped = escaped.replace(
					/خرید\s+از\s+لوک\s*آزما/,
					lookazmaLink('خرید از لوک آزما')
				);
			} else if (/لوک\s*آزما/.test(escaped)) {
				escaped = escaped.replace(/لوک\s*آزما/, lookazmaLink('لوک آزما'));
			}
		}

		return escaped;
	}

	function showResult(productName, text, isError) {
		var resultWrap = document.getElementById('ai-product-desc-result');
		var resultTitle = document.getElementById('ai-product-desc-result-title');
		var resultBody = document.getElementById('ai-product-desc-result-body');

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
		} else {
			resultBody.innerHTML = formatDescriptionHtml(text);
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
					showResult('', message, true);
					return;
				}

				var data = result.payload.data || {};
				showResult(data.product_name || '', data.description || '', false);
			})
			.catch(function () {
				btn.disabled = false;
				btn.classList.remove('is-loading');
				btn.classList.add('is-error');
				btn.textContent = originalText;
				showResult(
					'',
					(cfg.i18n && cfg.i18n.error) || 'خطا در ساخت توضیحات.',
					true
				);
			});
	}

	function boot() {
		initSearch();
	}

	document.addEventListener('click', handleGenerateClick, true);

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}

	window.addEventListener('load', boot);
})();
