# 02: Bonus points (sign-up, first order, verified review)

**What to build:** Three optional one-time bonuses, each with its own on/off and amount in the admin screen: on creating an account, on a New customer's first Completed order, and on an approved verified-purchase review (once per product, with a yearly cap). Each is a ledger entry of its own kind and can never be given twice for the same trigger.

**Blocked by:** none

**Status:** done locally (not uploaded and NOT in the deploy-round2 kit). The admin section was not opened in a browser

- [x] Each bonus is given once and only once, also when the trigger event fires twice (order status flipped back and forth, review edited and re-approved)
- [x] A review by someone who never bought the product, or an unapproved review, gives nothing
- [x] Switching a bonus off stops new bonuses and leaves earned ones alone
- [x] Worked in PHPUnit for the rules; the three triggers exercised against real WooCommerce locally with throwaway users, orders and reviews (deleted after)

## Notes (implementation)

- Pure: `amouriq_points_core_bonus_clean()`, `amouriq_points_core_review_bonus()`, `amouriq_points_core_type_names()` in `points-core.php` (tests `tests/PointsBonusTest.php`). Glue at the end of `amouriq-points.php`: `user_register` (customer role only, once per account, user meta flag), `woocommerce_order_status_completed` priority 20 (no other Completed order of the customer and never given before; order meta `_amouriq_points_bonus_first`; taken back with a cancelled/failed/refunded order), `comment_post` and `transition_comment_status` (approved `review` by a customer who bought the product per `wc_customer_bought_product()`; comment meta marks it given; ledger entry carries `ref` = product ID).
- Defaults: all three OFF; amounts 20 / 30 / 10 points; review cap 5 per 12 months (the amounts and cap were the proposed defaults, not yet confirmed by the owner). The ledger lists show Thai names for the new entry types.
- Run locally against real WooCommerce (throwaway users, products, orders, reviews; deleted after; mail blocked): sign-up +20 once, non-customer 0; no bonus on Processing, +30 on Completed, none on a second order, taken back when the first order is cancelled; review +10 for a bought product once per product, cap of 2 respected, an unbought product and a late-approved review over the cap give nothing; all off gives nothing; the admin section saves and renders.
- Note: `wc_customer_bought_product()` does not count cancelled orders, so cancelling the order that held a product also stops reviews of it from earning.
