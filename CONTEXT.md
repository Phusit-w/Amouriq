# AMOURIQ Site

A WordPress + WooCommerce site for AMOURIQ, a Thai organic skincare brand. This working directory is a restored UpdraftPlus backup of the live site **amouriq.co.th**.

**Active theme vs. target theme (see ADR-0002):** the theme actually active right now (`wp_options.stylesheet`) is `pcoursewebbs`, a near-empty Blocksy child theme — that's what the live site currently renders with, including its Home page. `hello-elementor-child` (a Hello Elementor child theme with AMOURIQ-specific mu-plugins) is where all the AMOURIQ-specific custom work lives, but it is **not currently active** — it's an in-progress redesign. New feature work (this project included) is built against `hello-elementor-child`, by deliberate decision, even though it isn't live yet.

## Language

**Castile Soap**:
AMOURIQ's olive-oil soap product line, sold as three separate WooCommerce products distinguished by **scent** — Lavender (product id 2278), Rosemary (2285), Rose Geranium (2293) — each further split by **size** (100/250/500 ML) as WooCommerce variations on the `pa_quantity` attribute.
_Avoid_: "Variant" alone (ambiguous — see Scent vs. Size below)

**Scent**:
Which Castile Soap product a customer is buying (Lavender / Rosemary / Rose Geranium). Modeled as three distinct WooCommerce products, not as a shared attribute on one product — each has its own id, permalink, and SEO history.
_Avoid_: Variant, flavor

**Size**:
The bottle volume (100/250/500 ML) a customer picks within a chosen Scent's product. Modeled as a WooCommerce variation on the `pa_quantity` attribute. Price and SKU depend on Size; Size choices are identical across all three Scents.
_Avoid_: Variant, option

