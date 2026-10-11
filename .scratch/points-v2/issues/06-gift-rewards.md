# 06: Gift rewards (free product for points)

**What to build:** A Reward kind `gift`: redeeming creates a personal coupon that triggers a zero-minimum `spend_gift` Deal for the chosen product, so the customer picks the gift in the cart with the existing gift choice box. Stock follows the product's own stock unless the owner asks for a separate limit (open question 3).

**Blocked by:** 05

**Status:** done locally (not uploaded, not in any kit yet). The cart pick in a browser was not run

- [x] Redeeming adds exactly one free gift entitlement; it disappears with the coupon, as a gift does today when its trigger is gone
- [x] An out-of-stock gift cannot be redeemed (the button is disabled with the reason), and one that sells out before checkout is refused with a message
- [x] The gift line shows ฿0 and is tagged like other gifts, and the order note says it came from points (the gift pick was simulated through the cart like the choice box does it; the choice box itself was not clicked in a browser)
- [x] No points are lost if the gift cannot be added (the redeem is all-or-nothing)

## Notes (implementation)

- Reward kind `gift` (`product_id` of a product or variation) in `points-core.php` (`rewards_clean`, `coupon_spec`); redeeming checks the product is purchasable and in stock first (refused with no points taken), then makes a personal coupon worth 0 with meta `amouriq_points_gift_product` / `_label`.
- While such a coupon is in the cart the points file adds a runtime gift Deal (`pts<coupon id>`, `spend_gift`, trigger coupon, minimum spend ฿1 so the gift is never the whole order) through the new filter `amouriq_bogo_deals_runtime`. `amouriq_bogo_deals()` now applies that filter to `amouriq_bogo_deals_stored()`; the admin screen reads the stored list only, so a runtime Deal can never be saved. The existing choice box, gift settling, stock check, ฿0 line and per-deal limit all apply unchanged.
- Order note "ลูกค้าใช้ของรางวัลที่แลกด้วยแต้มสะสม" on checkout. Stock follows the product itself (the proposed default; there is no separate prize limit).
- Run locally against real WooCommerce (throwaway member, products, coupon; deleted after): sold-out gift refused with the balance untouched; redeem 400 to 250; paid item + coupon lists the virtual Deal with 1 gift allowed; the pick adds a free line and the total stays the paid price; a second gift is dropped; removing the coupon drops the gift with notices; another member cannot apply the coupon; order note; admin add / bad product / listing and My Account text.
- Wording to improve later: dropping a gift after the coupon is removed shows the generic "below minimum or beyond entitlement" notice followed by "this Deal has ended".
- Needs `amouriq-bogo-deals.php`, `amouriq-bogo-admin.php`, `amouriq-points.php` and `amouriq-points/points-core.php` uploaded together.
