# 03: Coupon versus Member Privilege, whole cart

**What to build:** When a Coupon is in the cart, Member Privilege and the Coupon are compared over the whole cart and only the larger applies; they never add together. Deals and Coupons keep stacking as before. The cart says which one was used.

**Blocked by:** 02

**Status:** ready-for-agent

- [ ] A coupon worth more than the Member Privilege total replaces it; a smaller coupon is not applied (or Member Privilege is) so the customer always gets the better offer
- [ ] Removing the coupon brings Member Privilege back
- [ ] Deals still stack with a Coupon as before
- [ ] The cart shows which discount won and why
- [ ] PHPUnit covers both directions and ties

## Comments

- From ticket 01 review: the pricing core takes no Coupon definitions yet; this ticket adds them to its input.
