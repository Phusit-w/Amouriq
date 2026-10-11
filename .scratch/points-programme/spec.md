# Points programme (our own, mu-plugin)

Status: built and tested locally (2026-10-11); live upload is the owner's. Off until switched on under WooCommerce > แต้มสะสม.

Replaces the idea of using the Points & Rewards plugin (installed, inactive on live, no membership levels). Same approach as the shipping label and Member Privilege: our own code, no edit to any plugin.

## Rules (owner agreed "ตกลง" to these defaults; all editable in the admin screen)

- Earn: 1 point per ฿100 of the **paid item total** (tax included, shipping excluded, after every Deal, Member Privilege, Coupon and points used). Rounded down. Given when the order becomes **Completed**, once per order.
- Use: 1 point = ฿1. At most **20%** of the item total after every other discount. At least **50 points** at a time. Logged-in members only. Points used are never earned on again (the paid total already excludes them).
- Expiry: 12 months from the day earned. A use takes the points that expire first.
- Refund: money refunded takes back the points it earned (to the smaller paid amount). Cancelled / failed / fully refunded order: points used come back, points earned are taken back. An order that is paid again after being cancelled uses the points again.
- Never stacks with anything in the sense of ADR-0006: points are a payment in addition to discounts, not a discount that competes with them; they are worked out last.

## Where things are

- Rules (pure, tested): `wp-content/mu-plugins/amouriq-points/points-core.php`, `tests/PointsTest.php`
- WooCommerce glue, My Account tab "แต้มสะสม", admin screen: `wp-content/mu-plugins/amouriq-points.php`
- Ledger: user meta `amouriq_points_ledger`; order meta `_amouriq_points_awarded / _eligible / _used / _given_back`
- Options: `amouriq_points_enabled` (default no), `amouriq_points_settings`

## Verified locally (CLI against the restored site, mail blocked, stock restored afterwards)

Cart ฿778 -> asking 100 points = total ฿678 (exact, tax quirk handled by passing the tax-exclusive amount); asking 99,999 capped to 155 points (20%); order placed -> balance 300 to 200; Completed -> +6 (once, not twice); Cancelled -> 100 given back and the 6 taken back; back to Processing -> 100 used again; Refunded -> 100 given back. Box, My Account tab and admin screen render.

## Not yet tested

Placing an order through the real checkout page in a browser; the box on a phone; Omise payment retry path; the real order-list screen. Live upload and the switch are the owner's.

## Left on purpose

- A partial refund that includes shipping takes back slightly more points than strictly earned.
- Points earned before a settings change keep their old expiry.
- No email to the customer about points; no migration (the Points & Rewards plugin is off with no data).
- Cart and checkout use the classic shortcodes (confirmed on this site); the block checkout would need other hooks.
