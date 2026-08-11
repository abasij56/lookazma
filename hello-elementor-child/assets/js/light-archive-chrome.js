(function () {
	'use strict';

	function qs(sel, root) {
		return (root || document).querySelector(sel);
	}

	function syncHeaderOffset() {
		var header = qs('.lz-site-header');
		if (!header || !document.body) {
			return;
		}
		if (
			!document.body.classList.contains('lk-light-product')
			&& !document.body.classList.contains('lz-chrome')
		) {
			return;
		}
		var height = Math.round(header.getBoundingClientRect().height);
		if (height < 48) {
			return;
		}
		document.documentElement.style.setProperty('--lz-header-offset', '0px');
		document.documentElement.style.setProperty('--lz-header-h', height + 'px');
	}

	function init() {
		syncHeaderOffset();
		window.addEventListener('resize', syncHeaderOffset);
		window.addEventListener('load', syncHeaderOffset);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
