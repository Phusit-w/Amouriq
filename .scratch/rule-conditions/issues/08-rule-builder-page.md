# 08: Rule-builder admin page, ordering, duplicate, exclusive

**What to build:** Reorganise WooCommerce > ดีล BOGO into a stepped form (1 type and reward, 2 who and when, 3 products, 4 limits and options) with a plain-Thai summary sentence of the rule before saving. In the list: up/down buttons to reorder, Duplicate, and an "exclusive" option (stop later Deals for the same lines; Member Privilege and Coupons still obey ADR-0006).

**Blocked by:** 01, 04, 05 (06 and 07 add their own sections when they land)

**Status:** ready-for-agent

- [ ] A rule built in the form saves, reloads into the form and prints the right summary sentence
- [ ] Reorder and Duplicate work and keep a stale second browser tab from acting on the wrong rule (the existing deal-id guard)
- [ ] Exclusive stops a later Deal on the same lines and not on other lines (PHPUnit for the engine, browser for the form)
- [ ] Works on a phone-width admin screen
