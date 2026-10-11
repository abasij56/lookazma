(function () {
	'use strict';

	var cfg = window.lkProductCompare || {};
	var STORAGE_KEY = cfg.storageKey || 'lk_compare_ids';
	var PENDING_KEY = cfg.pendingKey || 'lk_compare_pending_id';
	var MAX = parseInt(cfg.maxItems, 10) || 4;
	var MIN = parseInt(cfg.minItems, 10) || 2;
	var i18n = cfg.i18n || {};
	var searchTimer = 0;
	var searchRequest = 0;
	var activeSlotIndex = 0;
	var matrixCache = null;
	var productMeta = {};
	var chromeBound = false;

	function qs(sel, root) {
		return (root || document).querySelector(sel);
	}

	function qsa(sel, root) {
		return Array.prototype.slice.call((root || document).querySelectorAll(sel));
	}

	function escapeHtml(str) {
		return String(str == null ? '' : str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;');
	}

	function toFaDigits(value) {
		return String(value == null ? '' : value).replace(/\d/g, function (d) {
			return '۰۱۲۳۴۵۶۷۸۹'.charAt(parseInt(d, 10));
		});
	}

	function formatTrayCount(count) {
		var template = i18n.trayCount || '%s محصول';
		return String(template).replace('%s', toFaDigits(count));
	}

	function emptySlots() {
		var slots = [];
		var i;
		for (i = 0; i < MAX; i++) {
			slots.push(0);
		}
		return slots;
	}

	/**
	 * Pack filled ids to the front (no holes). Removing a middle product
	 * shifts later products left so the add slot stays at the end.
	 */
	function compactSlots(raw) {
		var packed = emptySlots();
		var out = 0;
		var i;
		var id;
		if (!Array.isArray(raw)) {
			return packed;
		}
		for (i = 0; i < raw.length && out < MAX; i++) {
			id = parseInt(raw[i], 10) || 0;
			if (id > 0) {
				packed[out++] = id;
			}
		}
		return packed;
	}

	function normalizeSlots(raw) {
		return compactSlots(raw);
	}

	function readSlots() {
		try {
			var raw = window.localStorage.getItem(STORAGE_KEY);
			if (!raw) {
				return emptySlots();
			}
			var parsed = JSON.parse(raw);
			if (!Array.isArray(parsed)) {
				return emptySlots();
			}
			return normalizeSlots(parsed);
		} catch (err) {
			return emptySlots();
		}
	}

	function writeSlots(slots) {
		var clean = normalizeSlots(slots);
		try {
			window.localStorage.setItem(STORAGE_KEY, JSON.stringify(clean));
		} catch (err) {
			/* ignore */
		}
		syncUrl(clean);
		refreshChrome(clean);
		return clean;
	}

	function readIds() {
		var slots = readSlots();
		var ids = [];
		var i;
		for (i = 0; i < MAX; i++) {
			if (slots[i] > 0) {
				ids.push(slots[i]);
			}
		}
		return ids;
	}

	function filledCount(slots) {
		var count = 0;
		var i;
		for (i = 0; i < MAX; i++) {
			if (slots[i] > 0) {
				count++;
			}
		}
		return count;
	}

	function firstEmptySlot(slots) {
		var i;
		for (i = 0; i < MAX; i++) {
			if (!slots[i]) {
				return i;
			}
		}
		return -1;
	}

	function slotHasId(slots, id) {
		id = parseInt(id, 10);
		var i;
		for (i = 0; i < MAX; i++) {
			if (slots[i] === id) {
				return true;
			}
		}
		return false;
	}

	function slotsEqual(a, b) {
		var i;
		for (i = 0; i < MAX; i++) {
			if ((parseInt(a[i], 10) || 0) !== (parseInt(b[i], 10) || 0)) {
				return false;
			}
		}
		return true;
	}

	function readPending() {
		try {
			return parseInt(window.localStorage.getItem(PENDING_KEY), 10) || 0;
		} catch (err) {
			return 0;
		}
	}

	function setPending(id) {
		id = parseInt(id, 10);
		if (id <= 0) {
			return;
		}
		try {
			window.localStorage.setItem(PENDING_KEY, String(id));
		} catch (err) {
			/* ignore */
		}
	}

	function clearPending() {
		try {
			window.localStorage.removeItem(PENDING_KEY);
		} catch (err) {
			/* ignore */
		}
	}

	function buildCompareUrl(slots) {
		var base = cfg.compareUrl || '/compare/';
		var url;
		try {
			url = new URL(base, window.location.origin);
		} catch (err) {
			return base;
		}
		slots = normalizeSlots(slots || readSlots());
		var i;
		url.searchParams.delete('ids');
		for (i = 0; i < MAX; i++) {
			url.searchParams.delete(String(i));
			if (slots[i] > 0) {
				url.searchParams.set(String(i), 'product-' + slots[i]);
			}
		}
		return url.toString();
	}

	function syncUrl(slots) {
		if (!cfg.isComparePage || !window.history || !window.history.replaceState) {
			return;
		}
		var url = new URL(window.location.href);
		var i;
		url.searchParams.delete('ids');
		for (i = 0; i < MAX; i++) {
			url.searchParams.delete(String(i));
			if (slots[i] > 0) {
				url.searchParams.set(String(i), 'product-' + slots[i]);
			}
		}
		window.history.replaceState(null, '', url.toString());
	}

	function parseProductParam(value) {
		if (!value) {
			return 0;
		}
		var match = String(value).match(/^product-(\d+)$/i);
		if (match) {
			return parseInt(match[1], 10);
		}
		var num = parseInt(value, 10);
		return num > 0 ? num : 0;
	}

	function slotsFromUrl() {
		try {
			var url = new URL(window.location.href);
			var slots = emptySlots();
			var hasSlot = false;
			var i;
			for (i = 0; i < MAX; i++) {
				var val = url.searchParams.get(String(i));
				if (!val) {
					continue;
				}
				var id = parseProductParam(val);
				if (id > 0) {
					slots[i] = id;
					hasSlot = true;
				}
			}
			if (hasSlot) {
				return slots;
			}
			var legacy = url.searchParams.get('ids');
			if (!legacy) {
				return null;
			}
			var compact = legacy
				.split(',')
				.map(function (part) {
					return parseInt(part.trim(), 10);
				})
				.filter(function (id) {
					return id > 0;
				})
				.slice(0, MAX);
			for (i = 0; i < compact.length; i++) {
				slots[i] = compact[i];
			}
			return slots;
		} catch (err) {
			return null;
		}
	}

	function toast(message) {
		if (!message) {
			return;
		}
		var el = qs('[data-lk-compare-toast]');
		if (!el) {
			return;
		}
		/* Prefer FAB popup when chrome is present */
		var fabPopup = qs('.lk-compare-fab__popup[data-lk-compare-toast]');
		if (fabPopup) {
			el = fabPopup;
		}
		el.textContent = message;
		el.hidden = false;
		window.clearTimeout(toast._timer);
		toast._timer = window.setTimeout(function () {
			el.hidden = true;
		}, 2600);
	}

	function setBoardLoading(isLoading) {
		var el = qs('[data-lk-compare-board-loading]');
		if (!el) {
			return;
		}
		el.hidden = !isLoading;
		el.setAttribute('aria-hidden', isLoading ? 'false' : 'true');
	}

	function fetchMatrix(slots) {
		if (!cfg.ajaxUrl || !cfg.action || !cfg.nonce) {
			return Promise.reject(new Error('config'));
		}
		var body = new FormData();
		body.append('action', cfg.action);
		body.append('nonce', cfg.nonce);
		body.append('slots', JSON.stringify(normalizeSlots(slots)));

		return fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body,
		})
			.then(function (res) {
				return res.json();
			})
			.then(function (json) {
				if (!json || !json.success || !json.data) {
					throw new Error('ajax');
				}
				return json.data;
			});
	}

	function fetchPickerProducts(query) {
		if (!cfg.ajaxUrl || !cfg.searchAction || !cfg.nonce) {
			return Promise.reject(new Error('config'));
		}
		var body = new FormData();
		body.append('action', cfg.searchAction);
		body.append('nonce', cfg.nonce);
		body.append('q', query || '');

		return fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body,
		})
			.then(function (res) {
				return res.json();
			})
			.then(function (json) {
				if (!json || !json.success || !json.data) {
					throw new Error('ajax');
				}
				return json.data.products || [];
			});
	}

	function setPickerLoading(isLoading) {
		var picker = qs('[data-lk-compare-picker]');
		if (picker) {
			picker.classList.toggle('is-loading', !!isLoading);
		}
	}

	function renderPickerResults(products) {
		var list = qs('[data-lk-compare-results]');
		if (!list) {
			return;
		}

		var ids = readIds();
		if (!products || !products.length) {
			list.innerHTML =
				'<p class="lk-compare-picker__empty">' +
				escapeHtml(i18n.searchEmpty || 'نتیجه‌ای یافت نشد.') +
				'</p>';
			return;
		}

		var html = '<ul class="lk-compare-picker__list">';
		products.forEach(function (product) {
			var id = parseInt(product.id, 10);
			var inList = ids.indexOf(id) !== -1;
			var thumb = product.image
				? '<span class="lk-compare-picker__thumb"><img src="' +
					escapeHtml(product.image) +
					'" alt="" loading="lazy" width="40" height="40" /></span>'
				: '<span class="lk-compare-picker__thumb lk-compare-picker__thumb--empty" aria-hidden="true"></span>';

			html +=
				'<li class="lk-compare-picker__item" role="presentation">' +
				'<button type="button" class="lk-compare-picker__row' +
				(inList ? ' is-added' : '') +
				'" data-lk-compare-pick="' +
				escapeHtml(id) +
				'" role="option"' +
				(inList ? ' aria-disabled="true"' : '') +
				'>' +
				thumb +
				'<span class="lk-compare-picker__text">' +
				'<span class="lk-compare-picker__name">' +
				escapeHtml(product.name || '') +
				'</span>' +
				(product.meta
					? '<span class="lk-compare-picker__meta">' + escapeHtml(product.meta) + '</span>'
					: '') +
				'</span>' +
				(inList
					? '<span class="lk-compare-picker__badge">' + escapeHtml(i18n.inCompare || 'اضافه شد') + '</span>'
					: '') +
				'</button></li>';
		});
		html += '</ul>';
		list.innerHTML = html;
	}

	function loadPicker(query) {
		var requestId = ++searchRequest;
		setPickerLoading(true);

		return fetchPickerProducts(query)
			.then(function (products) {
				if (requestId !== searchRequest) {
					return;
				}
				renderPickerResults(products);
			})
			.catch(function () {
				if (requestId !== searchRequest) {
					return;
				}
				var list = qs('[data-lk-compare-results]');
				if (list) {
					list.innerHTML =
						'<p class="lk-compare-picker__empty">' +
						escapeHtml(i18n.searchError || 'خطا در جستجو.') +
						'</p>';
				}
			})
			.finally(function () {
				if (requestId === searchRequest) {
					setPickerLoading(false);
				}
			});
	}

	function renderAddSlot(slotIndex) {
		return (
			'<button type="button" class="lk-compare-slot lk-compare-slot--add" data-lk-compare-open="' +
			slotIndex +
			'">' +
			'<span class="lk-compare-slot__add-icon" aria-hidden="true">' +
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="28" height="28" focusable="false">' +
			'<path fill="currentColor" d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>' +
			'</svg>' +
			'</span>' +
			'<span class="lk-compare-slot__add-label">' +
			escapeHtml(i18n.addProduct || 'افزودن کالا') +
			'</span>' +
			'</button>'
		);
	}

	function renderFilledSlot(product, slotIndex) {
		return (
			'<article class="lk-compare-slot lk-compare-slot--filled" data-lk-compare-slot="' +
			escapeHtml(product.id) +
			'" data-slot-index="' +
			slotIndex +
			'">' +
			'<button type="button" class="lk-compare-slot__remove" data-lk-compare-remove="' +
			escapeHtml(product.id) +
			'" aria-label="' +
			escapeHtml(i18n.remove || 'حذف') +
			'">' +
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">' +
			'<path fill="currentColor" d="M19 6.41 17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>' +
			'</svg>' +
			'</button>' +
			'<div class="lk-compare-slot__media">' +
			(product.image
				? '<img src="' + escapeHtml(product.image) + '" alt="" loading="lazy" />'
				: '') +
			'</div>' +
			'<h3 class="lk-compare-slot__title">' + escapeHtml(product.name || '') + '</h3>' +
			(product.price_html
				? '<div class="lk-compare-slot__price">' + product.price_html + '</div>'
				: '') +
			'<a class="lk-compare-slot__cta" href="' +
			escapeHtml(product.permalink || '#') +
			'">' +
			escapeHtml(i18n.viewProduct || 'مشاهده و خرید') +
			'</a>' +
			'</article>'
		);
	}

	function renderGrid(slots, productsById) {
		var grid = qs('[data-lk-compare-grid]');
		if (!grid) {
			return;
		}

		slots = normalizeSlots(slots);
		var html = '';
		var i;
		var count = filledCount(slots);

		grid.classList.toggle('is-empty', count === 0);
		grid.classList.toggle('has-products', count > 0);

		if (!count) {
			html =
				'<div class="lk-compare__empty-row">' +
				renderAddSlot(0) +
				'</div>';
			grid.innerHTML = html;
			return;
		}

		/* Progressive slots: only the next empty "add" after filled ones (4th add only when 3 filled). */
		for (i = 0; i < MAX; i++) {
			var id = slots[i] || 0;
			var product = id > 0 ? productsById[id] : null;

			if (product) {
				html += renderFilledSlot(product, i);
			} else if (i === count) {
				html += renderAddSlot(i);
				break;
			}
		}

		grid.innerHTML = html;
	}

	function renderSpecs(products, rows) {
		var wrap = qs('[data-lk-compare-specs]');
		if (!wrap) {
			return;
		}

		if (!products || products.length < MIN || !rows || !rows.length) {
			wrap.hidden = true;
			wrap.innerHTML = '';
			wrap.removeAttribute('data-lk-compare-cols');
			return;
		}

		var productCount = products.length;
		var cols = Math.min(MAX, productCount + (productCount < MAX ? 1 : 0));
		wrap.setAttribute('data-lk-compare-cols', String(cols));

		var html = '';

		rows.forEach(function (row) {
			var values = row.values || [];
			var i;

			html += '<div class="lk-compare-spec">';
			html +=
				'<p class="lk-compare-spec__label">' + escapeHtml(row.label || '') + '</p>';
			html += '<div class="lk-compare-spec__values">';

			for (i = 0; i < cols; i++) {
				var value = i < values.length ? values[i] : '';
				var empty = !value || String(value).trim() === '';
				html +=
					'<span class="lk-compare-spec__value' +
					(empty ? ' is-empty' : '') +
					'">' +
					escapeHtml(empty ? i18n.emptyCell || '—' : value) +
					'</span>';
			}

			html += '</div></div>';
		});

		wrap.innerHTML = html;
		wrap.hidden = false;
	}

	function openModal(slotIndex) {
		var modal = qs('[data-lk-compare-modal]');
		if (!modal) {
			return;
		}
		activeSlotIndex = Math.max(0, Math.min(MAX - 1, parseInt(slotIndex, 10) || 0));
		modal.hidden = false;
		modal.setAttribute('aria-hidden', 'false');
		document.documentElement.classList.add('lk-compare-modal-open');
		var input = qs('[data-lk-compare-search]');
		if (input) {
			input.value = '';
			window.setTimeout(function () {
				input.focus();
			}, 50);
		}
		loadPicker('');
	}

	function closeModal() {
		var modal = qs('[data-lk-compare-modal]');
		if (!modal) {
			return;
		}
		modal.hidden = true;
		modal.setAttribute('aria-hidden', 'true');
		document.documentElement.classList.remove('lk-compare-modal-open');
	}

	function productsByIdFromList(products) {
		var map = {};
		(products || []).forEach(function (p) {
			map[parseInt(p.id, 10)] = p;
		});
		return map;
	}

	function checkPendingNotice(slots) {
		var pending = readPending();
		if (!pending) {
			return;
		}
		if (firstEmptySlot(slots) >= 0) {
			return;
		}
		var notice = qs('[data-lk-compare-notice]');
		if (notice) {
			notice.textContent =
				i18n.slotsFullRemove ||
				'همه جایگاه‌های مقایسه پر است. یک محصول را حذف کنید تا محصول جدید جایگزین شود.';
			notice.hidden = false;
		}
	}

	function renderComparePage() {
		var root = qs('[data-lk-compare-root]');
		if (!root) {
			return;
		}

		var notice = qs('[data-lk-compare-notice]');
		var slots = readSlots();
		var urlSlots = slotsFromUrl();
		if (urlSlots && !slotsEqual(slots, urlSlots)) {
			slots = writeSlots(urlSlots);
		}

		var productsById = matrixCache ? productsByIdFromList(matrixCache.products) : {};
		var count = filledCount(slots);

		checkPendingNotice(slots);

		if (!count) {
			matrixCache = null;
			renderGrid(slots, {});
			renderSpecs([], []);
			if (notice && !readPending()) {
				notice.hidden = true;
			}
			return;
		}

		var cachedSlots =
			matrixCache && matrixCache.slots ? normalizeSlots(matrixCache.slots) : null;

		if (cachedSlots && slotsEqual(cachedSlots, slots)) {
			renderGrid(slots, productsById);
			renderSpecs(matrixCache.products, matrixCache.rows || []);
			if (notice) {
				if (readPending()) {
					/* keep pending notice */
				} else if (count < MIN) {
					notice.textContent = i18n.needMore || '';
					notice.hidden = false;
				} else {
					notice.hidden = true;
				}
			}
			return;
		}

		renderGrid(slots, productsById);

		setBoardLoading(true);
		fetchMatrix(slots)
			.then(function (data) {
				var validSlots = data.slots ? normalizeSlots(data.slots) : slots;
				if (!slotsEqual(validSlots, slots)) {
					slots = writeSlots(validSlots);
				}

				if (!filledCount(validSlots)) {
					matrixCache = null;
					renderGrid(validSlots, {});
					renderSpecs([], []);
					return;
				}

				matrixCache = data;
				matrixCache.slots = validSlots;
				var byId = productsByIdFromList(data.products);
				renderGrid(validSlots, byId);
				renderSpecs(data.products, data.rows || []);

				if (notice) {
					if (readPending()) {
						/* keep pending notice */
					} else if (filledCount(validSlots) < MIN) {
						notice.textContent = i18n.needMore || '';
						notice.hidden = false;
					} else {
						notice.hidden = true;
					}
				}
			})
			.catch(function () {
				if (notice && !readPending()) {
					notice.textContent = i18n.error || 'خطا';
					notice.hidden = false;
				}
			})
			.finally(function () {
				setBoardLoading(false);
			});
	}

	function addIdAtSlot(id, slotIndex) {
		id = parseInt(id, 10);
		slotIndex = Math.max(0, Math.min(MAX - 1, parseInt(slotIndex, 10) || 0));
		if (id <= 0) {
			return false;
		}

		var slots = readSlots();
		if (slotHasId(slots, id) && slots[slotIndex] !== id) {
			toast(i18n.alreadyAdded || i18n.inCompare || '');
			return false;
		}

		if (slots[slotIndex] > 0 && slots[slotIndex] !== id) {
			var displaced = slots[slotIndex];
			var displacedIndex = -1;
			var i;
			for (i = 0; i < MAX; i++) {
				if (slots[i] === id) {
					displacedIndex = i;
					break;
				}
			}
			if (displacedIndex >= 0) {
				slots[displacedIndex] = displaced;
			}
		}

		slots[slotIndex] = id;
		writeSlots(slots);
		matrixCache = null;
		clearPending();
		toast(i18n.added || '');
		closeModal();
		renderComparePage();
		return true;
	}

	function removeId(id, slotIndex) {
		id = parseInt(id, 10);
		slotIndex = parseInt(slotIndex, 10);
		var slots = readSlots();
		var pending = readPending();
		var i;

		if (slotIndex >= 0 && slotIndex < MAX && slots[slotIndex] === id) {
			slots[slotIndex] = 0;
		} else {
			for (i = 0; i < MAX; i++) {
				if (slots[i] === id) {
					slotIndex = i;
					slots[i] = 0;
					break;
				}
			}
		}

		if (pending > 0 && slotIndex >= 0 && slotIndex < MAX) {
			slots[slotIndex] = pending;
			clearPending();
			toast(i18n.added || '');
		} else {
			toast(i18n.removed || '');
		}

		writeSlots(slots);
		matrixCache = null;
		renderComparePage();
	}

	function navigateFromSingle(productId) {
		productId = parseInt(productId, 10);
		if (productId <= 0) {
			return;
		}

		var slots = readSlots();

		if (slotHasId(slots, productId)) {
			clearPending();
			window.location.href = buildCompareUrl(slots);
			return;
		}

		var empty = firstEmptySlot(slots);
		if (empty >= 0) {
			slots[empty] = productId;
			writeSlots(slots);
			clearPending();
			window.location.href = buildCompareUrl(slots);
			return;
		}

		setPending(productId);
		window.location.href = buildCompareUrl(slots);
	}

	function bindComparePage() {
		var root = qs('[data-lk-compare-root]');
		if (!root) {
			return;
		}

		var searchInput = qs('[data-lk-compare-search]');
		if (searchInput) {
			searchInput.addEventListener('input', function () {
				window.clearTimeout(searchTimer);
				var query = searchInput.value.trim();
				searchTimer = window.setTimeout(function () {
					loadPicker(query);
				}, 280);
			});
		}

		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') {
				closeModal();
			}
		});

		root.addEventListener('click', function (event) {
			if (event.target.closest('[data-lk-compare-modal-close]')) {
				event.preventDefault();
				closeModal();
				return;
			}

			var openBtn = event.target.closest('[data-lk-compare-open]');
			if (openBtn) {
				event.preventDefault();
				var slot = parseInt(openBtn.getAttribute('data-lk-compare-open') || '0', 10);
				openModal(slot);
				return;
			}

			var pickBtn = event.target.closest('[data-lk-compare-pick]');
			if (pickBtn) {
				event.preventDefault();
				if (pickBtn.classList.contains('is-added') || pickBtn.getAttribute('aria-disabled') === 'true') {
					return;
				}
				var pickId = parseInt(pickBtn.getAttribute('data-lk-compare-pick') || '0', 10);
				if (pickId > 0) {
					addIdAtSlot(pickId, activeSlotIndex);
				}
				return;
			}

			var removeBtn = event.target.closest('[data-lk-compare-remove]');
			if (removeBtn) {
				event.preventDefault();
				event.stopPropagation();
				var id = parseInt(removeBtn.getAttribute('data-lk-compare-remove') || '0', 10);
				var slotEl = removeBtn.closest('[data-slot-index]');
				var slotIdx = slotEl
					? parseInt(slotEl.getAttribute('data-slot-index') || '-1', 10)
					: -1;
				if (id > 0) {
					removeId(id, slotIdx);
				}
			}
		});

		renderComparePage();
	}

	function rememberMeta(id, meta) {
		id = parseInt(id, 10);
		if (id <= 0) {
			return;
		}
		var prev = productMeta[id] || {};
		productMeta[id] = {
			name: (meta && meta.name) || prev.name || '',
			image: (meta && meta.image) || prev.image || '',
			url: (meta && meta.url) || prev.url || '',
		};
	}

	function metaFromToggle(input) {
		if (!input) {
			return {};
		}
		return {
			name: input.getAttribute('data-lk-compare-name') || '',
			image: input.getAttribute('data-lk-compare-image') || '',
			url: input.getAttribute('data-lk-compare-url') || '',
		};
	}

	function syncCardToggles(slots) {
		slots = normalizeSlots(slots || readSlots());
		qsa('[data-lk-compare-toggle]').forEach(function (input) {
			var id = parseInt(input.getAttribute('data-lk-compare-toggle') || '0', 10);
			var checked = id > 0 && slotHasId(slots, id);
			input.checked = checked;
			var label = input.closest('.lk-loop-item__compare');
			if (label) {
				label.classList.toggle('is-checked', checked);
			}
			rememberMeta(id, metaFromToggle(input));
		});
	}

	function renderTrayList(slots) {
		var list = qs('[data-lk-compare-tray-list]');
		if (!list) {
			return;
		}
		slots = normalizeSlots(slots || readSlots());
		var ids = [];
		var i;
		for (i = 0; i < MAX; i++) {
			if (slots[i] > 0) {
				ids.push(slots[i]);
			}
		}
		if (!ids.length) {
			list.innerHTML =
				'<p class="lk-compare-tray__empty">' +
				escapeHtml(i18n.trayEmpty || 'هنوز محصولی انتخاب نشده است.') +
				'</p>';
			return;
		}
		var html = '';
		ids.forEach(function (id) {
			var meta = productMeta[id] || {};
			var thumb = meta.image
				? '<span class="lk-compare-tray__thumb"><img src="' +
					escapeHtml(meta.image) +
					'" alt=""></span>'
				: '<span class="lk-compare-tray__thumb lk-compare-tray__thumb--empty" aria-hidden="true"></span>';
			html +=
				'<article class="lk-compare-tray__item" data-lk-compare-tray-item="' +
				id +
				'">' +
				'<button type="button" class="lk-compare-tray__remove" data-lk-compare-tray-remove="' +
				id +
				'" aria-label="' +
				escapeHtml(i18n.remove || 'حذف') +
				'">×</button>' +
				thumb +
				'<p class="lk-compare-tray__name">' +
				escapeHtml(meta.name || ('#' + id)) +
				'</p>' +
				'</article>';
		});
		list.innerHTML = html;
	}

	function ensureChromeDom() {
		if (cfg.isComparePage) {
			return;
		}
		if (qs('[data-lk-compare-chrome]')) {
			return;
		}
		if (!qs('[data-lk-compare-toggle]') && !qs('[data-lk-compare-from-single]')) {
			return;
		}
		var compareUrl = cfg.compareUrl || '/compare/';
		var wrap = document.createElement('div');
		wrap.innerHTML =
			'<div class="lk-compare-chrome" data-lk-compare-chrome hidden>' +
			'<div class="lk-compare-fab-wrap">' +
			'<span class="lk-compare-fab__popup" data-lk-compare-toast hidden role="status" aria-live="polite"></span>' +
			'<button type="button" class="lk-compare-fab" data-lk-compare-fab aria-expanded="false" aria-controls="lk-compare-tray">' +
			'<span class="lk-compare-fab__label">' +
			escapeHtml(i18n.compare || 'مقایسه') +
			'</span>' +
			'<span class="lk-compare-fab__count" data-lk-compare-fab-count>0</span>' +
			'</button></div>' +
			'<div class="lk-compare-tray" id="lk-compare-tray" data-lk-compare-tray hidden aria-hidden="true">' +
			'<div class="lk-compare-tray__backdrop" data-lk-compare-tray-close tabindex="-1" aria-hidden="true"></div>' +
			'<div class="lk-compare-tray__panel" role="dialog" aria-modal="true" aria-labelledby="lk-compare-tray-title">' +
			'<div class="lk-compare-tray__head"><div class="lk-compare-tray__heading">' +
			'<h2 class="lk-compare-tray__title" id="lk-compare-tray-title">' +
			escapeHtml(i18n.trayTitle || 'محصولات انتخاب‌شده') +
			'</h2>' +
			'<span class="lk-compare-tray__count" data-lk-compare-tray-count hidden></span>' +
			'</div>' +
			'<span class="lk-cmp-panel-x" data-lk-cmp-panel-x role="button" tabindex="0" aria-label="' +
			escapeHtml(i18n.close || 'بستن') +
			'">&times;</span></div>' +
			'<div class="lk-compare-tray__list" data-lk-compare-tray-list></div>' +
			'<div class="lk-compare-tray__actions">' +
			'<button type="button" class="lk-compare-tray__clear" data-lk-compare-tray-clear>' +
			escapeHtml(i18n.clear || 'پاک کردن') +
			'</button>' +
			'<a class="lk-compare-tray__go" data-lk-compare-tray-go href="' +
			escapeHtml(compareUrl) +
			'">' +
			'<span>' +
			escapeHtml(i18n.compare || 'مقایسه') +
			'</span></a></div></div></div></div>';
		var node = wrap.firstChild;
		if (node) {
			document.body.appendChild(node);
		}
	}

	function refreshChrome(slots) {
		ensureChromeDom();
		var chrome = qs('[data-lk-compare-chrome]');
		if (!chrome) {
			return;
		}
		/* Drop any leftover old close button from cached markup. */
		qsa('button.lk-compare-tray__close', chrome).forEach(function (el) {
			el.parentNode && el.parentNode.removeChild(el);
		});
		ensureTrayHeading(chrome);
		ensurePanelXButton(chrome);
		slots = normalizeSlots(slots || readSlots());
		var count = filledCount(slots);
		var fabCount = qs('[data-lk-compare-fab-count]');
		var trayCount = qs('[data-lk-compare-tray-count]');
		var go = qs('[data-lk-compare-tray-go]');

		chrome.hidden = count < 1;
		if (fabCount) {
			fabCount.textContent = String(count);
		}
		if (trayCount) {
			if (count > 0) {
				trayCount.textContent = formatTrayCount(count);
				trayCount.hidden = false;
				trayCount.removeAttribute('hidden');
			} else {
				trayCount.textContent = '';
				trayCount.hidden = true;
				trayCount.setAttribute('hidden', '');
			}
		}
		if (go) {
			go.href = buildCompareUrl(slots);
			go.classList.toggle('is-disabled', count < MIN);
			if (count < MIN) {
				go.setAttribute('aria-disabled', 'true');
			} else {
				go.removeAttribute('aria-disabled');
			}
		}
		syncCardToggles(slots);
		renderTrayList(slots);
		bindTrayDismissControls();
		if (count < 1) {
			closeTray();
		}
	}

	function ensureTrayHeading(root) {
		root = root || document;
		var head = root.querySelector
			? root.querySelector('.lk-compare-tray__head')
			: qs('.lk-compare-tray__head');
		if (!head) {
			return null;
		}
		var heading = head.querySelector('.lk-compare-tray__heading');
		var title = head.querySelector('.lk-compare-tray__title');
		if (!heading && title) {
			heading = document.createElement('div');
			heading.className = 'lk-compare-tray__heading';
			head.insertBefore(heading, title);
			heading.appendChild(title);
		}
		if (!heading) {
			return null;
		}
		var chip = heading.querySelector('[data-lk-compare-tray-count]');
		if (!chip) {
			chip = document.createElement('span');
			chip.className = 'lk-compare-tray__count';
			chip.setAttribute('data-lk-compare-tray-count', '');
			chip.hidden = true;
			heading.appendChild(chip);
		}
		return heading;
	}

	function ensurePanelXButton(root) {
		root = root || document;
		var head = root.querySelector
			? root.querySelector('.lk-compare-tray__head')
			: qs('.lk-compare-tray__head');
		if (!head) {
			return null;
		}
		var x = head.querySelector('[data-lk-cmp-panel-x]');
		if (x) {
			return x;
		}
		x = document.createElement('span');
		x.className = 'lk-cmp-panel-x';
		x.setAttribute('data-lk-cmp-panel-x', '');
		x.setAttribute('role', 'button');
		x.setAttribute('tabindex', '0');
		x.setAttribute('aria-label', i18n.close || 'بستن');
		x.innerHTML = '&times;';
		head.appendChild(x);
		return x;
	}

	function isTrayOpen(tray) {
		tray = tray || qs('[data-lk-compare-tray]');
		if (!tray) {
			return false;
		}
		return tray.classList.contains('is-open') || tray.getAttribute('data-open') === '1';
	}

	function openTray() {
		var tray = qs('[data-lk-compare-tray]');
		var fab = qs('[data-lk-compare-fab]');
		if (!tray) {
			return;
		}
		refreshChrome();
		tray.classList.add('is-open');
		tray.setAttribute('data-open', '1');
		tray.hidden = false;
		tray.removeAttribute('hidden');
		tray.setAttribute('aria-hidden', 'false');
		tray.style.setProperty('display', 'flex', 'important');
		document.documentElement.classList.add('lk-compare-tray-open');
		if (fab) {
			fab.setAttribute('aria-expanded', 'true');
		}
		bindTrayDismissControls(tray);
		hydrateTrayMeta();
	}

	function closeTray() {
		var tray = qs('[data-lk-compare-tray]');
		var fab = qs('[data-lk-compare-fab]');
		if (!tray) {
			return;
		}
		tray.classList.remove('is-open');
		tray.removeAttribute('data-open');
		tray.hidden = true;
		tray.setAttribute('hidden', '');
		tray.setAttribute('aria-hidden', 'true');
		tray.style.setProperty('display', 'none', 'important');
		document.documentElement.classList.remove('lk-compare-tray-open');
		if (fab) {
			fab.setAttribute('aria-expanded', 'false');
		}
	}

	function bindTrayDismissControls(tray) {
		tray = tray || qs('[data-lk-compare-tray]');
		if (!tray) {
			return;
		}
		ensurePanelXButton(tray);

		var x = tray.querySelector('[data-lk-cmp-panel-x]');
		if (x && x.getAttribute('data-lk-x-bound') !== '1') {
			x.setAttribute('data-lk-x-bound', '1');
			x.onclick = function (event) {
				if (event) {
					event.preventDefault();
					event.stopPropagation();
				}
				closeTray();
				return false;
			};
			x.onkeydown = function (event) {
				if (!event) {
					return;
				}
				if (event.key === 'Enter' || event.key === ' ' || event.key === 'Spacebar') {
					event.preventDefault();
					closeTray();
				}
			};
		}

		var backdrop = tray.querySelector('[data-lk-compare-tray-close]');
		if (backdrop && backdrop.getAttribute('data-lk-x-bound') !== '1') {
			backdrop.setAttribute('data-lk-x-bound', '1');
			backdrop.onclick = function (event) {
				if (event) {
					event.preventDefault();
					event.stopPropagation();
				}
				closeTray();
				return false;
			};
		}
	}

	function hydrateTrayMeta() {
		var slots = readSlots();
		var missing = false;
		var i;
		for (i = 0; i < MAX; i++) {
			var id = slots[i];
			if (id > 0 && !(productMeta[id] && productMeta[id].name)) {
				missing = true;
				break;
			}
		}
		if (!missing) {
			return;
		}
		fetchMatrix(slots)
			.then(function (data) {
				var products = (data && data.products) || [];
				products.forEach(function (p) {
					if (!p || !p.id) {
						return;
					}
					rememberMeta(p.id, {
						name: p.name || p.title || '',
						image: p.image || p.thumb || '',
						url: p.url || p.permalink || '',
					});
				});
				renderTrayList(readSlots());
			})
			.catch(function () {
				/* ignore */
			});
	}

	function toggleCompareId(id, wantOn, meta) {
		id = parseInt(id, 10);
		if (id <= 0) {
			return false;
		}
		rememberMeta(id, meta || {});
		var slots = readSlots();
		var has = slotHasId(slots, id);

		if (wantOn) {
			if (has) {
				refreshChrome(slots);
				return true;
			}
			var empty = firstEmptySlot(slots);
			if (empty < 0) {
				toast(i18n.full || '');
				syncCardToggles(slots);
				return false;
			}
			slots[empty] = id;
			writeSlots(slots);
			toast(i18n.added || '');
			return true;
		}

		if (!has) {
			refreshChrome(slots);
			return true;
		}
		for (var i = 0; i < MAX; i++) {
			if (slots[i] === id) {
				slots[i] = 0;
			}
		}
		writeSlots(slots);
		toast(i18n.removed || '');
		return true;
	}

	function clearAllCompare() {
		writeSlots(emptySlots());
		closeTray();
		toast(i18n.removed || '');
	}

	function bindChrome() {
		if (chromeBound || cfg.isComparePage) {
			return;
		}
		chromeBound = true;

		document.addEventListener(
			'change',
			function (event) {
				var input = event.target && event.target.closest
					? event.target.closest('[data-lk-compare-toggle]')
					: null;
				if (!input || !input.hasAttribute('data-lk-compare-toggle')) {
					return;
				}
				var id = parseInt(input.getAttribute('data-lk-compare-toggle') || '0', 10);
				toggleCompareId(id, !!input.checked, metaFromToggle(input));
			},
			true
		);

		document.addEventListener(
			'click',
			function (event) {
				var label = event.target.closest
					? event.target.closest('label.lk-loop-item__compare')
					: null;
				if (label) {
					var toggleInput = label.querySelector('[data-lk-compare-toggle]');
					if (toggleInput && event.target !== toggleInput) {
						var style = window.getComputedStyle(toggleInput);
						if (style && style.pointerEvents === 'none') {
							event.preventDefault();
							toggleInput.checked = !toggleInput.checked;
							var toggleId = parseInt(
								toggleInput.getAttribute('data-lk-compare-toggle') || '0',
								10
							);
							toggleCompareId(toggleId, !!toggleInput.checked, metaFromToggle(toggleInput));
							return;
						}
					}
				}

				if (event.target.closest('[data-lk-compare-fab]')) {
					event.preventDefault();
					if (isTrayOpen()) {
						closeTray();
					} else {
						openTray();
					}
					return;
				}
				if (event.target.closest('[data-lk-cmp-panel-x]')) {
					event.preventDefault();
					event.stopPropagation();
					closeTray();
					return;
				}
				if (event.target.closest('[data-lk-compare-tray-close]')) {
					event.preventDefault();
					event.stopPropagation();
					closeTray();
					return;
				}
				if (event.target.closest('[data-lk-compare-tray-clear]')) {
					event.preventDefault();
					clearAllCompare();
					return;
				}
				var removeBtn = event.target.closest('[data-lk-compare-tray-remove]');
				if (removeBtn) {
					event.preventDefault();
					var rid = parseInt(removeBtn.getAttribute('data-lk-compare-tray-remove') || '0', 10);
					toggleCompareId(rid, false);
					return;
				}
				var go = event.target.closest('[data-lk-compare-tray-go]');
				if (go && go.classList.contains('is-disabled')) {
					event.preventDefault();
					toast(i18n.needMore || '');
				}
			},
			true
		);

		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') {
				closeTray();
			}
		});

		document.addEventListener('lk-shop-cards-updated', function () {
			refreshChrome();
		});

		refreshChrome();
	}

	function bindSingleCompare() {
		document.addEventListener('click', function (event) {
			var btn = event.target.closest('[data-lk-compare-from-single]');
			if (!btn) {
				return;
			}
			event.preventDefault();
			var id = parseInt(btn.getAttribute('data-lk-compare-from-single') || '0', 10);
			if (id <= 0) {
				return;
			}
			var slots = readSlots();
			if (slotHasId(slots, id)) {
				window.location.href = buildCompareUrl(slots);
				return;
			}
			var empty = firstEmptySlot(slots);
			if (empty < 0) {
				setPending(id);
				window.location.href = buildCompareUrl(slots);
				return;
			}
			slots[empty] = id;
			writeSlots(slots);
			if (qs('[data-lk-compare-chrome]')) {
				openTray();
			} else {
				window.location.href = buildCompareUrl(slots);
			}
		});
	}

	function boot() {
		bindSingleCompare();
		bindChrome();
		if (cfg.isComparePage) {
			bindComparePage();
		} else {
			window.setTimeout(function () {
				refreshChrome();
			}, 0);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
