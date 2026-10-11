# 08: Spend-threshold gift Deal

**What to build:** An admin can create a Deal "spend X baht, choose a free gift": minimum spend, the gift list (ticked from the live product list), how many gifts the customer may choose, start and end date and time, and on/off. In the cart the customer chooses from the eligible gifts, using the same cart choice as ticket 07. A gift is a real cart line at ฿0 with its quantity locked, so stock is decremented through the normal order flow and the gift appears on the order. The customer may remove it. It is removed automatically, with a notice, when the item total drops below the minimum spend or the gift runs out of stock. Gifts do not count toward minimum spend or other Deals' quantities. Member Privilege can be used alongside a gift Deal. Example: spend ฿1,500, choose one free Castile Soap 30 ml of three scents.

**Blocked by:** 07

**Status:** ready-for-human (tested locally; cart box on a phone not checked)

- [x] Admin can create, edit, switch off and delete the gift Deal with all fields
- [x] The cart shows all eligible gifts when the threshold is met; out-of-stock gifts cannot be chosen
- [x] The chosen gift is a ฿0 line, quantity locked, and real stock is decremented when the order is placed
- [x] Dropping below the threshold, or the gift selling out, removes the gift with a notice
- [x] The customer can decline by removing the gift; checkout is never blocked, with a prominent notice while an entitlement is unused
- [x] Gifts are excluded from the minimum spend and from other Deals' counts, and Member Privilege still applies to paid items
- [x] PHPUnit covers the entitlement logic; stock decrement and cart behaviour are checked in a real browser

## Notes (implementation)

- New Deal type `spend_gift` (`min_spend`, `gift_ids`, `gift_qty`, dates, on/off). The core filters gift lines (`is_gift`, `gift_deal`) out of every other Deal and out of Member Privilege's minimum, and says how many gifts are allowed and how many choices are left; `amouriq_bogo_core_gifts_to_remove()` says which gift lines must go. Tests: `tests/BogoGiftTest.php`.
- `amouriq-bogo-cart-choice.php` (same box as ticket 07): a pick adds a real cart line tagged `amq_gift`; before totals the line is made free and locked to quantity 1, and dropped with a notice when the minimum is no longer met, the Deal is off/outside its dates, more gifts than allowed are in the cart, or the gift is out of stock. Declining = removing the line. The same gift item cannot be picked twice (so "how many gifts" means how many different items).
- Local run (throwaway admin and Deal, removed afterwards): real admin form; 1 item (฿779) no box, 2 items (฿1,558) box with 2 in stock and 1 out of stock (disabled); the pick is a ฿0 line without a quantity box, member 5% still applies to the paid item; lowering the paid quantity below ฿1,500 removed the gift with the notice. By PHP with real WooCommerce: an order built from that cart has the gift as a 1 x ฿0 item and the gift's stock went 5 to 4; a gift that sold out while in the cart was removed with a notice. Test stock and order restored/deleted.
- Live: upload `amouriq-bogo-cart-choice.php` with the changed admin, deals and core files.
