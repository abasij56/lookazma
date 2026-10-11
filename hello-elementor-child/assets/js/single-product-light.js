document.addEventListener('DOMContentLoaded', function () {
	if (window.__lkSplReady) {
		return;
	}
	window.__lkSplReady = true;
	initTabs();
	initDatasheetViewer();
	initVariationPriceSync();
	initMediaTools();
	ensureVariationsVisible();
});

// If this script loads after DOMContentLoaded (e.g. late inject), still init.
if (document.readyState !== 'loading' && !window.__lkSplReady) {
	window.__lkSplReady = true;
	initTabs();
	initDatasheetViewer();
	initVariationPriceSync();
	initMediaTools();
	ensureVariationsVisible();
}

/**
 * Keep table.variations visible even when plugins hide single-option UIs.
 * Skip screen-reader-only tables used with the single-packaging notice.
 */
function ensureVariationsVisible() {
	var tables = document.querySelectorAll('.lk-price-card__cart table.variations:not(.lk-variations--sr-only)');
	tables.forEach(function (table) {
		table.hidden = false;
		table.removeAttribute('hidden');
		table.style.setProperty('display', 'block', 'important');
		table.style.setProperty('visibility', 'visible', 'important');
		table.style.setProperty('opacity', '1', 'important');
	});

	lockSinglePackagingVariation();
}

/**
 * Pre-select the only variation when the packaging notice is shown.
 */
function lockSinglePackagingVariation() {
	var forms = document.querySelectorAll('form.variations_form.lk-variations-form--single-packaging');
	forms.forEach(function (form) {
		var $form = window.jQuery ? window.jQuery(form) : null;
		var selects = form.querySelectorAll('table.variations select');
		selects.forEach(function (select) {
			if (!select.value) {
				var opts = Array.prototype.filter.call(select.options, function (o) {
					return o.value;
				});
				if (opts.length === 1) {
					select.value = opts[0].value;
				}
			}
			select.dispatchEvent(new Event('change', { bubbles: true }));
		});
		if ($form && $form.length) {
			$form.trigger('check_variations');
			$form.trigger('woocommerce_variation_has_changed');
		}
	});
}

function initTabs() {
	var tabsRoot = document.querySelector('[data-lk-tabs]');
	if (!tabsRoot) {
		return;
	}

	var buttons = tabsRoot.querySelectorAll('.lk-tabs__nav button, .lk-cat-tabs__btn');
	var panels = tabsRoot.querySelectorAll('.lk-tabs__panel');

	buttons.forEach(function (button) {
		button.addEventListener('click', function () {
			var tab = button.getAttribute('data-tab');

			buttons.forEach(function (item) {
				var active = item === button;
				item.classList.toggle('is-active', active);
				item.setAttribute('aria-selected', active ? 'true' : 'false');
			});

			panels.forEach(function (panel) {
				var match = panel.getAttribute('data-panel') === tab;
				panel.classList.toggle('is-active', match);
				if (match) {
					panel.removeAttribute('hidden');
				} else {
					panel.setAttribute('hidden', 'hidden');
				}
			});

			/* Woo star picker may need a kick after the reviews panel becomes visible. */
			if (tab === 'reviews' && window.jQuery) {
				window.setTimeout(function () {
					var $ = window.jQuery;
					var $select = $('.lk-reviews #rating');
					if ($select.length && ! $select.parent().find('p.stars').length) {
						$select.trigger('init');
					}
					$(document.body).trigger('init_comment_ratings');
				}, 30);
			}
		});
	});
}

function lkClosest(el, selector) {
	if (!el) {
		return null;
	}
	if (el.nodeType !== 1) {
		el = el.parentElement;
	}
	if (!el) {
		return null;
	}
	if (typeof el.closest === 'function') {
		return el.closest(selector);
	}
	while (el && el.nodeType === 1) {
		if (el.matches && el.matches(selector)) {
			return el;
		}
		el = el.parentElement;
	}
	return null;
}

