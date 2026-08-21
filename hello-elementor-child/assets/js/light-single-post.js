/**
 * Light single post — TOC (box + nested H2/H3) + active section.
 */
(function () {
	'use strict';

	function qs(sel, root) {
		return (root || document).querySelector(sel);
	}

	function qsa(sel, root) {
		return Array.prototype.slice.call((root || document).querySelectorAll(sel));
	}

	function setExpanded(el, expanded) {
		el.setAttribute('aria-expanded', expanded ? 'true' : 'false');
	}

	function initToc() {
		var toc = qs('[data-lk-post-toc]');
		if (!toc || toc.getAttribute('data-lk-toc-ready') === '1') {
			return;
		}
		toc.setAttribute('data-lk-toc-ready', '1');

		var toggle = qs('[data-lk-post-toc-toggle]', toc);
		var body = qs('[data-lk-post-toc-body]', toc) || qs('.lk-post-toc__body', toc);

		function setBoxOpen(open) {
			toc.classList.toggle('is-collapsed', !open);
			if (body) {
				body.hidden = !open;
			}
			if (toggle) {
				setExpanded(toggle, open);
			}
		}

		if (toggle) {
			toggle.addEventListener('click', function (e) {
				e.preventDefault();
				var open = toc.classList.contains('is-collapsed');
				setBoxOpen(open);
			});
		}

		// Collapse TOC box by default on tablet/mobile.
		var preferCollapsed =
			window.matchMedia && window.matchMedia('(max-width: 1024px)').matches;
		setBoxOpen(!preferCollapsed);

		// H2 branch toggles (H3 submenu).
		qsa('[data-lk-toc-group]', toc).forEach(function (group) {
			var branch = qs('[data-lk-toc-branch]', group);
			var sub = qs('[data-lk-toc-sub]', group);
			if (!branch || !sub) {
				return;
			}

			function setBranchOpen(open) {
				group.classList.toggle('is-open', open);
				sub.hidden = !open;
				setExpanded(branch, open);
			}

			setBranchOpen(false);

			branch.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				setBranchOpen(sub.hidden);
			});
		});

		var links = qsa('.lk-post-toc__link', toc);
		var sections = links
			.map(function (link) {
				var id = (link.getAttribute('href') || '').replace(/^#/, '');
				return id ? document.getElementById(id) : null;
			})
			.filter(Boolean);

		if (!sections.length || !('IntersectionObserver' in window)) {
			return;
		}

		var observer = new IntersectionObserver(
			function (entries) {
				entries.forEach(function (entry) {
					if (!entry.isIntersecting) {
						return;
					}
					var id = entry.target.id;
					links.forEach(function (link) {
						var active = (link.getAttribute('href') || '') === '#' + id;
						link.classList.toggle('is-active', active);
						if (active) {
							var group = link.closest('[data-lk-toc-group]');
							if (group && link.closest('[data-lk-toc-sub]')) {
								var branch = qs('[data-lk-toc-branch]', group);
								var sub = qs('[data-lk-toc-sub]', group);
								if (branch && sub && sub.hidden) {
									group.classList.add('is-open');
									sub.hidden = false;
									setExpanded(branch, true);
								}
							}
						}
					});
				});
			},
			{
				rootMargin: '-20% 0px -65% 0px',
				threshold: 0,
			}
		);

		sections.forEach(function (sec) {
			observer.observe(sec);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initToc);
	} else {
		initToc();
	}
})();
