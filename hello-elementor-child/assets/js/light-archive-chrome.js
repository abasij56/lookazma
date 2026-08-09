(function () {
	'use strict';

	function qs(sel, root) {
		return (root || document).querySelector(sel);
	}

	function syncHeaderOffset() {
		var header = qs('.lz-site-header');
		if (!header || !document.body || !document.body.classList.contains('lk-light-product')) {
			return;
		}
		var admin = document.getElementById('wpadminbar');
		var offset = admin ? Math.round(admin.getBoundingClientRect().height) : 0;
		var height = Math.round(header.getBoundingClientRect().height);
		if (height < 48) {
			return;
		}
		document.documentElement.style.setProperty('--lz-header-offset', offset + 'px');
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
