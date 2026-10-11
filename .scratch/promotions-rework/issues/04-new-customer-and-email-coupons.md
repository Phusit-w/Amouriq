# 04: Coupons for new customers and listed emails

**What to build:** An admin can create a coupon that is restricted to New customers, or to a list of emails, with percent or baht value, minimum spend, included and excluded products, dates and per-person and total usage limits. Most of this is native WooCommerce; this ticket confirms the native fields cover the brief, and adds or fixes the "new customers only" option using the glossary definition (no counted order; matched by account, or by billing email at checkout). Example: WELCOME10, 10% for the first order only.

**Blocked by:** 01

**Status:** ready-for-human (tested locally; per-person limit relies on native WooCommerce and was not exercised)

- [x] A "new customers only" coupon works for a customer with no counted order and is refused for one who has a pending, on-hold, processing or completed order; cancelled, failed and refunded orders do not count
- [x] A customer registering during checkout is matched by the billing email
- [x] A coupon limited to listed emails works only for those emails
- [x] Per-person and total usage limits, minimum spend, included and excluded products and dates behave as in the brief
- [x] The refusal message tells the customer why
- [x] PHPUnit covers the New customer rule; the rest is checked in the browser

## Notes (implementation)

- The rule is `amouriq_is_new_customer()` in `wp-content/mu-plugins/amouriq-coupon-first-order/new-customer.php` (plain data, `tests/NewCustomerTest.php`). `amouriq-coupon-first-order.php` fetches the customer's orders by account and by billing email and asks it. Refusal message now says why.
- Run locally with real WooCommerce (throwaway user/orders/coupons, deleted after): no orders -> applied; cancelled/failed/refunded only -> applied; pending/on-hold/processing/completed -> refused; a guest registering with the billing email of an earlier completed order -> refused, brand-new email -> applied; email-list coupon works for listed emails (case-insensitive) and refuses others; minimum spend, total usage limit, excluded/included products and expiry behave natively.
- Native WooCommerce refusal messages for those limits are in English on this site (not changed here).
- Live: `amouriq-coupon-first-order.php` is not uploaded yet and now needs its `amouriq-coupon-first-order/` folder uploaded with it.
