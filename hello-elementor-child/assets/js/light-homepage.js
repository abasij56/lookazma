(function () {
	'use strict';

	function qsa(sel, root) {
		return Array.prototype.slice.call((root || document).querySelectorAll(sel));
	}

	function initHeroCarousel(carousel) {
		var slides = qsa('.lk-hero-carousel__slide', carousel);
		var dots = qsa('.lk-hero-carousel__dot', carousel);
		var current = 0;
		var timer = null;
		var delay = parseInt(carousel.getAttribute('data-autoplay-ms') || '5000', 10);

		if (!slides.length) {
			return;
		}

		if (delay < 2000) {
			delay = 2000;
		}

		function goTo(index) {
			current = (index + slides.length) % slides.length;

			slides.forEach(function (slide, i) {
				slide.classList.toggle('is-active', i === current);
			});

			dots.forEach(function (dot, i) {
				var active = i === current;
				dot.classList.toggle('is-active', active);
				dot.setAttribute('aria-selected', active ? 'true' : 'false');
			});
		}

		function next() {
			goTo(current + 1);
		}

		function start() {
			stop();
			timer = window.setInterval(next, delay);
		}

		function stop() {
			if (timer) {
				window.clearInterval(timer);
				timer = null;
			}
		}

		dots.forEach(function (dot, index) {
			dot.addEventListener('click', function () {
				goTo(index);
				start();
			});
		});

		carousel.addEventListener('mouseenter', stop);
		carousel.addEventListener('mouseleave', start);
		carousel.addEventListener('focusin', stop);
		carousel.addEventListener('focusout', start);

		goTo(0);
		start();
	}

	function initBrandSlider(root) {
		var viewport = root.querySelector('.lk-brand-strip__viewport');
		var track = root.querySelector('.lk-brand-strip__track');
		if (!viewport || !track) {
			return;
		}

		var originals = qsa('.lk-brand', track);
		var count = originals.length;
		if (count < 2) {
			return;
		}

		function makeRule() {
			var rule = document.createElement('span');
			rule.className = 'lk-brand-strip__rule';
			rule.setAttribute('aria-hidden', 'true');
			return rule;
		}

		function appendSet(nodes) {
			nodes.forEach(function (node, itemIndex) {
				if (itemIndex > 0 || track.childNodes.length) {
					track.appendChild(makeRule());
				}
				track.appendChild(node);
			});
		}

		var clonesA = originals.map(function (node) {
			var clone = node.cloneNode(true);
			clone.setAttribute('data-lk-brand-clone', '1');
			return clone;
		});
		var clonesB = originals.map(function (node) {
			var clone = node.cloneNode(true);
			clone.setAttribute('data-lk-brand-clone', '1');
			return clone;
		});

		track.textContent = '';
		appendSet(originals);
		appendSet(clonesA);
		appendSet(clonesB);

		var index = count;
		var animating = false;
		var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

		function visibleCount() {
			var raw = window.getComputedStyle(root).getPropertyValue('--lk-brand-visible');
			var visible = parseInt(raw, 10);
			return visible > 0 ? visible : 7;
		}

		function stepWidth() {
			var visible = visibleCount();
			var ruleWidth = 1;
			var brandWidth = (viewport.clientWidth - Math.max(0, visible - 1) * ruleWidth) / visible;
			qsa('.lk-brand', track).forEach(function (item) {
				item.style.flex = '0 0 ' + brandWidth + 'px';
				item.style.width = brandWidth + 'px';
			});
			return brandWidth + ruleWidth;
		}

		function apply(animate) {
			var x = -index * stepWidth();
			if (!animate || reduce) {
				track.style.transition = 'none';
				track.style.transform = 'translate3d(' + x + 'px,0,0)';
				track.getBoundingClientRect();
				return;
			}
			track.style.transition = 'transform 0.38s ease';
			track.style.transform = 'translate3d(' + x + 'px,0,0)';
		}

		function normalize() {
			if (index >= count * 2) {
				index -= count;
				apply(false);
			} else if (index < count) {
				index += count;
				apply(false);
			}
			animating = false;
		}

		function go(delta) {
			if (animating) {
				return;
			}
			animating = true;
			index += delta;
			apply(true);
			if (reduce) {
				normalize();
				return;
			}
			window.setTimeout(function () {
				if (animating) {
					normalize();
				}
			}, 480);
		}

		track.addEventListener('transitionend', function (event) {
			if (event.target !== track || event.propertyName !== 'transform') {
				return;
			}
			normalize();
		});

		var delay = parseInt(root.getAttribute('data-autoplay-ms') || '3000', 10);
		if (delay < 1000) {
			delay = 3000;
		}
		var timer = null;

		function stop() {
			if (timer) {
				window.clearInterval(timer);
				timer = null;
			}
		}

		function start() {
			stop();
			if (reduce) {
				return;
			}
			timer = window.setInterval(function () {
				go(1);
			}, delay);
		}

		qsa('.lk-brand-strip__arrow', root).forEach(function (button) {
			button.addEventListener('click', function () {
				go(parseInt(button.getAttribute('data-lk-brand-dir') || '1', 10));
				start();
			});
		});

		root.addEventListener('mouseenter', stop);
		root.addEventListener('mouseleave', start);
		root.addEventListener('focusin', stop);
		root.addEventListener('focusout', start);

		apply(false);
		start();

		var resizeTimer = null;
		window.addEventListener('resize', function () {
			window.clearTimeout(resizeTimer);
			resizeTimer = window.setTimeout(function () {
				apply(false);
			}, 80);
		});
	}

	function init() {
		qsa('[data-lk-hero-carousel]').forEach(initHeroCarousel);
		qsa('[data-lk-brand-slider]').forEach(initBrandSlider);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
