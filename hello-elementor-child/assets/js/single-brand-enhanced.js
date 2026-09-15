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

	function init() {
		initAboutToggle();
		initCarousels();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
