# 06: Gift rewards (free product for points)

**What to build:** A Reward kind `gift`: redeeming creates a personal coupon that triggers a zero-minimum `spend_gift` Deal for the chosen product, so the customer picks the gift in the cart with the existing gift choice box. Stock follows the product's own stock unless the owner asks for a separate limit (open question 3).

**Blocked by:** 05

**Status:** ready-for-agent (after the owner answers open question 3)

- [ ] Redeeming adds exactly one free gift entitlement; it disappears with the coupon, as a gift does today when its trigger is gone
- [ ] An out-of-stock gift cannot be redeemed (the button is disabled with the reason), and one that sells out before checkout is refused with a message
- [ ] The gift line shows ฿0 and is tagged like other gifts, and the order note says it came from points
- [ ] No points are lost if the gift cannot be added (the redeem is all-or-nothing)
