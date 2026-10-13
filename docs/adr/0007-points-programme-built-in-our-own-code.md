# The points programme is built in our own code, not on the Points & Rewards plugin

The owner wants customers to earn points on paid orders and spend them as a discount. The Points & Rewards plugin is installed (inactive; it still holds balances for a handful of members, which a one-time import in the Members tab carries over as an opening entry each) and could do this, but it cannot see our Deal engine, Member Privilege or Coupons, cannot be tuned to "20% of the item total after every other discount", and every rule change would mean working around a plugin we cannot edit.

**Decision**: our own mu-plugin (`amouriq-points.php` and `amouriq-points/points-core.php`), the same shape as the Deal engine and shipping label: pure rules in a tested core, WooCommerce glue beside it. The plugin stays inactive. The ledger lives in user meta (one entry per earn / use / reversal), not a new table, because a mu-plugin has no install step and the volume per customer is small.

**Consequences**: points are spent last, after Deals, Member Privilege and Coupons, as a negative fee computed tax-exclusive (WooCommerce taxes negative fees on top, see `amouriq_bogo_unit_price()`). The programme is off until the owner switches it on. The old plugin must stay inactive or customers would earn twice.
