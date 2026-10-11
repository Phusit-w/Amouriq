# 01: Condition evaluator in the pricing core

**What to build:** A pure function in `amouriq-bogo/` that takes a Condition set (`match` all|any, rows of type/op/value) and a customer-and-cart context and says matched, not matched, or unknown (with what is missing). Types: `logged_in`, `user_role`, `email_in_list`, `orders_count`, `total_spent`, `days_since_last_order`, `weekday`, `time_of_day`, `cart_items`, `cart_quantity`, `cart_subtotal`, `cart_has_product`, `cart_has_category`. (Payment method and shipping province are ticket 03.) Wire it into `amouriq_bogo_core_stop_reason()` as a new reason `conditions_not_met`, so every Deal type gets it.

**Blocked by:** none

**Status:** ready-for-agent (after the owner answers open question 1; build the suggested core types first)

- [ ] Each type has a matched, a not-matched and an unknown case in PHPUnit
- [ ] `match: any` and `match: all` both work; an empty set always matches
- [ ] Unknown input (for example email not yet typed) is "not matched" and reports what is missing; the existing new-customer exception is untouched
- [ ] A Deal with no conditions behaves exactly as before (all existing tests pass unchanged)
- [ ] The context is a plain array; no WordPress call in the core
