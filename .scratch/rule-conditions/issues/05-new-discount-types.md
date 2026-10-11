# 05: Fixed price per item, bundle price, baht off per item

**What to build:** Three new Deal types over a Product scope: `fixed_price_item` (each qualifying item costs ฿Y), `bundle_price` (any N qualifying items for ฿Y; items beyond a multiple of N stay full price; the dearest N form the bundle, which favours the customer), `amount_off_item` (฿Y off each qualifying item). All return a per-line split so Member Privilege compares per line (ADR-0006).

**Blocked by:** 04

**Status:** done locally (core + list text; the form to create them is ticket 08)

- [x] Worked amounts by hand in PHPUnit for each type, including a cart with 2N+1 items for the bundle
- [x] A fixed price above the item's price gives no discount, never a surcharge
- [x] Member Privilege takes the larger per line next to each new type
- [x] The cart and checkout labels read correctly in Thai

## Notes (implementation)

- `amouriq_bogo_core_price_type_split()` in `pricing-core.php` returns the per-line discount for all three; the total is its sum. Tests: `tests/BogoPriceTypesTest.php` (worked by hand, incl. 2N+1 bundle, scope, sale skipping, Member Privilege per line).
- Prices are what the shopper sees; the discount is converted by each unit's own ex-tax ratio. A bundle uses the dearest units first. `max_per_order` limits the number of bundles.
- The admin list shows a Thai description for each type. They cannot be created from the form yet (ticket 08); the cart fee line uses the Deal label as for every Deal.