/**
 * Mirror WooCommerce variation price into the visible price card amount.
 * Also keeps stock label in sync when availability HTML is present.
 */
function initVariationPriceSync() {
	var card = document.querySelector('.lk-price-card');
	if (!card) {
		return;
	}

	var amount = card.querySelector('[data-lk-price-amount]');
	var stock = card.querySelector('.lk-price-card__stock');
	var form = card.querySelector('form.variations_form');
	if (!amount || !form) {
		return;
	}

	var defaultPrice = amount.innerHTML;
	var defaultStockHtml = stock ? stock.innerHTML : '';
	var defaultStockClass = stock ? stock.className : '';

	function setPrice(html) {
		if (html && String(html).trim()) {
			amount.innerHTML = html;
		} else {
			amount.innerHTML = defaultPrice;
		}
	}

	function setStockFromAvailability(html) {
		if (!stock || !html) {
			return;
		}
		var tmp = document.createElement('div');
		tmp.innerHTML = html;
		var text = (tmp.textContent || '').trim();
		if (!text) {
			return;
		}
		var inStock = !/out-of-stock|ناموجود|تمام/i.test(html + text);
		stock.textContent = text;
		stock.className = 'lk-price-card__stock ' + (inStock ? 'is-in' : 'is-muted');
	}

	function resetStock() {
		if (!stock) {
			return;
		}
		stock.innerHTML = defaultStockHtml;
		stock.className = defaultStockClass;
	}

	function bindWithJquery($) {
		$(function () {
			var $form = $(form);

			$form.on('found_variation', function (event, variation) {
				if (!variation) {
					return;
				}
				if (variation.price_html) {
					setPrice(variation.price_html);
				}
				if (variation.availability_html) {
					setStockFromAvailability(variation.availability_html);
				}
			});

			$form.on('reset_data hide_variation', function () {
				setPrice(defaultPrice);
				resetStock();
			});

			/* Re-trigger if a value is already selected (e.g. browser restore). */
			window.setTimeout(function () {
				$form.trigger('check_variations');
			}, 50);
		});
	}

	if (window.jQuery) {
		bindWithJquery(window.jQuery);
		return;
	}

	/* Fallback without jQuery: watch select changes + WC-injected price node. */
	var selects = form.querySelectorAll('select');
	selects.forEach(function (select) {
		select.addEventListener('change', function () {
			window.setTimeout(function () {
				var injected = form.querySelector('.woocommerce-variation-price .price, .woocommerce-variation-price');
				if (injected && injected.innerHTML.trim()) {
					setPrice(injected.innerHTML);
				} else {
					var anyEmpty = false;
					selects.forEach(function (s) {
						if (!s.value) {
							anyEmpty = true;
						}
					});
					if (anyEmpty) {
						setPrice(defaultPrice);
						resetStock();
					}
				}
			}, 80);
		});
	});
}

