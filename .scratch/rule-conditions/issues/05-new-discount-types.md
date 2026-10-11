# 05: Fixed price per item, bundle price, baht off per item

**What to build:** Three new Deal types over a Product scope: `fixed_price_item` (each qualifying item costs ฿Y), `bundle_price` (any N qualifying items for ฿Y; items beyond a multiple of N stay full price; the dearest N form the bundle, which favours the customer), `amount_off_item` (฿Y off each qualifying item). All return a per-line split so Member Privilege compares per line (ADR-0006).

**Blocked by:** 04

**Status:** ready-for-agent

- [ ] Worked amounts by hand in PHPUnit for each type, including a cart with 2N+1 items for the bundle
- [ ] A fixed price above the item's price gives no discount, never a surcharge
- [ ] Member Privilege takes the larger per line next to each new type
- [ ] The cart and checkout labels read correctly in Thai
