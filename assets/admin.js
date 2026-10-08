(function ($) {
	'use strict';

	// Force new-tab navigation even if something else on the page intercepts the click.
	$(document).on('click', 'a[target="_blank"]', function (e) {
		var href = $(this).attr('href');
		if (!href) {
			return;
		}
		e.preventDefault();
		window.open(href, '_blank', 'noopener,noreferrer');
	});

	$(document).on('change', '.tttc-select-all', function () {
		$('.tttc-assignment-form tbody input[type="checkbox"]:not(:disabled)').prop('checked', this.checked);
	});

	$(document).on('click', '.tttc-player-type-tab', function () {
		var $tab = $(this);
		var $form = $tab.closest('.tttc-assignment-form');
		var showAll = $tab.attr('aria-pressed') === 'true';
		var activeType = showAll ? '' : $tab.data('player-type');

		$form.find('.tttc-player-type-tab')
			.removeClass('nav-tab-active')
			.attr('aria-pressed', 'false');

		if (activeType) {
			$tab.addClass('nav-tab-active').attr('aria-pressed', 'true');
		}

		$form.find('tbody tr').each(function () {
			var isHidden = activeType && $(this).data('type') !== activeType;
			$(this).toggleClass('tttc-row-hidden-by-type', Boolean(isHidden));
		});
	});

	$(document).on('input', '.tttc-score-pair input', function () {
		var value = $.trim($(this).val());
		if (!/^\d+$/.test(value)) {
			return;
		}

		var score = Number(value);
		if (!isFinite(score) || Math.floor(score) !== score) {
			return;
		}

		$(this).siblings('input').val(score <= 9 ? 11 : score + 2);
	});

	$(document).ready(function () {
		if ($('.tttc-scores-form.tttc-print-all-groups').length) {
			window.print();
		}

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

	$(document).on('focus', '.tttc-status-select', function () {
		$(this).data('previous', $(this).val());
	});

	$(document).on('change', '.tttc-status-select', function () {
		if (typeof tttcStatusData === 'undefined') {
			return;
		}

		var $select = $(this);
		var $row = $select.closest('tr');
		var $feedback = $select.siblings('.tttc-status-feedback');
		var previous = $select.data('previous');
		var onError = function () {
			if (previous !== undefined) {
				$select.val(previous);
			}
			$feedback.addClass('is-error').text(tttcStatusData.error);
		};

		$select.prop('disabled', true);
		$feedback.removeClass('is-error').text('');

		$.post(tttcStatusData.ajaxUrl, {
			action: 'tttc_quick_status',
			post_id: $select.data('post-id'),
			nonce: $select.data('nonce'),
			status: $select.val()
		}).done(function (response) {
			if (!response || !response.success) {
				onError();
				return;
			}
			$row.find('td.column-tttc_players').html(response.data.tttc_players);
			$row.find('td.column-tttc_scores').html(response.data.tttc_scores);
			$select.data('previous', $select.val());
			$feedback.text(tttcStatusData.saved);
			setTimeout(function () {
				$feedback.text('');
			}, 2000);
		}).fail(onError).always(function () {
			$select.prop('disabled', false);
		});
	});

	$(document).on('click', '.tttc-info-trigger', function () {
		var $modal = $('#' + $(this).attr('aria-controls'));
		$modal.prop('hidden', false);
		$modal.find('.tttc-modal-close').trigger('focus');
	});

	$(document).on('click', '.tttc-modal-close, .tttc-modal-overlay', function () {
		$(this).closest('.tttc-modal').prop('hidden', true);
	});

	$(document).on('keydown', function (e) {
		if (e.key === 'Escape' || e.keyCode === 27) {
			$('.tttc-modal:not([hidden])').prop('hidden', true);
		}
	});
}(jQuery));
