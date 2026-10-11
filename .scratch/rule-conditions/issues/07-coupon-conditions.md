# 07: Conditions on native WooCommerce coupons

**What to build:** A "เงื่อนไขเพิ่มเติม" panel on the coupon edit screen using the same Condition set, enforced in `woocommerce_coupon_is_valid` with a Thai refusal message that says which condition failed. The existing "ลูกค้าใหม่เท่านั้น" checkbox stays as it is.

**Blocked by:** 01, 02

**Status:** ready-for-agent

- [ ] A coupon with a role, order-count, weekday or cart-quantity condition is accepted and refused as set, with a clear message
- [ ] A coupon with no extra conditions behaves exactly as today
- [ ] The panel saves and reloads its values; only users with `manage_woocommerce` can change them
- [ ] Checked in the real cart in a browser, not only by CLI
