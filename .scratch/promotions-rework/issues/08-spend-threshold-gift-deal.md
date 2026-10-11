# 08: Spend-threshold gift Deal

**What to build:** An admin can create a Deal "spend X baht, choose a free gift": minimum spend, the gift list (ticked from the live product list), how many gifts the customer may choose, start and end date and time, and on/off. In the cart the customer chooses from the eligible gifts, using the same cart choice as ticket 07. A gift is a real cart line at ฿0 with its quantity locked, so stock is decremented through the normal order flow and the gift appears on the order. The customer may remove it. It is removed automatically, with a notice, when the item total drops below the minimum spend or the gift runs out of stock. Gifts do not count toward minimum spend or other Deals' quantities. Member Privilege can be used alongside a gift Deal. Example: spend ฿1,500, choose one free Castile Soap 30 ml of three scents.

**Blocked by:** 07

**Status:** ready-for-agent

- [ ] Admin can create, edit, switch off and delete the gift Deal with all fields
- [ ] The cart shows all eligible gifts when the threshold is met; out-of-stock gifts cannot be chosen
- [ ] The chosen gift is a ฿0 line, quantity locked, and real stock is decremented when the order is placed
- [ ] Dropping below the threshold, or the gift selling out, removes the gift with a notice
- [ ] The customer can decline by removing the gift; checkout is never blocked, with a prominent notice while an entitlement is unused
- [ ] Gifts are excluded from the minimum spend and from other Deals' counts, and Member Privilege still applies to paid items
- [ ] PHPUnit covers the entitlement logic; stock decrement and cart behaviour are checked in a real browser
