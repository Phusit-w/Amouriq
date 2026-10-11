Status: ready-for-agent

# Promotions Rework

Source: the owner's brief "โปรฯ บนเวปไซด์.docx" (7 items), settled in a grilling session on 2026-10-11. Vocabulary is in `CONTEXT.md` (Deal, Coupon, Member Privilege, Minimum spend, Stacking, New customer). The stacking rule is recorded in `docs/adr/0006-member-privilege-never-stacks-larger-discount-wins.md`.

## Problem Statement

The owner wants the site's promotions to be configurable by an admin without a developer, and to behave consistently. Today the Deal engine only supports a single fixed product per Deal, has no spend-threshold promotions, has no gift-with-purchase, has no Member Privilege, and the printed shipping label is not laid out the way the warehouse wants. The owner's brief also gave overlapping, partly contradictory rules for how discounts combine with a member's 5%.

## Solution

Extend the Deal engine and the AMOURIQ BOGO admin screen so an admin can set up: spend-threshold discounts, spend-threshold gifts, buy-A-discount-B with a customer choice in the cart, and mix-and-match quantity tiers, each across several products and variations picked by ticking a live list from WooCommerce. Add Member Privilege (5%) as its own discount that never stacks: the larger discount wins. Extend Coupons for new customers and listed emails. Re-lay out the shipping label as a portrait, top-to-bottom document without prices.

## User Stories

1. As an admin, I want to create a "spend X baht, get Y% off" Deal, so that I can run a threshold promotion without a developer.
2. As an admin, I want to choose whether a Deal discount is a percentage or a baht amount, so that I can run either kind of promotion.
3. As an admin, I want to set the Deal's minimum spend, discount value, start and end date and time, so that the promotion runs exactly when planned.
4. As an admin, I want to tick the products and variations that take part in a Deal (or that are excluded), so that I control which items it applies to.
5. As an admin, I want the product list to come live from WooCommerce, so that new or changed products appear without extra steps.
6. As an admin, I want each product and variation in the list to show its stock status, so that I can see what is out of stock before picking it.
7. As an admin, I want to switch a Deal on or off, so that I can pause it without deleting it.
8. As an admin, I want to see why a Deal is not Active (off, outside dates, trigger unmet, limit reached), so that I can fix it.
9. As an admin, I want to create a "spend X baht, choose a free gift" Deal, so that I can reward larger baskets.
10. As an admin, I want to set the gift list, how many gifts the customer may choose, minimum spend, dates and on/off for a gift Deal, so that I control the offer.
11. As an admin, I want a Deal on a product A to give a chosen number of product B a percentage or baht discount, so that I can run buy-A-discount-B promotions.
12. As an admin, I want to tick several variations as product B in one Deal, so that one promotion covers every scent.
13. As an admin, I want to define quantity tiers (for example 2 pieces 8%, 3 pieces 10%) over several ticked products, so that customers can mix and match.
14. As an admin, I want new Deals to skip Sale Price items by default, with a per-Deal switch, so that sale prices are not discounted twice.
15. As an admin, I want to create a coupon for new customers only, so that I can send a welcome code such as WELCOME10.
16. As an admin, I want to limit a coupon to a list of emails, so that a code reaches only a chosen group.
17. As an admin, I want per-person and total usage limits on a coupon, so that a code cannot be abused.
18. As an admin, I want to set a coupon's minimum spend, included and excluded products and dates, so that I control where it applies.
19. As a customer with an account, I want 5% off Regular Price items from my first order when my items total at least 299 baht, so that I am rewarded for buying.
20. As a customer, I want Sale Price items to keep their sale price and not be discounted further by Member Privilege, so that I understand my total.
21. As a customer, I want only the larger of Member Privilege and a Deal on an item to apply, so that discounts are never silently doubled.
22. As a customer, I want items not in a Deal to still get Member Privilege when another item in my cart gets the Deal, so that I do not lose the 5% on the rest.
23. As a customer, I want a coupon to replace Member Privilege when the coupon gives more, so that I always get the better offer.
24. As a customer, I want to see in the cart why a discount did or did not apply, so that I am not confused at checkout.
25. As a customer, I want the cart to show every eligible gift when I reach the spend threshold, so that I can choose the one I want.
26. As a customer, I want out-of-stock gifts to be unselectable, so that I cannot choose something unavailable.
27. As a customer, I want my gift to disappear, with a notice, if my cart drops below the threshold, so that the cart total stays honest.
28. As a customer, I want to remove a gift I do not want, so that I can decline it.
29. As a customer, I want a clear prompt in the cart and at checkout when I have an unused gift entitlement, so that I do not miss it, but I want to be able to check out without choosing.
30. As a customer, I want to choose product B myself from the cart when I qualify for a buy-A-discount-B Deal, so that I get the scent I like.
31. As a customer, I want out-of-stock choices for product B to be unselectable, so that I only pick items I can receive.
32. As a customer, I want a mix-and-match tier to count all eligible pieces across scents, so that I can combine freely.
33. As a customer, I want the highest tier I reach to apply to all eligible pieces (3 pieces is 10%, not 8% plus 10%), so that the price is predictable.
34. As a customer, I want a threshold promotion to count my whole item total, before discounts and excluding shipping, so that the rule is easy to understand.
35. As a new customer, I want a welcome coupon to work on my first paid order only, so that the offer is fair.
36. As a customer who registers during checkout, I want Member Privilege and new-customer coupons to work in that same checkout, so that I get them on my first order.
37. As a warehouse worker, I want the shipping label as a portrait document with sender, recipient, order number and an item table, so that I can pack and ship quickly.
38. As a warehouse worker, I want long addresses to wrap on the label, so that nothing is cut off.
39. As a warehouse worker, I want every ordered item (including free gifts) listed in the table with name, option (size or scent) and quantity, so that I pack the right goods.
40. As a warehouse worker, I want no prices, discounts or totals on the label, so that the recipient does not see them.
41. As the owner, I want a review of the live Discount Rules before any of this goes live, so that old rules do not discount the same items again.

