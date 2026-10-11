Status: ready-for-human (owner to read the Open questions at the end, then it becomes ready-for-agent)

# Rule conditions and a Discount-Rules-PRO-style rule builder

Source: the owner's request of 2026-10-11 ("ระบบคูปองสามารถ custom ได้แบบ plugin Discount Rules PRO"), with the choices made in chat: **cart / customer conditions, product scope, more discount types, one rule-builder page**. Builds on `.scratch/promotions-rework/spec.md` (the Deal engine) and ADR-0006 (stacking). Vocabulary is in `CONTEXT.md`; the new words (Condition, Product scope, Exclusive) are added there. The design choice (extend the Deal engine, do not write a second one) is `docs/adr/0008-conditions-extend-the-deal-engine.md`.

## Problem Statement

Discount Rules PRO is inactive on live (the owner deactivated it) and the owner no longer wants to depend on it, but still wants what it gave: set up a promotion by saying *who* it is for, *when*, *what is in the cart*, *which products* and *what it gives*, all from one admin page, with no developer. Today a Deal can only be limited by dates, a trigger coupon, a per-customer count, "new customers only" and two hand-ticked product lists. Native WooCommerce coupons can only be limited by the checkboxes WooCommerce has plus "new customers only". The enabled Discount Rules on live are all free-shipping rules, which today only work through coupons.

## Solution

Add one **Condition** layer that both Deals and Coupons use, richer **Product scope** on every Deal type, a few more **discount types**, and reorganise the BOGO admin page into a single rule-builder form (type, who/when conditions, products, reward, limits) with a rule list that can be reordered, duplicated and switched on and off.

## User Stories

1. As an admin, I want to limit a Deal or Coupon to logged-in customers, to a user role, or to a list of emails, so that I can run member-only or staff offers.
2. As an admin, I want to limit by purchase history (number of earlier orders, total spent so far, days since the last order), so that I can run "welcome back" and loyal-customer offers.
3. As an admin, I want to limit by days of the week and time of day, so that I can run a weekend or evening promotion.
4. As an admin, I want to limit by cart contents (number of different items, total quantity, subtotal range, a given product or category being in the cart), so that I control when it triggers.
5. As an admin, I want to limit by payment method and by shipping province, so that I can reward bank transfer or target an area.
6. As an admin, I want several conditions on one rule and to choose whether all or any must match, so that I can express the real offer.
7. As an admin, I want to pick products by category, tag, attribute (for example size), SKU or "only items on sale", with include and exclude lists, in every Deal type, so that scope is not just hand-ticked ids.
8. As an admin, I want a "fixed price per item" discount (for example any 250 ml soap at ฿299), so that I can run price-point promotions.
9. As an admin, I want a bundle discount (any N items of the scope for ฿Y), so that I can run "3 for ฿999".
10. As an admin, I want a baht-off-per-item discount, so that I can take ฿20 off each qualifying item.
11. As an admin, I want a free-shipping rule driven by the same conditions, so that the free-shipping coupons that relied on Discount Rules need no other plugin.
12. As an admin, I want to reorder rules, duplicate a rule, and mark a rule "exclusive" so later rules do not also apply, so that overlapping promotions behave as I intend.
13. As an admin, I want one page to build a rule in plain Thai, with a summary sentence of what it will do before I save, so that I can check it.
14. As an admin, I want the rule list to say, per rule, whether it is Active and why not, and how often it was used, so that I can manage it (already true for Deals; kept).
15. As a customer, I want the cart to tell me what is missing to get an offer (already true for spend and quantity; extended to the new conditions where it is knowable), so that I am not confused.
16. As a customer, I want a coupon that I am not eligible for to say why, in Thai, so that I know what to do.

## Implementation Decisions

