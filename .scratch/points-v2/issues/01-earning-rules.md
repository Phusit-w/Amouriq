# 01: Earning rules (per-category rate, exclusions, caps, multiplier)

**What to build:** An "กติกาการได้แต้ม" table in WooCommerce > แต้มสะสม. Rule kinds: `rate` (points per ฿100 for a category, product or Product scope, replacing the base rate on those lines), `exclude` (scope that earns nothing), `multiplier` (x2 between two dates, optionally with Conditions), plus order-level settings: minimum order total to earn and a maximum points per order. The base rule (1 per ฿100, whole paid item total) stays the default when no rule matches.

**Blocked by:** none for rate/exclude/caps; the Conditions on a multiplier wait for `rule-conditions` ticket 01

**Status:** ready-for-agent (after the owner reads the spec)

- [ ] Earn amount worked out by hand in PHPUnit for: base only, a category rate, an excluded line, a multiplier inside and outside its dates, the minimum order total, and the per-order cap
- [ ] Rounding stays "down, once per order"; points used still earn nothing (the paid total already excludes them)
- [ ] An order placed before a rule changes keeps the points it was eligible for when placed
- [ ] With no rules saved, every existing points test passes unchanged
