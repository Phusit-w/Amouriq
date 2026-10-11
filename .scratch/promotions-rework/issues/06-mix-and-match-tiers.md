# 06: Mix and match quantity tiers over several products

**What to build:** The quantity-tier Deal takes a set of ticked products and variations instead of one product. Pieces are counted across all of them (for example any scent of Castile Soap 250 ml), and the highest tier reached applies to every eligible piece: tiers at 2 pieces 8% and 3 pieces 10% give 10% on 3 pieces, never 8% plus 10%, and 5 pieces also get 10% on all 5. Member Privilege never adds; larger wins per line.

**Blocked by:** 05

**Status:** ready-for-agent

- [ ] Admin ticks several products and variations in one tier Deal using the shared tick list
- [ ] Quantity is summed across ticked items; the highest tier reached applies to all eligible pieces
- [ ] Sale Price items are skipped by default, with the per-Deal switch
- [ ] Existing single-product tier Deals keep working
- [ ] Larger-wins against Member Privilege holds per line
- [ ] PHPUnit covers tier boundaries (1, 2, 3, 5 pieces), mixed scents and Sale Price items
