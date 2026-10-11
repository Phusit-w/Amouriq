# 05: Spend-threshold discount Deal with a live product tick list

**What to build:** An admin can create a Deal "spend X baht, get Y% or Y baht off" on the AMOURIQ BOGO admin screen: minimum spend, percent or baht, value, start and end date and time, on/off, and which products and variations take part or are excluded. The product choice is a tick list read live from WooCommerce (published products and variations) that shows each item's stock status, replacing the single dropdown; later tickets reuse it. New Deals skip Sale Price items by default, with a per-Deal switch. Minimum spend counts the whole item total before discounts, shipping excluded. Member Privilege never adds to it; the larger discount wins per line. The Active status column keeps showing why a Deal is not active.

**Blocked by:** 02

**Status:** ready-for-agent

- [ ] An admin can create, edit, switch off and delete this Deal type with all the listed fields
- [ ] The product tick list comes live from WooCommerce, shows stock status, and a variation deleted later still shows the existing "not found" warning
- [ ] Discount applies only to ticked (or non-excluded) products; minimum spend counts every item
- [ ] Percent and baht modes both work; a baht discount never exceeds the eligible items' value
- [ ] Member Privilege and this Deal never stack on a line
- [ ] Dates and times and the on/off switch gate the Deal; the status column explains inactive Deals
- [ ] PHPUnit covers the calculation; the admin screen is checked in the browser
