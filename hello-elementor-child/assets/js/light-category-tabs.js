(function () {
	'use strict';

	function qs(sel, root) {
		return (root || document).querySelector(sel);
	}

	function qsa(sel, root) {
		return Array.prototype.slice.call((root || document).querySelectorAll(sel));
	}

	function headerOffset() {
		var raw = getComputedStyle(document.documentElement).getPropertyValue('--lz-header-h');
		var header = parseFloat(raw) || 128;
		var admin = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--lz-header-offset')) || 0;
		return header + admin + 12;
	}

	function setTab(name) {
		var tabs = qsa('[data-lk-cat-tab]');
		var panels = qsa('[data-lk-cat-panel]');
		if (!tabs.length) {
			return;
		}
		if (name !== 'specs') {
			name = 'products';
		}
		if (name === 'specs' && !qs('[data-lk-cat-tab="specs"]')) {
			name = 'products';
		}

		tabs.forEach(function (btn) {
			var on = btn.getAttribute('data-lk-cat-tab') === name;
			btn.classList.toggle('is-active', on);
			btn.setAttribute('aria-selected', on ? 'true' : 'false');
		});

		panels.forEach(function (panel) {
			var on = panel.getAttribute('data-lk-cat-panel') === name;
			panel.classList.toggle('is-active', on);
			if (on) {
				panel.removeAttribute('hidden');
			} else {
				panel.setAttribute('hidden', 'hidden');
			}
		});
	}

	function setActiveToc(id) {
		qsa('.lk-cat-toc__btn[data-lk-cat-sec]').forEach(function (btn) {
			var on = btn.getAttribute('data-lk-cat-sec') === id;
			btn.classList.toggle('is-active', on);
			if (on) {
				btn.setAttribute('aria-current', 'true');
			} else {
				btn.removeAttribute('aria-current');
			}
		});
	}

	function scrollToSection(id) {
		var target = id ? document.getElementById(id) : null;
		if (!target) {
			return;
		}
		setActiveToc(id);
		window.requestAnimationFrame(function () {
			var top = target.getBoundingClientRect().top + window.pageYOffset - headerOffset();
			window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
		});
	}

	function hashName() {
		return (window.location.hash || '').replace('#', '');
	}

	function initFromHash() {
		var hash = hashName();
		if (hash.indexOf('lk-cat-sec-') === 0) {
			setTab('specs');
			window.setTimeout(function () {
				scrollToSection(hash);
			}, 50);
			return;
		}
		setTab(hash === 'specs' ? 'specs' : 'products');
	}

	function init() {
		var shell = qs('.lk-cat-shell');
		if (!shell) {
			return;
		}

		shell.addEventListener('click', function (e) {
			var tabBtn = e.target.closest('[data-lk-cat-tab]');
			if (tabBtn) {
				e.preventDefault();
				var tab = tabBtn.getAttribute('data-lk-cat-tab');
				setTab(tab);
				if (window.history && window.history.replaceState) {
					var url = new URL(window.location.href);
					url.hash = tab === 'specs' ? 'specs' : 'products';
					window.history.replaceState(null, '', url.toString());
				}
				return;
			}

			var secBtn = e.target.closest('.lk-cat-toc__btn[data-lk-cat-sec]');
			if (secBtn) {
				e.preventDefault();
				var id = secBtn.getAttribute('data-lk-cat-sec');
				setTab('specs');
				if (window.history && window.history.replaceState) {
					var specUrl = new URL(window.location.href);
					specUrl.hash = id || 'specs';
					window.history.replaceState(null, '', specUrl.toString());
				}
				scrollToSection(id);
			}
		});

		initFromHash();
		window.addEventListener('hashchange', initFromHash);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