- **Conditions are plain data** evaluated in the pricing core (`amouriq-bogo/`), with no WordPress calls, so they are PHPUnit-testable like the rest. The WooCommerce side builds a *customer and cart context* once per recalculation and passes it in.
- A Condition set is `{ match: all|any, rows: [ {type, op, value} ] }`. Types: `logged_in`, `user_role`, `email_in_list`, `orders_count`, `total_spent`, `days_since_last_order`, `weekday`, `time_of_day`, `cart_items`, `cart_quantity`, `cart_subtotal`, `cart_has_product`, `cart_has_category`, `payment_method`, `shipping_province`. Operators are small and per type (equals / in list, at least / at most / between).
- **Unknown is not a match.** A condition whose input is not known yet (payment method before the shopper picks one, province before an address) does not match, and the cart hint says what is missing. The one exception stays "new customer": a customer not yet identified counts as new until the email is known (see `new_customer_only`, already built).
- **Existing Deals keep working unchanged.** `new_customer_only`, `max_uses_per_customer` and the date window are not rewritten; they become the first three rows the form offers, but the stored fields stay as they are so nothing already saved changes meaning.
- **Coupons**: native WooCommerce coupons get the same Condition set through `woocommerce_coupon_is_valid` (and a panel in the coupon edit screen), with refusal messages in Thai. The existing "ลูกค้าใหม่เท่านั้น" checkbox stays.
- **Product scope** is `{include: {categories, tags, attributes, skus, products, on_sale_only}, exclude: {...}}`. Variation ids are resolved at cart time. The picker reuses the live WooCommerce list the admin already has for hand-ticked products.
- **New discount types**: `fixed_price_item`, `bundle_price`, `amount_off_item`, `free_shipping`. All run through the per-line split already used by every Deal (so Member Privilege's larger-discount-wins stays correct per ADR-0006). `free_shipping` does not change prices; it plugs into the same Flexible Shipping filter the coupon free-shipping mu-plugin uses.
- **Ordering and exclusive**: Deals are evaluated in list order; a Deal marked exclusive stops later Deals for the same cart lines (it does not stop Member Privilege or Coupons: those obey ADR-0006). Without exclusive, today's combine-by-default (see Stacking in CONTEXT.md) is unchanged.
- **Admin**: the current form is one long page with the type chosen first. It becomes a stepped form (1 type and reward, 2 who and when, 3 products, 4 limits and options) with a plain-Thai summary sentence. The rule list gains drag-free up/down buttons (works without extra scripts), Duplicate and the exclusive chip.
- **Nothing here changes prices on live by itself**: the new fields default to "no extra condition", so every Deal already saved behaves exactly as before. New rules are created by the owner.

## Testing Decisions

Test through the core's public functions with plain arrays, as `tests/Bogo*Test.php` do. Each condition type gets its match / no match / unknown case; each new discount type gets worked-out amounts and a Member Privilege comparison; free shipping gets the filter test. The coupon-side checks and the admin form are checked in a real browser against the local site (raw CDP recipe in memory), including a real checkout.

## Out of Scope

- Replacing Flexible Shipping's own rate rules, or tax rules.
- Customer groups and membership levels (the Points & Rewards plugin's levels are not used by us).
- Importing the old Discount Rules rows automatically; the few enabled free-shipping rules are recreated by hand (ticket 08).
- Per-product "badge" and strike-through price changes on shop pages (Discount Rules' storefront price display). Cart and checkout only.
- Scheduling by recurring dates beyond weekday and time of day.

## Open questions for the owner

1. Which of the conditions in the list do you really want? Fewer is simpler to test and to use. Suggested core: logged in / role, orders count, total spent, weekday and time, cart quantity, cart subtotal, product or category in cart. Payment method and shipping province need the shopper to have chosen them, so they only apply at checkout.
2. Free shipping rule: should it replace the ฿299 threshold in Flexible Shipping (one place to manage), or stay beside it as extra free-shipping rules only?
3. "Exclusive": is "stop later rules for the same items" the behaviour you expect, or do you want a whole-cart "only the best one rule applies" switch instead?
