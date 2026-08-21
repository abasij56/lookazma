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

	function initCategoryScroll(viewport) {
		if (viewport.getAttribute('data-lk-scroll-ready') === '1') {
			return;
		}

		var track = viewport.querySelector('.lk-categories__track');
		var items = qsa('.lk-category', track || viewport);
		if (!track || items.length < 2) {
			return;
		}

		viewport.setAttribute('data-lk-scroll-ready', '1');

		var paused = false;
		var finished = false;
		var autoTimer = null;
		var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

		var isDragging = false;
		var startX = 0;
		var startScroll = 0;
		var moved = false;
		var activePointer = null;

		function maxScroll() {
			return Math.max(0, viewport.scrollWidth - viewport.clientWidth);
		}

		function clampScroll() {
			var max = maxScroll();
			if (viewport.scrollLeft < 0) {
				viewport.scrollLeft = 0;
			} else if (viewport.scrollLeft > max) {
				viewport.scrollLeft = max;
			}
		}

		function startFromRight() {
			viewport.scrollLeft = maxScroll();
		}

		function scrollStep() {
			if (paused || finished || reduceMotion || isDragging) {
				return;
			}

			var max = maxScroll();
			if (max <= 0) {
				return;
			}

			var before = viewport.scrollLeft;
			viewport.scrollLeft = before - 1;

			if (viewport.scrollLeft === before || viewport.scrollLeft <= 0) {
				viewport.scrollLeft = 0;
				finished = true;
				stopAuto();
			}
		}

		function startAuto() {
			stopAuto();
			if (reduceMotion || maxScroll() <= 0 || isDragging) {
				return;
			}
			autoTimer = window.setInterval(scrollStep, 20);
		}

		function stopAuto() {
			if (autoTimer) {
				window.clearInterval(autoTimer);
				autoTimer = null;
			}
		}

		function boot() {
			finished = false;
			startFromRight();
			clampScroll();
			if (!isDragging && !paused) {
				startAuto();
			}
		}

		function onPointerDown(event) {
			if (event.pointerType === 'mouse' && event.button !== 0) {
				return;
			}

			isDragging = true;
			moved = false;
			paused = true;
			activePointer = event.pointerId;
			startX = event.clientX;
			startScroll = viewport.scrollLeft;
			viewport.classList.add('is-dragging');
			stopAuto();

			if (viewport.setPointerCapture) {
				try {
					viewport.setPointerCapture(event.pointerId);
				} catch (err) {
					/* ignore */
				}
			}
		}

		function onPointerMove(event) {
			if (!isDragging || event.pointerId !== activePointer) {
				return;
			}

			var delta = event.clientX - startX;
			if (Math.abs(delta) > 4) {
				moved = true;
			}

			viewport.scrollLeft = startScroll - delta;
			clampScroll();

			if (event.cancelable) {
				event.preventDefault();
			}
		}

		function endDrag(event) {
			if (!isDragging) {
				return;
			}

			if (event && event.pointerId !== activePointer) {
				return;
			}

			isDragging = false;
			activePointer = null;
			viewport.classList.remove('is-dragging');

			if (event && viewport.releasePointerCapture) {
				try {
					viewport.releasePointerCapture(event.pointerId);
				} catch (err) {
					/* ignore */
				}
			}

			window.setTimeout(function () {
				paused = false;
				if (!finished && !isDragging) {
					startAuto();
				}
			}, 150);
		}

		function onClickCapture(event) {
			if (!moved) {
				return;
			}
			event.preventDefault();
			event.stopImmediatePropagation();
			moved = false;
		}

		viewport.addEventListener('pointerdown', onPointerDown);
		viewport.addEventListener('pointermove', onPointerMove);
		viewport.addEventListener('pointerup', endDrag);
		viewport.addEventListener('pointercancel', endDrag);
		viewport.addEventListener('lostpointercapture', endDrag);
		viewport.addEventListener('click', onClickCapture, true);

		viewport.addEventListener('mouseenter', function () {
			stopAuto();
		});
		viewport.addEventListener('mouseleave', function () {
			endDrag(null);
			if (!finished && !isDragging) {
				startAuto();
			}
		});

		window.addEventListener('resize', boot);
		window.addEventListener('load', boot);

		if (document.fonts && document.fonts.ready) {
			document.fonts.ready.then(boot);
		}

		window.setTimeout(boot, 100);
		window.setTimeout(boot, 500);
	}

	function init() {
		qsa('[data-lk-hero-carousel]').forEach(initHeroCarousel);
		qsa('[data-lk-category-scroll]').forEach(initCategoryScroll);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
