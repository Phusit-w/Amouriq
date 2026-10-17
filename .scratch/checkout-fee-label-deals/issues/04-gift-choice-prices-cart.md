# 04: Gift choice shows prices (cart page)

**What to build:** On the cart page, the Gift choice for a spend-threshold gift Deal shows each candidate's regular price struck through with "ฟรี", and the customer can pick the product option (size etc.) in place and see that option's price. Sold-out gifts stay unavailable. Not choosing never blocks checkout.

**Blocked by:** None (can start immediately).

**Status:** ready-for-agent

- [ ] The choice list builder returns the regular price and per-option price for every candidate (extend `BogoGiftTest`)
- [ ] Cart box shows the struck-through price and "ฟรี" per item, and the chosen option's price
- [ ] Out-of-stock candidates are shown as unavailable
- [ ] Existing pick, settle and drop-with-notice behaviour is unchanged
- [ ] Manual: pick a gift with options on local
