# 03: Payment method and shipping province conditions

**What to build:** Two more Condition types that only exist at checkout: `payment_method` (chosen gateway id) and `shipping_province` (from the shipping address, or billing when there is none). Unknown before the shopper picks them; the cart shows "will apply when you choose X". The checkout totals refresh when the payment method changes.

**Blocked by:** 01, 02

**Status:** ready-for-agent (after the owner answers open question 1)

- [ ] Both types match, do not match, and are unknown in PHPUnit
- [ ] In a real checkout (browser), changing the payment method adds or removes the discount without a page reload
- [ ] Omise card and bank transfer gateway ids are listed by name in the picker, not typed by hand
