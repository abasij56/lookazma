/**
 * Sticky mini-cart drawer interactions.
 */
(function ($) {
	'use strict';

	var root = null;
	var cfg = window.lkMiniCart || {};
	var adding = false;

	function getRoot() {
		if (!root || !document.body.contains(root)) {
			root = document.querySelector('[data-lk-mini-cart]');
		}
		return root;
	}

	function openCart() {
		var el = getRoot();
		if (!el) {
			return;
		}
		el.hidden = false;
		window.requestAnimationFrame(function () {
			el.classList.add('is-open');
			document.body.classList.add('lk-mini-cart-open');
		});
	}

	function closeCart() {
		var el = getRoot();
		if (!el) {
			return;
		}
		el.classList.remove('is-open');
		document.body.classList.remove('lk-mini-cart-open');
		window.setTimeout(function () {
			if (!el.classList.contains('is-open')) {
				el.hidden = true;
			}
		}, 280);
	}

	function applyFragments(fragments) {
		if (!fragments) {
			return;
		}
		Object.keys(fragments).forEach(function (selector) {
			var html = fragments[selector];
			var nodes = document.querySelectorAll(selector);
			nodes.forEach(function (node) {
				var wrap = document.createElement('div');
				wrap.innerHTML = html;
				var next = wrap.firstElementChild;
				if (next && node.parentNode) {
					node.parentNode.replaceChild(next, node);
				}
			});
		});
		root = document.querySelector('[data-lk-mini-cart]');
	}

	function post(action, data) {
		data = data || {};
		data.action = action;
		data.nonce = cfg.nonce;
		return $.post(cfg.ajaxUrl, data);
	}

	function wcAddToCartUrl() {
		return (cfg.wcAjaxUrl || '/?wc-ajax=%%endpoint%%')
			.toString()
			.replace('%%endpoint%%', 'add_to_cart');
	}

	/**
	 * AJAX add from a single-product form (simple or variable).
	 */
	function addFromForm($form, $btn) {
		if (adding) {
			return;
		}

		var $variationId = $form.find('input.variation_id');
		if ($form.find('.variations').length) {
			var vid = $variationId.val();
			if (!vid || vid === '0') {
				return false;
			}
		}

		var productId = $form.find('[name="add-to-cart"]').val()
			|| $form.find('input[name="product_id"]').val();
		if (!productId) {
			return false;
		}

		var qty = $form.find('input[name="quantity"]').val() || 1;
		var data = {
			product_id: productId,
			quantity: qty
		};

		if ($variationId.length && $variationId.val() && $variationId.val() !== '0') {
			data.variation_id = $variationId.val();
			$form.find('select[name^="attribute_"], input[name^="attribute_"]').each(function () {
				if (this.name) {
					data[this.name] = $(this).val();
				}
			});
		}

		adding = true;
		$btn.addClass('loading').prop('disabled', true);

		$.post(wcAddToCartUrl(), data)
			.done(function (response) {
				if (!response) {
					$form.off('submit.lkMiniCart').trigger('submit');
					return;
				}
				if (response.error && response.product_url) {
					window.location = response.product_url;
					return;
				}
				var fragments = response.fragments || {};
				applyFragments(fragments);
				$(document.body).trigger('added_to_cart', [fragments, response.cart_hash || '', $btn]);
				openCart();
			})
			.fail(function () {
				$form.off('submit.lkMiniCart').trigger('submit');
			})
			.always(function () {
				adding = false;
				$btn.removeClass('loading').prop('disabled', false);
			});

		return true;
	}

	function bind() {
		document.addEventListener('click', function (event) {
			var t = event.target;
			if (!t || !t.closest) {
				return;
			}

			if (t.closest('[data-lk-mini-cart-open]')) {
				event.preventDefault();
				openCart();
				return;
			}

			if (t.closest('[data-lk-mini-cart-close]')) {
				event.preventDefault();
				closeCart();
				return;
			}

			/* Header cart button → open drawer instead of navigating */
			var headerCart = t.closest('a.lz-cart-btn');
			if (headerCart) {
				event.preventDefault();
				openCart();
				return;
			}

			/* Single product: .single_add_to_cart_button.button.alt */
			var addBtn = t.closest('.single_add_to_cart_button');
			if (addBtn) {
				if (addBtn.classList.contains('disabled') || addBtn.disabled) {
					return;
				}
				var form = addBtn.closest('form.cart');
				if (form) {
					var started = addFromForm($(form), $(addBtn));
					if (started) {
						event.preventDefault();
						event.stopPropagation();
					}
					return;
				}
			}

			var item = t.closest('.lk-mini-cart__item');
			if (!item) {
				return;
			}
			var key = item.getAttribute('data-cart-key');
			if (!key) {
				return;
			}

			if (t.closest('[data-lk-mini-remove]')) {
				event.preventDefault();
				post('lk_mini_cart_remove', { key: key }).done(function (res) {
					if (res && res.success && res.data && res.data.fragments) {
						applyFragments(res.data.fragments);
						$(document.body).trigger('wc_fragments_refreshed');
					}
				});
				return;
			}

			var minus = t.closest('[data-lk-mini-qty-minus]');
			var plus = t.closest('[data-lk-mini-qty-plus]');
			if (minus || plus) {
				event.preventDefault();
				var valEl = item.querySelector('[data-lk-mini-qty-val]');
				var qty = valEl ? parseInt(valEl.textContent, 10) || 1 : 1;
				qty = minus ? qty - 1 : qty + 1;
				post('lk_mini_cart_set_qty', { key: key, qty: qty }).done(function (res) {
					if (res && res.success && res.data && res.data.fragments) {
						applyFragments(res.data.fragments);
						$(document.body).trigger('wc_fragments_refreshed');
					}
				});
			}
		}, true);

		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') {
				closeCart();
			}
		});

		$(document.body).on('added_to_cart', function (event, fragments) {
			if (fragments) {
				applyFragments(fragments);
			}
			openCart();
		});

		$(document.body).on('removed_from_cart updated_wc_div wc_fragments_refreshed', function () {
			root = document.querySelector('[data-lk-mini-cart]');
		});

		/* Backup: intercept native submit so drawer still opens */
		$(document.body).on('submit.lkMiniCart', 'form.cart', function (event) {
			var $form = $(this);
			var $btn = $form.find('.single_add_to_cart_button').first();
			if (!$btn.length) {
				return;
			}
			var started = addFromForm($form, $btn);
			if (started) {
				event.preventDefault();
			}
		});

		if (cfg.openOnLoad) {
			openCart();
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', bind);
	} else {
		bind();
	}

	window.lkMiniCartApi = {
		open: openCart,
		close: closeCart
	};
})(window.jQuery);
