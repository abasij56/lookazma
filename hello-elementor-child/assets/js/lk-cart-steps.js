(function () {
	var root = document.querySelector('[data-lk-cart-steps]');
	if (!root || typeof lkCartSteps === 'undefined') {
		return;
	}

	var login = document.querySelector('[data-lk-cart-login]');
	var loginLink = login ? login.querySelector('[data-lk-cart-login-url]') : null;
	var items = root.querySelectorAll('.lk-cart-steps__item');
	var panels = {};
	var busy = false;
	var continueCheckout = !!lkCartSteps.continueCheckout;

	for (var n = 1; n <= 6; n++) {
		panels[n] = document.querySelector('[data-lk-cart-panel="' + n + '"]');
	}

	if (loginLink && lkCartSteps.loginUrl) {
		loginLink.setAttribute('href', lkCartSteps.loginUrl);
	}

	function setStep(step) {
		root.setAttribute('data-step', String(step));
		items.forEach(function (item, index) {
			var current = index + 1;
			item.classList.toggle('is-current', current === step);
			item.classList.toggle('is-done', current < step);
		});
		Object.keys(panels).forEach(function (key) {
			var panel = panels[key];
			if (!panel) {
				return;
			}
			var active = Number(key) === step;
			panel.hidden = !active;
			panel.classList.toggle('is-active', active);
		});
	}

	function openLogin() {
		if (!login) {
			return;
		}
		login.hidden = false;
		var closeBtn = login.querySelector('[data-lk-cart-login-close]');
		if (closeBtn) {
			closeBtn.focus();
		}
	}

	function closeLogin() {
		if (login) {
			login.hidden = true;
		}
	}

	function loadStep(step) {
		var panel = panels[step];
		if (busy || !panel) {
			return;
		}
		if (step > 2 && !continueCheckout) {
			return;
		}
		if (panel.getAttribute('data-lk-loaded') === '1') {
			setStep(step);
			return;
		}
		busy = true;
		var body = new FormData();
		body.append('action', 'lk_cart_step');
		body.append('nonce', lkCartSteps.nonce || '');
		body.append('step', String(step));
		fetch(lkCartSteps.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (payload) {
				if (!payload || !payload.success || !payload.data || !payload.data.html) {
					return;
				}
				panel.innerHTML = payload.data.html;
				panel.setAttribute('data-lk-loaded', '1');
				setStep(step);
			})
			.finally(function () {
				busy = false;
			});
	}

	document.addEventListener('click', function (event) {
		var continueBtn = event.target.closest('[data-lk-cart-continue]');
		if (continueBtn) {
			event.preventDefault();
			if (!lkCartSteps.loggedIn) {
				openLogin();
				return;
			}
			loadStep(2);
			return;
		}

		var next = event.target.closest('[data-lk-cart-next]');
		if (next) {
			event.preventDefault();
			if (!continueCheckout || !lkCartSteps.loggedIn) {
				if (!lkCartSteps.loggedIn) {
					openLogin();
				}
				return;
			}
			loadStep(Number(next.getAttribute('data-lk-cart-next')));
			return;
		}

		var back = event.target.closest('[data-lk-cart-goto]');
		if (back) {
			var target = Number(back.getAttribute('data-lk-cart-goto'));
			if (!target || target === Number(root.getAttribute('data-step'))) {
				return;
			}
			event.preventDefault();
			if (target === 1) {
				setStep(1);
				return;
			}
			loadStep(target);
		}

		if (event.target.closest('[data-lk-cart-login-close]')) {
			event.preventDefault();
			closeLogin();
		}
	});

	if (login) {
		login.addEventListener('click', function (event) {
			if (event.target === login) {
				closeLogin();
			}
		});
	}

	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape') {
			closeLogin();
		}
	});
})();
