# 02: Member Privilege 5% with larger-discount-wins against Deals

**What to build:** Any customer with an account gets 5% off Regular Price items from their first order, when the item total is at least ฿299 (counted before discounts, shipping excluded, Sale Price items counted). Sale Price items get nothing from it. It never adds to a Deal: per cart line the larger discount wins, so the Deal's item gets the Deal and the other items still get 5%. The cart says which discount was applied and why. A customer who registers during checkout gets it in that same checkout. Follows ADR-0006.

**Blocked by:** 01

**Status:** done (browser check on the local site still open)

- [x] 5% applies to Regular Price lines only, with no maximum, once the ฿299 minimum spend is met (boundary: 298 no, 299 yes)
- [x] Sale Price lines are never discounted by it
- [x] With a Deal on one line, that line gets the larger of the two and every other line still gets 5%
- [x] Logged-out visitors see no Member Privilege; a customer registering during checkout gets it on that order
- [x] Cart and checkout show a clear line saying which discount applied
- [x] PHPUnit covers the cases above through the pricing core
- [x] Notes record that any Points & Rewards membership levels active on the live site must be switched off before launch

## Comments

- From ticket 01 review: the pricing core currently returns one discount total per Deal. This ticket must extend it to report the discount per cart line, since the comparison with Member Privilege is per line.

## Notes (implementation)

- Core: `amouriq_bogo_price()` now also returns `per_line`; `amouriq_bogo_cart_discounts()` runs all Deals plus `amouriq_bogo_member_privilege()`. Member Privilege is a separate fee, equal to the part of 5% that exceeds the Deal's discount on each Regular Price line. Tests: `tests/BogoMemberPrivilegeTest.php`.
- Minimum spend is judged on the displayed price (`price_incl_tax`), the discount on the tax-exclusive price. Unverified: that ฿299 is meant tax-inclusive (matches the free-shipping threshold).
- "Registers during checkout": member if logged in or `createaccount` is ticked (form or refresh post_data). A logged-out visitor is not a member before that. Needs a browser check.
- **Not yet done (ticket 03)**: Member Privilege still stacks with native Coupons; whole-cart larger-wins comes later.
- **Launch gate**: any Points & Rewards membership levels active on the live site must be switched off before this goes live, otherwise customers get both.
- Not covered by automated tests: the WooCommerce layer (fee label, account detection). Check in the browser: logged out (no fee), logged in (fee), a Deal item plus other items, a Sale Price item, 298 vs 299 cart.
