(function ($) {
	'use strict';

	var cfg = window.artLmsPaymentButtonMetaBox || {};
	var metaKeys = cfg.metaKeys || {};
	var REQUIRED_NOTICE_ID = 'art-lms-payment-button-required';
	var savedState = '';
	var allowNavigation = false;
	var isSubmittingSave = false;
	var userMadeChanges = false;
	var paymentButtonEditorInitialized = false;

	function getAllMetaBoxes() {
		return $('.art-lms-payment-button-meta-box');
	}

	function getVisibleMetaBox() {
		var $activeBox = $(document.activeElement).closest('.art-lms-payment-button-meta-box');

		if ($activeBox.length && $activeBox.is(':visible')) {
			return $activeBox.first();
		}

		var $visible = getAllMetaBoxes().filter(function () {
			return $(this).is(':visible') && $(this).width() > 0;
		});

		if ($visible.length) {
			return $visible.first();
		}

		return getAllMetaBoxes().first();
	}

	/**
	 * Read a named field across duplicate meta-box copies.
	 * Never use #id selectors here — duplicate IDs break jQuery/DOM lookups.
	 *
	 * @param {string} fieldName Input/select name attribute.
	 * @return {string}
	 */
	function getNamedFieldValue(fieldName) {
		var active = document.activeElement;

		if (active && active.getAttribute && active.getAttribute('name') === fieldName) {
			return $.trim(active.value || '');
		}

		var best = '';
		var bestVisible = '';

		$('[name="' + fieldName + '"]').each(function () {
			var $field = $(this);
			var val = $.trim($field.val() || '');

			if (!val) {
				return;
			}

			best = val;

			if ($field.is(':visible')) {
				bestVisible = val;
			}
		});

		return bestVisible || best;
	}

	/**
	 * @deprecated Use getNamedFieldValue — kept as thin wrapper for call sites.
	 * @param {string} fieldSelector Legacy "#id" or ".class" selector.
	 * @return {string}
	 */
	function getMetaBoxFieldValue(fieldSelector) {
		var nameMap = {
			'#art_lms_product_name': 'art_lms_product_name',
			'#art_lms_compare_price': 'art_lms_compare_price',
			'#art_lms_price': 'art_lms_price',
			'#art_lms_access_days_custom': 'art_lms_access_days_custom',
			'.art-lms-access-mode': 'art_lms_access_mode',
		};

		if (nameMap[fieldSelector]) {
			return getNamedFieldValue(nameMap[fieldSelector]);
		}

		var best = '';

		getAllMetaBoxes().each(function () {
			var candidate = $.trim($(this).find(fieldSelector).first().val() || '');

			if (candidate) {
				best = candidate;
				return false;
			}
		});

		return best;
	}

	function syncMetaFieldAcrossBoxes($source) {
		var name = $source.attr('name');

		if (!name) {
			return;
		}

		var val = $source.val();

		$('[name="' + name + '"]')
			.not($source)
			.each(function () {
				if ($(this).val() !== val) {
					$(this).val(val);
				}
			});
	}

	function getVisibleStatusMetaBox() {
		var $visible = $('.art-lms-payment-button-status').filter(function () {
			return $(this).is(':visible') && $(this).width() > 0;
		});

		if ($visible.length) {
			return $visible.first();
		}

		return $('.art-lms-payment-button-status').first();
	}

	function getButtonEnabledState() {
		return getVisibleStatusMetaBox().find('input[name="art_lms_button_enabled"]:checked').val() === '1' ? '1' : '0';
	}

	function getInitialStateRaw() {
		var $json = getVisibleMetaBox().find('#art_lms_payment_button_initial_state');

		if (!$json.length) {
			$json = $('#art_lms_payment_button_initial_state').first();
		}

		if (!$json.length) {
			return '';
		}

		return $json.text() || '';
	}

	function markUserChanged() {
		userMadeChanges = true;
	}

	function resetUserChanges() {
		userMadeChanges = false;
		updateSavedStateFromForm();
	}

	function captureStableBaseline() {
		savedState = collectFormState();

		var $json = getVisibleMetaBox().find('#art_lms_payment_button_initial_state');

		if ($json.length) {
			$json.text(savedState);
		}

		userMadeChanges = false;
	}

	function getSavedState() {
		if (savedState) {
			return savedState;
		}

		savedState = getInitialStateRaw();
		return savedState;
	}

	function updateSavedStateFromForm() {
		var current = collectFormState();

		savedState = current;

		$('#art_lms_payment_button_initial_state').text(current);
	}

	function getTitleFromDom(doc) {
		doc = doc || document;

		var titleInput = doc.querySelector('.editor-post-title__input, #title, #post-title-0');

		if (!titleInput) {
			return '';
		}

		if (typeof titleInput.value === 'string' && titleInput.value !== '') {
			return $.trim(titleInput.value);
		}

		return $.trim(titleInput.textContent || titleInput.innerText || '');
	}

	function getEditedTitle() {
		var title = '';

		if (window.wp && wp.data && wp.data.select('core/editor')) {
			try {
				title = $.trim(wp.data.select('core/editor').getEditedPostAttribute('title') || '');
			} catch (error) {
				title = '';
			}
		}

		if (title) {
			return title;
		}

		title = getTitleFromDom(document);

		if (title) {
			return title;
		}

		// Block editor may keep the title inside the canvas iframe.
		$('iframe').each(function () {
			if (title) {
				return false;
			}

			try {
				if (this.contentDocument) {
					title = getTitleFromDom(this.contentDocument);
				}
			} catch (error) {
				// Cross-origin iframe — ignore.
			}
		});

		if (title) {
			return title;
		}

		return $.trim($('#title').val() || '');
	}

	function collectFormState() {
		return JSON.stringify({
			title: getEditedTitle(),
			productName: getNamedFieldValue('art_lms_product_name'),
			comparePrice: getNamedFieldValue('art_lms_compare_price'),
			price: getNamedFieldValue('art_lms_price'),
			accessMode: String(getNamedFieldValue('art_lms_access_mode') || '0'),
			accessDaysCustom: String(getNamedFieldValue('art_lms_access_days_custom') || '30'),
			materialIds: getSelectedMaterialIds(),
			enabled: getButtonEnabledState(),
		});
	}

	function isFormDirty() {
		if (allowNavigation || isSubmittingSave || !userMadeChanges) {
			return false;
		}

		if (window.wp && wp.data && wp.data.select('core/editor')) {
			var editor = wp.data.select('core/editor');

			if (editor.isSavingPost && editor.isSavingPost()) {
				return false;
			}

			if (editor.isAutosavingPost && editor.isAutosavingPost()) {
				return false;
			}
		}

		var saved = getSavedState();

		if (!saved) {
			return false;
		}

		return collectFormState() !== saved;
	}

	function markSaveInProgress() {
		isSubmittingSave = true;
		pushMetaToEditor();
		updateSavedStateFromForm();
	}

	function bindSaveButtons() {
		var saveButtonSelector = [
			'.editor-post-publish-button',
			'.editor-post-publish-button__button',
			'.editor-post-save-draft',
			'.editor-post-switch-to-draft',
			'.editor-post-publish-panel__toggle',
			'.entities-saved-states .components-button.is-primary',
			'#publish',
			'#save-post',
			'input[name="save"]',
		].join(', ');

		document.addEventListener(
			'click',
			function (event) {
				var target = event.target;

				if (!target || !target.closest || !target.closest(saveButtonSelector)) {
					return;
				}

				if (!shouldBlockPaymentButtonSave()) {
					return;
				}

				event.preventDefault();
				event.stopPropagation();
				event.stopImmediatePropagation();
				showRequiredFieldsFeedback();
			},
			true
		);

		$(document).on('click', saveButtonSelector, function (event) {
			if (!shouldBlockPaymentButtonSave()) {
				markSaveInProgress();
				return;
			}

			event.preventDefault();
			showRequiredFieldsFeedback();
			return false;
		});
	}

	function confirmLeave() {
		if (!isFormDirty()) {
			return true;
		}

		return window.confirm(
			(cfg.strings && cfg.strings.unsavedChanges) ||
				'Есть несохранённые изменения. Выйти без сохранения?'
		);
	}

	function bindUnsavedGuards() {
		$(window).on('beforeunload', function (event) {
			if (!isFormDirty()) {
				return;
			}

			event.preventDefault();
			event.returnValue = (cfg.strings && cfg.strings.unsavedWarning) || '';
			return event.returnValue;
		});

		$(document).on('click', '.art-lms-payment-button-back', function (event) {
			if (!confirmLeave()) {
				event.preventDefault();
			}
		});

		$(document).on('click', '#adminmenu a, .subsubsub a, .wrap .page-title-action', function (event) {
			if (allowNavigation || !isFormDirty()) {
				return;
			}

			var href = $(this).attr('href');

			if (!href || href.indexOf('#') === 0 || href.indexOf('javascript:') === 0) {
				return;
			}

			if (!confirmLeave()) {
				event.preventDefault();
			}
		});
	}

	function bindSaveStateReset() {
		if (!window.wp || !wp.data || !wp.data.subscribe) {
			return;
		}

		var wasSaving = false;

		wp.data.subscribe(function () {
			var editor = wp.data.select('core/editor');

			if (!editor || !editor.isSavingPost) {
				return;
			}

			var isSaving = editor.isSavingPost();
			var isAutosaving = editor.isAutosavingPost ? editor.isAutosavingPost() : false;

			if (isSaving && !wasSaving) {
				markSaveInProgress();
			}

			if (wasSaving && !isSaving && !isAutosaving) {
				isSubmittingSave = false;
				pushMetaToEditor();
				resetUserChanges();
			}

			wasSaving = isSaving;
		});
	}

	function toggleAccessCustomField() {
		getAllMetaBoxes().each(function () {
			var $root = $(this);
			var isCustom = $root.find('.art-lms-access-mode').val() === 'custom';

			$root.find('.art-lms-access-days-custom-wrap').toggle(isCustom);
		});
	}

	function resolveAccessDays() {
		var mode = getNamedFieldValue('art_lms_access_mode');

		if (mode === 'custom') {
			return parseInt(getNamedFieldValue('art_lms_access_days_custom'), 10) || 1;
		}

		return parseInt(mode, 10) || 0;
	}

	function getMaterialCatalog() {
		var $json = getVisibleMetaBox().find('.art-lms-material-catalog, #art_lms_material_catalog');

		if (!$json.length) {
			$json = $('.art-lms-material-catalog, #art_lms_material_catalog').first();
		}

		if (!$json.length) {
			return {};
		}

		try {
			return JSON.parse($json.text() || '{}');
		} catch (error) {
			return {};
		}
	}

	function getSelectedMaterialIds() {
		var ids = [];
		var seen = {};

		function pushId(raw) {
			var id = parseInt(raw, 10) || 0;

			if (id && !seen[id]) {
				seen[id] = true;
				ids.push(id);
			}
		}

		// Prefer visible list items — more reliable than name="…[]" across jQuery/DOM quirks.
		document.querySelectorAll('.art-lms-material-picker__item[data-material-id]').forEach(function (item) {
			pushId(item.getAttribute('data-material-id'));
		});

		if (ids.length) {
			return ids;
		}

		document.querySelectorAll('input[name="art_lms_material_ids[]"]').forEach(function (input) {
			pushId(input.value);
		});

		if (ids.length) {
			return ids;
		}

		// jQuery fallback with escaped brackets.
		$('input[name="art_lms_material_ids\\[\\]"], input[name="art_lms_material_ids[]"]').each(function () {
			pushId($(this).val());
		});

		if (ids.length) {
			return ids;
		}

		if (!window.wp || !wp.data || !metaKeys.materialIds) {
			return ids;
		}

		var editor = wp.data.select('core/editor');

		if (!editor || !editor.getEditedPostAttribute) {
			return ids;
		}

		var meta = editor.getEditedPostAttribute('meta') || {};
		var raw = meta[metaKeys.materialIds];

		if (!Array.isArray(raw)) {
			return ids;
		}

		raw.forEach(pushId);

		return ids;
	}

	function materialsRequiredForSave() {
		return !!cfg.requireMaterials;
	}

	function getRequiredFieldMessages() {
		return {
			title: (cfg.strings && cfg.strings.titleRequired) || 'Укажите заголовок платёжной кнопки.',
			productName: (cfg.strings && cfg.strings.productNameRequired) || 'Укажите название продукта.',
			price: (cfg.strings && cfg.strings.priceRequired) || 'Укажите цену.',
			materials: (cfg.strings && cfg.strings.materialsRequired) || 'Добавьте хотя бы один материал, прежде чем сохранять платёжную кнопку.',
		};
	}

	function getRequiredFieldState() {
		return {
			title: getEditedTitle(),
			productName: getNamedFieldValue('art_lms_product_name'),
			price: getNamedFieldValue('art_lms_price'),
			materialIds: getSelectedMaterialIds(),
		};
	}

	function getMissingRequiredFields(state) {
		var messages = getRequiredFieldMessages();
		var current = state || getRequiredFieldState();
		var missing = [];

		if (!current.title) {
			missing.push({ id: 'title', message: messages.title });
		}

		if (!current.productName) {
			missing.push({ id: 'productName', message: messages.productName });
		}

		if (!current.price) {
			missing.push({ id: 'price', message: messages.price });
		}

		if (materialsRequiredForSave() && !current.materialIds.length) {
			missing.push({ id: 'materials', message: messages.materials });
		}

		return missing;
	}

	function getRequiredFieldsMessage(missing) {
		missing = missing || getMissingRequiredFields();

		if (!missing.length) {
			return '';
		}

		if (missing.length === 1) {
			return missing[0].message;
		}

		return (cfg.strings && cfg.strings.requiredFieldsSummary) || 'Заполните обязательные поля перед сохранением платёжной кнопки.';
	}

	function shouldBlockPaymentButtonSave() {
		return getMissingRequiredFields().length > 0;
	}

	function showEditorErrorNotice(message) {
		var noticesDispatch = window.wp && wp.data && wp.data.dispatch ? wp.data.dispatch('core/notices') : null;

		if (noticesDispatch && noticesDispatch.createErrorNotice) {
			noticesDispatch.createErrorNotice(message, {
				id: REQUIRED_NOTICE_ID,
				type: 'snackbar',
			});
			return;
		}

		window.alert(message);
	}

	function ensureTitleRequiredNotice() {
		var $titleWrap = $('.edit-post-visual-editor__post-title-wrapper, .editor-post-title').first();

		if (!$titleWrap.length) {
			return $();
		}

		var $notice = $('#art_lms_title_required_notice');

		if (!$notice.length) {
			$notice = $('<p>', {
				id: 'art_lms_title_required_notice',
				class: 'art-lms-field-required-notice art-lms-title-required-notice',
				role: 'alert',
				text: getRequiredFieldMessages().title,
			});
			$titleWrap.after($notice);
		}

		return $notice;
	}

	function toggleFieldRequiredNotice($notice, isRequired) {
		if (!$notice || !$notice.length) {
			return;
		}

		$notice.each(function () {
			var $el = $(this);

			if (isRequired) {
				$el.prop('hidden', false);
				$el.removeAttr('hidden');
				$el.removeClass('is-required-notice-hidden');
				$el.addClass('is-required-notice-visible');
				$el.css('display', '');
				return;
			}

			$el.prop('hidden', true);
			$el.attr('hidden', 'hidden');
			$el.removeClass('is-required-notice-visible');
			$el.addClass('is-required-notice-hidden');
			$el.css('display', 'none');
		});
	}

	function toggleFieldRequiredState($fieldWrap, isRequired) {
		if (!$fieldWrap || !$fieldWrap.length) {
			return;
		}

		$fieldWrap.toggleClass('is-field-required', !!isRequired);
	}

	function updateRequiredFieldsUi() {
		var state = getRequiredFieldState();
		var missing = getMissingRequiredFields(state);
		var missingMap = {};

		missing.forEach(function (item) {
			missingMap[item.id] = true;
		});

		var $titleWrap = $('.edit-post-visual-editor__post-title-wrapper, .editor-post-title, .editor-visual-editor__post-title-wrapper').first();
		var $titleInput = $('.editor-post-title__input, #title, #post-title-0').first();
		var $titleNotice = ensureTitleRequiredNotice();

		toggleFieldRequiredNotice($titleNotice, !!missingMap.title);
		toggleFieldRequiredState($titleWrap.length ? $titleWrap : $titleInput, !!missingMap.title);
		toggleFieldRequiredState($titleInput, !!missingMap.title);

		// Also update title highlight inside the editor canvas iframe when present.
		$('iframe').each(function () {
			try {
				if (!this.contentDocument) {
					return;
				}

				var $iframeTitle = $(this.contentDocument).find(
					'.edit-post-visual-editor__post-title-wrapper, .editor-post-title, .editor-post-title__input'
				);

				toggleFieldRequiredState($iframeTitle, !!missingMap.title);
			} catch (error) {
				// Ignore cross-origin frames.
			}
		});

		// Update notices globally by class (avoid #id lookups with duplicate IDs).
		toggleFieldRequiredNotice($('.art-lms-product-name-required-notice'), !!missingMap.productName);
		toggleFieldRequiredState($('input[name="art_lms_product_name"]').closest('td'), !!missingMap.productName);

		toggleFieldRequiredNotice($('.art-lms-price-required-notice'), !!missingMap.price);
		toggleFieldRequiredState($('input[name="art_lms_price"]').closest('td'), !!missingMap.price);

		toggleFieldRequiredNotice($('.art-lms-materials-required-notice'), !!missingMap.materials);
		$('.art-lms-material-picker').toggleClass('is-materials-required', !!missingMap.materials);
	}

	function scrollToFirstMissingField(missing) {
		missing = missing || getMissingRequiredFields();

		if (!missing.length) {
			return;
		}

		var target = null;
		var firstId = missing[0].id;
		var $root = getVisibleMetaBox();

		if (firstId === 'title') {
			target = $('.editor-post-title__input, #title').first()[0];
		} else if (firstId === 'productName') {
			target = $('input[name="art_lms_product_name"]:visible').first()[0] || $root.find('input[name="art_lms_product_name"]')[0];
		} else if (firstId === 'price') {
			target = $('input[name="art_lms_price"]:visible').first()[0] || $root.find('input[name="art_lms_price"]')[0];
		} else if (firstId === 'materials') {
			target = $root.find('.art-lms-materials-required-notice:visible, .art-lms-material-picker').first()[0];
		}

		if (target && target.scrollIntoView) {
			target.scrollIntoView({ behavior: 'smooth', block: 'center' });
		}
	}

	function showRequiredFieldsFeedback() {
		var missing = getMissingRequiredFields();

		updateRequiredFieldsUi();
		showEditorErrorNotice(getRequiredFieldsMessage(missing));
		scrollToFirstMissingField(missing);
	}

	function bindRequiredFieldsValidation() {
		var attempts = 0;

		updateRequiredFieldsUi();

		var intervalId = window.setInterval(function () {
			attempts += 1;
			updateRequiredFieldsUi();

			if ($('.edit-post-visual-editor__post-title-wrapper, .editor-post-title, .editor-post-title__input').length || attempts >= 100) {
				window.clearInterval(intervalId);
			}
		}, 100);

		if (window.wp && wp.data && wp.data.subscribe) {
			var lastTitle = null;
			var lastMaterials = null;

			wp.data.subscribe(function () {
				var title = getEditedTitle();
				var materialsKey = getSelectedMaterialIds().join(',');

				if (title === lastTitle && materialsKey === lastMaterials) {
					return;
				}

				lastTitle = title;
				lastMaterials = materialsKey;
				updateRequiredFieldsUi();
			});
		}

		$(document).on(
			'input.artLmsTitleRequired keyup.artLmsTitleRequired',
			'.editor-post-title__input, #title, #post-title-0',
			function () {
				window.setTimeout(updateRequiredFieldsUi, 0);
			}
		);
	}

	function bindPreSavePostFilter() {
		if (!window.wp || !wp.hooks) {
			return;
		}

		wp.hooks.addFilter('editor.preSavePost', 'art-lms/required-fields', function (edits, options) {
			if (options && options.isAutosave) {
				return edits;
			}

			var missing = getMissingRequiredFields();

			if (!missing.length) {
				return edits;
			}

			showRequiredFieldsFeedback();
			throw new Error(getRequiredFieldsMessage(missing));
		});
	}

	function refreshMaterialSelectOptions($box) {
		var $roots = $box && $box.length ? $box : getAllMetaBoxes();
		var catalog = getMaterialCatalog();
		var selectedIds = getSelectedMaterialIds();

		$roots.each(function () {
			var $select = $(this).find('.art-lms-material-picker__select');
			var currentValue = $select.val();

			$select.find('option:not(:first)').remove();

			Object.keys(catalog).forEach(function (key) {
				var id = parseInt(key, 10) || 0;

				if (!id || selectedIds.indexOf(id) !== -1) {
					return;
				}

				$select.append(
					$('<option>', {
						value: String(id),
						text: catalog[key],
					})
				);
			});

			if (currentValue && selectedIds.indexOf(parseInt(currentValue, 10)) === -1) {
				$select.val(currentValue);
			} else {
				$select.val('');
			}
		});
	}

	function renderSelectedEmptyState($picker) {
		var $pickers = $picker && $picker.length ? $picker : $('.art-lms-material-picker');

		$pickers.each(function () {
			var $currentPicker = $(this);
			var $list = $currentPicker.find('.art-lms-material-picker__selected');
			var emptyLabel = $currentPicker.data('emptyLabel') || '';
			var $empty = $list.find('.art-lms-material-picker__empty');

			if ($list.find('.art-lms-material-picker__item').length) {
				$empty.remove();
				return;
			}

			if (!$empty.length && emptyLabel) {
				$list.append(
					$('<li>', {
						class: 'art-lms-material-picker__empty',
						text: emptyLabel,
					})
				);
			}
		});
	}

	function addMaterial(materialId, $box) {
		var catalog = getMaterialCatalog();
		var id = parseInt(materialId, 10) || 0;

		if (!id || !(catalog[id] || catalog[String(id)])) {
			return;
		}

		var label = catalog[id] || catalog[String(id)];

		// Already selected anywhere in the editor.
		if (getSelectedMaterialIds().indexOf(id) !== -1) {
			updateRequiredFieldsUi();
			return;
		}

		getAllMetaBoxes().each(function () {
			var $root = $(this);
			var $list = $root.find('.art-lms-material-picker__selected');

			if (!$list.length || $list.find('[data-material-id="' + id + '"]').length) {
				return;
			}

			$list.find('.art-lms-material-picker__empty').remove();

			$list.append(
				$('<li>', {
					class: 'art-lms-material-picker__item',
					'data-material-id': String(id),
				})
					.append($('<span>', { class: 'art-lms-material-picker__title', text: label }))
					.append(
						$('<button>', {
							type: 'button',
							class: 'button-link-delete art-lms-material-picker__remove',
							text: cfg.strings && cfg.strings.remove ? cfg.strings.remove : 'Remove',
						})
					)
					.append(
						$('<input>', {
							type: 'hidden',
							name: 'art_lms_material_ids[]',
							value: String(id),
						})
					)
			);
		});

		refreshMaterialSelectOptions();
		renderSelectedEmptyState();
		markUserChanged();
		pushMetaToEditor();
		updateRequiredFieldsUi();
	}

	function removeMaterial($item) {
		$item.remove();
		refreshMaterialSelectOptions();
		renderSelectedEmptyState();
		markUserChanged();
		pushMetaToEditor();
		updateRequiredFieldsUi();
	}

	function collectMeta() {
		var meta = {};

		if (metaKeys.productName) {
			meta[metaKeys.productName] = getNamedFieldValue('art_lms_product_name');
		}

		if (metaKeys.comparePrice) {
			meta[metaKeys.comparePrice] = getNamedFieldValue('art_lms_compare_price');
		}

		if (metaKeys.price) {
			meta[metaKeys.price] = getNamedFieldValue('art_lms_price');
		}

		if (metaKeys.accessDays) {
			meta[metaKeys.accessDays] = resolveAccessDays();
		}

		if (metaKeys.materialIds) {
			meta[metaKeys.materialIds] = getSelectedMaterialIds();
		}

		if (metaKeys.enabled) {
			meta[metaKeys.enabled] = getButtonEnabledState() === '1';
		}

		return meta;
	}

	function pushMetaToEditor() {
		if (!window.wp || !wp.data || !wp.data.select('core/editor')) {
			return;
		}

		var editor = wp.data.select('core/editor');
		var currentMeta = editor.getEditedPostAttribute('meta') || {};

		wp.data.dispatch('core/editor').editPost({
			meta: $.extend({}, currentMeta, collectMeta()),
		});
	}

	function bindEditorSync() {
		if (!window.wp || !wp.data || !wp.data.subscribe) {
			return;
		}

		var wasSaving = false;

		wp.data.subscribe(function () {
			var editor = wp.data.select('core/editor');

			if (!editor || !editor.isSavingPost) {
				return;
			}

			var isSaving = editor.isSavingPost();

			if (isSaving && !wasSaving) {
				pushMetaToEditor();
			}

			wasSaving = isSaving;
		});
	}

	function bindMaterialPicker() {
		getAllMetaBoxes().each(function () {
			renderSelectedEmptyState($(this).find('.art-lms-material-picker'));
		});

		$(document)
			.off('click.artLmsMaterialAdd', '.art-lms-material-picker__add')
			.on('click.artLmsMaterialAdd', '.art-lms-material-picker__add', function (event) {
				event.preventDefault();

				var $box = $(this).closest('.art-lms-payment-button-meta-box');
				var materialId = $box.find('.art-lms-material-picker__select').val();

				addMaterial(materialId, $box);
			});

		$(document)
			.off('click.artLmsMaterialRemove', '.art-lms-material-picker__remove')
			.on('click.artLmsMaterialRemove', '.art-lms-material-picker__remove', function (event) {
				event.preventDefault();
				removeMaterial($(this).closest('.art-lms-material-picker__item'));
			});
	}

	function bindUserChangeTracking() {
		$(document).on(
			'input.artLmsPaymentButtonChange change.artLmsPaymentButtonChange',
			'.editor-post-title__input, #title, .art-lms-payment-button-meta-box input, .art-lms-payment-button-meta-box select, .art-lms-payment-button-status input',
			function () {
				if (!paymentButtonEditorInitialized) {
					return;
				}

				markUserChanged();
			}
		);
	}

	function waitForStableBaseline(attempts) {
		attempts = attempts || 0;

		if (!paymentButtonEditorInitialized) {
			return;
		}

		var initialTitle = '';

		try {
			initialTitle = JSON.parse(getInitialStateRaw() || '{}').title || '';
		} catch (error) {
			initialTitle = '';
		}

		if (
			window.wp &&
			wp.data &&
			wp.data.select('core/editor') &&
			initialTitle &&
			!getEditedTitle()
		) {
			if (attempts < 50) {
				window.setTimeout(function () {
					waitForStableBaseline(attempts + 1);
				}, 100);
				return;
			}
		}

		captureStableBaseline();
	}

	function getCopyText($button) {
		var directValue = $button.attr('data-copy-value');

		if (directValue) {
			return $.trim(directValue);
		}

		var targetSelector = $button.attr('data-copy-target');
		var $target = targetSelector ? $(targetSelector).first() : $();
		var mode = $button.attr('data-copy-mode');

		if (!$target.length) {
			return '';
		}

		if (mode === 'text' || $target.is('a')) {
			return $.trim($target.attr('href') || $target.text() || '');
		}

		return $.trim($target.text() || '');
	}

	function fallbackCopy(text, deferred) {
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

	function copyToClipboard(text) {
		var deferred = $.Deferred();

		if (!text) {
			return deferred.reject().promise();
		}

		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(text).then(function () {
				deferred.resolve();
			}).catch(function () {
				fallbackCopy(text, deferred);
			});

			return deferred.promise();
		}

		fallbackCopy(text, deferred);
		return deferred.promise();
	}

	function selectElementText(element) {
		if (!element) {
			return;
		}

		var range = document.createRange();
		var selection = window.getSelection();

		range.selectNodeContents(element);
		selection.removeAllRanges();
		selection.addRange(range);
	}

	function bindShortcodeAutoSelect() {
		$(document)
			.off('click.artLmsShortcodeSelect focus.artLmsShortcodeSelect', '.art-lms-shortcode-select')
			.on('click.artLmsShortcodeSelect focus.artLmsShortcodeSelect', '.art-lms-shortcode-select', function () {
				selectElementText(this);
			});
	}

	function bindCopyButtons() {
		$(document).off('click.artLmsCopy', '.art-lms-copy-button');
		$(document).on('click.artLmsCopy', '.art-lms-copy-button', function (event) {
			event.preventDefault();

			var $button = $(this);
			var text = getCopyText($button);
			var defaultTitle = $button.attr('title') || '';

			if (!text) {
				window.alert((cfg.strings && cfg.strings.copyFailed) || 'Copy failed');
				return;
			}

			copyToClipboard(text)
				.done(function () {
					$button.addClass('is-copied');
					$button.attr('title', (cfg.strings && cfg.strings.copied) || 'Copied!');

					window.setTimeout(function () {
						$button.removeClass('is-copied');
						$button.attr('title', defaultTitle);
					}, 1600);
				})
				.fail(function () {
					window.alert((cfg.strings && cfg.strings.copyFailed) || 'Copy failed');
				});
		});
	}

	function initPaymentButtonEditor() {
		if (paymentButtonEditorInitialized) {
			return true;
		}

		if (!$('.art-lms-payment-button-meta-box').length) {
			return false;
		}

		paymentButtonEditorInitialized = true;
		getSavedState();
		toggleAccessCustomField();
		bindMaterialPicker();
		bindRequiredFieldsValidation();
		bindPreSavePostFilter();
		bindSaveButtons();
		bindUnsavedGuards();
		bindSaveStateReset();
		bindUserChangeTracking();
		$(document)
			.off('change.artLmsAccessMode', '.art-lms-access-mode')
			.on('change.artLmsAccessMode', '.art-lms-access-mode', function () {
				toggleAccessCustomField();
				pushMetaToEditor();
				updateRequiredFieldsUi();
			});
		$(document)
			.off('input.artLmsMetaBox change.artLmsMetaBox', '.art-lms-payment-button-meta-box input, .art-lms-payment-button-meta-box select')
			.on('input.artLmsMetaBox change.artLmsMetaBox', '.art-lms-payment-button-meta-box input, .art-lms-payment-button-meta-box select', function () {
				syncMetaFieldAcrossBoxes($(this));
				pushMetaToEditor();
				updateRequiredFieldsUi();
			});
		$(document).on(
			'input.artLmsRequiredFields change.artLmsRequiredFields',
			'.editor-post-title__input, #title, input[name="art_lms_product_name"], input[name="art_lms_price"]',
			function () {
				var $field = $(this);

				if ($field.is('input[name="art_lms_product_name"], input[name="art_lms_price"]')) {
					syncMetaFieldAcrossBoxes($field);
				}

				updateRequiredFieldsUi();
			}
		);
		$(document).on('change.artLmsButtonStatus', '.art-lms-payment-button-status input', pushMetaToEditor);
		bindEditorSync();
		pushMetaToEditor();
		waitForStableBaseline(0);

		return true;
	}

	function waitForPaymentButtonEditor(attempts) {
		attempts = attempts || 0;

		if (initPaymentButtonEditor()) {
			return;
		}

		if (attempts < 100) {
			window.setTimeout(function () {
				waitForPaymentButtonEditor(attempts + 1);
			}, 100);
		}
	}

	$(document).ready(function () {
		bindCopyButtons();
		bindShortcodeAutoSelect();
		waitForPaymentButtonEditor(0);
	});
})(jQuery);