## Implementation Decisions

- **Deal gains new types**: spend-threshold discount, spend-threshold gift. The existing product-pair and quantity-tier types are reworked to take a set of products/variations instead of one product.
- **Pure calculation module**: the pricing logic moves into one module that takes plain data (cart lines, customer facts, Deal and Coupon definitions) and returns the discount for each cart line. The WooCommerce hooks become a thin layer around it. This is the single testing seam.
- **Precedence** follows ADR-0006: Member Privilege never stacks; per cart line against Deals, whole cart against a Coupon. Deals and Coupons keep stacking with each other, with the existing "not combinable" flag.
- **Member Privilege**: 5% of Regular Price lines, for any customer with an account (including one created during that checkout); minimum spend 299 baht; no cap; no end date. It is built in our own code. Any Points & Rewards membership levels active on the live site must be turned off before launch.
- **Minimum spend** counts every item, Sale Price items included, before any discount, excluding shipping. The discount itself applies only to the items the rule covers.
- **Sale Price items**: Member Privilege never discounts them. New Deals default to skipping them, with the existing per-Deal switch.
- **Gifts and chosen items are real cart lines.** A gift is a zero-price line with locked quantity, so stock is decremented through normal WooCommerce flow and the item appears on the order and the label. The customer may remove it. It is removed automatically, with a notice, when the minimum spend is no longer met or the item runs out of stock. Gifts do not count toward minimum spend or toward other Deals' quantities.
- **Choosing in the cart**: for gift Deals and buy-A-discount-B Deals the cart lists every eligible item, out-of-stock ones disabled, and lets the customer pick up to the entitled number. Not choosing never blocks checkout; a prominent notice shows in the cart and at checkout.
- **Mix and match**: quantity is summed across all ticked products and variations; the highest tier reached applies to every eligible piece.
- **Product pickers** in the admin read live from WooCommerce (published products and their variations), as a tick list with stock status shown, replacing the single dropdown. A deal that references a deleted variation keeps showing the existing "not found" warning.
- **Coupons** stay native WooCommerce coupons. The existing first-order plugin supplies the "new customer only" option using the New customer definition; "customer group" means a list of allowed emails, which WooCommerce already supports. No new group concept.
- **Shipping label** is a separate piece of work: change the template of the existing WebToffee shipping-label module to the portrait layout (logo and sender; recipient; order number; item table of number, product name, option, quantity; long addresses wrap; no prices). Whether that plugin is active must be confirmed against the live or local database before starting.
- **Rollout**: nothing goes live until the ~20 live Discount Rules are reviewed (owner action). Live file uploads are done by the owner from a prepared kit, as for the previous Deal work.

## Testing Decisions

- A good test checks external behaviour: given these cart lines, this customer and these Deals/Coupons, these are the discounts and messages. It never asserts on internal functions or the order they run.
- **Primary seam**: the pure calculation module, tested with PHPUnit against fake products (`FakeWcProduct`), with no WordPress running. Cases include Member Privilege alone, Member Privilege versus a Deal on one line while other lines keep the 5%, Member Privilege versus a Coupon (larger wins in each direction), Sale Price lines, minimum-spend boundaries (299, threshold exactly met), the highest-tier rule (5 pieces with tiers at 2 and 3), gift removal when the cart falls below the threshold, out-of-stock choices, and the New customer rule (counted statuses, matched by account or billing email).
- **Not automated**: the admin screen, cart choice UI, real stock decrement and the shipping label are checked by hand and in a real headless browser, as in the earlier Deal work.
- **Prior art**: the existing `tests/CastileResolverTest.php` and `tests/Fixtures/FakeWcProduct.php`.

## Out of Scope

- Anything about the free-shipping threshold or free-shipping coupons.
- Deleting or migrating the live Discount Rules (only reviewing them is in scope as a launch gate).
- Customer groups other than a list of emails (tags, lifetime spend).
- Live-site deployment itself, which the owner performs.
- Guest checkout, points/rewards features, and the unrelated mobile text-clipping and photo items on the Castile page.

## Further Notes

- The owner confirmed that Member Privilege is effectively a 5% sitewide discount on Regular Price items, since every buyer has an account.
- The existing negative-fee tax quirk (WooCommerce taxes a negative fee on top regardless of the taxable flag) applies to any new discount that is implemented as a fee; reuse the tax-exclusive pricing approach already in the engine.
- Product data for local checking is in the exported CSV (59 variable products, 201 variations); the picker does not read it, it reads WooCommerce.
