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

	function getCheckoutDesignTextCssVars(settings) {
		settings = settings || {};

		return [
			'--art-lms-checkout-title-font-size:' + (settings.titleFontSize || 24) + 'px',
			'--art-lms-checkout-product-name-font-size:' + (settings.productNameFontSize || 16) + 'px',
			'--art-lms-checkout-compare-price-font-size:' + (settings.comparePriceFontSize || 16) + 'px',
			'--art-lms-checkout-price-font-size:' + (settings.priceFontSize || 16) + 'px',
			'--art-lms-checkout-field-label-font-size:' + (settings.fieldLabelFontSize || 16) + 'px',
			'--art-lms-checkout-field-input-font-size:' + (settings.fieldInputFontSize || 16) + 'px',
			'--art-lms-checkout-consent-checkbox-size:' + (settings.consentCheckboxSize || 16) + 'px',
			'--art-lms-checkout-consent-font-size:' + (settings.consentFontSize || 16) + 'px',
		].join(';');
	}

	function buildCheckoutPreviewHtml(options) {
		var fields = options.fields || [];
		var consents = options.consents || { title: '', items: [] };
		var settings = options.design || {};
		var strings = options.strings || {};
		var idPrefix = options.idPrefix || 'art-lms-preview';
		var showHint = !!options.showHint;
		var showChrome = settings.template === 'with_theme';
		var cssVars = '--art-lms-checkout-page-bg:' + settings.pageBackgroundColor + ';--art-lms-checkout-form-bg:' + settings.formBackgroundColor + ';--art-lms-button-bg:' + settings.buttonColor + ';--art-lms-button-color:' + settings.buttonTextColor + ';--art-lms-checkout-form-width:' + settings.formMaxWidth + 'px;--art-lms-checkout-form-padding:' + settings.formPadding + 'px;--art-lms-checkout-form-radius:' + settings.formBorderRadius + 'px;' + getCheckoutDesignTextCssVars(settings);
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
		html += '<span class="art-lms-checkout__prices">';
		if (strings.productComparePrice) {
			html += '<span class="art-lms-checkout__compare">' + escapeHtml(strings.productComparePrice) + '</span>';
		}
		html += '<span class="art-lms-checkout__price">' + escapeHtml(strings.productPrice || '') + '</span>';
		html += '</span>';
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

	function collectCheckoutPreviewStrings($form, config) {
		var strings = $.extend({}, config.strings || previewStrings);
		var $titleInput = $form.find('input[name*="[form_title]"]');

		if ($titleInput.length) {
			strings.title = $.trim($titleInput.val()) || strings.title || '';
		}

		return strings;
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
				strings: collectCheckoutPreviewStrings($form, config),
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

	function parseCheckoutDesignNumber($form, fieldName, fallback) {
		var value = parseInt($form.find('input[name*="[design][' + fieldName + ']"]').val(), 10);

		return isNaN(value) ? fallback : value;
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
			formMaxWidth: parseCheckoutDesignNumber($form, 'form_max_width', defaults.form_max_width || 450),
			formPadding: parseCheckoutDesignNumber($form, 'form_padding', defaults.form_padding || 20),
			formBorderRadius: parseCheckoutDesignNumber($form, 'form_border_radius', defaults.form_border_radius || 8),
			titleFontSize: parseCheckoutDesignNumber($form, 'title_font_size', defaults.title_font_size || 24),
			productNameFontSize: parseCheckoutDesignNumber($form, 'product_name_font_size', defaults.product_name_font_size || 16),
			comparePriceFontSize: parseCheckoutDesignNumber($form, 'compare_price_font_size', defaults.compare_price_font_size || 16),
			priceFontSize: parseCheckoutDesignNumber($form, 'price_font_size', defaults.price_font_size || 16),
			fieldLabelFontSize: parseCheckoutDesignNumber($form, 'field_label_font_size', defaults.field_label_font_size || 16),
			fieldInputFontSize: parseCheckoutDesignNumber($form, 'field_input_font_size', defaults.field_input_font_size || 16),
			consentCheckboxSize: parseCheckoutDesignNumber($form, 'consent_checkbox_size', defaults.consent_checkbox_size || 16),
			consentFontSize: parseCheckoutDesignNumber($form, 'consent_font_size', defaults.consent_font_size || 16),
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

		function resolvePageUrl(pageId) {
			var url = pageUrls[pageId] || pageUrls[String(pageId)] || '';

			if (!url && pageId && config.homeUrl) {
				url = String(config.homeUrl).replace(/\/$/, '') + '/?page_id=' + pageId;
			}

			return url;
		}

		function setPageViewLinkState($link, url) {
			if (!$link.length) {
				return;
			}

			if (url) {
				$link
					.attr('href', url)
					.attr('title', url)
					.prop('hidden', false)
					.removeClass('hidden')
					.removeAttr('aria-hidden');
			} else {
				$link
					.attr('href', '#')
					.removeAttr('title')
					.prop('hidden', true)
					.addClass('hidden')
					.attr('aria-hidden', 'true');
			}
		}

		function updatePageViewLink(fieldId) {
			var $select = $('#' + fieldId);
			var $link = $('#' + fieldId + '_view');

			if (!$select.length || !$link.length) {
				return;
			}

			var pageId = parseInt($select.val(), 10) || 0;
			var url = pageId ? resolvePageUrl(pageId) : '';

			setPageViewLinkState($link, url);
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
						pageUrls[String(data.page_id)] = data.view_url;
					}

					updatePageViewLink($select.attr('id'));

					var message = escapeHtml(data.message || '');

					if (data.view_url) {
						message += ' <a href="' + escapeHtml(data.view_url) + '" target="_blank" rel="noopener noreferrer">' +
							escapeHtml(strings.viewPage || 'Перейти') + '</a>';
					}

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

	function initLoginPageSettings() {
		var $form = $('.art-lms-login-settings-form');

		if (!$form.length) {
			return;
		}

		var loginConfig = window.artLmsLoginSettings || {};
		var loginStrings = loginConfig.strings || {};
		var designDefaults = loginConfig.defaults || {};
		var formDefaults = loginConfig.formDefaults || {};
		var buttonDefaults = loginConfig.buttonDefaults || {};
		var $enabled = $('#art_lms_login_enabled');
		var $slug = $('#art_lms_login_slug');
		var $preview = $('#art-lms-login-preview');
		var $previewCanvas = $('#art-lms-login-preview-canvas');
		var $previewForm = $('#art-lms-login-preview-form');
		var $previewUrl = $('#art-lms-login-preview-url');
		var $previewUrlCopy = $('#art-lms-login-preview-url-copy');
		var $urlPanel = $('.art-lms-login-settings-url-panel');
		var $formPanel = $('.art-lms-login-settings-form-panel');
		var $buttonPanel = $('.art-lms-login-settings-button-panel');
		var $designPanel = $('.art-lms-login-settings-design-panel');
		var $buttonSize = $('#art_lms_login_button_size');
		var $buttonAlign = $('#art_lms_login_button_align');
		var $buttonText = $('#art_lms_login_button_text');
		var $buttonFontSize = $('#art_lms_login_button_font_size');
		var $buttonBorderRadius = $('#art_lms_login_button_border_radius');
		var $buttonCustomPaddingY = $('#art_lms_login_button_custom_padding_y');
		var $buttonCustomPaddingX = $('#art_lms_login_button_custom_padding_x');
		var $buttonCustomSizeRow = $('.art-lms-login-button-custom-size-row');
		var $titleEnabled = $('#art_lms_login_title_enabled');
		var $titleText = $('#art_lms_login_title_text');
		var $subtitleEnabled = $('#art_lms_login_subtitle_enabled');
		var $subtitleText = $('#art_lms_login_subtitle_text');
		var $lostPasswordEnabled = $('#art_lms_login_lost_password_enabled');
		var $lostPasswordText = $('#art_lms_login_lost_password_text');
		var $usernameLabel = $('#art_lms_login_username_label');
		var $passwordLabel = $('#art_lms_login_password_label');
		var $rememberEnabled = $('#art_lms_login_remember_enabled');
		var $rememberLabel = $('#art_lms_login_remember_label');
		var $previewTitle = $('#art-lms-login-preview-title');
		var $previewSubtitle = $('#art-lms-login-preview-subtitle');
		var $previewLost = $('#art-lms-login-preview-lost');
		var $previewUsernameLabel = $('#art-lms-login-preview-username-label');
		var $designDimensionInputs = $form.find('.art-lms-login-design-dimension-input');
		var $designDimensionResetButtons = $form.find('.art-lms-login-design-reset-dimension');
		var $previewPasswordLabel = $('#art-lms-login-preview-password-label');
		var $previewRemember = $('#art-lms-login-preview-remember');
		var $previewRememberLabel = $('#art-lms-login-preview-remember-label');
		var $previewSubmit = $('#art-lms-login-preview-submit');
		var $designHexInputs = $form.find('.art-lms-login-design-color-hex');
		var $designPickers = $form.find('.art-lms-login-design-color-picker');
		var $designResetButtons = $form.find('.art-lms-login-design-reset-color');
		var homeUrl = String($preview.data('homeUrl') || '');
		var disabledText = loginStrings.disabled || 'Своя форма входа выключена';
		var copyResetTimer = null;

		function sanitizeSlug(value) {
			return String(value || '')
				.toLowerCase()
				.replace(/[^a-z0-9-]+/g, '-')
				.replace(/^-+|-+$/g, '');
		}

		function buildUrl(slug) {
			if (!homeUrl || !slug) {
				return '';
			}

			return homeUrl + '/' + slug + '/';
		}

		function isCopyableUrl(value) {
			return !!value && value !== disabledText && /^https?:\/\//i.test(value);
		}

		function fallbackCopyLoginUrl(text, deferred) {
			var textarea = document.createElement('textarea');

			textarea.value = text;
			textarea.setAttribute('readonly', '');
			textarea.style.position = 'fixed';
			textarea.style.top = '0';
			textarea.style.left = '-9999px';

			document.body.appendChild(textarea);
			textarea.focus();
			textarea.select();
			textarea.setSelectionRange(0, text.length);

			try {
				if (document.execCommand('copy')) {
					deferred.resolve();
				} else {
					deferred.reject();
				}
			} catch (error) {
				deferred.reject(error);
			}

			document.body.removeChild(textarea);
		}

		function copyLoginUrl(text) {
			var deferred = $.Deferred();

			if (!text) {
				return deferred.reject().promise();
			}

			if (navigator.clipboard && window.isSecureContext) {
				navigator.clipboard.writeText(text).then(function () {
					deferred.resolve();
				}).catch(function () {
					fallbackCopyLoginUrl(text, deferred);
				});

				return deferred.promise();
			}

			fallbackCopyLoginUrl(text, deferred);
			return deferred.promise();
		}

		function resetCopyButtonState() {
			if (copyResetTimer) {
				window.clearTimeout(copyResetTimer);
				copyResetTimer = null;
			}

			$previewUrlCopy.removeClass('is-copied');
			$previewUrlCopy.attr('title', loginStrings.copy || 'Скопировать');
		}

		function markCopyButtonCopied() {
			resetCopyButtonState();
			$previewUrlCopy.addClass('is-copied');
			$previewUrlCopy.attr('title', loginStrings.copied || 'Скопировано!');

			copyResetTimer = window.setTimeout(function () {
				resetCopyButtonState();
			}, 1800);
		}

		function normalizeHexColor(value) {
			var color = String(value || '').trim();

			if (!color) {
				return '';
			}

			if (color.charAt(0) !== '#') {
				color = '#' + color;
			}

			if (/^#[0-9a-f]{3}$/i.test(color)) {
				color = '#' + color.charAt(1) + color.charAt(1) + color.charAt(2) + color.charAt(2) + color.charAt(3) + color.charAt(3);
			}

			if (/^#[0-9a-f]{6}$/i.test(color)) {
				return color.toLowerCase();
			}

			return '';
		}

		function getLoginColorValue(colorKey, fallback) {
			var $hex = $form.find('input.art-lms-login-design-color-hex[name*="[design][' + colorKey + ']"]');
			var normalized = normalizeHexColor($hex.val());

			return normalized || fallback || '';
		}

		function syncLoginColorControl($hex, colorValue) {
			var normalized = normalizeHexColor(colorValue);
			var $control = $hex.closest('.art-lms-login-design-color-control');
			var $picker = $control.find('.art-lms-login-design-color-picker');

			if (!normalized) {
				return;
			}

			$hex.val(normalized);

			if ($picker.length) {
				$picker.val(normalized);
			}
		}

		function getLoginDesignDimensionValue(dimensionKey, fallback) {
			var $input = $form.find('input.art-lms-login-design-dimension-input[data-dimension-key="' + dimensionKey + '"]');
			var parsed = parseInt($input.val(), 10);

			if (isNaN(parsed)) {
				return fallback;
			}

			return parsed;
		}

		function getLoginDesignColors() {
			return {
				pageBackgroundColor: getLoginColorValue('page_background_color', designDefaults.page_background_color || '#f1f5f9'),
				formBackgroundColor: getLoginColorValue('form_background_color', designDefaults.form_background_color || '#ffffff'),
				formBorderColor: getLoginColorValue('form_border_color', designDefaults.form_border_color || '#c3c4c7'),
				fieldBorderColor: getLoginColorValue('field_border_color', designDefaults.field_border_color || '#c3c4c7'),
				fieldFocusBorderColor: getLoginColorValue('field_focus_border_color', designDefaults.field_focus_border_color || '#2271b1')
			};
		}

		function updateLoginDesignPreview() {
			var colors = getLoginDesignColors();
			var formMaxWidth = getLoginDesignDimensionValue('form_max_width', designDefaults.form_max_width || 360);
			var formPadding = getLoginDesignDimensionValue('form_padding', designDefaults.form_padding || 24);
			var formBorderRadius = getLoginDesignDimensionValue('form_border_radius', designDefaults.form_border_radius || 8);
			var labelFontSize = getLoginDesignDimensionValue('field_label_font_size', designDefaults.field_label_font_size || 14);
			var inputFontSize = getLoginDesignDimensionValue('field_input_font_size', designDefaults.field_input_font_size || 16);

			$previewCanvas.css('background-color', colors.pageBackgroundColor);
			$previewForm.css({
				backgroundColor: colors.formBackgroundColor,
				borderColor: colors.formBorderColor,
				maxWidth: formMaxWidth + 'px',
				padding: formPadding + 'px',
				borderRadius: formBorderRadius + 'px'
			});

			$previewForm.find('.art-lms-login-preview__field label').css('fontSize', labelFontSize + 'px');
			$previewForm.find('.art-lms-login-preview__input').css({
				borderColor: colors.fieldBorderColor,
				fontSize: inputFontSize + 'px'
			});
			$previewLost.css('color', getLoginButtonColorValue('background_color', buttonDefaults.background_color || '#2271b1'));
		}

		function getLoginFormFieldValue($field, fallback) {
			var value = $.trim($field.val() || '');

			return value || fallback || '';
		}

		function updateLoginFormPreview() {
			var titleOn = $titleEnabled.is(':checked');
			var subtitleOn = $subtitleEnabled.is(':checked');
			var subtitleText = getLoginFormFieldValue($subtitleText, formDefaults.subtitle_text || '');
			var rememberOn = $rememberEnabled.is(':checked');
			var lostOn = $lostPasswordEnabled.is(':checked');

			$previewTitle
				.toggleClass('is-hidden', !titleOn)
				.text(getLoginFormFieldValue($titleText, formDefaults.title_text || 'Вход'));

			$previewSubtitle
				.toggleClass('is-hidden', !subtitleOn || !subtitleText)
				.text(subtitleText);

			$previewUsernameLabel.text(getLoginFormFieldValue($usernameLabel, formDefaults.username_label || 'Email'));
			$previewPasswordLabel.text(getLoginFormFieldValue($passwordLabel, formDefaults.password_label || 'Пароль'));

			$previewRemember.toggleClass('is-hidden', !rememberOn);
			$previewRememberLabel.text(getLoginFormFieldValue($rememberLabel, formDefaults.remember_label || 'Запомнить меня'));

			$previewLost
				.toggleClass('is-hidden', !lostOn)
				.text(getLoginFormFieldValue($lostPasswordText, formDefaults.lost_password_text || 'Забыли пароль?'));
		}

		function getLoginButtonColorValue(colorKey, fallback) {
			var $hex = $form.find('input.art-lms-login-button-color-hex[name*="[button][' + colorKey + ']"]');
			var normalized = normalizeHexColor($hex.val());

			return normalized || fallback || '';
		}

		function syncLoginButtonColorControl($hex, colorValue) {
			var normalized = normalizeHexColor(colorValue);
			var $control = $hex.closest('.art-lms-login-design-color-control');
			var $picker = $control.find('.art-lms-login-button-color-picker');

			if (!normalized) {
				return;
			}

			$hex.val(normalized);

			if ($picker.length) {
				$picker.val(normalized);
			}
		}

		function getLoginButtonWrapperClasses() {
			var size = $buttonSize.val() || 'medium';
			var align = $buttonAlign.val() || 'full';

			return 'art-lms-login--button-size-' + size + ' art-lms-login--button-align-' + align;
		}

		function stripLoginButtonWrapperClasses() {
			var classes = ($previewForm.attr('class') || '').split(/\s+/).filter(function (className) {
				return className.indexOf('art-lms-login--button-size-') !== 0 && className.indexOf('art-lms-login--button-align-') !== 0;
			});

			$previewForm.attr('class', classes.join(' '));
		}

		function syncButtonCustomSizeState() {
			var isCustom = $buttonSize.val() === 'custom';
			var loginOn = $enabled.is(':checked');

			$buttonCustomSizeRow.toggleClass('is-hidden', !isCustom);
			$buttonCustomPaddingY.prop('disabled', !loginOn || !isCustom);
			$buttonCustomPaddingX.prop('disabled', !loginOn || !isCustom);
			$buttonCustomSizeRow.find('.art-lms-login-button-reset-dimension').prop('disabled', !loginOn || !isCustom);
		}

		function updateLoginButtonPreview() {
			var size = $buttonSize.val() || 'medium';
			var fontSize = parseInt($buttonFontSize.val(), 10) || buttonDefaults.font_size || 16;
			var borderRadius = parseInt($buttonBorderRadius.val(), 10) || buttonDefaults.border_radius || 4;
			var paddingY = parseInt($buttonCustomPaddingY.val(), 10) || buttonDefaults.custom_padding_y || 10;
			var paddingX = parseInt($buttonCustomPaddingX.val(), 10) || buttonDefaults.custom_padding_x || 16;
			var backgroundColor = getLoginButtonColorValue('background_color', buttonDefaults.background_color || '#2271b1');
			var textColor = getLoginButtonColorValue('text_color', buttonDefaults.text_color || '#ffffff');

			stripLoginButtonWrapperClasses();
			$previewForm.addClass(getLoginButtonWrapperClasses());

			$previewSubmit.text(getLoginFormFieldValue($buttonText, buttonDefaults.text || 'Войти'));
			$previewSubmit.css({
				backgroundColor: backgroundColor,
				color: textColor,
				fontSize: fontSize + 'px',
				borderRadius: borderRadius + 'px'
			});

			if (size === 'custom') {
				$previewSubmit.css({
					padding: paddingY + 'px ' + paddingX + 'px'
				});
			} else {
				$previewSubmit.css({ padding: '' });
			}

			$previewLost.css('color', backgroundColor);
		}

		function syncFormFieldState() {
			var loginOn = $enabled.is(':checked');

			$titleText.prop('disabled', !loginOn || !$titleEnabled.is(':checked'));
			$subtitleText.prop('disabled', !loginOn || !$subtitleEnabled.is(':checked'));
			$rememberLabel.prop('disabled', !loginOn || !$rememberEnabled.is(':checked'));
			$lostPasswordText.prop('disabled', !loginOn || !$lostPasswordEnabled.is(':checked'));
		}

		function syncState() {
			var enabled = $enabled.is(':checked');
			var url = enabled ? buildUrl(sanitizeSlug($slug.val())) : '';

			$slug.prop('disabled', !enabled);
			$urlPanel.toggleClass('is-disabled', !enabled);
			$formPanel.toggleClass('is-disabled', !enabled);
			$buttonPanel.toggleClass('is-disabled', !enabled);
			$designPanel.toggleClass('is-disabled', !enabled);
			$buttonPanel.find('input, select, button').prop('disabled', !enabled);
			$titleEnabled.prop('disabled', !enabled);
			$usernameLabel.prop('disabled', !enabled);
			$passwordLabel.prop('disabled', !enabled);
			$rememberEnabled.prop('disabled', !enabled);
			$designHexInputs.prop('disabled', !enabled);
			$designPickers.prop('disabled', !enabled);
			$designResetButtons.prop('disabled', !enabled);
			$designDimensionInputs.prop('disabled', !enabled);
			$designDimensionResetButtons.prop('disabled', !enabled);
			$subtitleEnabled.prop('disabled', !enabled);
			$lostPasswordEnabled.prop('disabled', !enabled);
			$preview.toggleClass('is-disabled', !enabled);
			syncFormFieldState();
			syncButtonCustomSizeState();

			if (!enabled || !url) {
				$previewUrl.val(disabledText);
				$previewUrlCopy.prop('disabled', true);
				return;
			}

			$previewUrl.val(url);
			$previewUrlCopy.prop('disabled', false);
		}

		$previewUrl.on('focus click', function () {
			this.select();
		});

		$previewUrlCopy.on('click', function () {
			var text = $.trim($previewUrl.val() || '');

			if (!isCopyableUrl(text)) {
				return;
			}

			copyLoginUrl(text)
				.done(function () {
					markCopyButtonCopied();
				})
				.fail(function () {
					window.alert(loginStrings.copyFailed || 'Не удалось скопировать.');
				});
		});

		$form.on('input', '.art-lms-login-design-color-hex', function () {
			var $hex = $(this);
			var normalized = normalizeHexColor($hex.val());

			if (normalized) {
				syncLoginColorControl($hex, normalized);
				updateLoginDesignPreview();
			}
		});

		$form.on('blur', '.art-lms-login-design-color-hex', function () {
			var $hex = $(this);
			var normalized = normalizeHexColor($hex.val());

			if (normalized) {
				syncLoginColorControl($hex, normalized);
			}

			updateLoginDesignPreview();
		});

		$form.on('input change', '.art-lms-login-design-color-picker', function () {
			var $picker = $(this);
			var $hex = $picker.closest('.art-lms-login-design-color-control').find('.art-lms-login-design-color-hex');

			syncLoginColorControl($hex, $picker.val());
			updateLoginDesignPreview();
		});

		$form.on('click', '.art-lms-login-design-reset-color', function (event) {
			event.preventDefault();

			var $button = $(this);
			var colorKey = $button.attr('data-color-key');
			var defaultColor = $button.attr('data-default-color') || designDefaults[colorKey] || '';
			var $hex = $form.find('input.art-lms-login-design-color-hex[name*="[design][' + colorKey + ']"]');

			if (!colorKey || !defaultColor || $button.prop('disabled') || !$hex.length) {
				return;
			}

			syncLoginColorControl($hex, defaultColor);
			updateLoginDesignPreview();
		});

		$form.on('input change', '#art_lms_login_title_text, #art_lms_login_subtitle_text, #art_lms_login_username_label, #art_lms_login_password_label, #art_lms_login_remember_label, #art_lms_login_lost_password_text', function () {
			updateLoginFormPreview();
		});

		$form.on('input change', '.art-lms-login-design-dimension-input', function () {
			updateLoginDesignPreview();
		});

		$form.on('click', '.art-lms-login-design-reset-dimension', function (event) {
			event.preventDefault();

			var $button = $(this);
			var dimensionKey = $button.attr('data-dimension-key');
			var defaultValue = $button.attr('data-default-value');

			if (!dimensionKey || defaultValue === undefined || defaultValue === '' || $button.prop('disabled')) {
				return;
			}

			$form.find('input.art-lms-login-design-dimension-input[data-dimension-key="' + dimensionKey + '"]').val(defaultValue);
			updateLoginDesignPreview();
		});

		$form.on('input change', '#art_lms_login_button_text, #art_lms_login_button_font_size, #art_lms_login_button_border_radius, #art_lms_login_button_custom_padding_y, #art_lms_login_button_custom_padding_x, #art_lms_login_button_size, #art_lms_login_button_align', function () {
			syncButtonCustomSizeState();
			updateLoginButtonPreview();
		});

		$form.on('input', '.art-lms-login-button-color-hex', function () {
			var $hex = $(this);
			var normalized = normalizeHexColor($hex.val());

			if (normalized) {
				syncLoginButtonColorControl($hex, normalized);
				updateLoginButtonPreview();
			}
		});

		$form.on('blur', '.art-lms-login-button-color-hex', function () {
			var $hex = $(this);
			var normalized = normalizeHexColor($hex.val());

			if (normalized) {
				syncLoginButtonColorControl($hex, normalized);
			}

			updateLoginButtonPreview();
		});

		$form.on('input change', '.art-lms-login-button-color-picker', function () {
			var $picker = $(this);
			var $hex = $picker.closest('.art-lms-login-design-color-control').find('.art-lms-login-button-color-hex');

			syncLoginButtonColorControl($hex, $picker.val());
			updateLoginButtonPreview();
		});

		$form.on('click', '.art-lms-login-button-reset-color', function (event) {
			event.preventDefault();

			var $button = $(this);
			var colorKey = $button.attr('data-color-key');
			var defaultColor = $button.attr('data-default-color') || buttonDefaults[colorKey] || '';
			var $hex = $form.find('input.art-lms-login-button-color-hex[name*="[button][' + colorKey + ']"]');

			if (!colorKey || !defaultColor || $button.prop('disabled') || !$hex.length) {
				return;
			}

			syncLoginButtonColorControl($hex, defaultColor);
			updateLoginButtonPreview();
		});

		$form.on('click', '.art-lms-login-button-reset-dimension', function (event) {
			event.preventDefault();

			var $button = $(this);
			var dimensionKey = $button.attr('data-dimension-key');
			var defaultValue = $button.attr('data-default-value');

			if (!dimensionKey || defaultValue === undefined || defaultValue === '' || $button.prop('disabled')) {
				return;
			}

			$form.find('input[name*="[button][' + dimensionKey + ']"]').val(defaultValue);
			updateLoginButtonPreview();
		});

		$titleEnabled.on('change', function () {
			syncFormFieldState();
			updateLoginFormPreview();
		});

		$subtitleEnabled.on('change', function () {
			syncFormFieldState();
			updateLoginFormPreview();
		});

		$lostPasswordEnabled.on('change', function () {
			syncFormFieldState();
			updateLoginFormPreview();
		});

		$rememberEnabled.on('change', function () {
			syncFormFieldState();
			updateLoginFormPreview();
		});

		$enabled.on('change', function () {
			syncState();
			updateLoginFormPreview();
			updateLoginButtonPreview();
		});
		$slug.on('input', function () {
			var sanitized = sanitizeSlug($slug.val());

			if ($slug.val() !== sanitized) {
				$slug.val(sanitized);
			}

			syncState();
		});

		syncState();
		updateLoginDesignPreview();
		updateLoginFormPreview();
		syncButtonCustomSizeState();
		updateLoginButtonPreview();
	}

	$(document).ready(function () {
		initGeneralPageSettings();
		initLoginPageSettings();
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