function initMediaTools() {
	var roots = document.querySelectorAll('[data-lk-share-root]');
	if (!roots.length) {
		return;
	}

	function closeAll(except) {
		roots.forEach(function (root) {
			if (except && root === except) {
				return;
			}
			root.classList.remove('is-open');
			var toggle = root.querySelector('[data-lk-share-toggle]');
			var panel = root.querySelector('[data-lk-share-panel]');
			if (toggle) {
				toggle.setAttribute('aria-expanded', 'false');
			}
			if (panel) {
				panel.setAttribute('hidden', 'hidden');
			}
		});
	}

	roots.forEach(function (root) {
		var toggle = root.querySelector('[data-lk-share-toggle]');
		var panel = root.querySelector('[data-lk-share-panel]');
		var copyBtn = root.querySelector('[data-lk-share-copy]');
		if (!toggle || !panel) {
			return;
		}

		function openPanel() {
			closeAll(root);
			root.classList.add('is-open');
			toggle.setAttribute('aria-expanded', 'true');
			panel.removeAttribute('hidden');
		}

		function closePanel() {
			root.classList.remove('is-open');
			toggle.setAttribute('aria-expanded', 'false');
			panel.setAttribute('hidden', 'hidden');
		}

		function togglePanel(event) {
			event.preventDefault();
			event.stopPropagation();
			if (root.classList.contains('is-open')) {
				closePanel();
			} else {
				openPanel();
			}
		}

		/* Toggle opens panel; control itself does not navigate. */
		toggle.addEventListener('click', togglePanel);
		toggle.addEventListener('keydown', function (event) {
			if (event.key === 'Enter' || event.key === ' ') {
				togglePanel(event);
			}
		});

		root.addEventListener('mouseenter', function () {
			if (window.matchMedia('(hover: hover)').matches) {
				window.clearTimeout(root._lkShareCloseTimer);
				openPanel();
			}
		});
		root.addEventListener('mouseleave', function () {
			if (window.matchMedia('(hover: hover)').matches) {
				window.clearTimeout(root._lkShareCloseTimer);
				root._lkShareCloseTimer = window.setTimeout(closePanel, 120);
			}
		});

		if (copyBtn) {
			copyBtn.addEventListener('click', function (event) {
				event.preventDefault();
				event.stopPropagation();
				var url = window.location.href;
				var done = function () {
					copyBtn.classList.add('is-copied');
					var label = copyBtn.querySelector('span:last-child');
					var prev = label ? label.textContent : '';
					if (label) {
						label.textContent = 'کپی شد';
					}
					window.setTimeout(function () {
						copyBtn.classList.remove('is-copied');
						if (label) {
							label.textContent = prev || 'کپی لینک';
						}
					}, 1600);
				};
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(url).then(done).catch(function () {
						window.prompt('کپی لینک:', url);
					});
				} else {
					window.prompt('کپی لینک:', url);
					done();
				}
			});
		}
	});

	document.addEventListener('click', function (event) {
		if (!lkClosest(event.target, '[data-lk-share-root]')) {
			closeAll();
		}
	});

	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape') {
			closeAll();
		}
	});
}

