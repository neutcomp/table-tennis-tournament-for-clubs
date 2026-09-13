(function () {
	'use strict';

	function initializeGroupTabs(tablist) {
		var tabs = Array.prototype.slice.call(tablist.querySelectorAll('[role="tab"]'));

		if (!tabs.length) {
			return;
		}

		function selectTab(tab, moveFocus) {
			tabs.forEach(function (candidate) {
				var panel = document.getElementById(candidate.getAttribute('aria-controls'));
				var isSelected = candidate === tab;

				candidate.classList.toggle('is-active', isSelected);
				candidate.setAttribute('aria-selected', isSelected ? 'true' : 'false');
				candidate.setAttribute('tabindex', isSelected ? '0' : '-1');
				if (panel) {
					panel.classList.toggle('is-active', isSelected);
					panel.hidden = !isSelected;
				}
			});

			if (moveFocus) {
				tab.focus();
			}
		}

		tabs.forEach(function (tab, index) {
			tab.addEventListener('click', function () {
				selectTab(tab, false);
			});
			tab.addEventListener('keydown', function (event) {
				var nextIndex = index;

				if ('ArrowRight' === event.key) {
					nextIndex = (index + 1) % tabs.length;
				} else if ('ArrowLeft' === event.key) {
					nextIndex = (index - 1 + tabs.length) % tabs.length;
				} else if ('Home' === event.key) {
					nextIndex = 0;
				} else if ('End' === event.key) {
					nextIndex = tabs.length - 1;
				} else {
					return;
				}

				event.preventDefault();
				selectTab(tabs[nextIndex], true);
			});
		});
	}

	document.querySelectorAll('.tttc-public-group-tabs').forEach(initializeGroupTabs);

	document.querySelectorAll('[data-tttc-print-groups]').forEach(function (button) {
		button.addEventListener('click', function () {
			window.print();
		});
	});
}());