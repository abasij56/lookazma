/**
 * Highlight the active AI provider section + connection test on settings page.
 */
(function () {
	'use strict';

	function ready(fn) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', fn);
			return;
		}
		fn();
	}

	ready(function () {
		const select = document.getElementById('ai-product-desc-active-provider');
		const page = document.querySelector('.ai-product-desc-settings');

		if (select && page) {
			const syncActiveHighlight = function () {
				const active = select.value;

				page.querySelectorAll('h2').forEach(function (heading) {
					const next = heading.nextElementSibling;
					if (!next || !next.classList.contains('ai-product-desc-provider-panel')) {
						heading.classList.remove('ai-provider-active');
						return;
					}

					const provider = next.getAttribute('data-provider');
					const isActive = provider === active;
					heading.classList.toggle('ai-provider-active', isActive);
					next.classList.toggle('ai-provider-active', isActive);

					const table = next.nextElementSibling;
					if (table && table.classList.contains('form-table')) {
						table.classList.toggle('ai-provider-active', isActive);
					}
				});
			};

			select.addEventListener('change', syncActiveHighlight);
			syncActiveHighlight();
		}
	});
})();
