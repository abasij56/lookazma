/**
 * Toggle visible AI provider connection fields on the settings page.
 */
document.addEventListener('DOMContentLoaded', function () {
	const select = document.getElementById('ai-product-desc-active-provider');
	const page = document.querySelector('.ai-product-desc-settings');

	if (!select || !page) {
		return;
	}

	const syncProviderPanels = function () {
		const active = select.value;
		const panels = page.querySelectorAll('[data-provider]');

		panels.forEach(function (el) {
			const isActive = el.getAttribute('data-provider') === active;
			el.hidden = !isActive;

			const row = el.closest('tr');
			if (row) {
				row.hidden = !isActive;
			}
		});

		page.querySelectorAll('h2').forEach(function (heading) {
			const next = heading.nextElementSibling;
			if (!next || !next.classList.contains('ai-product-desc-provider-panel')) {
				return;
			}

			const provider = next.getAttribute('data-provider');
			const table = next.nextElementSibling;
			const isActive = provider === active;

			heading.hidden = !isActive;
			next.hidden = !isActive;
			if (table && table.classList.contains('form-table')) {
				table.hidden = !isActive;
			}
		});
	};

	select.addEventListener('change', syncProviderPanels);
	syncProviderPanels();
});
