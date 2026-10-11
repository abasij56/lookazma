(function () {
	'use strict';

	function qs(sel, root) {
		return (root || document).querySelector(sel);
	}

	function qsa(sel, root) {
		return Array.prototype.slice.call((root || document).querySelectorAll(sel));
	}

	function initAboutToggle() {
		var btn = qs('[data-lk-brand-about-toggle]');
		var more = qs('#lk-brand-about-more');
		if (!btn || !more) {
			return;
		}
		btn.addEventListener('click', function () {
			var expanded = btn.getAttribute('aria-expanded') === 'true';
			btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
			more.hidden = expanded;
			btn.textContent = expanded ? 'مشاهده بیشتر' : 'بستن';
		});
	}

	function initCarousels() {
		qsa('[data-lk-brand-carousel]').forEach(function (wrap) {
			var list = qs('.lk-brand-enhanced-similar__list', wrap) || qs('.lk-brand-enhanced-articles__list', wrap);
			if (!list) {
				return;
			}

			var prev = qs('[data-lk-carousel-prev]', wrap);
			var next = qs('[data-lk-carousel-next]', wrap);

			function measureStep() {
				var slide = qs('li', list);
				if (!slide) {
					return 0;
				}
				var styles = window.getComputedStyle(list);
				var gap = parseFloat(styles.columnGap || styles.gap || '0') || 0;
				return slide.getBoundingClientRect().width + gap;
			}

			function scrollByDir(dir) {
				var step = measureStep() || list.clientWidth * 0.8;
				list.scrollBy({ left: dir * step, behavior: 'smooth' });
			}

			if (prev) {
				prev.addEventListener('click', function () {
					scrollByDir(1);
				});
			}
			if (next) {
				next.addEventListener('click', function () {
					scrollByDir(-1);
				});
			}
		});
	}

	function headerOffset() {
		var h = getComputedStyle(document.documentElement).getPropertyValue('--lz-header-h').trim();
		var px = parseFloat(h) || 0;
		if (h.indexOf('rem') !== -1) {
			var root = parseFloat(getComputedStyle(document.documentElement).fontSize) || 16;
			px = px * root;
		}
		return px + 6;
	}

	function scrollToProducts(el) {
		if (!el) {
			return;
		}
		var top = el.getBoundingClientRect().top + window.pageYOffset - headerOffset();
		window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
	}

	function initProductsJump() {
		qsa('a.lk-brand-enhanced-btn--hero-primary[href="#lk-brand-products"]').forEach(function (link) {
			link.addEventListener('click', function (e) {
				var target = qs('#lk-brand-products');
				if (!target) {
					return;
				}
				e.preventDefault();
				scrollToProducts(target);
				if (history.replaceState) {
					history.replaceState(null, '', '#lk-brand-products');
				}
			});
		});

		if (window.location.hash === '#lk-brand-products') {
			var target = qs('#lk-brand-products');
			if (target) {
				window.setTimeout(function () {
					scrollToProducts(target);
				}, 50);
			}
		}
	}

	function init() {
		initAboutToggle();
		initCarousels();
		initProductsJump();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
