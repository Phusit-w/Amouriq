# 06: Free-shipping rule driven by Conditions

**What to build:** A Deal type `free_shipping` that discounts nothing but turns Flexible Shipping to free while its Conditions match (reusing the filter in `amouriq-coupon-free-shipping.php`). Lets the owner recreate the enabled Discount Rules free-shipping rules (coupon code, spend, weekday) without the plugin.

**Blocked by:** 01, 02 (and the owner's answer to open question 2)

**Status:** ready-for-agent

- [ ] A rule with a spend condition makes shipping free at the threshold and not below it (browser check on the local cart)
- [ ] A rule triggered by a coupon code makes shipping free only while that code is in the cart
- [ ] Shown in the rule list with its own kind chip; "used" counts orders that got it
- [ ] Does not touch the ฿299 Flexible Shipping rule unless the owner chooses to move it here
