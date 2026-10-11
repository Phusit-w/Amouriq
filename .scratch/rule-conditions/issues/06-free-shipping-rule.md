# 06: Free-shipping rule driven by Conditions

**What to build:** A Deal type `free_shipping` that discounts nothing but turns Flexible Shipping to free while its Conditions match (reusing the filter in `amouriq-coupon-free-shipping.php`). Lets the owner recreate the enabled Discount Rules free-shipping rules (coupon code, spend, weekday) without the plugin.

**Blocked by:** 01, 02 (and the owner's answer to open question 2)

**Status:** done locally (not uploaded). The browser check of the new admin fields is still open

- [x] A rule with a spend condition makes shipping free at the threshold and not below it (checked by CLI against real Flexible Shipping in the local cart, not in a browser)
- [x] A rule triggered by a coupon code makes shipping free only while that code is in the cart
- [x] Shown in the rule list (the list text and "used" count are built; the list was not opened in a browser)
- [x] Does not touch the ฿299 Flexible Shipping rule unless the owner chooses to move it here

## Notes (implementation)

- Type `free_shipping` (optional `min_spend`, plus the usual trigger coupon, dates, Conditions, new-customer-only and per-customer limit). Pure check `amouriq_bogo_core_free_shipping()` in `pricing-core.php` (tests `tests/BogoFreeShippingTest.php`); it reuses `amouriq_bogo_core_stop_reason()`. The cart shows "buy N more for free shipping" when a minimum is set.
- `amouriq-bogo-deals.php` hooks `flexible-shipping/shipping-method/is-free-shipping` itself, because Flexible Shipping works out rates before the cart fees run. Orders record a live free-shipping Deal as 0.0 under its id in `amouriq_bogo_discounts`, so the list counts it as used and the per-customer limit works.
- Run locally against real WooCommerce + Flexible Shipping (Thailand zone, a ฿100 throwaway product, throwaway coupon; one process per case because `amouriq_bogo_deals()` caches per process): no deal = ฿40; deal with no limits = free; min 150 = ฿40 at ฿100 and free at ฿200; coupon deal = ฿40 without and free with the coupon; weekday condition = free; history condition for a guest = ฿40; disabled deal = ฿40.
- Does not touch the ฿299 Flexible Shipping rule; that stays where it is. Needs `amouriq-bogo-deals.php`, `amouriq-bogo-admin.php` and `amouriq-bogo/pricing-core.php` (+ `conditions.php`) uploaded together.
