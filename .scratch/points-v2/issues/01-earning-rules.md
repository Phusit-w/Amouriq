# 01: Earning rules (per-category rate, exclusions, caps, multiplier)

**What to build:** An "กติกาการได้แต้ม" table in WooCommerce > แต้มสะสม. Rule kinds: `rate` (points per ฿100 for a category, product or Product scope, replacing the base rate on those lines), `exclude` (scope that earns nothing), `multiplier` (x2 between two dates, optionally with Conditions), plus order-level settings: minimum order total to earn and a maximum points per order. The base rule (1 per ฿100, whole paid item total) stays the default when no rule matches.

**Blocked by:** none for rate/exclude/caps; the Conditions on a multiplier wait for `rule-conditions` ticket 01

**Status:** done locally (not uploaded). The admin section has not been opened in a browser yet

- [x] Earn amount worked out by hand in PHPUnit for: base only, a category rate, an excluded line, a multiplier inside and outside its dates, the minimum order total, and the per-order cap
- [x] Rounding stays "down, once per order"; points used still earn nothing (the paid total already excludes them)
- [x] An order placed before a rule changes keeps the points it was eligible for when placed
- [x] With no rules saved, every existing points test passes unchanged

## Notes (implementation)

- Pure rules: `amouriq_points_core_earning_clean()` and `amouriq_points_core_earn_order()` (+ `amouriq_points_core_refund_reversal_share()`) in `points-core.php`; tests `tests/PointsEarningRulesTest.php`. Scope and Conditions come from the Deal engine (`amouriq-bogo/pricing-core.php`), which `amouriq-points.php` now requires, so the two files are uploaded together.
- Rate rule: first enabled match wins; exclude: earns nothing; multiplier: largest live one, applied before the single round-down. Order-level discounts and points used are shared over the lines (paid amounts are scaled to the order eligible total).
- Points are planned when the order is placed (`_amouriq_points_planned`) and awarded at Completed from that plan. A refund on an order with rules keeps the same share of points; without rules the old flat reversal is unchanged.
- Multiplier Conditions only see what the order knows (time, logged in, email, quantity, subtotal); order-history conditions are unknown there and so never match.
- Run locally against real WooCommerce (throwaway user, product and orders, deleted after; mail blocked): flat = rules fn with no rules (10), category rate 50 baht/point planned 20 and kept after the rule was removed, half refund 20 -> 10, add-rule form and validation, section renders.
- The rule form picks categories and product IDs only; attribute, tag and SKU scopes exist in the core but have no form fields yet.
