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

	function init() {
		qsa('[data-lk-hero-carousel]').forEach(initHeroCarousel);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
