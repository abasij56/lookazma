(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		initSearch();
		initSaveButtons();
	});

	function initSearch() {
		var searchInput = document.getElementById('price-table-search');
		if (!searchInput) {
			return;
		}

		searchInput.addEventListener('input', function () {
			var filter = this.value.toLowerCase();
			var rows = document.querySelectorAll('#price-table tbody tr');

			rows.forEach(function (row) {
				var nameCell = row.querySelector('.product-name');
				var name = nameCell ? nameCell.textContent.toLowerCase() : '';
				row.style.display = name.includes(filter) ? '' : 'none';
			});
		});
	}

	function initSaveButtons() {
		var table = document.getElementById('price-table');
		if (!table || typeof jQuery === 'undefined' || typeof attrPriceEditor === 'undefined') {
			return;
		}

		table.addEventListener('click', function (e) {
			if (!e.target.classList.contains('attr-save-btn')) {
				return;
			}

			var btn = e.target;
			var row = btn.closest('tr');
			var priceInput = row.querySelector('.attr-price-input');
			var skuInput = row.querySelector('.attr-sku-input');

			btn.classList.add('btn-loading');
			btn.classList.remove('btn-success', 'btn-error');

			jQuery.post(
				attrPriceEditor.ajaxUrl,
				{
					action: attrPriceEditor.action,
					nonce: attrPriceEditor.nonce,
					product_id: btn.dataset.product,
					variation_id: btn.dataset.variation,
					price: parseFloat(priceInput.value),
					sku: skuInput ? skuInput.value : ''
				},
				function (response) {
					btn.classList.remove('btn-loading');

					if (response.success) {
						btn.classList.add('btn-success');
						setTimeout(function () {
							btn.classList.remove('btn-success');
						}, 2000);
					} else {
						btn.classList.add('btn-error');
						setTimeout(function () {
							btn.classList.remove('btn-error');
						}, 2000);
					}
				}
			).fail(function () {
				btn.classList.remove('btn-loading');
				btn.classList.add('btn-error');
				setTimeout(function () {
					btn.classList.remove('btn-error');
				}, 2000);
			});
		});
	}
})();
