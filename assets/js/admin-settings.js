(function ($) {
	'use strict';

	var previewConfig = window.artLmsCheckoutPreview || {};
	var previewStrings = previewConfig.strings || {};
	var builtinFields = previewConfig.builtinFields || [];

	function escapeHtml(value) {
		return $('<div>').text(value || '').html();
	}

	function collectCheckoutPreviewFields($form) {
		var fields = [];

		builtinFields.forEach(function (def) {
			var enabledSelector = '[name*="[fields][' + def.key + '][enabled]"]';
			var enabled = def.alwaysEnabled || $form.find(enabledSelector).is(':checked');

			if (!enabled) {
				return;
			}

			var labelSelector = '[name*="[fields][' + def.key + '][label]"]';
			var label = $.trim($form.find(labelSelector).val() || def.defaultLabel || '');
			var requiredSelector = '[name*="[fields][' + def.key + '][required]"]';
			var required = def.alwaysRequired || $form.find(requiredSelector).is(':checked');

			fields.push({
				label: label,
				required: required,
				input: def.input || 'text',
			});
		});

		$form.find('.art-lms-custom-field-row').each(function () {
			var $row = $(this);
			var label = $.trim($row.find('input[name*="[label]"]').val());

			if (!label) {
				return;
			}

			if (!$row.find('input[name*="[enabled]"]').is(':checked')) {
				return;
			}

			fields.push({
				label: label,
				required: $row.find('input[name*="[required]"]').is(':checked'),
				input: 'text',
			});
		});

		return fields;
	}

	function formatConsentLabel(text, linkText, pageId) {
		text = (text || '').replace(/\{link\}/g, '').replace(/\s+$/, '');
		linkText = linkText || '';
		pageId = parseInt(pageId, 10) || 0;

		var html = escapeHtml(text);

		if (linkText) {
			if (html) {
				html += ' ';
			}

			if (pageId) {
				html += '<a href="#" tabindex="-1" onclick="return false;">' + escapeHtml(linkText) + '</a>';
			} else {
				html += escapeHtml(linkText);
			}
		}

		return html;
	}

	function collectCheckoutPreviewConsents($form) {
		var consents = [];

		$form.find('.art-lms-checkout-consents-table .art-lms-consent-row').each(function () {
			var $row = $(this);

			if (!$row.find('input[name*="[enabled]"]').is(':checked')) {
				return;
			}

			var text = $.trim($row.find('.art-lms-consent-text').val().replace(/\{link\}/g, '').replace(/\s+$/, ''));
			var linkText = $.trim($row.find('.art-lms-consent-link-text').val());

			if (!text && !linkText) {
				return;
			}

			consents.push({
				text: text,
				linkText: $.trim($row.find('.art-lms-consent-link-text').val()),
				pageId: $row.find('select[name*="[page_id]"]').val(),
				required: $row.find('input[name*="[required]"]').is(':checked'),
			});
		});

		return {
			title: '',
			items: consents,
		};
	}

	function renderCheckoutPreviewConsents(consents, idPrefix) {
		idPrefix = idPrefix || 'art-lms-preview';

		if (!consents.items.length) {
			return '';
		}

		var html = '<div class="art-lms-checkout-form__consents">';

		if (consents.title) {
			html += '<p class="art-lms-checkout-form__consents-title">' + escapeHtml(consents.title) + '</p>';
		}

		consents.items.forEach(function (consent, index) {
			var inputId = idPrefix + '-consent-' + index;
			var labelHtml = formatConsentLabel(consent.text, consent.linkText, consent.pageId);

			html += '<p class="art-lms-checkout-form__consent">';
			html += '<label for="' + inputId + '">';
			html += '<input type="checkbox" id="' + inputId + '" disabled>';
			html += '<span>' + labelHtml + '</span>';
			html += '</label></p>';
		});

		html += '</div>';

		return html;
	}

	function renderCheckoutPreviewField(field, index, idPrefix) {
		idPrefix = idPrefix || 'art-lms-preview';
		var requiredMark = field.required ? '<span class="art-lms-required">*</span>' : '';
		var inputId = idPrefix + '-field-' + index;

		return (
			'<p class="art-lms-checkout-form__field">' +
				'<label for="' + inputId + '">' + escapeHtml(field.label) + ' ' + requiredMark + '</label>' +
				'<input type="' + escapeHtml(field.input) + '" id="' + inputId + '" disabled>' +
			'</p>'
		);
	}

	function buildCheckoutPreviewHtml(options) {
		var fields = options.fields || [];
		var consents = options.consents || { title: '', items: [] };
		var settings = options.design || {};
		var strings = options.strings || {};
		var idPrefix = options.idPrefix || 'art-lms-preview';
		var showHint = !!options.showHint;
		var showChrome = settings.template === 'with_theme';
		var cssVars = '--art-lms-checkout-page-bg:' + settings.pageBackgroundColor + ';--art-lms-checkout-form-bg:' + settings.formBackgroundColor + ';--art-lms-button-bg:' + settings.buttonColor + ';--art-lms-button-color:' + settings.buttonTextColor + ';--art-lms-checkout-form-width:' + settings.formMaxWidth + 'px;--art-lms-checkout-form-padding:' + settings.formPadding + 'px;--art-lms-checkout-form-radius:' + settings.formBorderRadius + 'px;';
		var formStyle = 'max-width:' + settings.formMaxWidth + 'px;padding:' + settings.formPadding + 'px;border-radius:' + settings.formBorderRadius + 'px;';
		var html = '<div class="art-lms-checkout-design-preview-frame" data-template="' + escapeHtml(settings.template) + '" style="' + cssVars + '">';

		if (showChrome) {
			html += '<div class="art-lms-checkout-design-preview-chrome art-lms-checkout-design-preview-chrome--header">' + escapeHtml(strings.header || '') + '</div>';
		}

		html += '<div class="art-lms-checkout-design-preview-canvas">';
		html += '<div class="art-lms-checkout art-lms-checkout--preview" style="' + formStyle + '">';
		html += '<h1>' + escapeHtml(strings.title || '') + '</h1>';
		html += '<p class="art-lms-checkout__summary">';
		html += '<strong>' + escapeHtml(strings.productTitle || '') + '</strong>';
		html += '<span>' + escapeHtml(strings.productPrice || '') + '</span>';
		html += '</p>';

		if (!fields.length && !consents.items.length) {
			html += '<p class="art-lms-notice art-lms-notice--warning">' + escapeHtml(strings.empty || '') + '</p>';
		} else {
			html += '<form class="art-lms-checkout-form" onsubmit="return false;">';

			fields.forEach(function (field, index) {
				html += renderCheckoutPreviewField(field, index, idPrefix);
			});

			html += renderCheckoutPreviewConsents(consents, idPrefix);

			html += '<p class="art-lms-checkout-form__actions art-lms-checkout-form__actions--align-' + escapeHtml(settings.buttonAlign) + '"><button type="button" class="art-lms-button art-lms-button--size-' + escapeHtml(settings.buttonSize) + '" disabled style="background:' + escapeHtml(settings.buttonColor) + ';color:' + escapeHtml(settings.buttonTextColor) + '">' + escapeHtml(settings.buttonText || strings.pay || '') + '</button></p>';
			html += '<div class="art-lms-checkout-form__feedback" hidden aria-live="polite"></div>';
			html += '</form>';
		}

		html += '</div>';
		html += '</div>';

		if (showChrome) {
			html += '<div class="art-lms-checkout-design-preview-chrome art-lms-checkout-design-preview-chrome--footer">' + escapeHtml(strings.footer || '') + '</div>';
		}

		html += '</div>';

		if (showHint && strings.hint) {
			html += '<p class="description art-lms-checkout-preview__hint">' + escapeHtml(strings.hint) + '</p>';
		}

		return html;
	}

	function updateCheckoutPreview() {
		var $preview = $('#art-lms-checkout-preview');
		var $form = $('.art-lms-checkout-settings-form');
		var config = window.artLmsCheckoutPreview || {};

		if (!$preview.length || !$form.length) {
			return;
		}

		$preview.html(
			buildCheckoutPreviewHtml({
				fields: collectCheckoutPreviewFields($form),
				consents: collectCheckoutPreviewConsents($form),
				design: config.design || {},
				strings: config.strings || previewStrings,
				idPrefix: 'art-lms-preview',
				showHint: true,
			})
		);
	}

	function getNextCustomFieldIndex($root) {
		var maxIndex = -1;

		$root.find('.art-lms-custom-field-row').each(function () {
			var name = $(this).find('input[name*="[custom_fields]["]').first().attr('name') || '';
			var match = name.match(/\[custom_fields\]\[(\d+)\]/);

			if (match) {
				maxIndex = Math.max(maxIndex, parseInt(match[1], 10));
			}
		});

		return maxIndex + 1;
	}

	function buildCustomFieldRow($root) {
		var template = $('#tmpl-art-lms-custom-field-row').html();

		if (!template) {
			return '';
		}

		return template
			.replace(/\{\{option\}\}/g, $root.data('option') || '')
			.replace(/\{\{index\}\}/g, String(getNextCustomFieldIndex($root)))
			.replace(/\{\{id\}\}/g, 'custom_' + Date.now());
	}

	function initCheckoutCustomFields() {
		var $root = $('.art-lms-checkout-custom-fields');

		if (!$root.length) {
			return;
		}

		$root.on('click', '.art-lms-add-custom-field', function () {
			var row = buildCustomFieldRow($root);

			if (!row) {
				return;
			}

			$root.find('tbody').append(row);
			$root.find('tbody tr:last input[type="text"]').trigger('focus');
			updateCheckoutPreview();
		});

		$root.on('click', '.art-lms-remove-custom-field', function () {
			$(this).closest('.art-lms-custom-field-row').remove();
			updateCheckoutPreview();
		});
	}

	function getNextCustomConsentIndex($root) {
		var maxIndex = -1;

		$root.find('.art-lms-custom-consent-row').each(function () {
			var name = $(this).find('input[name*="[custom_consents]["]').first().attr('name') || '';
			var match = name.match(/\[custom_consents\]\[(\d+)\]/);

			if (match) {
				maxIndex = Math.max(maxIndex, parseInt(match[1], 10));
			}
		});

		return maxIndex + 1;
	}

	function cloneConsentPageSelect($root, index) {
		var $source = $root.find('.art-lms-consent-row').first().find('select[name*="[page_id]"]');

		if (!$source.length) {
			return null;
		}

		var option = $root.data('option') || '';
		var $select = $source.clone();

		$select.attr({
			name: option + '[custom_consents][' + index + '][page_id]',
			id: 'checkout_custom_consent_page_' + index,
		}).val('0');

		return $select;
	}

	function buildCustomConsentRow($root) {
		var template = $('#tmpl-art-lms-custom-consent-row').html();

		if (!template) {
			return null;
		}

		var index = getNextCustomConsentIndex($root);
		var option = $root.data('option') || '';
		var $row = $(template
			.replace(/\{\{option\}\}/g, option)
			.replace(/\{\{index\}\}/g, String(index))
			.replace(/\{\{id\}\}/g, 'custom_' + Date.now())
		);
		var $select = cloneConsentPageSelect($root, index);

		if ($select) {
			$row.find('.art-lms-consent-page-cell').append($select);
		}

		return $row;
	}

	function initCheckoutCustomConsents() {
		var $root = $('.art-lms-checkout-consents');

		if (!$root.length) {
			return;
		}

		$root.on('click', '.art-lms-add-custom-consent', function () {
			var $row = buildCustomConsentRow($root);

			if (!$row || !$row.length) {
				return;
			}

			$root.find('.art-lms-checkout-consents-table tbody').append($row);
			$row.find('.art-lms-consent-link-text').trigger('focus');
			updateCheckoutPreview();
		});

		$root.on('click', '.art-lms-remove-custom-consent', function () {
			$(this).closest('.art-lms-custom-consent-row').remove();
			updateCheckoutPreview();
		});
	}

	function initCheckoutPreview() {
		var $form = $('.art-lms-checkout-settings-form');
		var config = window.artLmsCheckoutPreview || {};
		var messageDefaults = config.messageDefaults || {};

		if (!$form.length || !$('#art-lms-checkout-preview').length) {
			return;
		}

		$form.on('input change', 'input, select, textarea', function () {
			updateCheckoutPreview();
		});

		$form.on('click', '.art-lms-checkout-message-reset', function (event) {
			event.preventDefault();

			var $button = $(this);
			var targetId = $button.attr('data-target');
			var resetKey = $button.attr('data-reset-key');
			var defaultValue = resetKey ? messageDefaults[resetKey] : '';

			if (!targetId || defaultValue === undefined) {
				return;
			}

			$('#' + targetId).val(defaultValue);
		});

		updateCheckoutPreview();
	}

	function collectCheckoutDesignPreviewSettings($form) {
		var config = window.artLmsCheckoutDesignPreview || {};
		var defaults = config.defaults || {};

		return {
			template: $form.find('input[name*="[design][template]"]:checked').val() || 'standalone',
			pageBackgroundColor: $form.find('input[name*="[design][page_background_color]"]').val() || '#f1f5f9',
			formBackgroundColor: $form.find('input[name*="[design][form_background_color]"]').val() || '#ffffff',
			buttonColor: $form.find('input[name*="[design][button_color]"]').val() || '#2563eb',
			buttonTextColor: $form.find('input[name*="[design][button_text_color]"]').val() || '#ffffff',
			buttonSize: $form.find('select[name*="[design][button_size]"]').val() || 'medium',
			buttonAlign: $form.find('select[name*="[design][button_align]"]').val() || 'center',
			buttonText: $.trim($form.find('input[name*="[design][button_text]"]').val()) || defaults.button_text || 'Оплатить',
			formMaxWidth: parseInt($form.find('input[name*="[design][form_max_width]"]').val(), 10) || 640,
			formPadding: parseInt($form.find('input[name*="[design][form_padding]"]').val(), 10) || 20,
			formBorderRadius: parseInt($form.find('input[name*="[design][form_border_radius]"]').val(), 10) || 8,
		};
	}

	function updateCheckoutDesignPreview() {
		var $preview = $('#art-lms-checkout-design-preview');
		var $form = $('.art-lms-checkout-design-settings-form');
		var config = window.artLmsCheckoutDesignPreview || {};

		if (!$preview.length || !$form.length) {
			return;
		}

		$preview.html(
			buildCheckoutPreviewHtml({
				fields: config.fields || [],
				consents: config.consents || { title: '', items: [] },
				design: collectCheckoutDesignPreviewSettings($form),
				strings: config.strings || {},
				idPrefix: 'art-lms-design-preview',
				showHint: false,
			})
		);
	}

	function initCheckoutDesignPreview() {
		var $form = $('.art-lms-checkout-design-settings-form');
		var config = window.artLmsCheckoutDesignPreview || {};
		var defaults = config.defaults || {};

		if (!$form.length || !$('#art-lms-checkout-design-preview').length) {
			return;
		}

		$form.on('input change', 'input, select', function () {
			updateCheckoutDesignPreview();
		});

		$form.on('click', '.art-lms-checkout-design-reset-color', function (event) {
			event.preventDefault();

			var $button = $(this);
			var colorKey = $button.attr('data-color-key');
			var defaultColor = $button.attr('data-default-color') || (defaults[colorKey] || '');

			if (!colorKey || !defaultColor) {
				return;
			}

			$form.find('input[name*="[design][' + colorKey + ']"]').val(defaultColor);
			updateCheckoutDesignPreview();
		});

		$form.on('click', '.art-lms-checkout-design-reset-dimension', function (event) {
			event.preventDefault();

			var $button = $(this);
			var dimensionKey = $button.attr('data-dimension-key');
			var defaultValue = $button.attr('data-default-value');

			if (!dimensionKey || defaultValue === undefined || defaultValue === '') {
				return;
			}

			$form.find('input[name*="[design][' + dimensionKey + ']"]').val(defaultValue);
			updateCheckoutDesignPreview();
		});

		updateCheckoutDesignPreview();
	}

	function initGeneralPageSettings() {
		var config = window.artLmsGeneralSettings || {};
		var strings = config.strings || {};
		var pageUrls = config.pageUrls || {};
		var $form = $('.art-lms-settings-general-page');
		var pageFieldIds = ['account_page_id', 'success_page_id'];

		if (!$form.length) {
			return;
		}

		function updatePageViewLink(fieldId) {
			var $select = $('#' + fieldId);
			var $link = $('#' + fieldId + '_view');

			if (!$select.length || !$link.length) {
				return;
			}

			var pageId = parseInt($select.val(), 10) || 0;
			var url = pageUrls[pageId] || pageUrls[String(pageId)] || '';

			if (pageId && url) {
				$link.attr('href', url).prop('hidden', false);
			} else {
				$link.attr('href', '').prop('hidden', true);
			}
		}

		pageFieldIds.forEach(function (fieldId) {
			updatePageViewLink(fieldId);
			$form.on('change', '#' + fieldId, function () {
				updatePageViewLink(fieldId);
			});
		});

		if (!config.ajaxUrl) {
			return;
		}

		function setPageStatus($picker, message, type) {
			var pageType = $picker.data('pageType');
			var fieldMap = {
				account: 'account_page_id',
				success: 'success_page_id',
			};
			var fieldId = fieldMap[pageType] || '';
			var $status = fieldId ? $('#' + fieldId + '_status') : $();

			if (!$status.length) {
				return;
			}

			$status
				.removeClass('is-success is-error')
				.toggleClass(type ? 'is-' + type : '', !!type)
				.html(message || '');
		}

		function ensureSelectOption($select, pageId, pageTitle) {
			var value = String(pageId);
			var $existing = $select.find('option[value="' + value + '"]');

			if ($existing.length) {
				$existing.text(pageTitle);
				return;
			}

			$select.append(
				$('<option>', {
					value: value,
					text: pageTitle,
				})
			);
		}

		$form.on('click', '.art-lms-page-picker__create', function () {
			var $button = $(this);
			var $picker = $button.closest('.art-lms-page-picker');
			var $select = $picker.find('.art-lms-page-picker__select');
			var pageType = $button.data('pageType');
			var originalText = $button.text();

			if ($button.prop('disabled')) {
				return;
			}

			$button.prop('disabled', true).text(strings.creating || '...');
			setPageStatus($picker, '', '');

			$.post(config.ajaxUrl, {
				action: 'art_lms_create_settings_page',
				nonce: config.nonce,
				page_type: pageType,
			})
				.done(function (response) {
					if (!response || !response.success || !response.data) {
						setPageStatus($picker, strings.createFailed || '', 'error');
						return;
					}

					var data = response.data;
					ensureSelectOption($select, data.page_id, data.page_title);
					$select.val(String(data.page_id));

					if (data.view_url) {
						pageUrls[data.page_id] = data.view_url;
					}

					updatePageViewLink($select.attr('id'));

					var message = escapeHtml(data.message || '');

					if (data.edit_url) {
						message += ' <a href="' + escapeHtml(data.edit_url) + '" target="_blank" rel="noopener noreferrer">' +
							escapeHtml(strings.editPage || '') + '</a>';
					}

					setPageStatus($picker, message, 'success');
				})
				.fail(function (xhr) {
					var message = strings.createFailed || '';
					var payload = xhr.responseJSON;

					if (payload && payload.data && payload.data.message) {
						message = payload.data.message;
					}

					setPageStatus($picker, message, 'error');
				})
				.always(function () {
					$button.prop('disabled', false).text(originalText);
				});
		});
	}

	function initEmailSettings() {
		var $form = $('.art-lms-email-settings-form');
		var config = window.artLmsEmailSettings || {};
		var strings = config.strings || {};
		var defaults = config.defaults || {};

		if (!$form.length) {
			return;
		}

		var emailSections = {
			purchase: {
				subject: '#art_lms_purchase_email_subject',
				body: '#art_lms_purchase_email_body',
				preview: '#art-lms-purchase-email-preview',
				feedback: '#art-lms-purchase-email-feedback',
			},
			admin_payment: {
				subject: '#art_lms_admin_payment_email_subject',
				body: '#art_lms_admin_payment_email_body',
				recipient: '#art_lms_admin_payment_recipient',
				preview: '#art-lms-admin-payment-email-preview',
				feedback: '#art-lms-admin-payment-email-feedback',
			},
		};

		function getEmailSectionValues(type) {
			var section = emailSections[type];

			if (!section) {
				return { subject: '', body: '', recipient: '' };
			}

			return {
				subject: $.trim($(section.subject).val() || ''),
				body: $.trim($(section.body).val() || ''),
				recipient: section.recipient ? $.trim($(section.recipient).val() || '') : '',
			};
		}

		function setFeedback(type, message, isError) {
			var section = emailSections[type];

			if (!section) {
				return;
			}

			$(section.feedback)
				.text(message || '')
				.toggleClass('is-error', !!isError);
		}

		$form.on('click', '.art-lms-email-reset', function (event) {
			event.preventDefault();

			var $button = $(this);
			var targetId = $button.attr('data-target');
			var resetKey = $button.attr('data-reset-key');
			var group = $button.attr('data-defaults-group');
			var groupDefaults = group ? defaults[group] : null;
			var defaultValue = groupDefaults && resetKey ? groupDefaults[resetKey] : '';

			if (!targetId || defaultValue === undefined) {
				return;
			}

			$('#' + targetId).val(defaultValue);
			Object.keys(emailSections).forEach(function (type) {
				setFeedback(type, '');
			});
		});

		$form.on('click', '.art-lms-email-preview-button', function () {
			var type = $(this).attr('data-email-type') || 'purchase';
			var section = emailSections[type];
			var values = getEmailSectionValues(type);

			if (!section) {
				return;
			}

			setFeedback(type, '');

			$.post(config.ajaxUrl, {
				action: 'art_lms_preview_purchase_email',
				nonce: config.nonce,
				email_type: type,
				subject: values.subject,
				body: values.body,
			})
				.done(function (response) {
					if (!response || !response.success || !response.data) {
						setFeedback(type, strings.previewFailed || '', true);
						return;
					}

					$(section.preview).find('.art-lms-email-preview-subject').text(response.data.subject || '');
					$(section.preview).find('.art-lms-email-preview-body').text(response.data.body || '');
					$(section.preview).prop('hidden', false);
				})
				.fail(function () {
					setFeedback(type, strings.previewFailed || '', true);
				});
		});

		$form.on('click', '.art-lms-email-test-button', function () {
			var type = $(this).attr('data-email-type') || 'purchase';
			var values = getEmailSectionValues(type);
			var $button = $(this);
			var originalText = $button.text();

			setFeedback(type, '');
			$button.prop('disabled', true).text(strings.sending || '');

			$.post(config.ajaxUrl, {
				action: 'art_lms_send_test_purchase_email',
				nonce: config.nonce,
				email_type: type,
				subject: values.subject,
				body: values.body,
				recipient: values.recipient,
			})
				.done(function (response) {
					if (!response || !response.success) {
						var message = response && response.data && response.data.message
							? response.data.message
							: (strings.sendFailed || '');
						setFeedback(type, message, true);
						return;
					}

					setFeedback(type, response.data.message || '', false);
				})
				.fail(function (xhr) {
					var message = strings.sendFailed || '';
					var payload = xhr.responseJSON;

					if (payload && payload.data && payload.data.message) {
						message = payload.data.message;
					}

					setFeedback(type, message, true);
				})
				.always(function () {
					$button.prop('disabled', false).text(originalText);
				});
		});
	}

	function syncGatewayStatusControl($control) {
		var $input = $control.find('.art-lms-gateway-status-switch__input');
		var isEnabled = $input.is(':checked');
		var enabledLabel = $control.data('enabled-label') || 'Включен';
		var disabledLabel = $control.data('disabled-label') || 'Выключен';
		var label = isEnabled ? enabledLabel : disabledLabel;

		$control.toggleClass('is-enabled', isEnabled);
		$control.toggleClass('is-disabled', !isEnabled);
		$control.find('.art-lms-gateway-status-control__label').text(label);
		$control.find('.screen-reader-text').text(label);
	}

	function initGatewayStatusControls() {
		$(document).on('change', '.art-lms-gateway-status-switch__input', function () {
			syncGatewayStatusControl($(this).closest('.art-lms-gateway-status-control'));
		});
	}

	function syncYookassaReceiptsPanelStatus() {
		var $checkbox = $('#yookassa_receipts_enabled');
		var $status = $('#art-lms-yookassa-receipts-status');

		if (!$checkbox.length || !$status.length) {
			return;
		}

		var isEnabled = $checkbox.is(':checked');
		var enabledLabel = $status.data('enabled-label') || 'Включены';
		var disabledLabel = $status.data('disabled-label') || 'Выключены';

		$status
			.toggleClass('is-enabled', isEnabled)
			.toggleClass('is-disabled', !isEnabled)
			.text(isEnabled ? enabledLabel : disabledLabel);
	}

	function initYookassaReceiptsPanel() {
		syncYookassaReceiptsPanelStatus();

		$(document).on('change', '#yookassa_receipts_enabled', syncYookassaReceiptsPanelStatus);
	}

	function initPaymentGatewayList() {
		var $list = $('#art-lms-payment-gateway-list');

		if (!$list.length || !$.fn.sortable) {
			return;
		}

		$list.sortable({
			handle: '.art-lms-payment-gateway-list__handle',
			axis: 'y',
			containment: 'parent',
			tolerance: 'pointer',
		});
	}

	function initPaymentStatusMessages() {
		var $form = $('.art-lms-checkout-confirmation-settings-form');
		var config = window.artLmsPaymentStatusSettings || {};
		var messageDefaults = config.messageDefaults || {};

		if (!$form.length) {
			return;
		}

		$form.find('.art-lms-gateway-status-control').each(function () {
			syncGatewayStatusControl($(this));
		});

		$form.on('change', '.art-lms-gateway-status-switch__input', function () {
			syncGatewayStatusControl($(this).closest('.art-lms-gateway-status-control'));
		});

		$form.on('click', '.art-lms-payment-status-message-reset', function (event) {
			event.preventDefault();

			var $button = $(this);
			var targetId = $button.attr('data-target');
			var resetKey = $button.attr('data-reset-key');
			var defaultValue = resetKey ? messageDefaults[resetKey] : '';

			if (!targetId || defaultValue === undefined) {
				return;
			}

			$('#' + targetId).val(defaultValue);
		});
	}

	$(document).ready(function () {
		initGeneralPageSettings();
		initGatewayStatusControls();
		initYookassaReceiptsPanel();
		initPaymentGatewayList();

		initCheckoutCustomFields();
		initCheckoutCustomConsents();
		initCheckoutPreview();
		initCheckoutDesignPreview();
		initPaymentStatusMessages();
		initEmailSettings();
	});
})(jQuery);
