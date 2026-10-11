# 04: Coupons for new customers and listed emails

**What to build:** An admin can create a coupon that is restricted to New customers, or to a list of emails, with percent or baht value, minimum spend, included and excluded products, dates and per-person and total usage limits. Most of this is native WooCommerce; this ticket confirms the native fields cover the brief, and adds or fixes the "new customers only" option using the glossary definition (no counted order; matched by account, or by billing email at checkout). Example: WELCOME10, 10% for the first order only.

**Blocked by:** 01

**Status:** ready-for-agent

- [ ] A "new customers only" coupon works for a customer with no counted order and is refused for one who has a pending, on-hold, processing or completed order; cancelled, failed and refunded orders do not count
- [ ] A customer registering during checkout is matched by the billing email
- [ ] A coupon limited to listed emails works only for those emails
- [ ] Per-person and total usage limits, minimum spend, included and excluded products and dates behave as in the brief
- [ ] The refusal message tells the customer why
- [ ] PHPUnit covers the New customer rule; the rest is checked in the browser
