/**
 * amq-header-search.js
 *
 * Sitewide: wires up the header's search-icon toggle -> expandable panel,
 * and marks the current-page link in the header nav as active.
 * Works against every header variant (#amq-header, #amq-header-static,
 * .amq-shop-header) since it looks up the panel/toggle by class, not by
 * a specific header ID.
 *
 * Loaded by wp-content/mu-plugins/amouriq-header-search.php.
 */
(function () {
	function closestHeader(el) {
		return el.closest('#amq-header, #amq-header-static, .amq-shop-header');
	}

	document.querySelectorAll('.amq-search-toggle').forEach(function (toggle) {
		var header = closestHeader(toggle);
		if (!header) return;
		var panel = header.querySelector('.amq-search-panel');
		if (!panel) return;
		var input = panel.querySelector('input[type="search"]');
		var closeBtn = panel.querySelector('.amq-search-panel__close');

		function open() {
			header.classList.add('search-open');
			panel.classList.add('is-open');
			toggle.setAttribute('aria-expanded', 'true');
			if (input) { setTimeout(function () { input.focus(); }, 60); }
		}
		function close() {
			header.classList.remove('search-open');
			panel.classList.remove('is-open');
			toggle.setAttribute('aria-expanded', 'false');
		}

		toggle.addEventListener('click', function (e) {
			e.preventDefault();
			if (panel.classList.contains('is-open')) { close(); } else { open(); }
		});
		if (closeBtn) {
			closeBtn.addEventListener('click', function (e) { e.preventDefault(); close(); });
		}
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') close();
		});
	});

	// current-page nav highlight
	var path = window.location.pathname.replace(/\/+$/, '') || '/';
	document.querySelectorAll('.amq-header-nav a, .amq-shop-header__nav a').forEach(function (link) {
		var linkPath = link.pathname.replace(/\/+$/, '') || '/';
		if (linkPath === path) {
			link.classList.add('is-active-nav');
		}
	});
})();