**Sales page**:
A long-form, evidence-led marketing/landing page for one product line (e.g. the CASTILE sales page), built as a fully Elementor-editable page reproducing a Claude Design handoff pixel-for-pixel. Distinct from a **Product page** (see below): a Sales page argues the case for buying and ends in an embedded buy box; it is not the WooCommerce single-product template.
_Avoid_: Landing page (used interchangeably but "Sales page" is this project's term)

**Product page**:
The WooCommerce single-product detail template (`hello-elementor-child/woocommerce/single-product.php`) — one per SKU, shows ingredients/how-to-use/etc. Built as plain PHP with WordPress Customizer style controls, not Elementor (owner decision, 2026-07-27). Distinct from a Sales page (see above).
_Avoid_: Sales page, landing page

**Buying block**:
The interactive scent × size selector + Add to Cart control embedded in a Sales page. Bound to live WooCommerce product/variation data (price, SKU, stock), so unlike the rest of the Sales page it cannot be decomposed into independently Elementor-editable static fields — selecting a Scent swaps the underlying WooCommerce product entirely; selecting a Size swaps the variation within it.

**Deal**:
A discount or gift the site computes from the items in the cart, managed in the AMOURIQ BOGO admin screen. Covers BOGO (same product, product pair, cheapest item free), quantity tiers (more units, bigger percentage), spend-threshold discounts ("spend X, get Y% or Y baht off") and spend-threshold gifts ("spend X, choose a free item"). A Deal is never a free-shipping rule or a coupon; a Coupon is a code the customer types.
_Avoid_: Promotion, offer, Campaign (use Deal), "ดีล" for shipping or coupons

**Member Privilege**:
A 5% discount on Regular Price items for any customer with an account, from their first order, with a minimum item total of ฿299 and no maximum discount. Because every buyer has an account (guest checkout is off), in practice it applies to every order. Items already on Sale Price get nothing from it. It never adds to another discount: see Stacking.
_Avoid_: Member discount, loyalty discount (the Points & Rewards plugin's membership levels are a different, unused-by-us mechanism)
In Thai screens it is called **สิทธิพิเศษสมาชิก** (a working choice; the owner can change it). Do not call it ส่วนลดสมาชิก, which is "member discount". It is not a Deal, so it is not listed as one: it has its own owner switch (WooCommerce > ดีล BOGO), off until switched on, so uploading the code never changes prices by itself.

**Minimum spend**:
The cart item total, before any discount and excluding shipping, that a Deal, Coupon or Member Privilege requires. It counts every item, Sale Price items included, even when the discount itself only applies to some of them.

**Trigger**:
What makes a Deal apply: **auto** (applies as soon as the cart qualifies) or **coupon** (applies only while a named WooCommerce coupon code is in the cart). A coupon-trigger Deal references an existing coupon code; it never creates the coupon.

**Coupon**:
A WooCommerce coupon created under Marketing → Coupons. The source of truth for coupon codes, expiry, usage limits and its own free-shipping flag. Deals reference coupon codes but never own them.

**Stacking**:
Deals and Coupons combine on the same cart by default, and each is computed from regular price, not from a price already reduced by another discount. A coupon's minimum spend is also measured before Deals. A Deal can be marked as not combinable with other Coupons; when another Coupon is in the cart (not the Deal's own trigger coupon), that Deal gives no discount and the cart says why. **Member Privilege is the exception: it never stacks with anything, and the larger discount wins.** Against a Deal the comparison is per cart line (the Deal's item gets the Deal, other items still get Member Privilege); against a Coupon it is whole-cart.
_Avoid_: Mixing

**Enabled**:
The on/off switch an admin flips on a Deal or a free-shipping Coupon. A Coupon that is off is a draft, so it stays listed and can be switched back on.
_Avoid_: Active (a different thing, see below)

**Active**:
A Deal or Coupon that applies to the cart right now: it is Enabled, inside its dates, its Trigger is met and its usage limit is not reached. The admin status column shows "Active" or the reason it is not.

**New customer**:
A customer with no counted order (pending, on-hold, processing or completed; cancelled, failed and refunded do not count). Matched by account when logged in, otherwise by the billing email entered at checkout. Every buyer ends up with an account because guest checkout is off and new customers register during checkout.
_Avoid_: First-time visitor

**Free-shipping threshold**:
The cart subtotal at which the Flexible Shipping method ships free automatically. Owned by Flexible Shipping (Thailand zone), not by any Deal. Deals and coupons do not change it.
_Avoid_: Deal, promotion

**Points**:
The store's loyalty currency, kept in our own ledger (ADR-0007), not by the Points & Rewards plugin. Earned when an order is Completed (default 1 point per ฿100 of paid item total, shipping excluded) and spent at the cart or checkout (default 1 point = ฿1, at most 20% of the item total after every other discount, at least 50 at a time). Expire 12 months after they are earned. Off until switched on under WooCommerce > แต้มสะสม. In Thai screens: **แต้มสะสม**.
_Avoid_: Reward points, credit, cashback; "Member Privilege" (a different, percentage-based discount)

**Condition**:
A test on who the customer is, when it is, or what is in the cart or checkout, that a Deal, a Coupon or an Earning rule needs before it applies (logged in, role, orders so far, total spent, weekday and time, cart quantity or subtotal, product in cart, payment method, shipping province). A set of Conditions is "all must match" or "any must match". A Condition whose input is not known yet does not match (except New customer, which assumes new until the email is known). See ADR-0008.
_Avoid_: Rule (a Deal is the thing that gives something; a Condition only decides whether it applies), Restriction

**Product scope**:
Which products a Deal or Coupon covers: include and exclude lists by category, tag, attribute (such as size), SKU, product or variation, and "only items on sale". Replaces the hand-ticked product lists, which stay valid.

**Exclusive**:
A Deal option: later Deals in the list do not also apply to the cart lines this Deal discounted. It never stops Member Privilege or Coupons, which follow Stacking.

**Earning rule**:
A line in the points settings that changes how many Points an order earns: a different rate for a scope, an exclusion, a date-range multiplier, a minimum order total or a per-order cap. The base rule (1 point per ฿100) applies where no rule matches.

**Reward**:
Something a customer buys with Points from My Account: a single-use personal Coupon, or a free gift product. Redeeming takes the points at once and is recorded in the ledger.
_Avoid_: Prize, voucher

**Adjustment**:
A staff change to one customer's Points balance (add or deduct) with a required reason, recorded in the ledger with who made it.
