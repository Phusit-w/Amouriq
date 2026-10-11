# Review of the Discount Rules (ticket 10)

Source: the `wp_wdr_rules` table of the local copy (`amouriq-backup.local`, restored from a backup of the live site; newest rule edited 2026-06-14, newest order using one 2026-08-24). Read only, nothing changed. **The live site may differ from this copy** (the owner counted about 20 rules; the copy has 23 rows, 6 of them deleted, so 17 usable). The owner should confirm against the live list before acting (see "To confirm on live").

## Result in one line

**No enabled Discount Rule reduces a product price.** Every enabled rule (#16 to #23) only gives free shipping. All the price-discount rules (#4 to #12) are switched off, and #1 to #3 and #13 to #15 are deleted. So the old rules cannot discount the same items again on top of the new Deals, สิทธิพิเศษสมาชิก or coupons. Free shipping is out of scope of this rework.

## Enabled rules (8): all free shipping

| # | Title | Trigger | Used in orders | Verdict |
|---|---|---|---|---|
| 16 | AMOURIQ2025 | coupon `amouriq2025` | 6 | Keep (free shipping, out of scope) |
| 17 | WELCOME | coupon `welcome` | 0 | Keep |
| 18 | 67LX6HU8U | coupon `67lx6hu8u` | 0 | Keep |
| 19 | FKAPRNPG7 | coupon `fkaprnpg7` | 2 | Keep |
| 20 | AMOTHCM20 | coupon `amothcm20` | 1 | Keep |
| 21 | AMOTHTHX20 | coupon `amoththx20` | 0 | Keep (duplicate of #23, harmless; delete one later) |
| 22 | Free Shipping | subtotal at least ฿299 | 1 | Keep. Same threshold as the Flexible Shipping rule the BOGO admin screen shows; two sources, one result. Tidy later |
| 23 | AMOTHTHX20 | coupon `amoththx20` | 0 | Duplicate of #21 |

## Switched-off price-discount rules (9): replaced by the new features

All are tests built around a coupon. None is enabled. After launch they can be deleted, never switched on (they would stack with the new Deals).

| # | Title | What it did | Replaced by |
|---|---|---|---|
| 4 | First Order with code | ฿100 off the first order with coupon `welcome2025` (used once, order 4036) | New-customer coupon (ticket 04) |
| 5 | Spend 599 Get 10% Off with Coupon | 10% off the cart, coupon `spend599` | Spend-threshold Deal (ticket 05) |
| 6 | Buy CASTIL 2 more get CASTIL with coupon | buy 2 get 1 free, coupon `buycastilgetcastil` | Buy X get Y Deal (existing) |
| 7 | Spend 5000 get 750 off with coupon | ฿750 off at ฿5,000, coupon `spend5000` | Spend-threshold Deal, baht mode |
| 8 | Spend Castile 500 ml get 200 off with coupon | ฿200 off, coupon `castil5000` | Spend-threshold Deal with ticked products |
| 9 | Castile 2 more get 20% off with coupon | 20% for 2 or more, coupon `castiloil` | Mix-and-match Deal (ticket 06) |
| 10 | BUY X 999 BATH GET Y with coupon | free product 2287 at ฿999, coupon `buy999getcastil` | Gift Deal (ticket 08) |
| 11 | BUY 599 GET FREE SHIPPING with coupon | free shipping at ฿599, coupon `freeshipping` | Out of scope; off |
| 12 | BUY 2 3 5 ITEM GET 3% 5% 8% OFF | quantity tiers, coupon `buybuy` | Mix-and-match Deal (ticket 06) |

Deleted (nothing to do): #1 to #3 (plugin samples), #13 "BUY 3 ITEM GET 5%", #14 "BUY 5 GET 8%", #15 "Test New coupon".

## What this means for the launch

- The Discount Rules plugin does **not** need to be switched off. Its eight enabled rules are free shipping; switching it off would stop those coupons giving free shipping. Leave it active.
- The earlier warning "do not deactivate Discount Rules until reviewed" can be read the other way round: there is nothing to deactivate for the new Deals to be safe, as long as the live list matches this copy.
- Real usage of old price rules: one order ever (4036, ฿100 off with a coupon rule that is now off).

## Points & Rewards (membership levels)

In this copy the plugin is installed but **not active** and has no membership levels saved. On live this is unknown.

## To confirm on live (owner)

1. WooCommerce > Discount Rules: the list shows the same 8 enabled rules (all "Free shipping") and no enabled rule with a percentage, fixed or buy X get Y adjustment. Anything else enabled needs a verdict before สิทธิพิเศษสมาชิก is switched on.
2. Plugins: is Points & Rewards active on live? If yes, open its Membership tab and make sure no membership levels with a discount are enabled.
3. Optional: screenshot of the enabled rules so this file can be updated.
