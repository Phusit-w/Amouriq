Status: ready-for-human (owner to read the Open questions at the end, then it becomes ready-for-agent)

# Points programme, second round

Source: the owner's request of 2026-10-11 ("ระบบสะสมแต้มทำระบบเพิ่มแบบ plugin สะสมแต้มที่อยู่"), with the choices made in chat: **more earning rules, redeem points for coupons / rewards, admin adjusts points + reports**. Membership tiers were *not* chosen. Builds on `.scratch/points-programme/spec.md` (the first round: earn on Completed, spend at cart, ledger, My Account tab, admin screen) and ADR-0007. Vocabulary is in `CONTEXT.md`; the new words (Earning rule, Reward, Adjustment) are added there.

## Problem Statement

The first round gives one flat rule: 1 point per ฿100, spend at the cart. The owner wants what the old Points & Rewards plugin offered beyond that: different earning for different things, bonuses, a way to turn points into a coupon or a prize, and a way for staff to fix a customer's balance and see how the programme is doing, all without a developer.

## Solution

Keep the ledger and the spend-at-cart flow as they are. Add (1) an **Earning rules** table in the admin screen, (2) a **Rewards** catalogue the customer redeems from My Account, (3) an **Adjustment** form and customer list for staff, and (4) a **Reports** tab. Earning rules reuse the Condition layer from `.scratch/rule-conditions/` where they need conditions (who, when, cart contents).

## User Stories

1. As an admin, I want a different earn rate for a category or product (for example 2 points per ฿100 on soap), so that I can push what I want to sell.
2. As an admin, I want to exclude products or categories from earning, so that sale or gift items do not earn.
3. As an admin, I want a minimum order total to earn, and a cap of points per order, so that the programme cannot be gamed.
4. As an admin, I want a date-range multiplier (double points this weekend), so that I can run a points promotion.
5. As an admin, I want a one-time sign-up bonus and a first-order bonus, so that new customers have a reason to join.
6. As an admin, I want a bonus for an approved verified-purchase product review, once per product with a yearly cap, so that I collect reviews.
7. As an admin, I want to change the expiry period and see which points are about to expire, so that I control liability.
8. As an admin, I want to define Rewards: a points price that gives a coupon (baht or percent, with minimum spend and validity) or a free product, so that customers can choose what to redeem.
9. As a customer, I want to see the Rewards I can afford on my My Account points tab and redeem one with one click, so that points feel useful.
10. As a customer, I want the redeemed coupon code to appear in My Account and be usable only by me, so that nobody else can take it.
11. As a customer, I want a reminder before my points expire, so that I use them.
12. As an admin, I want to add or remove points for one customer with a reason, so that I can fix mistakes and give goodwill.
13. As an admin, I want a customer list with balances and a per-customer ledger, so that I can answer a customer's question.
14. As an admin, I want a report of points earned, used, expired and still outstanding by month, with a CSV export, so that I know the cost of the programme.
15. As an admin, I want every change to a balance to be in the ledger with who and why, so that it can be audited.

## Implementation Decisions

- **The ledger stays the single truth** (user meta `amouriq_points_ledger`, ADR-0007). New entry kinds: `bonus_signup`, `bonus_first_order`, `bonus_review`, `adjustment` (with `by` user id and `reason`), `redeem` (reward id, coupon id), `expire`. The pure rules stay in `amouriq-points/points-core.php` with PHPUnit; the WooCommerce glue stays in `amouriq-points.php`. The admin screen grows tabs (settings, earning rules, rewards, customers, reports) and may be split into more files so no file grows past review size.
- **Earning rules** are stored as a list, each `{type: rate|multiplier|bonus, scope, conditions, value, from, to, enabled}`. The earn amount for an order is: base rule (the existing 1 per ฿100) per eligible line, then multipliers, then caps; rounding down once per order. Details and worked examples are in ticket 01.
- **Rewards**: `{id, label, cost_points, kind: coupon|gift, coupon: {type, value, min_spend, days_valid}, gift_product_id, enabled, stock?}`. Redeeming deducts points at once and creates a WooCommerce coupon restricted to the customer's email, single use, expiring after `days_valid`. A gift reward creates a coupon that triggers a gift Deal (reusing `spend_gift` with zero minimum). Points are **not** refunded if the coupon expires unused (stated to the customer before they confirm) unless the owner answers otherwise below.
- **Coupons from rewards are ordinary Coupons**, so they compete with Member Privilege exactly as ADR-0006 says (larger wins) and with points spent at the cart (points are worked out last; unchanged).
- **Adjustment** is only for users with `manage_woocommerce`; the reason is required; a deduction cannot take a balance below zero; every one is a ledger entry with the admin's user id.
- **Reports** are computed from the ledgers (no new table) with a cached monthly summary; fine for the store's size. CSV export streams, no files kept.
- **Emails**: expiry reminder (and optional "you earned" note) go through WooCommerce's mailer, off by default, with a daily scheduled check. Locally mail is blocked, so the send path is checked with a captured `wp_mail` filter.
- **Switch**: still off until the owner switches it on; every new feature has its own default (off or neutral) so uploading never changes a balance by itself.

## Testing Decisions

Core rules (earn amounts for every rule type and combination, caps, expiry order, adjustment limits, redeem arithmetic) are PHPUnit on plain arrays in `tests/PointsTest.php` and new files. The WooCommerce flows (redeem creates the right coupon, the coupon only works for that email, reports add up to the ledgers, CSV) are checked locally against real WooCommerce with throwaway users, then in a browser for the My Account and admin screens.

## Out of Scope

- Membership tiers / levels (not chosen).
- Birthday points (the site does not collect a birthday; adding the field is a separate decision).
- Importing balances from the old plugin (it is off and holds no data).
- Social-share or referral points.
- A points shop page on the storefront; redemption is from My Account only.

## Open questions for the owner

1. If a redeemed coupon expires unused, do the points come back, or are they lost? (Suggested: lost, and say so on the confirm button; simpler and cannot be gamed.)
2. Review bonus: is a verified-purchase, admin-approved review the right trigger, and what size (suggested 10 points, once per product, 5 reviews a year)?
3. Gift rewards: should a gift reward be limited by stock (a prize that runs out), or follow the product's own stock?
4. Do you want the "points about to expire" email, and how many days before (suggested 14)?
