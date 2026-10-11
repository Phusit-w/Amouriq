# 05: Spend-threshold discount Deal with a live product tick list

**What to build:** An admin can create a Deal "spend X baht, get Y% or Y baht off" on the AMOURIQ BOGO admin screen: minimum spend, percent or baht, value, start and end date and time, on/off, and which products and variations take part or are excluded. The product choice is a tick list read live from WooCommerce (published products and variations) that shows each item's stock status, replacing the single dropdown; later tickets reuse it. New Deals skip Sale Price items by default, with a per-Deal switch. Minimum spend counts the whole item total before discounts, shipping excluded. Member Privilege never adds to it; the larger discount wins per line. The Active status column keeps showing why a Deal is not active.

**Blocked by:** 02

**Status:** ready-for-human (tested locally; edit-form look on a phone not checked)

- [x] An admin can create, edit, switch off and delete this Deal type with all the listed fields
- [x] The product tick list comes live from WooCommerce, shows stock status, and a variation deleted later still shows the existing "not found" warning
- [x] Discount applies only to ticked (or non-excluded) products; minimum spend counts every item
- [x] Percent and baht modes both work; a baht discount never exceeds the eligible items' value
- [x] Member Privilege and this Deal never stack on a line
- [x] Dates and times and the on/off switch gate the Deal; the status column explains inactive Deals
- [x] PHPUnit covers the calculation; the admin screen is checked in the browser

## Notes (implementation)

- New Deal type `spend_threshold` (fields `min_spend`, `mode` percent|baht, `value`, `product_ids`, `excluded_ids`, `exclude_sale`). Core: `amouriq_bogo_core_spend_threshold()`; tests `tests/BogoSpendThresholdTest.php`.
- Minimum spend uses the displayed price (`price_incl_tax`) of every line; the discount is taken from eligible lines' tax-exclusive price. A baht value is what the shopper sees: it is converted to the tax-exclusive fee with the eligible lines' ex/incl ratio, so ฿100 off shows as exactly ฿100 off; never more than the eligible items.
- All Deals now take a start and end with time (`Y-m-d H:i`); a bare stored date still means the whole day, so existing Deals are unchanged. The admin status says "ยังไม่ถึงเวลาเริ่ม" / "หมดช่วงเวลาแล้ว".
- Admin: tick lists (live from WooCommerce, stock status shown, search box, a deleted ID stays ticked with a not-found warning), new Deals default to "skip Sale Price items".
- Existing Deal types still use the single-product dropdown; tickets 06 and 07 move them to the tick list.
- Local run (throwaway admin and Deal, removed afterwards): create/edit/switch off/on through the real admin form in headless Chrome; cart amounts for percent, baht, cap, excluded and not-ticked items, ended/not-yet/off windows, and Member Privilege per line.
