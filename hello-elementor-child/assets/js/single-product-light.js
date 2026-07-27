document.addEventListener('DOMContentLoaded', function () {
	initTabs();
});

function initTabs() {
	var tabsRoot = document.querySelector('[data-lk-tabs]');
	if (!tabsRoot) {
		return;
	}

	var buttons = tabsRoot.querySelectorAll('.lk-tabs__nav button');
	var panels = tabsRoot.querySelectorAll('.lk-tabs__panel');

	buttons.forEach(function (button) {
		button.addEventListener('click', function () {
			var tab = button.getAttribute('data-tab');

			buttons.forEach(function (item) {
				var active = item === button;
				item.classList.toggle('is-active', active);
				item.setAttribute('aria-selected', active ? 'true' : 'false');
			});

			panels.forEach(function (panel) {
				var match = panel.getAttribute('data-panel') === tab;
				panel.classList.toggle('is-active', match);
				if (match) {
					panel.removeAttribute('hidden');
				} else {
					panel.setAttribute('hidden', 'hidden');
				}
			});
		});
	});
}
