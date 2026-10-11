# 03: Admin adjusts points, customer list, per-customer ledger

**What to build:** A "ลูกค้า" tab in WooCommerce > แต้มสะสม: a searchable list (name, email, balance, points expiring soon) and a per-customer page with the full ledger and an Adjustment form (add or deduct, amount, required reason). Every adjustment is a ledger entry with the admin's user id and reason; a deduction cannot take the balance below zero.

**Blocked by:** none

**Status:** ready-for-agent

- [ ] Only users with `manage_woocommerce` can see or change it; the form is nonce-protected
- [ ] Add and deduct update the balance and show in the customer's My Account ledger with the reason
- [ ] A deduction larger than the balance is refused with a clear message
- [ ] Deducting takes the points that expire first, like a normal use
- [ ] PHPUnit for the rule; list, search and the form checked in a browser against the local site
