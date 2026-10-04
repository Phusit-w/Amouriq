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
A buy-X-get-Y style discount on cart items (BOGO) managed in the AMOURIQ BOGO admin screen. Three kinds: same product, product pair, cheapest item in a category free. A Deal is only the BOGO discount; it is never a free-shipping rule or a coupon.
_Avoid_: Promotion, offer (use Deal), "ดีล" for shipping or coupons

**Trigger**:
What makes a Deal apply: **auto** (applies as soon as the cart qualifies) or **coupon** (applies only while a named WooCommerce coupon code is in the cart). A coupon-trigger Deal references an existing coupon code; it never creates the coupon.

**Coupon**:
A WooCommerce coupon created under Marketing → Coupons. The source of truth for coupon codes, expiry, usage limits and its own free-shipping flag. Deals reference coupon codes but never own them.

**Free-shipping threshold**:
The cart subtotal at which the Flexible Shipping method ships free automatically. Owned by Flexible Shipping (Thailand zone), not by any Deal. Deals and coupons do not change it.
_Avoid_: Deal, promotion
