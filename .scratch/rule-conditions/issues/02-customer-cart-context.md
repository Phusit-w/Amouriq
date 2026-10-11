# 02: Build the customer and cart context on the WooCommerce side

**What to build:** In `amouriq-bogo-deals.php`, gather the context the evaluator needs, lazily (callables, like `customer_uses` today): roles of the logged-in user, counted-order count, total spent and date of last counted order (by account, else by billing email, reusing the New customer order lookup), local weekday and time (site timezone), cart item count, quantity, subtotal and the product and category ids in the cart.

**Blocked by:** 01

**Status:** done locally (not uploaded)

- [x] Order history uses the same counted statuses as New customer (pending, on-hold, processing, completed)
- [x] Lookups run only for a Deal that has a condition needing them, and at most once per recalculation
- [x] Verified against the local site with throwaway users and orders (deleted after): a returning customer, a new one, a guest typing an email
- [x] Weekday and time use the site timezone, not the server's

## Notes (implementation)

- `amouriq_bogo_condition_context()` and `amouriq_bogo_customer_history()` in `amouriq-bogo-deals.php`; the pure summary is `amouriq_cond_history()` in `conditions.php` (tests `tests/BogoHistoryTest.php`). Every value is a lazy callable remembered for the request; `null` = not known yet.
- Customer matched by account and by billing email (merged by order id), counted statuses only (pending, on-hold, processing, completed). Spend = order total minus refunds. Not logged in and no email typed = unknown.
- `cart_subtotal` is the shown price (as in every minimum spend) of the paid lines, gifts left out.
- Run locally against real WooCommerce with a throwaway customer, product, category and three orders (completed 1500, pending 500, cancelled 1000; deleted after): count 2 and spend 2000 (cancelled ignored), role/email/cart/product/category conditions match and mismatch as expected, a Deal needing 2 orders applied (discount 93.46) and one needing 5 was stopped with `conditions_not_met`, a guest with no email got unknown and with the email typed got the history.
- Weekday/time use `current_time()` (site timezone). Not checked: the cart page in a browser.
