/**
 * CASTILE sales page (ticket 03) — scroll-triggered reveal, count-up, and
 * chart-bar-fill animations for sections 00-09. See amq-castile.css for
 * why this targets specific ".elementor-element-<id>" selectors rather
 * than a custom class (Elementor's custom-class Advanced control doesn't
 * render in this install — verified against the plugin source) and
 * docs/adr/0001-castile-sales-page-built-in-elementor.md for why this
 * exists as a small standalone script rather than editable Elementor
 * fields: it only ever touches presentation (adding a class, animating
 * already-rendered text), never content, so the page underneath stays
 * fully Elementor-editable.
 *
 * Each element animates once only (IntersectionObserver + unobserve on
 * fire). Respects prefers-reduced-motion: reduce by skipping straight to
 * the final state (the CSS media query handles the visual side; this file
 * just avoids the count-up tween in that case too).
 */
(function () {
	'use strict';

	var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	// Element ids captured from the single script run that wrote this
	// page's _elementor_data (ticket 03) — stable across content edits in
	// the Elementor editor (an element keeps its id unless deleted and
	// recreated), but if that ever happens for one of these specific
	// elements, its animation silently stops rather than erroring; re-run
	// the ticket 03 build notes' id-capture step to refresh this list.
	var IDS = {
		mask: ['46d6b39', '8565c5d', '178d758', 'f60a040', '1ae4342', '1922e0e', 'cb54ad0', 'a10e2a5', 'a594814', 'd411e14', '49c49a3', '815f34b', 'fd11604'],
		count: ['f9b9b55', '5fac264', '190f66d', 'b3b4491', '2a05e74', '1ba9d18', 'ef1e6e0', '500a59f', '6357f22', 'a653aba'],
		fill: ['7466111', 'b0edbed', '9dd8676', '678f1c3', 'cce8ca8', '7a8de10', 'db7213b', '6d0f07f'],
	};

	function elements(ids) {
		return ids
			.map(function (id) { return document.querySelector('.elementor-element-' + id); })
			.filter(Boolean);
	}

	function revealOnce(els, onIntersect, threshold) {
		if (!els.length) return;

		if (reduceMotion || typeof IntersectionObserver === 'undefined') {
			els.forEach(function (el) { onIntersect(el); });
			return;
		}

		var observer = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (!entry.isIntersecting) return;
				onIntersect(entry.target);
				observer.unobserve(entry.target);
			});
		}, { threshold: threshold });

		els.forEach(function (el) { observer.observe(el); });
	}

	// Mask reveal — just needs the .is-visible class; CSS drives the motion.
	revealOnce(elements(IDS.mask), function (el) {
		el.classList.add('is-visible');
	}, 0.25);

	// Chart bar fill — same mechanism, separate selector/threshold per the
	// design brief (bars reveal after their row scrolls further into view
	// than a heading needs to).
	revealOnce(elements(IDS.fill), function (el) {
		el.classList.add('is-visible');
	}, 0.4);

	// Count-up — parses the element's own rendered text (e.g. "+13.37%",
	// "−10.67%") so the widget's text stays the single source of truth;
	// no separate data-attribute to keep in sync.
	var COUNT_PATTERN = /^([+\-−])?\s*([\d.]+)(%?)$/;

	revealOnce(elements(IDS.count), function (el) {
		var raw = el.textContent.trim();
		var match = raw.match(COUNT_PATTERN);
		if (!match) return; // not a parseable number (e.g. the "✓" card) — leave as-is
		if (reduceMotion) return; // text is already the final value — nothing to animate

		var sign = match[1] || '';
		var target = parseFloat(match[2]);
		var decimals = (match[2].split('.')[1] || '').length;
		var suffix = match[3] || '';
		var duration = 1400;
		var start = null;

		function frame(ts) {
			if (start === null) start = ts;
			var p = Math.min((ts - start) / duration, 1);
			var eased = 1 - Math.pow(1 - p, 3); // cubic ease-out, per the design brief
			var value = (target * eased).toFixed(decimals);
			el.textContent = sign + value + suffix;
			if (p < 1) requestAnimationFrame(frame);
			else el.textContent = raw; // land exactly on the original text
		}

		requestAnimationFrame(frame);
	}, 0.4);
})();
