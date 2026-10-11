# 04: Product scope on every Deal type

**What to build:** Replace the hand-ticked product lists with a Product scope: include and exclude by category, tag, attribute (for example size), SKU, product/variation, and "only items on sale". Resolve to variation ids at cart time. Existing `product_ids` / `excluded_ids` / `exclude_sale` stay valid and are read as part of the scope.

**Blocked by:** none (can run beside 01)

**Status:** ready-for-agent

- [ ] Each filter type has include and exclude cases in PHPUnit
- [ ] A Deal saved before this ticket gives the same discounts as before (existing tests unchanged)
- [ ] The attribute filter works for the `pa_quantity` size on the Castile Soap products
- [ ] Scope is used by quantity counting, minimum spend of the scope, and the per-line split alike
