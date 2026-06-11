(function ($) {
	'use strict';

	var paymentButtons = (window.artLmsAdminOrder && artLmsAdminOrder.paymentButtons) || [];

	function setStatus($status, message, type) {
		$status
			.removeClass('is-success is-warning is-error')
			.addClass(type ? 'is-' + type : '')
			.text(message || '');
	}

	function getPaymentButton(buttonId) {
		var id = parseInt(buttonId, 10);

		if (!id) {
			return null;
		}

		for (var i = 0; i < paymentButtons.length; i += 1) {
			if (parseInt(paymentButtons[i].id, 10) === id) {
				return paymentButtons[i];
			}
		}

		return null;
	}

	function renderProductMaterials($form, buttonId) {
		var $materials = $form.find('#art_lms_order_product_materials');
		var button = getPaymentButton(buttonId);
		var strings = (window.artLmsAdminOrder && artLmsAdminOrder.strings) || {};

		if (!$materials.length) {
			return;
		}

		if (!button || !button.materials || !button.materials.length) {
			$materials.text(button ? strings.noMaterials || '' : '');
			return;
		}

		$materials.text((strings.materialsPrefix || 'Материалы:') + ' ' + button.materials.join(', '));
	}

	function applyPaymentButtonSelection($form, options) {
		var settings = $.extend(
			{
				forceAmount: false,
			},
			options || {}
		);
		var $select = $form.find('#art_lms_order_product_id');
		var $amount = $form.find('#art_lms_order_amount');
		var button = getPaymentButton($select.val());

		renderProductMaterials($form, $select.val());

		if (!button || !button.price) {
			return;
		}

		if (settings.forceAmount || !$.trim($amount.val())) {
			$amount.val(button.price);
		}
	}

	function applyBuyerDetails($form, details) {		var $email = $form.find('#art_lms_order_email');
		var $name = $form.find('#art_lms_order_name');
		var $phone = $form.find('#art_lms_order_phone');

		if (details.email) {
			$email.val(details.email);
		}

		if (details.found) {
			if (details.name) {
				$name.val(details.name);
			}

			if (details.phone) {
				$phone.val(details.phone);
			}
		}
	}

	function lookupBuyer($form, options) {
		var settings = $.extend(
			{
				silent: false,
			},
			options || {}
		);

		var $identity = $form.find('#art_lms_order_buyer_identity');
		var $status = $form.find('#art_lms_buyer_lookup_status');
		var identity = $.trim($identity.val());

		if (!identity) {
			setStatus($status, '', '');
			return $.Deferred().reject().promise();
		}

		if (!settings.silent) {
			setStatus($status, artLmsAdminOrder.strings.searching, 'warning');
		}

		return $.ajax({
			url: artLmsAdminOrder.restUrl,
			method: 'GET',
			beforeSend: function (xhr) {
				xhr.setRequestHeader('X-WP-Nonce', artLmsAdminOrder.nonce);
			},
			data: {
				identity: identity,
			},
		})
			.done(function (details) {
				applyBuyerDetails($form, details);

				if (details.found) {
					setStatus($status, details.message, 'success');
				} else if (details.email) {
					setStatus($status, details.message, 'warning');
				} else {
					setStatus($status, details.message, 'error');
				}
			})
			.fail(function () {
				setStatus($status, artLmsAdminOrder.strings.lookupFailed, 'error');
			});
	}

	$(function () {
		var $form = $('[data-art-lms-order-form]');

		if (!$form.length) {
			return;
		}

		$form.on('click', '#art_lms_lookup_buyer', function (event) {
			event.preventDefault();
			lookupBuyer($form);
		});

		$form.on('blur', '#art_lms_order_buyer_identity', function () {
			if ($.trim($(this).val())) {
				lookupBuyer($form, { silent: true });
			}
		});

		$form.on('change', '#art_lms_order_product_id', function () {
			applyPaymentButtonSelection($form, { forceAmount: false });
		});

		$form.on('submit', function () {			var $identity = $form.find('#art_lms_order_buyer_identity');
			var $email = $form.find('#art_lms_order_email');
			var identity = $.trim($identity.val());

			if (!identity) {
				return true;
			}

			if (!$email.val() && identity.indexOf('@') !== -1) {
				$email.val(identity);
			}

			return true;
		});

		if ($.trim($form.find('#art_lms_order_buyer_identity').val())) {
			lookupBuyer($form, { silent: true });
		}

		if ($form.find('#art_lms_order_product_id').length) {
			applyPaymentButtonSelection($form, { forceAmount: false });
		}
	});
})(jQuery);