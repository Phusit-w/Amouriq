# 05: Rewards catalogue and redeeming points for a coupon

**What to build:** A "ของรางวัล" tab where the admin defines Rewards (label, points price, a coupon of ฿ or % with minimum spend and days valid, on/off). On My Account > แต้มสะสม the customer sees the Rewards they can afford and redeems one: the points are deducted at once (a `redeem` ledger entry) and a single-use WooCommerce coupon restricted to the customer's email is created and shown with its expiry date. The confirm button states whether unused points come back (open question 1).

**Blocked by:** none

**Status:** done locally (not uploaded, not in the deploy-round2 kit). My Account and the admin tab were not opened in a browser

- [x] Redeeming with too few points, or twice quickly (double click, two tabs), deducts at most once per click that succeeds and never goes below zero (the same form submitted twice and too few points were run; a truly parallel double click was not)
- [x] The coupon works only with the customer's email, once, and expires on the stated day (checked with the real WooCommerce cart by CLI, not in a browser)
- [x] The coupon competes with Member Privilege as ADR-0006 says (larger wins), checked in the real WooCommerce cart by CLI
- [x] The redemption shows in the ledger and in the reports; the admin can see which coupon belongs to which redemption
- [x] PHPUnit for the arithmetic and limits; the WooCommerce flow checked locally with throwaway users

## Notes (implementation)

- Pure: `amouriq_points_core_rewards_clean()`, `amouriq_points_core_check_redeem()`, `amouriq_points_core_coupon_spec()`, `amouriq_points_core_redeem_confirm()` in `points-core.php` (tests `tests/PointsRewardsTest.php`); a `redeem` ledger entry counts as points used in the report. Glue at the end of `amouriq-points.php`: `amouriq_points_redeem()` (all or nothing: the coupon is created first, points are taken only when it exists), the My Account blocks (`แลกแต้มเป็นคูปอง`, `คูปองที่แลกแล้ว`), and the admin section (add / on-off / delete, plus the last 20 redemptions with their coupon and whether it was used).
- Guards against redeeming twice: a per-member lock in user meta (30 seconds), a one-time token per form (a transient for an hour) and the balance check. A second submit of the same form is refused with a message pointing to the coupon list.
- The coupon: one use, one per user, restricted to the member email, expires at the end of the day N days later, code `PTS-XXXXXXXX`, post meta `amouriq_points_user` and `amouriq_points_reward`. Points are not returned if it expires unused (stated on the confirm button and in the admin text; the owner has not confirmed this rule).
- Run locally against real WooCommerce (throwaway members, product and coupons; deleted after): redeem fixed (balance 500 to 300), same token refused, a new token worked, not enough points, disabled and unknown rewards refused, percent reward, ledger entries; another member and a guest with no email cannot apply the coupon (WooCommerce shows its own English message about the email), the owner can; with Member Privilege on and the 100 baht coupon on a 1000 cart no member fee line appears (the coupon is larger, ADR-0006); My Account and admin screens render and a member with 0 points sees disabled buttons.
- Not checked: a truly parallel double click (the lock and token were exercised one after another), and the screens in a browser or on a phone.
- Gift rewards are ticket 06.
