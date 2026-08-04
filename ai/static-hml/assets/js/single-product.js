(function () {
	'use strict';

	var product = document.querySelector('.lk-product');
	var modeButtons = document.querySelectorAll('.lk-demo-switch button');
	var simplePanel = document.querySelector('.lk-price-card [data-panel="simple"]');
	var variablePanel = document.querySelector('.lk-price-card [data-panel="variable"]');
	var addBtn = document.getElementById('lk-add-to-cart');
	var qtyInput = document.getElementById('lk-qty');
	var cartCount = document.getElementById('lk-cart-count');
	var toast = document.getElementById('lk-toast');
	var packSelect = document.getElementById('lk-attr-pack');
	var gradeSelect = document.getElementById('lk-attr-grade');
	var variablePrice = document.getElementById('lk-variable-price');
	var variableStock = document.getElementById('lk-variable-stock');
	var cartTotal = 0;

	function formatMoney(value) {
		return new Intl.NumberFormat('fa-IR').format(value);
	}

	function setMode(mode) {
		if (!product) {
			return;
		}

		product.setAttribute('data-product-type', mode);

		modeButtons.forEach(function (button) {
			button.classList.toggle('is-active', button.getAttribute('data-mode') === mode);
		});

		if (simplePanel && variablePanel) {
			var isSimple = mode === 'simple';
			simplePanel.hidden = !isSimple;
			variablePanel.hidden = isSimple;
		}

		updateVariableState();
	}

	function updateVariableState() {
		if (!product || product.getAttribute('data-product-type') !== 'variable') {
			addBtn.disabled = false;
			return;
		}

		var pack = packSelect.value;
		var grade = gradeSelect.value;
		var option = packSelect.options[packSelect.selectedIndex];
		var ready = Boolean(pack && grade);

		addBtn.disabled = !ready;

		if (!ready) {
			variablePrice.innerHTML =
				'<span class="lk-money">۸۷۵,۰۰۰</span>' +
				'<span class="lk-sep">–</span>' +
				'<span class="lk-money">۳,۷۰۰,۰۰۰</span>' +
				'<span class="lk-currency">تومان</span>';
			variableStock.textContent = 'یک گزینه را انتخاب کنید';
			variableStock.className = 'lk-price-card__stock is-muted';
			return;
		}

		var price = Number(option.getAttribute('data-price') || 0);
		var regular = Number(option.getAttribute('data-regular') || 0);
		var html = '';

		if (regular > price) {
			html += '<span class="lk-money is-regular">' + formatMoney(regular) + '</span>';
		}

		html += '<span class="lk-money is-sale">' + formatMoney(price) + '</span>';
		html += '<span class="lk-currency">تومان</span>';

		variablePrice.innerHTML = html;
		variableStock.textContent = 'موجود در انبار';
		variableStock.className = 'lk-price-card__stock is-in';
	}

	modeButtons.forEach(function (button) {
		button.addEventListener('click', function () {
			setMode(button.getAttribute('data-mode'));
		});
	});

	document.querySelectorAll('.lk-qty [data-qty]').forEach(function (button) {
		button.addEventListener('click', function () {
			var delta = Number(button.getAttribute('data-qty'));
			var next = Number(qtyInput.value || 1) + delta;
			qtyInput.value = Math.min(99, Math.max(1, next));
		});
	});

	document.querySelectorAll('[data-tabs] .lk-tabs__nav button').forEach(function (button) {
		button.addEventListener('click', function () {
			var tab = button.getAttribute('data-tab');
			var root = button.closest('[data-tabs]');

			root.querySelectorAll('.lk-tabs__nav button').forEach(function (item) {
				var active = item === button;
				item.classList.toggle('is-active', active);
				item.setAttribute('aria-selected', active ? 'true' : 'false');
			});

			root.querySelectorAll('.lk-tabs__panel').forEach(function (panel) {
				var match = panel.getAttribute('data-panel') === tab;
				panel.classList.toggle('is-active', match);
				panel.hidden = !match;
			});
		});
	});

	[packSelect, gradeSelect].forEach(function (select) {
		select.addEventListener('change', updateVariableState);
	});

	document.getElementById('lk-cart-form').addEventListener('submit', function (event) {
		event.preventDefault();
		if (addBtn.disabled) {
			return;
		}

		cartTotal += Number(qtyInput.value || 1);
		cartCount.textContent = String(cartTotal);
		toast.hidden = false;
		window.clearTimeout(toast._timer);
		toast._timer = window.setTimeout(function () {
			toast.hidden = true;
		}, 1800);
	});

	setMode('simple');
})();
