(function ($) {
	'use strict';

	$(document).on('change', '.tttc-select-all', function () {
		$('.tttc-assignment-form tbody input[type="checkbox"]:not(:disabled)').prop('checked', this.checked);
	});
}(jQuery));
