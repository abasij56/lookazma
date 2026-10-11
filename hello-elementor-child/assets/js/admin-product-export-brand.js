/* global jQuery */
(function ($) {
	'use strict';

	/**
	 * WooCommerce product export only posts known fields.
	 * Append selected brand (product_tag) IDs to each export step.
	 */
	$.ajaxPrefilter(function (options) {
		if (!options || !options.data) {
			return;
		}

		var action = null;
		if (typeof options.data === 'object' && options.data.action) {
			action = options.data.action;
		} else if (typeof options.data === 'string') {
			var match = options.data.match(/(?:^|&)action=([^&]*)/);
			action = match ? decodeURIComponent(match[1]) : null;
		}

		if (action !== 'woocommerce_do_ajax_product_export') {
			return;
		}

		var brands = $('.woocommerce-exporter-brand').val() || [];
		if (!Array.isArray(brands)) {
			brands = brands ? [brands] : [];
		}

		if (typeof options.data === 'object') {
			options.data.export_brand = brands;
			return;
		}

		brands.forEach(function (id) {
			options.data +=
				'&export_brand[]=' + encodeURIComponent(String(id));
		});
	});
})(jQuery);
