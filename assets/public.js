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

	function initializeTv(root) {
		var slides = Array.prototype.slice.call(root.querySelectorAll('.tttc-tv-slide'));
		var indicator = root.querySelector('.tttc-tv-indicator');
		var interval = parseInt(root.getAttribute('data-interval'), 10) || 20000;
		var poll = parseInt(root.getAttribute('data-poll'), 10) || 10000;
		var version = root.getAttribute('data-version');
		var versionUrl = root.getAttribute('data-version-url');
		var storageKey = 'tttc-tv-slide-' + root.getAttribute('data-tournament');
		var current = 0;

		function readIndex() {
			try {
				return parseInt(window.sessionStorage.getItem(storageKey), 10) || 0;
			} catch (error) {
				return 0;
			}
		}

		function saveIndex() {
			try {
				window.sessionStorage.setItem(storageKey, String(current));
			} catch (error) {
				// Storage may be unavailable (private mode); cycling still works.
			}
		}

		function show(index) {
			current = (index + slides.length) % slides.length;
			slides.forEach(function (slide, slideIndex) {
				slide.hidden = slideIndex !== current;
			});
			if (indicator) {
				var label = slides[current].getAttribute('data-tttc-label');
				indicator.textContent = slides.length > 1 ? (label ? label + ' · ' : '') + (current + 1) + '/' + slides.length : '';
			}
			saveIndex();
		}

		if (slides.length) {
			show(readIndex());
			if (slides.length > 1) {
				window.setInterval(function () {
					show(current + 1);
				}, interval);
			}
		}

		if (!versionUrl || !window.fetch) {
			return;
		}

		window.setInterval(function () {
			window.fetch(versionUrl + (-1 === versionUrl.indexOf('?') ? '?' : '&') + '_=' + Date.now(), { cache: 'no-store', credentials: 'omit' })
				.then(function (response) {
					return response.ok ? response.json() : null;
				})
				.then(function (data) {
					if (!data || !data.version || data.version === version) {
						return;
					}
					saveIndex();
					// Version in the URL bypasses page caches that would otherwise serve stale scores.
					var url = new URL(window.location.href);
					url.searchParams.set('tttc_v', data.version);
					window.location.replace(url.toString());
				})
				.catch(function () {});
		}, poll);
	}

	document.querySelectorAll('.tttc-public-group-tabs').forEach(initializeGroupTabs);
	document.querySelectorAll('[data-tttc-tv]').forEach(initializeTv);
}());