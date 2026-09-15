(function ($) {
	'use strict';

	$(document).on('change', '.tttc-select-all', function () {
		$('.tttc-assignment-form tbody input[type="checkbox"]:not(:disabled)').prop('checked', this.checked);
	});

	$(document).on('change', '.tttc-show-all-players', function () {
		$('.tttc-assignment-form table').toggleClass('tttc-show-all-players-active', this.checked);
	});

	$(document).ready(function () {
		var $seedList = $('.tttc-seed-list').not('.tttc-seed-list--locked');
		if ($seedList.length && $.fn.sortable) {
			$seedList.sortable({
				axis: 'y',
				handle: '.tttc-seed-handle',
				update: function () {
					$(this).closest('form').find('.tttc-seed-order-input').val(
						$(this).children('li').map(function () {
							return $(this).data('player-id');
						}).get().join(',')
					);
				}
			});
		}

		$('.tttc-seed-form').on('submit', function () {
			var $list = $(this).find('.tttc-seed-list');
			$(this).find('.tttc-seed-order-input').val(
				$list.children('li').map(function () {
					return $(this).data('player-id');
				}).get().join(',')
			);
		});
	});

	$(document).ready(function () {
		var $postForm = $('#post');
		if (!$postForm.length || typeof tttcAdminData === 'undefined' || !tttcAdminData.players) {
			return;
		}

		$postForm.on('submit', function (e) {
			var nameInput = $.trim($('#title').val() || '').replace(/\s+/g, ' ');
			var emailInput = $.trim($('#tttc-email').val() || '').toLowerCase();

			if (!nameInput || !emailInput) {
				return;
			}

			var isDuplicate = false;
			$.each(tttcAdminData.players, function (index, player) {
				if (tttcAdminData.currentPostId && parseInt(player.id, 10) === parseInt(tttcAdminData.currentPostId, 10)) {
					return true;
				}

				var playerName = $.trim(player.name || '').replace(/\s+/g, ' ');
				var playerEmail = $.trim(player.email || '').toLowerCase();

				if (playerName.toLowerCase() === nameInput.toLowerCase() && playerEmail === emailInput) {
					isDuplicate = true;
					return false;
				}
			});

			if (isDuplicate) {
				e.preventDefault();
				e.stopImmediatePropagation();
				alert(tttcAdminData.duplicateMsg || 'A player with this name and email already exists.');

				var $publishBtn = $('#publish');
				if ($publishBtn.length) {
					$publishBtn.removeClass('button-primary-disabled disabled');
					$('#major-publishing-actions .spinner').removeClass('is-active');
				}
				return false;
			}
		});
	});
}(jQuery));
