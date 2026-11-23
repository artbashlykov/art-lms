(function ($) {
	'use strict';

	function bindDateClearButton($input) {
		var $widget = $input.datepicker('widget');
		var $pane = $widget.find('.ui-datepicker-buttonpane');

		if (!$pane.length) {
			return;
		}

		$pane.find('.art-lms-datepicker-clear').remove();

		$('<button type="button" class="ui-datepicker-current ui-state-default ui-priority-secondary ui-corner-all art-lms-datepicker-clear"></button>')
			.text(artLmsAdminOrders.strings.clearDate)
			.on('click', function (event) {
				event.preventDefault();
				$input.val('').trigger('change');
				$input.datepicker('hide');
			})
			.prependTo($pane);
	}

	function initDatepickers($scope) {
		if (!$.datepicker || !artLmsAdminOrders.datepicker) {
			return;
		}

		$.datepicker.regional.ru = $.extend(
			{
				dateFormat: 'yy-mm-dd',
				firstDay: 1,
				isRTL: false,
				showMonthAfterYear: false,
				yearSuffix: '',
			},
			artLmsAdminOrders.datepicker
		);

		$.datepicker.setDefaults($.datepicker.regional.ru);

		$scope.find('.art-lms-date-input').each(function () {
			var $input = $(this);

			if ($input.hasClass('has-art-lms-datepicker')) {
				return;
			}

			$input.addClass('has-art-lms-datepicker');

			$input.datepicker({
				dateFormat: 'yy-mm-dd',
				showButtonPanel: true,
				beforeShow: function () {
					window.setTimeout(function () {
						bindDateClearButton($input);
					}, 0);
				},
				onChangeMonthYear: function () {
					window.setTimeout(function () {
						bindDateClearButton($input);
					}, 0);
				},
			});
		});
	}

	function syncSelectAll($form) {
		var $boxes = $form.find('.art-lms-order-cb');
		var total = $boxes.length;
		var checked = $boxes.filter(':checked').length;
		var allChecked = total > 0 && checked === total;

		$form.find('.art-lms-orders-cb-select-all').prop('checked', allChecked).prop('indeterminate', checked > 0 && !allChecked);
	}

	function initBulkActions($page) {
		var $form = $page.find('#art-lms-orders-bulk-form');

		if (!$form.length) {
			return;
		}

		var $actionTop = $form.find('#art-lms-bulk-action-top');
		var $actionBottom = $form.find('#art-lms-bulk-action-bottom');

		$form.on('change', '.art-lms-orders-cb-select-all', function () {
			var checked = $(this).prop('checked');

			$form.find('.art-lms-order-cb').prop('checked', checked);
			$form.find('.art-lms-orders-cb-select-all').prop('checked', checked).prop('indeterminate', false);
		});

		$form.on('change', '.art-lms-order-cb', function () {
			syncSelectAll($form);
		});

		$actionTop.add($actionBottom).on('change', function () {
			var value = $(this).val();

			$actionTop.val(value).trigger('art-lms-select-sync');
			$actionBottom.val(value).trigger('art-lms-select-sync');
		});

		$form.on('submit', function (event) {
			var action = $actionTop.val();
			var selected = $form.find('.art-lms-order-cb:checked').length;
			var strings = artLmsAdminOrders.strings || {};

			if (action === '-1' || !action) {
				event.preventDefault();
				window.alert(strings.selectAction || '');
				return;
			}

			if (!selected) {
				event.preventDefault();
				window.alert(strings.selectOrders || '');
				return;
			}

			if (action === 'trash' || action === 'delete') {
				if (!window.confirm(strings.confirmBulkTrash || strings.confirmBulkDelete || '')) {
					event.preventDefault();
				}
			}

			if (action === 'delete_permanently') {
				if (!window.confirm(strings.confirmBulkDeletePermanent || '')) {
					event.preventDefault();
				}
			}
		});
	}

	/**
	 * Native <select> popups overflow the viewport on mobile Chrome.
	 * Keep the select for form submit; on narrow screens use an in-page list.
	 */
	function initMobileSelects($page) {
		var $selects = $page.find('.art-lms-orders-filters select, .art-lms-orders-page .bulkactions select');

		$selects.each(function () {
			var $select = $(this);

			if ($select.parent().hasClass('art-lms-select-wrap')) {
				return;
			}

			var $wrap = $('<div class="art-lms-select-wrap"></div>');
			var $trigger = $('<button type="button" class="art-lms-select-trigger" aria-haspopup="listbox" aria-expanded="false"></button>');
			var $list = $('<ul class="art-lms-select-list" role="listbox" hidden></ul>');
			var listId = $select.attr('id')
				? $select.attr('id') + '-art-lms-list'
				: 'art-lms-select-list-' + String(Math.random()).slice(2);

			$list.attr('id', listId);
			$trigger.attr('aria-controls', listId);

			$select.wrap($wrap);
			$wrap = $select.parent();
			$wrap.append($trigger).append($list);

			function selectedLabel() {
				var option = $select[0].options[$select[0].selectedIndex];
				return option ? option.text : '';
			}

			function syncTrigger() {
				$trigger.text(selectedLabel());
			}

			function buildList() {
				$list.empty();

				$select.find('option').each(function () {
					var $option = $(this);
					var value = $option.attr('value');
					var $item = $('<li role="option" tabindex="-1"></li>');

					$item
						.attr('data-value', value)
						.attr('aria-selected', $option.is(':selected') ? 'true' : 'false')
						.toggleClass('is-selected', $option.is(':selected'))
						.text($option.text());

					$list.append($item);
				});
			}

			function closeList() {
				$list.attr('hidden', true);
				$trigger.attr('aria-expanded', 'false');
				$wrap.removeClass('is-open');
			}

			function openList() {
				buildList();
				$list.removeAttr('hidden');
				$trigger.attr('aria-expanded', 'true');
				$wrap.addClass('is-open');
			}

			syncTrigger();
			buildList();

			$trigger.on('click', function (event) {
				event.preventDefault();

				if ($wrap.hasClass('is-open')) {
					closeList();
					return;
				}

				$page.find('.art-lms-select-wrap.is-open').each(function () {
					$(this).find('.art-lms-select-trigger').trigger('art-lms-select-close');
				});

				openList();
			});

			$trigger.on('art-lms-select-close', closeList);

			$list.on('click', '[role="option"]', function (event) {
				event.preventDefault();

				var value = $(this).attr('data-value');

				$select.val(value).trigger('change');
				syncTrigger();
				closeList();
			});

			$select.on('change art-lms-select-sync', function () {
				syncTrigger();
				buildList();
			});
		});

		$(document).on('click.artLmsMobileSelect', function (event) {
			if ($(event.target).closest('.art-lms-select-wrap').length) {
				return;
			}

			$page.find('.art-lms-select-wrap.is-open .art-lms-select-trigger').trigger('art-lms-select-close');
		});

		$(document).on('keydown.artLmsMobileSelect', function (event) {
			if (event.key !== 'Escape') {
				return;
			}

			$page.find('.art-lms-select-wrap.is-open .art-lms-select-trigger').trigger('art-lms-select-close');
		});
	}

	$(function () {
		var $page = $('.art-lms-orders-page');

		if (!$page.length || typeof artLmsAdminOrders === 'undefined') {
			return;
		}

		initDatepickers($page);
		initBulkActions($page);
		initMobileSelects($page);
	});
})(jQuery);
