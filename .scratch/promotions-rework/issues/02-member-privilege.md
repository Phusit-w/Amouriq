# 02: Member Privilege 5% with larger-discount-wins against Deals

**What to build:** Any customer with an account gets 5% off Regular Price items from their first order, when the item total is at least ฿299 (counted before discounts, shipping excluded, Sale Price items counted). Sale Price items get nothing from it. It never adds to a Deal: per cart line the larger discount wins, so the Deal's item gets the Deal and the other items still get 5%. The cart says which discount was applied and why. A customer who registers during checkout gets it in that same checkout. Follows ADR-0006.

**Blocked by:** 01

**Status:** ready-for-agent

- [ ] 5% applies to Regular Price lines only, with no maximum, once the ฿299 minimum spend is met (boundary: 298 no, 299 yes)
- [ ] Sale Price lines are never discounted by it
- [ ] With a Deal on one line, that line gets the larger of the two and every other line still gets 5%
- [ ] Logged-out visitors see no Member Privilege; a customer registering during checkout gets it on that order
- [ ] Cart and checkout show a clear line saying which discount applied
- [ ] PHPUnit covers the cases above through the pricing core
- [ ] Notes record that any Points & Rewards membership levels active on the live site must be switched off before launch

## Comments

- From ticket 01 review: the pricing core currently returns one discount total per Deal. This ticket must extend it to report the discount per cart line, since the comparison with Member Privilege is per line.
