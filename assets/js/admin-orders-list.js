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

			$actionTop.val(value);
			$actionBottom.val(value);
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

			if (action === 'delete') {
				if (!window.confirm(strings.confirmBulkDelete || '')) {
					event.preventDefault();
				}
			}
		});
	}

	$(function () {
		var $page = $('.art-lms-orders-page');

		if (!$page.length || typeof artLmsAdminOrders === 'undefined') {
			return;
		}

		initDatepickers($page);
		initBulkActions($page);
	});
})(jQuery);
