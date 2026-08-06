(function () {
	'use strict';

	var cfg = window.lkLookazmaSearch || {};
	var MIN = Math.max(1, parseInt(cfg.minChars, 10) || 3);

	var ICONS = {
		tags:
			'<svg class="lookazma_pas__section-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10zm-2-8h-2v2h2v-2zm0 4h-2v2h2v-2z"/></svg>',
		categories:
			'<svg class="lookazma_pas__section-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" aria-hidden="true" focusable="false"><path fill="currentColor" d="M10 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>',
		products:
			'<svg class="lookazma_pas__section-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" aria-hidden="true" focusable="false"><path fill="currentColor" d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 14c-2.76 0-5-2.24-5-5h2a3 3 0 0 0 6 0h2c0 2.76-2.24 5-5 5zm0-7c-.55 0-1-.45-1-1V5h2v4c0 .55-.45 1-1 1z"/></svg>',
	};

	function qs(sel, root) {
		return (root || document).querySelector(sel);
	}

	function escapeHtml(str) {
		return String(str == null ? '' : str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;');
	}

	function thumbHtml(item) {
		if (item && item.image) {
			return (
				'<span class="lookazma_pas__thumb">' +
				'<img src="' +
				escapeHtml(item.image) +
				'" alt="" loading="lazy" width="40" height="40" />' +
				'</span>'
			);
		}
		return '<span class="lookazma_pas__thumb lookazma_pas__thumb--empty" aria-hidden="true"></span>';
	}

	function sectionHtml(key, title, items, withMeta) {
		if (!items || !items.length) {
			return '';
		}
		var icon = ICONS[key] || '';
		var rows = items
			.map(function (item) {
				var meta =
					withMeta && item.meta
						? '<span class="lookazma_pas__meta">' +
						  escapeHtml(item.meta) +
						  '</span>'
						: '';
				return (
					'<li class="lookazma_pas__item">' +
					'<a class="lookazma_pas__link" href="' +
					escapeHtml(item.url || '#') +
					'" role="option">' +
					thumbHtml(item) +
					'<span class="lookazma_pas__text">' +
					'<span class="lookazma_pas__name">' +
					escapeHtml(item.name || '') +
					'</span>' +
					meta +
					'</span>' +
					'</a></li>'
				);
			})
			.join('');

		return (
			'<section class="lookazma_pas__section lookazma_pas__section--' +
			escapeHtml(key) +
			'">' +
			'<h3 class="lookazma_pas__section-title">' +
			icon +
			'<span>' +
			escapeHtml(title) +
			'</span></h3>' +
			'<ul class="lookazma_pas__list">' +
			rows +
			'</ul></section>'
		);
	}

	function buildResultsHtml(data) {
		var i18n = cfg.i18n || {};
		var html = '';
		// Order: companies (tags) → categories → products
		html += sectionHtml('tags', i18n.tags || 'شرکت ها', data.tags || [], false);
		html += sectionHtml(
			'categories',
			i18n.categories || 'دسته بندی',
			data.categories || [],
			false
		);
		html += sectionHtml(
			'products',
			i18n.products || 'محصولات',
			data.products || [],
			true
		);
		return html;
	}

	function hasAnyResults(data) {
		return (
			(data.categories && data.categories.length) ||
			(data.products && data.products.length) ||
			(data.tags && data.tags.length)
		);
	}

	function bindRoot(root) {
		if (!root || root.getAttribute('data-lk-bound') === '1') {
			return;
		}
		root.setAttribute('data-lk-bound', '1');

		var input = qs('.lookazma_pas__input', root);
		var dropdown = qs('.lookazma_pas__dropdown', root);
		var scroll = qs('[data-lk-scroll]', root);
		var more = qs('[data-lk-more]', root);
		if (!input || !dropdown || !scroll || !more) {
			return;
		}

		var timer = null;
		var requestId = 0;

		function close() {
			dropdown.hidden = true;
			input.setAttribute('aria-expanded', 'false');
			root.classList.remove('is-loading');
		}

		function open() {
			dropdown.hidden = false;
			input.setAttribute('aria-expanded', 'true');
		}

		function setMore(url) {
			if (url) {
				more.href = url;
				more.hidden = false;
				more.textContent =
					(cfg.i18n && cfg.i18n.more) || 'مشاهده سایر محصولات';
			} else {
				more.hidden = true;
			}
		}

		function runSearch(q) {
			if (!cfg.ajaxUrl || !cfg.action || !cfg.nonce) {
				return;
			}

			var myId = ++requestId;
			root.classList.add('is-loading');

			var body = new FormData();
			body.append('action', cfg.action);
			body.append('nonce', cfg.nonce);
			body.append('q', q);

			fetch(cfg.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: body,
			})
				.then(function (res) {
					return res.json();
				})
				.then(function (json) {
					if (myId !== requestId) {
						return;
					}
					root.classList.remove('is-loading');
					if (!json || !json.success || !json.data) {
						scroll.innerHTML =
							'<p class="lookazma_pas__empty">' +
							escapeHtml((cfg.i18n && cfg.i18n.error) || 'خطا') +
							'</p>';
						setMore('');
						open();
						return;
					}

					var data = json.data;
					setMore(data.more_url || '');

					if (!hasAnyResults(data)) {
						scroll.innerHTML =
							'<p class="lookazma_pas__empty">' +
							escapeHtml((cfg.i18n && cfg.i18n.empty) || 'نتیجه‌ای یافت نشد.') +
							'</p>';
					} else {
						scroll.innerHTML = buildResultsHtml(data);
					}
					open();
				})
				.catch(function () {
					if (myId !== requestId) {
						return;
					}
					root.classList.remove('is-loading');
					scroll.innerHTML =
						'<p class="lookazma_pas__empty">' +
						escapeHtml((cfg.i18n && cfg.i18n.error) || 'خطا') +
						'</p>';
					setMore('');
					open();
				});
		}

		function onInput() {
			var q = String(input.value || '').trim();
			window.clearTimeout(timer);

			if (q.length < MIN) {
				close();
				scroll.innerHTML = '';
				setMore('');
				return;
			}

			timer = window.setTimeout(function () {
				runSearch(q);
			}, 250);
		}

		input.addEventListener('input', onInput);
		input.addEventListener('focus', function () {
			var q = String(input.value || '').trim();
			if (q.length >= MIN && scroll.innerHTML) {
				open();
			}
		});

		input.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') {
				close();
			}
		});

		document.addEventListener('click', function (event) {
			if (!root.contains(event.target)) {
				close();
			}
		});
	}

	function boot() {
		document.querySelectorAll('[data-lk-lookazma-search]').forEach(bindRoot);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}

	window.addEventListener('elementor/frontend/init', function () {
		if (window.elementorFrontend && window.elementorFrontend.hooks) {
			window.elementorFrontend.hooks.addAction(
				'frontend/element_ready/lookazma_search.default',
				function ($scope) {
					var el =
						$scope && $scope[0]
							? $scope[0].querySelector('[data-lk-lookazma-search]')
							: null;
					if (el) {
						bindRoot(el);
					}
				}
			);
		}
	});

	window.addEventListener('load', boot);
})();
