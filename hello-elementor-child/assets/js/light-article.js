(function () {
	function initCarousel() {
		var root = document.querySelector('.lk-article-carousel');
		if (!root) return;
		var track = root.querySelector('.lk-article-carousel__track');
		var dotsWrap = root.querySelector('.lk-article-carousel__dots');
		if (!track) return;
		var items = track.children;
		if (!items.length) return;

		var index = 0;
		var timer = null;
		var dots = [];

		function go(nextIndex) {
			index = (nextIndex + items.length) % items.length;
			track.style.transform = 'translateX(' + (index * 100) + '%)';
			for (var i = 0; i < dots.length; i++) {
				dots[i].classList.toggle('is-active', i === index);
				dots[i].setAttribute('aria-selected', i === index ? 'true' : 'false');
			}
		}

		function start() {
			stop();
			if (items.length < 2) return;
			timer = window.setInterval(function () {
				go(index + 1);
			}, 5000);
		}

		function stop() {
			if (timer) {
				window.clearInterval(timer);
				timer = null;
			}
		}

		if (dotsWrap) {
			dotsWrap.innerHTML = '';
			for (var i = 0; i < items.length; i++) {
				var btn = document.createElement('button');
				btn.type = 'button';
				btn.className = 'lk-article-carousel__dot' + (i === 0 ? ' is-active' : '');
				btn.setAttribute('role', 'tab');
				btn.setAttribute('aria-label', String(i + 1));
				btn.setAttribute('aria-selected', i === 0 ? 'true' : 'false');
				btn.addEventListener('click', (function (slideIndex) {
					return function () {
						go(slideIndex);
						start();
					};
				})(i));
				dotsWrap.appendChild(btn);
				dots.push(btn);
			}
		}

		root.addEventListener('mouseenter', stop);
		root.addEventListener('mouseleave', start);
		start();
	}

	function pageFromHref(href) {
		var raw = href ? String(href) : '';
		var match = raw.match(/\/page\/(\d+)/i);
		if (match) {
			return parseInt(match[1], 10) || 1;
		}
		return 1;
	}

	function initPagination() {
		var cfg = window.lkArticle || {};
		var grid = document.getElementById('lk-article-grid');
		var nav = document.getElementById('lk-article-pagination');
		if (!grid || !nav || !cfg.ajaxUrl) {
			return;
		}

		var loading = false;

		function loadPage(paged, pushUrl) {
			if (loading || paged < 1) {
				return;
			}
			loading = true;
			grid.classList.add('is-loading');

			var body = new URLSearchParams();
			body.set('action', cfg.action || 'lk_article_load_posts');
			body.set('nonce', cfg.nonce || '');
			body.set('page_id', String(cfg.pageId || ''));
			body.set('cat_id', String(cfg.catId || ''));
			body.set('paged', String(paged));

			fetch(cfg.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
				},
				body: body.toString()
			})
				.then(function (res) {
					return res.json();
				})
				.then(function (json) {
					var payload = json && json.data ? json.data : null;
					if (json && json.success && payload) {
						grid.innerHTML = payload.html || '';
						nav.innerHTML = payload.pagination || '';
						nav.hidden = !payload.pagination;
						if (pushUrl && payload.url && window.history && window.history.pushState) {
							window.history.pushState({ lkArticlePage: paged }, '', payload.url);
						}
						if (typeof grid.scrollIntoView === 'function') {
							grid.scrollIntoView({ behavior: 'smooth', block: 'start' });
						}
					}
				})
				.catch(function () {})
				.then(function () {
					loading = false;
					grid.classList.remove('is-loading');
				});
		}

		document.addEventListener('click', function (event) {
			var link = event.target.closest('#lk-article-pagination a.page-numbers');
			if (!link) {
				return;
			}
			event.preventDefault();
			event.stopPropagation();
			loadPage(pageFromHref(link.getAttribute('href')), true);
		});

		window.addEventListener('popstate', function () {
			loadPage(pageFromHref(window.location.href), false);
		});
	}

	initCarousel();
	initPagination();
})();
