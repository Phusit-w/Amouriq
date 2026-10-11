# 07: Conditions on native WooCommerce coupons

**What to build:** A "เงื่อนไขเพิ่มเติม" panel on the coupon edit screen using the same Condition set, enforced in `woocommerce_coupon_is_valid` with a Thai refusal message that says which condition failed. The existing "ลูกค้าใหม่เท่านั้น" checkbox stays as it is.

**Blocked by:** 01, 02

**Status:** done locally (not uploaded). The coupon edit screen was not opened in a browser

- [x] A coupon with a role, order-count, weekday or cart-quantity condition is accepted and refused as set, with a clear message
- [x] A coupon with no extra conditions behaves exactly as today
- [x] The panel saves and reloads its values; only users with `manage_woocommerce` can change them
- [ ] Checked in the real cart in a browser (checked by CLI against the real WooCommerce cart only)

## Notes (implementation)

- New mu-plugin `amouriq-coupon-conditions.php` (panel under Usage restriction, save on `woocommerce_coupon_options_save`, check on `woocommerce_coupon_is_valid`, stored as coupon meta `amouriq_conditions`). It reuses the Deal engine: evaluator and `amouriq_cond_explain()` (Thai reason, tests `tests/BogoConditionsExplainTest.php`), `amouriq_bogo_condition_context()` and `amouriq_bogo_build_conditions()`, so it must be uploaded together with the Deal files.
- A refusal names only the conditions not met (all of them for an "any" set). A condition that cannot be judged yet says what to do ("sign in or type your email at checkout"). A bad row on save is reported on the coupon page and the earlier conditions are kept.
- Run locally against real WooCommerce with throwaway coupons, product and a guest cart (deleted after): plain coupon applied; history condition refused with the sign-in/email message; quantity condition refused for 1 item and applied for 3; every-weekday applied; wholesale-role refused; save handler stores, keeps on a bad row, deletes when empty; panel renders pre-filled with no PHP messages.
- Not checked: the panel in the real coupon screen (layout and its small script).
