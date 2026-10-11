# 05: Rewards catalogue and redeeming points for a coupon

**What to build:** A "ของรางวัล" tab where the admin defines Rewards (label, points price, a coupon of ฿ or % with minimum spend and days valid, on/off). On My Account > แต้มสะสม the customer sees the Rewards they can afford and redeems one: the points are deducted at once (a `redeem` ledger entry) and a single-use WooCommerce coupon restricted to the customer's email is created and shown with its expiry date. The confirm button states whether unused points come back (open question 1).

**Blocked by:** none

**Status:** ready-for-agent (the expiry rule waits for open question 1)

- [ ] Redeeming with too few points, or twice quickly (double click, two tabs), deducts at most once per click that succeeds and never goes below zero
- [ ] The coupon works only with the customer's email, once, and expires on the stated day; WooCommerce applies it in a real cart (browser)
- [ ] The coupon competes with Member Privilege as ADR-0006 says (larger wins), checked in the cart
- [ ] The redemption shows in the ledger and in the reports; the admin can see which coupon belongs to which redemption
- [ ] PHPUnit for the arithmetic and limits; the WooCommerce flow checked locally with throwaway users
