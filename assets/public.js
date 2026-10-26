(function () {
	'use strict';

	function initializeQrCodes() {
		if (typeof qrcode !== 'function') {
			return;
		}

		document.querySelectorAll('[data-tttc-qr]').forEach(function (image) {
			var url = image.getAttribute('data-tttc-qr');

			if (!url) {
				return;
			}

			try {
				var qr = qrcode(0, 'M');
				qr.addData(url, 'Byte');
				qr.make();
				image.src = qr.createDataURL(4, 0);
			} catch (error) {
				image.remove();
			}
		});
	}

	function initializeJoiningSignup(form) {
		var yesFields = form.querySelector('[data-tttc-joining-fields]');
		var noFields = form.querySelector('[data-tttc-not-joining-fields]');
		var choiceInputs = form.querySelectorAll('input[name="tttc_signup_joining"]');

		if (!yesFields || !noFields || !choiceInputs.length) {
			return;
		}

		function updateFields() {
			var choice = form.querySelector('input[name="tttc_signup_joining"]:checked');
			var isJoining = choice && 'yes' === choice.value;
			var isNotJoining = choice && 'no' === choice.value;

			yesFields.hidden = !isJoining;
			noFields.hidden = !isNotJoining;
			yesFields.querySelectorAll('input, select, textarea, button').forEach(function (field) {
				field.disabled = !isJoining;
			});
			noFields.querySelectorAll('input, select, textarea, button').forEach(function (field) {
				field.disabled = !isNotJoining;
			});
		}

		choiceInputs.forEach(function (input) {
			input.addEventListener('change', updateFields);
		});
		updateFields();
	}

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

		// Reopen the group containing the anchor after a per-round save.
		var target = window.location.hash ? document.getElementById(window.location.hash.slice(1)) : null;
		var targetPanel = target ? target.closest('[role="tabpanel"]') : null;
		if (targetPanel) {
			tabs.forEach(function (tab) {
				if (tab.getAttribute('aria-controls') === targetPanel.id) {
					selectTab(tab, false);
					target.scrollIntoView();
				}
			});
		}
	}

	function initializeTv(root) {
		var slides = Array.prototype.slice.call(root.querySelectorAll('.tttc-tv-slide'));
		var indicator = root.querySelector('.tttc-tv-indicator');
		var progress = root.querySelector('.tttc-tv-progress span');
		var clock = root.querySelector('.tttc-tv-clock');
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
			if (progress && slides.length > 1) {
				progress.classList.remove('is-running');
				// Force reflow so the CSS animation restarts.
				void progress.offsetWidth;
				progress.classList.add('is-running');
			}
			saveIndex();
		}

		function updateClock() {
			var now = new Date();
			clock.textContent = ('0' + now.getHours()).slice(-2) + ':' + ('0' + now.getMinutes()).slice(-2);
		}

		if (clock) {
			updateClock();
			window.setInterval(updateClock, 1000);
		}

		root.style.setProperty('--tttc-tv-interval', interval + 'ms');

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

	initializeQrCodes();
	document.querySelectorAll('[data-tttc-ask-joining]').forEach(initializeJoiningSignup);
	document.querySelectorAll('.tttc-public-group-tabs').forEach(initializeGroupTabs);
	document.querySelectorAll('[data-tttc-tv]').forEach(initializeTv);
}());