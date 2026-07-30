(function () {
	'use strict';

	var cfg = window.lkArchiveFilters || {};
	var injectHtml = window.lkArchiveFiltersInjectHtml || '';
	var debounceTimer = null;
	var requestId = 0;
	var currentPage = 1;
	var shopCardsLoaded = false;

	function qs(sel, root) {
		return (root || document).querySelector(sel);
	}

	function qsa(sel, root) {
		return Array.prototype.slice.call((root || document).querySelectorAll(sel));
	}

	function findAccordionFilterHost() {
		var byId = qs('.elementor-element-09116fc');
		if (byId) {
			return byId;
		}

		var widgets = qsa(
			'.elementor-widget-n-accordion, .elementor-widget-accordion, .elementor-widget-nested-accordion'
		);
		for (var i = 0; i < widgets.length; i++) {
			var text = String(widgets[i].textContent || '');
			if (
				text.indexOf('برند') !== -1 &&
				(text.indexOf('گرید') !== -1 || text.indexOf('بسته') !== -1)
			) {
				return widgets[i];
			}
		}
		return null;
	}

	function findOldFiltersRoot() {
		var accordionHost = findAccordionFilterHost();
		if (accordionHost) {
			return accordionHost;
		}

		var jetNodes = qsa(
			'.elementor-widget-jet-smart-filters-checkboxes, .elementor-widget-jet-smart-filters-select, .elementor-widget-jet-smart-filters-range, .jet-smart-filters'
		);
		if (!jetNodes.length) {
			return null;
		}

		var node = jetNodes[0];
		var accordion = node.closest(
			'.elementor-widget-n-accordion, .elementor-widget-accordion, .e-n-accordion, .elementor-accordion'
		);
		if (accordion) {
			return accordion.closest('.elementor-element') || accordion;
		}

		var col = node.closest('.elementor-element.elementor-column, .e-con');
		return col || node.parentElement;
	}

	function hideLegacyFilterChrome() {
		[
			'.elementor-element-3fc416f',
			'.elementor-element-77cf154',
		].forEach(function (sel) {
			qsa(sel).forEach(function (el) {
				el.classList.add('lk-hide-old-filters');
			});
		});

		qsa('.elementor-widget-jet-smart-filters-remove-filters').forEach(function (el) {
			var con = el.closest('.e-con.e-child, .e-con, .elementor-element');
			if (con) {
				con.classList.add('lk-hide-old-filters');
			}
		});

		qsa('.elementor-heading-title').forEach(function (title) {
			var text = String(title.textContent || '')
				.replace(/\s+/g, ' ')
				.trim();
			if (text !== 'فیلتر ها' && text !== 'فیلترها' && text !== 'فیلتر') {
				return;
			}
			var widget = title.closest('.elementor-element');
			if (!widget) {
				return;
			}
			var con = widget.closest('.e-con.e-child, .e-con');
			if (con && con.querySelector('.elementor-widget-heading')) {
				con.classList.add('lk-hide-old-filters');
			} else {
				widget.classList.add('lk-hide-old-filters');
			}
		});
	}

	function hideOldFilters() {
		hideLegacyFilterChrome();

		var root = findOldFiltersRoot();
		qsa(
			'.elementor-widget-jet-smart-filters-checkboxes, .elementor-widget-jet-smart-filters-select, .elementor-widget-jet-smart-filters-range, .elementor-widget-jet-smart-filters-remove-filters, .elementor-widget-jet-smart-filters-active, .jet-smart-filters'
		).forEach(function (el) {
			el.classList.add('lk-hide-old-filters');
		});

		if (root) {
			qsa('.e-n-accordion, .elementor-accordion, .e-n-accordion-item', root).forEach(
				function (el) {
					el.classList.add('lk-hide-old-filters');
				}
			);
		}

		return root;
	}

	function placeFiltersInHost(filters, host) {
		if (!filters || !host) {
			return false;
		}

		var mount =
			qs('.elementor-widget-container', host) || host;
		qsa('.e-n-accordion, .elementor-accordion', mount).forEach(function (el) {
			el.classList.add('lk-hide-old-filters');
		});

		if (filters.parentNode !== mount) {
			mount.appendChild(filters);
		}
		host.classList.add('lk-archive-filters-host');
		return true;
	}

	function injectFilters() {
		var existing = document.getElementById('lk-archive-filters');
		var host = hideOldFilters() || findAccordionFilterHost();

		if (existing) {
			if (host) {
				placeFiltersInHost(existing, host);
			}
			return existing;
		}

		if (!injectHtml) {
			return null;
		}

		var wrap = document.createElement('div');
		wrap.innerHTML = injectHtml;
		var filters = wrap.firstElementChild;
		if (!filters) {
			return null;
		}

		if (host && placeFiltersInHost(filters, host)) {
			return filters;
		}

		var loop =
			qs('.elementor-loop-container.elementor-grid') ||
			qs('.elementor-loop-container');
		if (loop) {
			var loopHost =
				loop.closest('.elementor-widget-container') ||
				loop.parentElement;
			var col = loopHost && loopHost.closest('.elementor-element');
			var section = col && col.parentElement;
			if (section) {
				section.insertBefore(filters, section.firstChild);
				return filters;
			}
		}

		var main = qs('main') || qs('#content') || document.body;
		main.insertBefore(filters, main.firstChild);
		return filters;
	}

	function getLoopContainer() {
		return (
			qs('#lk-archive-products') ||
			qs('.lk-archive-products__grid') ||
			qs('.elementor-widget-container > .elementor-loop-container.elementor-grid') ||
			qs('.elementor-loop-container.elementor-grid') ||
			qs('.elementor-loop-container')
		);
	}

	function getPaginationContainer() {
		return (
			qs('#lk-archive-products-pagination') ||
			qs('.lk-shop-pagination') ||
			qs('.lk-archive-products__pagination')
		);
	}

	function ensurePaginationContainer() {
		var existing = getPaginationContainer();
		if (existing) {
			return existing;
		}

		var loop = getLoopContainer();
		if (!loop) {
			return null;
		}

		var nav = document.createElement('nav');
		nav.id = 'lk-archive-products-pagination';
		nav.className = 'lk-shop-pagination lk-archive-products__pagination';
		nav.setAttribute(
			'aria-label',
			(cfg.i18n && cfg.i18n.pagination) || 'صفحه‌بندی محصولات'
		);

		var widget = loop.closest('.elementor-element') || loop.parentElement;
		if (widget && widget.parentElement) {
			widget.parentElement.insertBefore(nav, widget.nextSibling);
		} else if (loop.parentElement) {
			loop.parentElement.appendChild(nav);
		} else {
			return null;
		}

		return nav;
	}

	function updatePagination(html) {
		var nav = cfg.isShop
			? ensurePaginationContainer() || getPaginationContainer()
			: getPaginationContainer();
		if (!nav) {
			return;
		}
		nav.innerHTML = html || '';
		nav.hidden = !html;
	}

	function pageFromHref(href) {
		if (!href) {
			return 1;
		}
		var match = String(href).match(/\/page\/(\d+)/i);
		if (match && match[1]) {
			return Math.max(1, parseInt(match[1], 10) || 1);
		}
		try {
			var url = new URL(href, window.location.origin);
			var paged = url.searchParams.get('paged') || url.searchParams.get('page');
			if (paged) {
				return Math.max(1, parseInt(paged, 10) || 1);
			}
		} catch (e) {
			// ignore
		}
		return 1;
	}

	function setLoopLoading(on) {
		var loop = getLoopContainer();
		if (!loop) {
			return;
		}
		loop.classList.toggle('lk-loop-loading', !!on);

		var overlay = qs('.lk-loop-loading-overlay', loop);
		if (on) {
			if (!overlay) {
				overlay = document.createElement('div');
				overlay.className = 'lk-loop-loading-overlay';
				overlay.setAttribute('role', 'status');
				overlay.innerHTML =
					'<span class="lk-loop-loading-label"></span>' +
					'<span class="lk-loop-loading-icon" aria-hidden="true">' +
					'<svg viewBox="0 0 24 24" width="20" height="20" focusable="false">' +
					'<circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-dasharray="46 60"/>' +
					'</svg>' +
					'</span>';
				loop.appendChild(overlay);
			}
			var label = qs('.lk-loop-loading-label', overlay);
			if (label) {
				label.textContent =
					(cfg.i18n && cfg.i18n.loading) || 'در حال فیلتر…';
			}
		} else if (overlay) {
			overlay.remove();
		}
	}

	function collectFilters(root) {
		var payload = {};
		qsa('[data-lk-filter-key]', root).forEach(function (panel) {
			var key = panel.getAttribute('data-lk-filter-key');
			var values = qsa('input[type="checkbox"]:checked', panel).map(function (input) {
				return input.value;
			});
			payload[key] = values;
		});

		var minInput = qs('[data-lk-price="min"]', root);
		var maxInput = qs('[data-lk-price="max"]', root);
		payload.price = {
			min: minInput && minInput.value !== '' ? minInput.value : '',
			max: maxInput && maxInput.value !== '' ? maxInput.value : '',
		};
		if (payload.price.min !== '') {
			payload.lk_f_price_min = payload.price.min;
		}
		if (payload.price.max !== '') {
			payload.lk_f_price_max = payload.price.max;
		}

		return payload;
	}

	function panelHasSelection(panel) {
		if (panel.hasAttribute('data-lk-filter-key')) {
			return qsa('input[type="checkbox"]:checked', panel).length > 0;
		}
		if (panel.hasAttribute('data-lk-filter-price')) {
			var minInput = qs('[data-lk-price="min"]', panel);
			var maxInput = qs('[data-lk-price="max"]', panel);
			return (
				(minInput && minInput.value !== '') ||
				(maxInput && maxInput.value !== '')
			);
		}
		return false;
	}

	function syncPanelOpenState(root) {
		qsa('.lk-archive-filters__panel', root).forEach(function (panel) {
			panel.open = panelHasSelection(panel);
		});
	}

	function applySearchFilter(panel) {
		var input = qs('.lk-archive-filters__search', panel);
		var q = input
			? String(input.value || '')
					.trim()
					.toLowerCase()
			: '';
		qsa('.lk-archive-filters__item', panel).forEach(function (item) {
			var available = item.getAttribute('data-lk-available') !== '0';
			var checked = !!qs('input[type="checkbox"]:checked', item);
			var label = item.getAttribute('data-lk-label') || '';
			var matchSearch = q === '' || label.indexOf(q) !== -1;
			item.hidden = !(matchSearch && (available || checked));
		});
	}

	function updateFacets(root, facets) {
		if (!facets || typeof facets !== 'object') {
			return;
		}

		Object.keys(facets).forEach(function (key) {
			var panel = qs('[data-lk-filter-key="' + key + '"]', root);
			if (!panel) {
				return;
			}

			var options = facets[key] || [];
			var bySlug = {};
			options.forEach(function (opt) {
				if (opt && opt.slug) {
					bySlug[opt.slug] = opt;
				}
			});

			qsa('.lk-archive-filters__item', panel).forEach(function (item) {
				var input = qs('input[type="checkbox"]', item);
				if (!input) {
					return;
				}
				var slug = input.value;
				var opt = bySlug[slug];
				var countEl = qs('.lk-archive-filters__check-count', item);

				if (opt) {
					item.setAttribute('data-lk-available', '1');
					if (countEl) {
						countEl.textContent = '(' + String(opt.count) + ')';
					}
				} else {
					item.setAttribute('data-lk-available', '0');
					if (countEl) {
						countEl.textContent = '(0)';
					}
				}
			});

			applySearchFilter(panel);
		});
	}

	function runFilter(root, page) {
		if (!cfg.ajaxUrl || !cfg.action || !cfg.nonce) {
			return;
		}

		currentPage = Math.max(1, parseInt(page, 10) || 1);

		var myId = ++requestId;
		setLoopLoading(true);
		syncPanelOpenState(root);

		var formData = new FormData();
		formData.append('action', cfg.action);
		formData.append('nonce', cfg.nonce);
		formData.append('term_id', root.getAttribute('data-term-id') || cfg.termId || 0);
		formData.append('page', String(currentPage));
		formData.append('per_page', String(cfg.perPage || 12));
		formData.append('layout', cfg.layout || (cfg.isShop ? 'shop' : 'archive'));
		formData.append('filters', JSON.stringify(collectFilters(root)));

		fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData,
		})
			.then(function (res) {
				return res.json();
			})
			.then(function (json) {
				if (myId !== requestId) {
					return;
				}
				setLoopLoading(false);
				var loop = getLoopContainer();
				if (!loop) {
					return;
				}
				if (!json || !json.success) {
					loop.innerHTML =
						'<div class="lk-filtered-products__empty">' +
						((cfg.i18n && cfg.i18n.error) || 'خطا') +
						'</div>';
					updatePagination('');
					return;
				}
				loop.innerHTML =
					(json.data && json.data.html) ||
					'<div class="lk-filtered-products__empty">' +
						((cfg.i18n && cfg.i18n.empty) || '') +
						'</div>';
				if (cfg.isShop) {
					markShopGrid();
					shopCardsLoaded = true;
				}
				if (json.data && typeof json.data.pagination !== 'undefined') {
					updatePagination(json.data.pagination);
				}
				if (json.data && json.data.facets) {
					updateFacets(root, json.data.facets);
				}
				syncPanelOpenState(root);
			})
			.catch(function () {
				if (myId !== requestId) {
					return;
				}
				setLoopLoading(false);
			});
	}

	function scheduleFilter(root, page) {
		window.clearTimeout(debounceTimer);
		debounceTimer = window.setTimeout(function () {
			runFilter(root, typeof page === 'undefined' ? 1 : page);
		}, 250);
	}

	function bindSearch(root) {
		qsa('.lk-archive-filters__search', root).forEach(function (input) {
			input.addEventListener('input', function () {
				var panel = input.closest('.lk-archive-filters__panel');
				if (panel) {
					applySearchFilter(panel);
				}
			});
		});
	}

	function bindPagination(root) {
		if (!root) {
			return;
		}
		var pagination = cfg.isShop
			? ensurePaginationContainer()
			: getPaginationContainer();
		if (!pagination || pagination.getAttribute('data-lk-bound') === '1') {
			return;
		}
		pagination.setAttribute('data-lk-bound', '1');
		pagination.addEventListener('click', function (event) {
			var link = event.target.closest('a.page-numbers');
			if (!link) {
				return;
			}
			event.preventDefault();
			scheduleFilter(root, pageFromHref(link.getAttribute('href')));
			var loop = getLoopContainer();
			if (loop && typeof loop.scrollIntoView === 'function') {
				loop.scrollIntoView({ behavior: 'smooth', block: 'start' });
			}
		});
	}

	function bindEvents(root) {
		if (!root || root.getAttribute('data-lk-bound') === '1') {
			bindPagination(root);
			return;
		}
		root.setAttribute('data-lk-bound', '1');

		bindSearch(root);
		syncPanelOpenState(root);

		root.addEventListener('change', function (event) {
			var t = event.target;
			if (t && t.matches('input[type="checkbox"]')) {
				scheduleFilter(root, 1);
			}
		});

		var priceBtn = qs('.lk-archive-filters__price-apply', root);
		if (priceBtn) {
			priceBtn.addEventListener('click', function () {
				scheduleFilter(root, 1);
			});
		}

		var clearBtn = qs('#lk-archive-filters-clear', root);
		if (clearBtn) {
			clearBtn.addEventListener('click', function () {
				qsa('input[type="checkbox"]', root).forEach(function (input) {
					input.checked = false;
				});
				qsa('[data-lk-price]', root).forEach(function (input) {
					input.value = '';
				});
				qsa('.lk-archive-filters__item', root).forEach(function (item) {
					item.setAttribute('data-lk-available', '1');
				});
				qsa('.lk-archive-filters__search', root).forEach(function (input) {
					input.value = '';
				});
				qsa('[data-lk-filter-key]', root).forEach(function (panel) {
					applySearchFilter(panel);
				});
				scheduleFilter(root, 1);
			});
		}

		bindPagination(root);
	}

	function markShopGrid() {
		var loop = getLoopContainer();
		if (loop) {
			loop.classList.add('lk-shop-products-grid');
		}
		ensurePaginationContainer();
	}

	function getCartUrl() {
		if (cfg.cartUrl) {
			return cfg.cartUrl;
		}
		if (window.wc_add_to_cart_params && window.wc_add_to_cart_params.cart_url) {
			return window.wc_add_to_cart_params.cart_url;
		}
		return '/cart/';
	}

	function ensureViewCartIcon(card) {
		if (!card) {
			return null;
		}
		var media = card.querySelector('.lk-loop-item__media');
		if (!media) {
			return null;
		}
		var existing = media.querySelector('.lk-loop-item__view-cart');
		if (existing) {
			return existing;
		}

		var tip = (cfg.i18n && cfg.i18n.viewCart) || 'مشاهده سبد خرید';
		var link = document.createElement('a');
		link.className = 'lk-loop-item__view-cart';
		link.href = getCartUrl();
		link.setAttribute('aria-label', tip);
		link.setAttribute('data-tooltip', tip);
		link.setAttribute('title', tip);
		link.innerHTML =
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512" aria-hidden="true" focusable="false">' +
			'<path d="M572.52 241.4C518.29 135.59 410.93 64 288 64S57.68 135.64 3.48 241.41a32.35 32.35 0 0 0 0 29.19C57.71 376.41 165.07 448 288 448s230.32-71.64 284.52-177.41a32.35 32.35 0 0 0 0-29.19zM288 400a144 144 0 1 1 144-144 143.93 143.93 0 0 1-144 144zm0-240a95.31 95.31 0 0 0-25.31 3.79 47.85 47.85 0 0 1-66.9 66.9A95.78 95.78 0 1 0 288 160z"/>' +
			'</svg>';
		media.appendChild(link);
		return link;
	}

	function showViewCartIcon(card) {
		var icon = ensureViewCartIcon(card);
		if (icon) {
			icon.classList.add('is-visible');
		}
	}

	function bindShopAddToCartFeedback() {
		if (!cfg.isShop || window.lkShopAddToCartBound) {
			return;
		}
		window.lkShopAddToCartBound = true;

		if (typeof jQuery === 'undefined') {
			return;
		}

		jQuery(document.body).on('adding_to_cart', function (event, $button) {
			if (!$button || !$button.length || !$button.hasClass('lk-loop-item__add-to-cart')) {
				return;
			}
			var label = $button.find('.lk-loop-item__add-to-cart-label');
			if (label.length) {
				label.text((cfg.i18n && cfg.i18n.adding) || 'در حال افزودن…');
			}
		});

		jQuery(document.body).on('added_to_cart', function (event, fragments, cartHash, $button) {
			if (!$button || !$button.length || !$button.hasClass('lk-loop-item__add-to-cart')) {
				return;
			}
			var label = $button.find('.lk-loop-item__add-to-cart-label');
			if (label.length) {
				label.text((cfg.i18n && cfg.i18n.added) || 'افزوده شد');
			}

			var card = $button.closest('.lk-loop-item--shop').get(0);
			showViewCartIcon(card);

			// Remove WooCommerce's default "View cart" text link if inserted.
			$button.siblings('a.added_to_cart').remove();

			window.setTimeout(function () {
				if (label.length) {
					label.text((cfg.i18n && cfg.i18n.addToCart) || 'افزودن به سبد');
				}
				$button.removeClass('added');
			}, 1800);
		});
	}

	function boot() {
		var root = injectFilters();
		if (root) {
			bindEvents(root);
		}
		if (cfg.isShop) {
			markShopGrid();
			bindShopAddToCartFeedback();
			document.documentElement.classList.add('lk-shop-filters-ready');
			if (root && !shopCardsLoaded && !document.querySelector('.lk-loop-item--shop')) {
				shopCardsLoaded = true;
				runFilter(root, 1);
			}
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
	window.addEventListener('load', boot);
})();
