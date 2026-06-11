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

	$(function () {
		var $page = $('.art-lms-orders-page');

		if (!$page.length || typeof artLmsAdminOrders === 'undefined') {
			return;
		}

		initDatepickers($page);
	});
})(jQuery);