function initDatasheetViewer() {
	var openButtons = document.querySelectorAll('[data-lk-datasheet-open]');
	if (!openButtons.length) {
		return;
	}

	var viewer = document.getElementById('lk-ds-viewer');
	if (!viewer) {
		viewer = document.createElement('div');
		viewer.id = 'lk-ds-viewer';
		viewer.className = 'lk-ds-viewer';
		viewer.setAttribute('hidden', 'hidden');
		viewer.setAttribute('aria-hidden', 'true');
		viewer.innerHTML =
			'<div class="lk-ds-viewer__backdrop" data-lk-datasheet-close tabindex="-1"></div>' +
			'<div class="lk-ds-viewer__dialog" role="dialog" aria-modal="true" aria-label="برگه مشخصات فنی">' +
			'<div class="lk-ds-viewer__toolbar" role="toolbar">' +
			'<button type="button" class="lk-ds-viewer__btn" data-lk-datasheet-close aria-label="بستن">×</button>' +
			'<div class="lk-ds-viewer__share">' +
			'<button type="button" class="lk-ds-viewer__btn" data-lk-datasheet-share-toggle aria-expanded="false" aria-label="اشتراک‌گذاری">⇪</button>' +
			'<div class="lk-ds-viewer__share-menu" data-lk-datasheet-share-menu hidden role="menu">' +
			'<a class="lk-ds-viewer__share-item" data-lk-share="whatsapp" role="menuitem" target="_blank" rel="noopener noreferrer"><span>اشتراک در واتساپ</span></a>' +
			'<a class="lk-ds-viewer__share-item" data-lk-share="telegram" role="menuitem" target="_blank" rel="noopener noreferrer"><span>اشتراک در تلگرام</span></a>' +
			'</div></div>' +
			'<a class="lk-ds-viewer__btn" data-lk-datasheet-download href="#" download aria-label="دانلود">↓</a>' +
			'<button type="button" class="lk-ds-viewer__btn" data-lk-datasheet-fullscreen aria-label="بزرگ‌نمایی برگه">⛶</button>' +
			'</div><div class="lk-ds-viewer__stage" data-lk-datasheet-stage></div></div>';
		document.body.appendChild(viewer);
	} else if (viewer.parentElement !== document.body) {
		document.body.appendChild(viewer);
	}

	var stage = viewer.querySelector('[data-lk-datasheet-stage]');
	var downloadBtn = viewer.querySelector('[data-lk-datasheet-download]');
	var shareToggle = viewer.querySelector('[data-lk-datasheet-share-toggle]');
	var shareMenu = viewer.querySelector('[data-lk-datasheet-share-menu]');
	var shareWhatsapp = viewer.querySelector('[data-lk-share="whatsapp"]');
	var shareTelegram = viewer.querySelector('[data-lk-share="telegram"]');
	var fsBtn = viewer.querySelector('[data-lk-datasheet-fullscreen]');
	var lastFocus = null;
	var activeSrc = '';
	var activeMime = '';
	var activeTitle = '';

	function closeShareMenu() {
		if (!shareMenu || !shareToggle) {
			return;
		}
		shareMenu.setAttribute('hidden', 'hidden');
		shareToggle.setAttribute('aria-expanded', 'false');
	}

	function stripHash(url) {
		var i = url.indexOf('#');
		return i === -1 ? url : url.slice(0, i);
	}

	function buildViewUrl(url, zoomed) {
		var base = stripHash(url);
		var lower = base.toLowerCase();
		var isPdf =
			activeMime.indexOf('pdf') !== -1 ||
			/\.pdf(?:$|\?)/i.test(lower) ||
			lower.indexOf('lk_datasheet') !== -1;

		if (!isPdf) {
			return base;
		}

		// Fit whole page on open; zoomed = readable scrollable view (Chrome/Edge PDF viewer).
		return zoomed
			? base + '#toolbar=0&navpanes=0&scrollbar=1&view=FitH'
			: base + '#toolbar=0&navpanes=0&scrollbar=0&view=Fit';
	}

	function setZoomed(zoomed) {
		viewer.classList.toggle('is-zoomed', zoomed);
		if (fsBtn) {
			fsBtn.setAttribute('aria-pressed', zoomed ? 'true' : 'false');
			fsBtn.setAttribute('aria-label', zoomed ? 'نمایش کامل صفحه' : 'بزرگ‌نمایی برگه');
		}

		var media = stage.querySelector('.lk-ds-viewer__frame, .lk-ds-viewer__img');
		if (!media || !activeSrc) {
			return;
		}

		if (media.tagName === 'IFRAME') {
			var next = buildViewUrl(activeSrc, zoomed);
			if (media.getAttribute('src') !== next) {
				media.setAttribute('src', next);
			}
		}
	}

	function renderMedia(zoomed) {
		stage.innerHTML = '';

		if (!activeSrc) {
			var empty = document.createElement('p');
			empty.className = 'lk-ds-viewer__empty';
			empty.textContent = 'آدرس فایل مشخصات فنی در دسترس نیست.';
			stage.appendChild(empty);
			return;
		}

		var isImage = activeMime.indexOf('image/') === 0;
		if (isImage) {
			var img = document.createElement('img');
			img.className = 'lk-ds-viewer__img';
			img.src = stripHash(activeSrc);
			img.alt = activeTitle;
			stage.appendChild(img);
			return;
		}

		var frame = document.createElement('iframe');
		frame.className = 'lk-ds-viewer__frame';
		frame.src = buildViewUrl(activeSrc, zoomed);
		frame.title = activeTitle;
		stage.appendChild(frame);
	}

	function openViewer(trigger) {
		var viewUrl =
			trigger.getAttribute('data-view-url') ||
			trigger.getAttribute('data-fallback-url') ||
			trigger.getAttribute('href') ||
			'';
		var downloadUrl =
			trigger.getAttribute('data-download-url') ||
			viewUrl;
		var mime = (trigger.getAttribute('data-mime') || '').toLowerCase();
		var shareUrl = trigger.getAttribute('data-share-url') || window.location.href;
		var shareTitle = trigger.getAttribute('data-share-title') || document.title;

		activeSrc = (viewUrl || '').trim();
		activeMime = mime;
		activeTitle = shareTitle;
		downloadUrl = (downloadUrl || '').trim();

		lastFocus = trigger;
		closeShareMenu();

		viewer.removeAttribute('hidden');
		viewer.setAttribute('aria-hidden', 'false');
		document.body.classList.add('lk-ds-viewer-open');

		// Default: whole page fitted in the field (not browser fullscreen).
		setZoomed(false);
		renderMedia(false);

		if (downloadBtn) {
			downloadBtn.setAttribute('href', downloadUrl || activeSrc);
		}

		var text = shareTitle + ' — ' + shareUrl;
		if (shareWhatsapp) {
			shareWhatsapp.href = 'https://wa.me/?text=' + encodeURIComponent(text);
		}
		if (shareTelegram) {
			shareTelegram.href =
				'https://t.me/share/url?url=' +
				encodeURIComponent(shareUrl) +
				'&text=' +
				encodeURIComponent(shareTitle);
		}

		var closeBtn = viewer.querySelector('[data-lk-datasheet-close]');
		if (closeBtn && typeof closeBtn.focus === 'function') {
			closeBtn.focus();
		}
	}

	function closeViewer() {
		closeShareMenu();
		setZoomed(false);
		stage.innerHTML = '';
		activeSrc = '';
		activeMime = '';
		activeTitle = '';
		viewer.setAttribute('hidden', 'hidden');
		viewer.setAttribute('aria-hidden', 'true');
		document.body.classList.remove('lk-ds-viewer-open');
		if (lastFocus && typeof lastFocus.focus === 'function') {
			lastFocus.focus();
		}
	}

	function toggleZoom() {
		var zoomed = !viewer.classList.contains('is-zoomed');
		setZoomed(zoomed);
		renderMedia(zoomed);
	}

	openButtons.forEach(function (btn) {
		btn.addEventListener('click', function (event) {
			event.preventDefault();
			event.stopPropagation();
			openViewer(btn);
		});
	});

	viewer.addEventListener('click', function (event) {
		if (lkClosest(event.target, '[data-lk-datasheet-close]')) {
			event.preventDefault();
			closeViewer();
			return;
		}

		if (shareToggle && lkClosest(event.target, '[data-lk-datasheet-share-toggle]')) {
			event.preventDefault();
			var shouldOpen = shareMenu.hasAttribute('hidden');
			if (shouldOpen) {
				shareMenu.removeAttribute('hidden');
				shareToggle.setAttribute('aria-expanded', 'true');
			} else {
				closeShareMenu();
			}
			return;
		}

		if (lkClosest(event.target, '[data-lk-datasheet-fullscreen]')) {
			event.preventDefault();
			toggleZoom();
			return;
		}

		if (shareMenu && !shareMenu.hasAttribute('hidden') && !lkClosest(event.target, '.lk-ds-viewer__share')) {
			closeShareMenu();
		}
	});

	document.addEventListener('keydown', function (event) {
		if (viewer.hasAttribute('hidden')) {
			return;
		}
		if (event.key === 'Escape') {
			if (shareMenu && !shareMenu.hasAttribute('hidden')) {
				closeShareMenu();
				return;
			}
			if (viewer.classList.contains('is-zoomed')) {
				setZoomed(false);
				renderMedia(false);
				return;
			}
			closeViewer();
		}
	});
}
