# 01: Extract the pricing core behind a test seam

**What to build:** The discount maths for the four existing Deal types (same product, product pair, category cheapest free, quantity tier) moves into one module that takes plain data (cart lines, customer facts, Deal and Coupon definitions) and returns the discount per cart line. The WooCommerce hooks become a thin layer around it. The site behaves exactly as it does today; the point is that every later ticket can be tested without WordPress running. Spec: `.scratch/promotions-rework/spec.md`.

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [x] The four existing Deal types produce the same discounts, labels and "why not applied" messages as before, for the same carts
- [x] PHPUnit tests exercise the module through its single public entry point using fake products, covering each existing type, the not-combinable flag, per-order caps, the Sale Price skip switch and per-customer limits
- [ ] The existing test-* coupons and Deals on the local site still work when checked in the browser
- [x] The tax-exclusive pricing approach for negative fees is preserved

## Comments

- Done on 2026-10-11: pricing core in `amouriq-bogo/pricing-core.php` (single entry `amouriq_bogo_price()`), WooCommerce wrapper in `amouriq-bogo-deals.php`, 43 PHPUnit tests pass.
- **Browser check of the existing test-* coupons and Deals is still open**: the local site database was not running, so it could not be done. Do it first thing when the local site is up (category Deals given as a term ID are the one regression this review caught and fixed; check one).
- Deliberately left for later tickets: the core returns one discount total per Deal, not a per-line breakdown (ticket 02 needs that for the per-line comparison with Member Privilege), and it takes no Coupon definitions yet (ticket 03). Tax-exclusive pricing stays in the wrapper; the core takes the prices as given.
- Small difference to be aware of: coupon codes now compare with a lower-case trim, not WooCommerce's full code formatting.
