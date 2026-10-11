/**
 * Put account address fields back after WooCommerce locale sorting.
 */
(function ($) {
	var order = [
		'first_name',
		'last_name',
		'company',
		'country',
		'state',
		'city',
		'address_1',
		'address_2',
		'postcode',
		'phone',
		'email'
	];

	function setLabel($field, text) {
		var $label = $field.find('label').first();
		if (!$label.length) {
			return;
		}
		var $required = $label.find('.required').detach();
		var $optional = $label.find('.optional').detach();
		$label.empty().append(document.createTextNode(text));
		if ($required.length) {
			$label.append('\u00a0').append($required);
		} else if ($optional.length) {
			$label.append('\u00a0').append($optional);
		}
	}

	function restore(context) {
		var $forms = $(context).closest('.lk-account-address-form');
		if (!$forms.length) {
			$forms = $('.lk-account-address-form');
		}
		$forms.each(function () {
			var $wrap = $(this).find('.woocommerce-address-fields__field-wrapper');
			if (!$wrap.length) {
				return;
			}
			var prefix = $wrap.find('[id^="shipping_"]').length ? 'shipping_' : 'billing_';
			order.forEach(function (slug) {
				var $row = $wrap.find('#' + prefix + slug + '_field');
				if ($row.length) {
					$wrap.append($row);
				}
			});
			setLabel($wrap.find('#' + prefix + 'address_1_field'), 'خیابان');
			setLabel($wrap.find('#' + prefix + 'address_2_field'), 'پلاک');
		});
	}

	$(function () {
		restore(document);
		$(document.body).on('country_to_state_changing', function (event, country, wrapper) {
			restore(wrapper && wrapper.length ? wrapper : document);
		});
	});
})(jQuery);
