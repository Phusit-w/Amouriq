/**
 * amq-cart-dropdown-fix.js
 *
 * Fixes: hovering the header cart icon never shows Blocksy's mini-cart
 * dropdown, even with items in the cart, on every page (reported against
 * /cart-page/ and confirmed on /castile/ too — not page-specific).
 *
 * Root cause (verified via headless Chrome + CDP, reading Blocksy's own
 * source rather than the minified bundle):
 *
 * Blocksy's header renders TWO `.ct-header-cart` elements on every page —
 * the real one in the sticky top header, and a second copy inside the
 * mobile off-canvas menu (#offcanvas), which is intentionally dropdown-less
 * (see blocksy/static/sass/.../cart-header-element-lazy.scss:
 * `#offcanvas { .ct-cart-content { display: none; } }`).
 *
 * The dropdown only becomes visible once Blocksy's popper-elements module
 * (blocksy/static/js/frontend/popper-elements.js) stamps a `data-placement`
 * attribute onto `.ct-cart-content` — the hover CSS
 * (`.ct-header-cart:hover [data-placement] { opacity:1; visibility:visible }`)
 * is otherwise permanently opacity:0/visibility:hidden. Blocksy decides
 * whether to even attempt this (blocksy/static/js/main.js, the cart popper
 * entry point) with:
 *
 *     const maybeCart = document.querySelector(
 *       '.ct-header-cart > .ct-cart-content:not([data-count="0"])'
 *     )
 *     if (maybeCart && !maybeCart.closest('#offcanvas')) {
 *       popperEls.push('.ct-header-cart > .ct-cart-item')
 *     }
 *
 * `document.querySelector` returns only the FIRST match in DOM order — and
 * the off-canvas copy of `.ct-cart-content` happens to come first in this
 * theme's markup. So whenever the cart is non-empty (data-count !== "0"),
 * `maybeCart` resolves to the off-canvas copy, `.closest('#offcanvas')` is
 * truthy, the condition is false, and the REAL header cart is silently
 * never added to popperEls at all — `data-placement` never gets set, the
 * dropdown never becomes visible, for the rest of that page view. Confirmed
 * with a real Add to Cart + hover simulation: data-count updates to "1"
 * correctly, but data-placement never appears. Manually setting
 * data-placement on the real cart's dropdown and re-hovering shows it
 * perfectly (display:block, opacity:1, visibility:visible) — so the CSS/
 * hover mechanism itself is fine; only Blocksy's own detection is broken.
 *
 * This is a bug in Blocksy core (theme file), not something to patch there
 * directly — instead, re-implement just the missing piece here, scoped
 * correctly to the one real (non off-canvas) header cart.
 */
