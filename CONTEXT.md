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
A discount the site computes from the items in the cart, managed in the AMOURIQ BOGO admin screen. Covers BOGO (same product, product pair, cheapest item free) and quantity tiers (more units, bigger percentage). A Deal is never a free-shipping rule or a coupon, and a spend-threshold discount ("spend X, get 20% off") is a plain Coupon, not a Deal.
_Avoid_: Promotion, offer (use Deal), "ดีล" for shipping or coupons

**Trigger**:
What makes a Deal apply: **auto** (applies as soon as the cart qualifies) or **coupon** (applies only while a named WooCommerce coupon code is in the cart). A coupon-trigger Deal references an existing coupon code; it never creates the coupon.

**Coupon**:
A WooCommerce coupon created under Marketing → Coupons. The source of truth for coupon codes, expiry, usage limits and its own free-shipping flag. Deals reference coupon codes but never own them.

**Stacking**:
Deals and Coupons combine on the same cart by default, and each is computed from regular price, not from a price already reduced by another discount. A coupon's minimum spend is also measured before Deals. A Deal can be marked as not combinable with other Coupons; when another Coupon is in the cart (not the Deal's own trigger coupon), that Deal gives no discount and the cart says why.
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
