# 03: Admin adjusts points, customer list, per-customer ledger

**What to build:** A "ลูกค้า" tab in WooCommerce > แต้มสะสม: a searchable list (name, email, balance, points expiring soon) and a per-customer page with the full ledger and an Adjustment form (add or deduct, amount, required reason). Every adjustment is a ledger entry with the admin's user id and reason; a deduction cannot take the balance below zero.

**Blocked by:** none

**Status:** done locally (not uploaded). Browser pass of the admin screen still to do

- [x] Only users with `manage_woocommerce` can see or change it; the form is nonce-protected
- [ ] Add and deduct update the balance and show in the customer's My Account ledger with the reason (My Account display not yet checked)
- [x] A deduction larger than the balance is refused with a clear message
- [x] Deducting takes the points that expire first, like a normal use
- [ ] PHPUnit for the rule; list, search and the form checked in a browser against the local site (browser part not done)

## Notes (implementation)

- Rule: `amouriq_points_core_check_adjustment()` (tests in PointsTest). Handler, `by` on the ledger entry, "โดย" column and the "สมาชิกที่มีแต้ม" list (20 per page, search) are in `amouriq-points.php`.
- Run locally against real WordPress with a throwaway user (deleted after): +120, refused deduct of 200, refused empty reason, -20 recorded with the admin id, list and ledger page render. The admin screen has not been opened in a browser yet.
