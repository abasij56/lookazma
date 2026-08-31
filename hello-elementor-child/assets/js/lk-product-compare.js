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

	function emptySlots() {
		var slots = [];
		var i;
		for (i = 0; i < MAX; i++) {
			slots.push(0);
		}
		return slots;
	}

	function normalizeSlots(raw) {
		var slots = emptySlots();
		var i;
		if (!Array.isArray(raw)) {
			return slots;
		}
		for (i = 0; i < MAX; i++) {
			slots[i] = parseInt(raw[i], 10) || 0;
		}
		return slots;
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

	function renderVsDivider() {
		return (
			'<div class="lk-compare-vs" aria-hidden="true">' +
			'<span class="lk-compare-vs__badge">VS</span>' +
			'</div>'
		);
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

	function renderEmptyHintSlot() {
		return (
			'<div class="lk-compare-slot lk-compare-slot--hint">' +
			'<span class="lk-compare-slot__hint-icon" aria-hidden="true">' +
			'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="36" height="36" focusable="false">' +
			'<path fill="currentColor" d="M9 3 7.17 5H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2h-3.17L15 3H9zm3 15c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5z"/>' +
			'</svg>' +
			'</span>' +
			'<p class="lk-compare-slot__hint-title">' +
			escapeHtml(i18n.emptyNoProduct || 'کالایی اضافه نکرده‌اید') +
			'</p>' +
			'<p class="lk-compare-slot__hint-text">' +
			escapeHtml(i18n.emptyMaxHint || 'می‌توانید ۴ کالا را باهم مقایسه کنید') +
			'</p>' +
			'</div>'
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
				renderVsDivider() +
				renderEmptyHintSlot() +
				'</div>';
			grid.innerHTML = html;
			return;
		}

		for (i = 0; i < MAX; i++) {
			var id = slots[i] || 0;
			var product = id > 0 ? productsById[id] : null;

			if (product) {
				html += renderFilledSlot(product, i);
			} else {
				html += renderAddSlot(i);
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
			return;
		}

		var html = '';

		rows.forEach(function (row) {
			var values = row.values || [];
			var i;

			html += '<div class="lk-compare-spec">';
			html +=
				'<p class="lk-compare-spec__label">' + escapeHtml(row.label || '') + '</p>';
			html += '<div class="lk-compare-spec__values">';

			for (i = 0; i < MAX; i++) {
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

	function bindSingleCompare() {
		document.addEventListener('click', function (event) {
			var btn = event.target.closest('[data-lk-compare-from-single]');
			if (!btn) {
				return;
			}
			event.preventDefault();
			var id = parseInt(btn.getAttribute('data-lk-compare-from-single') || '0', 10);
			if (id > 0) {
				navigateFromSingle(id);
			}
		});
	}

	function boot() {
		bindSingleCompare();
		if (cfg.isComparePage) {
			bindComparePage();
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
