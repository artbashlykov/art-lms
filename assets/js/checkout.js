(function ($) {
	'use strict';

	var checkoutConfig = window.artLmsCheckout || {};
	var formConfig = checkoutConfig.config || {};
	var fields = formConfig.fields || [];
	var consents = formConfig.consents || [];
	var messages = formConfig.messages || {};
	var requirePayment = !!formConfig.requirePayment;

	function replaceTokens(template, tokens) {
		var text = template || '';

		Object.keys(tokens).forEach(function (token) {
			text = text.split(token).join(tokens[token]);
		});

		return text;
	}

	function formatMessage(key, tokens) {
		var template = messages[key] || messages.generic_error || '';

		return replaceTokens(template, tokens || {});
	}

	function isValidEmail(value) {
		return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value || '').trim());
	}

	function getFieldValue($form, name) {
		var $input = $form.find('[name="' + name + '"]');

		if (!$input.length) {
			return '';
		}

		return $.trim(String($input.val() || ''));
	}

	function isConsentChecked($form, name) {
		return $form.find('[name="' + name + '"]').is(':checked');
	}

	function clearFeedback($form) {
		var $feedback = $form.find('.art-lms-checkout-form__feedback');

		$feedback
			.text('')
			.prop('hidden', true)
			.removeClass('is-visible');
	}

	function showFeedback($form, message) {
		var $feedback = $form.find('.art-lms-checkout-form__feedback');

		$feedback
			.text(message)
			.prop('hidden', false)
			.addClass('is-visible');
	}

	function validateForm($form) {
		var field;
		var consent;
		var value;
		var i;

		for (i = 0; i < fields.length; i += 1) {
			field = fields[i];
			value = getFieldValue($form, field.name);

			if (field.required && !value) {
				return formatMessage('required_field', {
					'{поле}': field.label || field.key,
				});
			}

			if (field.input === 'email' && value && !isValidEmail(value)) {
				return formatMessage('invalid_email');
			}
		}

		for (i = 0; i < consents.length; i += 1) {
			consent = consents[i];

			if (consent.required && !isConsentChecked($form, consent.name)) {
				return formatMessage('consent_required', {
					'{согласие}': consent.label || consent.key,
				});
			}
		}

		if (requirePayment && !getFieldValue($form, 'payment_gateway')) {
			return formatMessage('payment_method_required');
		}

		return '';
	}

	function collectSubmission($form) {
		var payload = {
			button_id: checkoutConfig.buttonId || 0,
		};
		var i;

		for (i = 0; i < fields.length; i += 1) {
			payload[fields[i].name] = getFieldValue($form, fields[i].name);
		}

		for (i = 0; i < consents.length; i += 1) {
			payload[consents[i].name] = isConsentChecked($form, consents[i].name) ? '1' : '';
		}

		if (requirePayment) {
			payload.payment_gateway = getFieldValue($form, 'payment_gateway');
		}

		return payload;
	}

	function setSubmitting($form, isSubmitting) {
		var $button = $form.find('[type="submit"]');

		$button.prop('disabled', isSubmitting);

		if (isSubmitting) {
			$button.data('art-lms-original-text', $button.text());
			$button.text(checkoutConfig.strings && checkoutConfig.strings.submitting ? checkoutConfig.strings.submitting : '...');
		} else if ($button.data('art-lms-original-text')) {
			$button.text($button.data('art-lms-original-text'));
		}
	}

	function submitCheckout($form) {
		if (!checkoutConfig.restUrl || !checkoutConfig.nonce) {
			showFeedback($form, checkoutConfig.strings ? checkoutConfig.strings.submitFailed : '');
			return;
		}

		setSubmitting($form, true);

		$.ajax({
			url: checkoutConfig.restUrl,
			method: 'POST',
			contentType: 'application/json',
			dataType: 'json',
			data: JSON.stringify(collectSubmission($form)),
			beforeSend: function (xhr) {
				xhr.setRequestHeader('X-WP-Nonce', checkoutConfig.nonce);
			},
		})
			.done(function (response) {
				if (response && response.verification_pending) {
					showFeedback(
						$form,
						response.message || (checkoutConfig.strings ? checkoutConfig.strings.verificationPending : '')
					);
					setSubmitting($form, false);
					return;
				}

				var redirect = response && response.redirect ? response.redirect : '';

				if (redirect) {
					window.location.href = redirect;
					return;
				}

				showFeedback(
					$form,
					checkoutConfig.strings ? checkoutConfig.strings.submitFailed : ''
				);
				setSubmitting($form, false);
			})
			.fail(function (xhr) {
				var message =
					xhr.responseJSON && xhr.responseJSON.message
						? xhr.responseJSON.message
						: checkoutConfig.strings
							? checkoutConfig.strings.submitFailed
							: '';

				showFeedback($form, message);
				setSubmitting($form, false);
			});
	}

	function initCheckoutForm() {
		var $form = $('.art-lms-checkout-form');

		if (!$form.length) {
			return;
		}

		$form.on('input change', 'input, textarea, select', function () {
			clearFeedback($form);
		});

		$form.on('submit', function (event) {
			event.preventDefault();

			var error = validateForm($form);

			if (error) {
				showFeedback($form, error);
				return;
			}

			clearFeedback($form);
			submitCheckout($form);
		});
	}

	$(document).ready(initCheckoutForm);
})(jQuery);
