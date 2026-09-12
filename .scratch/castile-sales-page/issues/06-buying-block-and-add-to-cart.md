# 06: Buying block, sticky cart bar, and Add to Cart (Section 19)

**What to build:** The interactive scent × size buying block, wired to the real WooCommerce cart, plus the mobile sticky Add to Cart bar — the only stateful, code-driven part of the page.

**Blocked by:** 01 (Scent/size resolver function + PHPUnit harness), 02 (CASTILE page + navigation wiring)

**Status:** ready-for-agent

- [ ] Section 19 is built as a custom Elementor widget with a scent selector (Lavender / Rosemary / Rose Geranium), a size selector (100 / 250 / 500 ML), and a quantity stepper
- [ ] Selecting a scent swaps the packshot image, product name, and essential-oil label to match the corresponding real product, using `amq_castile_resolve_selection()` from ticket 01
- [ ] Selecting a size swaps price and SKU to match the corresponding real variation, with no page reload
- [ ] The page loads with Lavender pre-selected and a sensible default size pre-selected
- [ ] Clicking Add to Cart adds the exact selected product + variation + quantity to the real WooCommerce cart via the standard AJAX add-to-cart action, reusing WooCommerce's cart-fragments refresh so the header cart icon count updates without a page reload
- [ ] The customer remains on `/castile/` after adding to cart, with a visible success indicator (matching Add to Cart behavior elsewhere on the site)
- [ ] A mobile sticky Add to Cart bar appears under ~860px viewport width once `#hero` has scrolled out of view, driven by an IntersectionObserver plus a media query listener (not cached scroll offsets), and hides again when scrolled back above the hero
- [ ] The sticky bar reflects the currently selected scent, size, and price
- [ ] Selecting an out-of-stock combination (per `amq_castile_resolve_selection()`) is handled — not silently allowed to add to cart
