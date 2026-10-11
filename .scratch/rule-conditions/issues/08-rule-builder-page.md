# 08: Rule-builder admin page, ordering, duplicate, exclusive

**What to build:** Reorganise WooCommerce > ดีล BOGO into a stepped form (1 type and reward, 2 who and when, 3 products, 4 limits and options) with a plain-Thai summary sentence of the rule before saving. In the list: up/down buttons to reorder, Duplicate, and an "exclusive" option (stop later Deals for the same lines; Member Privilege and Coupons still obey ADR-0006).

**Blocked by:** 01, 04, 05 (06 and 07 add their own sections when they land)

**Status:** done locally (not uploaded). Two items left open below

- [x] A rule built in the form saves, reloads into the form and prints the right summary sentence (the summary is shown in the confirmation notice after saving and as chips in the list; there is no live preview while typing)
- [x] Reorder and Duplicate work and keep a stale second browser tab from acting on the wrong rule (the existing deal-id guard)
- [x] Exclusive stops a later Deal on the same lines and not on other lines (PHPUnit for the engine; the form checkbox renders)
- [ ] Works on a phone-width admin screen (not checked)
- [ ] A live summary sentence while the form is being filled in (not built)

## Notes (implementation)

- Form: a "เงื่อนไขเพิ่มเติม: ใครและเมื่อไหร่" block (up to 5 Condition rows, all/any, operators and hints follow the chosen type), a "ขอบเขตสินค้า" block (categories, tags, one attribute, SKUs, on-sale only; excludes by category, tag, SKU) and fields for the three new types. Types that use the scope: spend_threshold, quantity_tier (the ticked list may now be empty when a scope is given), fixed_price_item, amount_off_item, bundle_price.
- Pure helpers (tests `tests/BogoConditionsAdminTest.php`): `amouriq_cond_catalog()`, `amouriq_cond_parse_row()` (Thai weekday names, 18:00-21:00, ranges), `amouriq_cond_format_value()`, `amouriq_cond_describe()`.
- List: ↑ ↓, ทำสำเนา (a copy starts OFF), Exclusive chip, scope and condition chips. Engine: `amouriq_bogo_cart_discounts()` hides lines an Exclusive Deal discounted from the Deals after it (tests `tests/BogoExclusiveTest.php`). Lines taken that way do not count toward a later Deal's minimum spend.
- Checked locally: building each type from posted form data (errors for bad price, bundle of 1, missing category, half-filled attribute), add/edit pages render with no PHP messages, edit form pre-fills scope and conditions, list text, handler actions in separate processes (move up/down, no-op at the ends, stale deal id refused, duplicate), and the form script in real headless Chrome: choosing "bundle price" shows/hides the right fields, a weekday condition shows its operators and hint, a numeric one offers eq/gte/lte/between.
- Not checked: the form in the real wp-admin (styles, mobile), saving through the real POST + redirect.
