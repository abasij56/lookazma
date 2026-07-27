/**
 * Elementor Menu Cart fallback for light Timber templates.
 * Starts closed; ensures X / Escape always close the drawer.
 */
(function () {
	'use strict';

	var shownSelector = '.elementor-menu-cart--shown';

	function closeAllCarts() {
		document.querySelectorAll(shownSelector).forEach(function (el) {
			el.classList.remove('elementor-menu-cart--shown');
		});
		document.documentElement.classList.remove('elementor-menu-cart--shown');
		document.body.classList.remove('elementor-menu-cart--shown');
	}

	function findCartRoot(from) {
		return (
			from.closest('.elementor-widget-woocommerce-menu-cart') ||
			from.closest('.elementor-menu-cart') ||
			from.closest('[data-widget_type="woocommerce-menu-cart.default"]')
		);
	}

	function bind() {
		closeAllCarts();

		document.addEventListener(
			'click',
			function (event) {
				var target = event.target;
				if (!target || !target.closest) {
					return;
				}

				var closeBtn = target.closest(
					'.elementor-menu-cart__close-button, .elementor-menu-cart__close-button-wrapper, .elementor-menu-cart__close-button i, .elementor-menu-cart__close-button svg'
				);
				if (closeBtn) {
					event.preventDefault();
					event.stopPropagation();
					if (typeof event.stopImmediatePropagation === 'function') {
						event.stopImmediatePropagation();
					}
					closeAllCarts();
					return;
				}

				// Overlay / empty area click closes.
				if (target.classList && target.classList.contains('elementor-menu-cart__container')) {
					event.preventDefault();
					event.stopPropagation();
					closeAllCarts();
					return;
				}

				if (window.elementorFrontend) {
					return;
				}

				var toggle = target.closest(
					'.elementor-menu-cart__toggle, .elementor-menu-cart__toggle_button'
				);
				if (toggle) {
					var root = findCartRoot(toggle);
					if (!root) {
						return;
					}
					event.preventDefault();
					event.stopPropagation();
					var willOpen = !root.classList.contains('elementor-menu-cart--shown');
					closeAllCarts();
					if (willOpen) {
						root.classList.add('elementor-menu-cart--shown');
					}
				}
			},
			true
		);

		document.addEventListener('keyup', function (event) {
			if (event.key === 'Escape') {
				closeAllCarts();
			}
		});

		// Re-close if fragments refresh leaves the drawer open.
		document.body.addEventListener('wc_fragments_refreshed', closeAllCarts);
		document.body.addEventListener('wc_fragments_loaded', closeAllCarts);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', bind);
	} else {
		bind();
	}

	// Late Elementor header render.
	window.addEventListener('load', function () {
		window.setTimeout(closeAllCarts, 0);
		window.setTimeout(closeAllCarts, 300);
	});
})();
