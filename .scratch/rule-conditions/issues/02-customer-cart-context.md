# 02: Build the customer and cart context on the WooCommerce side

**What to build:** In `amouriq-bogo-deals.php`, gather the context the evaluator needs, lazily (callables, like `customer_uses` today): roles of the logged-in user, counted-order count, total spent and date of last counted order (by account, else by billing email, reusing the New customer order lookup), local weekday and time (site timezone), cart item count, quantity, subtotal and the product and category ids in the cart.

**Blocked by:** 01

**Status:** ready-for-agent

- [ ] Order history uses the same counted statuses as New customer (pending, on-hold, processing, completed)
- [ ] Lookups run only for a Deal that has a condition needing them, and at most once per recalculation
- [ ] Verified against the local site with throwaway users and orders (deleted after): a returning customer, a new one, a guest typing an email
- [ ] Weekday and time use the site timezone, not the server's
