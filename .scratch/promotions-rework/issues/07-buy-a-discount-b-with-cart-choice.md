# 07: Buy A, discount B, with the customer choosing B in the cart

**What to build:** The product-pair Deal becomes "buy N of product A, then M of product B get a percent or baht discount". The admin ticks several variations as B. When the customer qualifies, the cart lists every eligible B (out-of-stock ones cannot be chosen) and lets the customer pick up to M of them; the pick becomes a normal cart line and gets the discount. Not choosing never blocks checkout, but a prominent notice shows in the cart and at checkout. For the chosen B, Member Privilege is not added: the larger discount wins on that line, and other lines keep their 5%. Example: buy 1 of A, choose one Castile Soap 250 ml scent at 20% off.

**Blocked by:** 05

**Status:** ready-for-agent

- [ ] Admin labels read "buy how many of product A" and "how many of product B get the discount", with percent or baht
- [ ] Several B variations can be ticked in one Deal
- [ ] The cart shows all eligible B items when the Deal is met, out-of-stock ones disabled, with live stock checks
- [ ] The chosen B gets the discount; the customer can change or remove the choice
- [ ] Checkout is not blocked when no choice was made; a notice shows in the cart and at checkout
- [ ] The larger-wins rule holds per line against Member Privilege
- [ ] PHPUnit covers the calculation; the cart choice is checked in a real browser
