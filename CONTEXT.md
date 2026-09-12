# AMOURIQ Site

A WordPress + WooCommerce site for AMOURIQ, a Thai organic skincare brand. This working directory is a restored UpdraftPlus backup of the live site **amouriq.co.th**. Theme in use: `hello-elementor-child` (a Hello Elementor child theme with AMOURIQ-specific mu-plugins). `pcoursewebbs` is an unrelated leftover theme from a different project and is not part of this context.

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