(function () {
	'use strict';

	function computePlacement(reference, target) {
		var referenceRect = reference.getBoundingClientRect();
		var targetRect = target.getBoundingClientRect();
		var initialPlacement = referenceRect.left > window.innerWidth / 2 ? 'left' : 'right';
		var placement = initialPlacement;

		var offset = parseFloat(
			getComputedStyle(target).getPropertyValue('--theme-submenu-inline-offset')
		);
		if (isNaN(offset)) offset = -20;

		if (targetRect.width > window.innerWidth - referenceRect.left + offset) {
			placement = 'left';
		}
		if (referenceRect.right - offset - targetRect.width < 0) {
			placement = 'right';
		}

		return placement;
	}

	// Re-derived every time (cheap: a couple of getBoundingClientRect calls)
	// rather than cached — WooCommerce's fragment refresh replaces
	// .ct-cart-content wholesale after every add/remove, which would
	// otherwise leave a stale or missing data-placement on the new node.
	function ensurePlacement(cartEl) {
		var item = cartEl.querySelector(':scope > .ct-cart-item');
		var content = cartEl.querySelector(':scope > .ct-cart-content');
		if (!item || !content) return;
		content.setAttribute('data-placement', computePlacement(item, content));
	}

	function realCarts() {
		return Array.prototype.filter.call(
			document.querySelectorAll('.ct-header-cart'),
			function (cart) { return !cart.closest('#offcanvas'); }
		);
	}

	function handle(e) {
		var cart = e.target.closest && e.target.closest('.ct-header-cart');
		if (!cart || cart.closest('#offcanvas')) return;
		ensurePlacement(cart);
	}

	// mouseover (bubbles) covers hover; focusin covers the site's own
	// `:focus-within` fallback for keyboard/touch (see interactions.scss).
	document.addEventListener('mouseover', handle);
	document.addEventListener('focusin', handle);

	function forceVisible(cartEl) {
		var content = cartEl.querySelector(':scope > .ct-cart-content');
		if (!content) return;
		content.style.setProperty('opacity', '1', 'important');
		content.style.setProperty('visibility', 'visible', 'important');
	}
	function releaseForcedVisible(cartEl) {
		var content = cartEl.querySelector(':scope > .ct-cart-content');
		if (!content) return;
		content.style.removeProperty('opacity');
		content.style.removeProperty('visibility');
	}

	realCarts().forEach(function (cart) {
		cart.addEventListener('mouseenter', function () { cart.__amqHovering = true; });
		cart.addEventListener('mouseleave', function () {
			cart.__amqHovering = false;
			if (!cart.__amqHoldOpen) releaseForcedVisible(cart);
		});
	});

	// Clicking "remove" on an item INSIDE the open mini-cart dropdown swaps
	// `.ct-cart-content` for a brand-new node (WooCommerce's add-to-cart.js
	// AddToCartHandler.updateFragments does `$(key).replaceWith(value)`),
	// which breaks the dropdown two ways at once (each verified live, real
	// headed Chrome and real synthetic mouse input, not just JS-dispatched
	// events or headless-only quirks - a first attempt here that only
	// re-stamped data-placement and relied on the user's mouse naturally
	// twitching afterwards turned out not to be enough: reported as still
	// broken on the live site, where a real click often leaves the pointer
	// perfectly still):
	//
	// - The new node has no data-placement yet - `handle()` above never
	//   reruns for it, since the swap fires no native mouseover/focusin (the
	//   pointer never "enters" anything new from the browser's point of
	//   view).
	// - Chrome also synthesizes a mouseleave for the removed element - even
	//   though the pointer itself never moves - and won't re-fire
	//   mouseenter for whatever ends up under the pointer until an actual
	//   subsequent mousemove happens. There is no way from page JS to force
	//   a real :hover match back onto the new node without that.
	//
	// So: hold the dropdown open with an explicit inline-style override for
	// a short window after the click (long enough to survive both that and
	// any chained fragment-refresh cycles other plugins on this site trigger
	// off the same add/remove events), then hand control back to plain CSS
	// :hover. `mouseleave` stands down (doesn't release) while a hold is
	// running, since the leave it's reacting to is the spurious one from the
	// removal, not a genuine "user moved away" - and multiple clicks in a
	// row (removing several items back to back) just keep extending the one
	// running hold rather than starting overlapping ones.
	var HOLD_OPEN_MS = 1200;
	var HOLD_POLL_MS = 50;

	document.addEventListener('click', function (e) {
		var btn = e.target.closest && e.target.closest('.remove_from_cart_button');
		var cart = btn && btn.closest('.ct-header-cart');
		if (!cart || cart.closest('#offcanvas')) return;

		cart.__amqHoldOpenUntil = Date.now() + HOLD_OPEN_MS;
		if (cart.__amqHoldOpen) return; // a poll loop is already running for this cart - it'll pick up the later deadline
		cart.__amqHoldOpen = true;
		(function poll() {
			ensurePlacement(cart);
			forceVisible(cart);
			if (Date.now() < cart.__amqHoldOpenUntil) {
				setTimeout(poll, HOLD_POLL_MS);
				return;
			}
			cart.__amqHoldOpen = false;
			// The removal's own spurious mouseleave (see above) has, by now,
			// already set __amqHovering false - unless the user genuinely
			// re-entered (a real subsequent mouseenter) during the hold, in
			// which case leave this alone; CSS :hover is back in control.
			if (!cart.__amqHovering) releaseForcedVisible(cart);
		})();
	}, true);

	// WooCommerce also fires `wc_fragments_loaded` / `wc_fragments_refreshed`
	// on document.body right after replacing fragment content - but only as
	// a jQuery-internal event (verified live: a native
	// document.addEventListener never sees it, only jQuery(...).on(...)
	// does). Re-stamping data-placement there too covers fragment swaps that
	// happen without a remove-button click at all (e.g. this site's other
	// plugins triggering their own refresh cycles), so the dropdown's
	// position stays correct even outside the hold-open path above.
	function bindFragmentsLoaded() {
		if (!window.jQuery) {
			setTimeout(bindFragmentsLoaded, 50);
			return;
		}
		window.jQuery(document.body).on('wc_fragments_loaded wc_fragments_refreshed', function () {
			realCarts().forEach(ensurePlacement);
		});
	}
	bindFragmentsLoaded();
})();
