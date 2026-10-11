# 03: Coupon versus Member Privilege, whole cart

**What to build:** When a Coupon is in the cart, Member Privilege and the Coupon are compared over the whole cart and only the larger applies; they never add together. Deals and Coupons keep stacking as before. The cart says which one was used.

**Blocked by:** 02

**Status:** done (browser check on the local site still open)

- [x] A coupon worth more than the Member Privilege total replaces it; a smaller coupon is not applied (or Member Privilege is) so the customer always gets the better offer
- [x] Removing the coupon brings Member Privilege back
- [x] Deals still stack with a Coupon as before
- [x] The cart shows which discount won and why
- [x] PHPUnit covers both directions and ties

## Comments

- From ticket 01 review: the pricing core takes no Coupon definitions yet; this ticket adds them to its input.

## Notes (implementation)

- WooCommerce works out native coupons before fees, so the core is handed `coupon_discount` (tax-exclusive total) and Member Privilege adds only `member - coupon` when it is larger; the customer ends with the larger of the two. A tie goes to the coupon. The coupon is never removed, so removing it brings the full 5% back on the next recalculation.
- Deals are untouched and still stack with coupons; Member Privilege is compared after the per-line Deal comparison.
- Cart shows a fee label or notice saying which won. Browser check still to do: a coupon bigger and smaller than the 5%, then remove it.
- Coupon discount is taken ex-tax (WooCommerce subtracts the tax when prices include tax), same basis as Member Privilege.
