# 01: Payment fee for cash on delivery

**What to build:** When the customer picks cash on delivery, the cart adds a Payment fee (default ฿30, named "ค่าธรรมเนียมเก็บเงินปลายทาง"). It is charged even when shipping is free, added after all discounts, with no extra tax, and never counted as purchase value for Points or "spend X" tests. The owner can switch it on or off and change the amount in the admin. Changing the payment method on checkout updates the total at once. The fee shows on the order and in order emails. See spec stories 1-12, ADR-0008 (pure core), CONTEXT.md "Payment fee".

**Blocked by:** None (can start immediately).

**Status:** ready-for-agent

- [ ] Pure fee function (payment method, setting, cart context) returns the fee; tests: cod gives the amount, other methods give none, free shipping still charged, switch off gives none, amount is editable
- [ ] The customer pays exactly the configured amount: if WooCommerce taxes the positive fee despite the taxable flag, the tax is backed out; a test locks this
- [ ] The fee is excluded from Points earning and from "spend X" / Minimum spend measures (test)
- [ ] Discounts (Deals, Coupons, Member Privilege, Points redemption) still compute as before with the fee present
- [ ] Setting (on/off, amount, default on at ฿30) is editable in the admin area
- [ ] Manual: a real cash-on-delivery checkout on local; switching method refreshes the total
