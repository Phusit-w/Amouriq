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

	function handle(e) {
		var cart = e.target.closest && e.target.closest('.ct-header-cart');
		if (!cart || cart.closest('#offcanvas')) return;
		ensurePlacement(cart);
	}

	// mouseover (bubbles) covers hover; focusin covers the site's own
	// `:focus-within` fallback for keyboard/touch (see interactions.scss).
	document.addEventListener('mouseover', handle);
	document.addEventListener('focusin', handle);
})();
