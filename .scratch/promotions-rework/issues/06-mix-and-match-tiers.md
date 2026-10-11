# 06: Mix and match quantity tiers over several products

**What to build:** The quantity-tier Deal takes a set of ticked products and variations instead of one product. Pieces are counted across all of them (for example any scent of Castile Soap 250 ml), and the highest tier reached applies to every eligible piece: tiers at 2 pieces 8% and 3 pieces 10% give 10% on 3 pieces, never 8% plus 10%, and 5 pieces also get 10% on all 5. Member Privilege never adds; larger wins per line.

**Blocked by:** 05

**Status:** ready-for-human (tested locally)

- [x] Admin ticks several products and variations in one tier Deal using the shared tick list
- [x] Quantity is summed across ticked items; the highest tier reached applies to all eligible pieces
- [x] Sale Price items are skipped by default, with the per-Deal switch
- [x] Existing single-product tier Deals keep working
- [x] Larger-wins against Member Privilege holds per line
- [x] PHPUnit covers tier boundaries (1, 2, 3, 5 pieces), mixed scents and Sale Price items

## Notes (implementation)

- `quantity_tier` with `product_ids` (and optional `excluded_ids`) is mix and match: `amouriq_bogo_core_mix_and_match()` sums the eligible pieces, takes the highest tier reached and applies it to each eligible piece at its own price. A tier Deal with only `product_id` runs the old code path unchanged; opening one in the admin shows its product ticked and saving migrates it to `product_ids`. Tests: `tests/BogoMixMatchTest.php`.
- Admin: the tier form uses the shared tick list (and an exclude list); up to 5 tiers; at least one product must be ticked.
- Local run (throwaway admin/member and Deals, removed afterwards): legacy deal opens ticked and saves; new mix Deal created through the real form; cart with 1, 2, 3 and 5 pieces over three scents gives 0 / 8% / 10% / 10% on all pieces; an unticked product gets nothing from the Deal and the member 5%; Member Privilege never adds on ticked lines.
