(function ($) {
	'use strict';

	var cfg = window.artLmsPaymentButtonMetaBox || {};
	var metaKeys = cfg.metaKeys || {};
	var savedState = '';
	var allowNavigation = false;
	var isSubmittingSave = false;
	var userMadeChanges = false;
	var paymentButtonEditorInitialized = false;

	function getVisibleMetaBox() {
		var $visible = $('.art-lms-payment-button-meta-box').filter(function () {
			return $(this).is(':visible') && $(this).width() > 0;
		});

		if ($visible.length) {
			return $visible.first();
		}

		return $('.art-lms-payment-button-meta-box').first();
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

	function getEditedTitle() {
		if (window.wp && wp.data && wp.data.select('core/editor')) {
			return $.trim(wp.data.select('core/editor').getEditedPostAttribute('title') || '');
		}

		return $.trim($('#title').val() || '');
	}

	function collectFormState() {
		var $root = getVisibleMetaBox();

		return JSON.stringify({
			title: getEditedTitle(),
			productName: $.trim($root.find('#art_lms_product_name').val() || ''),
			comparePrice: $.trim($root.find('#art_lms_compare_price').val() || ''),
			price: $.trim($root.find('#art_lms_price').val() || ''),
			accessMode: String($root.find('.art-lms-access-mode').val() || '0'),
			accessDaysCustom: String($root.find('#art_lms_access_days_custom').val() || '30'),
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
			'.editor-post-save-draft',
			'.editor-post-switch-to-draft',
			'#publish',
			'#save-post',
			'input[name="save"]',
		].join(', ');

		$(document).on('click', saveButtonSelector, function () {
			markSaveInProgress();
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
		var $root = getVisibleMetaBox();
		var isCustom = $root.find('.art-lms-access-mode').val() === 'custom';

		$root.find('.art-lms-access-days-custom-wrap').toggle(isCustom);
	}

	function resolveAccessDays() {
		var $root = getVisibleMetaBox();
		var mode = $root.find('.art-lms-access-mode').val();

		if (mode === 'custom') {
			return parseInt($root.find('#art_lms_access_days_custom').val(), 10) || 1;
		}

		return parseInt(mode, 10) || 0;
	}

	function getMaterialCatalog() {
		var $json = getVisibleMetaBox().find('#art_lms_material_catalog');

		if (!$json.length) {
			$json = $('#art_lms_material_catalog').first();
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

		getVisibleMetaBox()
			.find('#art_lms_material_selected_list input[name="art_lms_material_ids[]"]')
			.each(function () {
				var id = parseInt($(this).val(), 10) || 0;

				if (id) {
					ids.push(id);
				}
			});

		return ids;
	}

	function refreshMaterialSelectOptions() {
		var $root = getVisibleMetaBox();
		var $select = $root.find('#art_lms_material_picker_select');
		var catalog = getMaterialCatalog();
		var selectedIds = getSelectedMaterialIds();
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
	}

	function renderSelectedEmptyState() {
		var $root = getVisibleMetaBox();
		var $list = $root.find('#art_lms_material_selected_list');
		var $picker = $root.find('.art-lms-material-picker');
		var emptyLabel = $picker.data('emptyLabel') || '';
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
	}

	function addMaterial(materialId) {
		var catalog = getMaterialCatalog();
		var id = parseInt(materialId, 10) || 0;
		var $root = getVisibleMetaBox();
		var $list = $root.find('#art_lms_material_selected_list');

		if (!id || !catalog[id] || $list.find('[data-material-id="' + id + '"]').length) {
			return;
		}

		$list.find('.art-lms-material-picker__empty').remove();

		$list.append(
			$('<li>', {
				class: 'art-lms-material-picker__item',
				'data-material-id': String(id),
			})
				.append($('<span>', { class: 'art-lms-material-picker__title', text: catalog[id] }))
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

		refreshMaterialSelectOptions();
		renderSelectedEmptyState();
		markUserChanged();
		pushMetaToEditor();
	}

	function removeMaterial($item) {
		$item.remove();
		refreshMaterialSelectOptions();
		renderSelectedEmptyState();
		markUserChanged();
		pushMetaToEditor();
	}

	function collectMeta() {
		var $root = getVisibleMetaBox();
		var meta = {};

		if (metaKeys.productName) {
			meta[metaKeys.productName] = $.trim($root.find('#art_lms_product_name').val() || '');
		}

		if (metaKeys.comparePrice) {
			meta[metaKeys.comparePrice] = $.trim($root.find('#art_lms_compare_price').val() || '');
		}

		if (metaKeys.price) {
			meta[metaKeys.price] = $.trim($root.find('#art_lms_price').val() || '');
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
		var $picker = getVisibleMetaBox().find('.art-lms-material-picker');

		if (!$picker.length) {
			return;
		}

		renderSelectedEmptyState();

		$picker.on('click', '.art-lms-material-picker__add', function () {
			addMaterial(getVisibleMetaBox().find('#art_lms_material_picker_select').val());
		});

		$picker.on('click', '.art-lms-material-picker__remove', function () {
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
		bindSaveButtons();
		bindUnsavedGuards();
		bindSaveStateReset();
		bindUserChangeTracking();
		$(document)
			.off('change.artLmsAccessMode', '.art-lms-access-mode')
			.on('change.artLmsAccessMode', '.art-lms-access-mode', function () {
				toggleAccessCustomField();
				pushMetaToEditor();
			});
		$('.art-lms-payment-button-meta-box').on('input change', 'input, select', pushMetaToEditor);
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
