Status: ready-for-agent

# CASTILE Sales Page

## Problem Statement

AMOURIQ sells its Organic Olive Castile Soap line (Lavender, Rosemary, Rose Geranium) on amouriq.co.th, but there is no persuasive, evidence-led entry point for it — shoppers only reach bare WooCommerce product pages, or a non-selling brand-story page ("Our Castile Soap") buried as a submenu item under ABOUT. The owner already had a full, high-fidelity sales page designed (via Claude Design, 24 sections, evidence-led copy, lab data, FAQ) but has no way to get it live on the real WordPress site at production fidelity while still being able to edit it themselves going forward, and no way for a shopper to actually buy from it.

## Solution

Add a new top-level **CASTILE** item to the site's navigation (between SHOP and BLOG) that opens a new sales page at `/castile/`. The page reproduces the supplied Claude Design handoff section-by-section as genuine, field-editable Elementor content, defaults to the Lavender scent, lets the shopper switch scent and size (backed by the three real, already-published WooCommerce products), and adds the selected variant to the real WooCommerce cart the same way Add to Cart already works elsewhere on the site — without navigating the shopper away from the page. The existing "Our Castile Soap" page stays exactly where it is, untouched, as a submenu item under ABOUT.

## User Stories

1. As a shopper browsing amouriq.co.th, I want a "CASTILE" item in the main navigation, so that I can find the Castile Soap sales page without searching or landing on it by accident.
2. As a shopper, I want the CASTILE menu item positioned between SHOP and BLOG, so that the navigation matches the order the brand intends.
3. As a shopper landing on the CASTILE sales page, I want the full evidence-led story (trust bar, hero claim, lab evidence, ingredient sourcing, ritual, certifications, testimonial, FAQ, footnotes) reproduced faithfully from the approved design, so that I can make an informed purchase decision.
4. As a shopper, I want the page to default to the Lavender scent, so that I see a complete, realistic example immediately on load.
5. As a shopper, I want to switch between Lavender, Rosemary, and Rose Geranium in the buying block, so that I can explore the scent that appeals to me without leaving the page.
6. As a shopper, when I switch scent, I want the packshot image, product name, essential-oil description, and price to update to match that scent's real product, so that what I see matches what I'll actually buy.
7. As a shopper, I want to choose a size (100/250/500 ML), so that I can buy the bottle size I want.
8. As a shopper, when I switch size, I want the price and SKU to update immediately without a page reload, so that I always see accurate pricing before I buy.
9. As a shopper, I want to adjust the quantity before adding to cart, so that I can buy more than one at a time.
10. As a shopper, I want to click "Add to Cart" and have the exact scent+size+quantity I selected added to my real WooCommerce cart, so that checkout reflects my actual choice.
11. As a shopper, I want to stay on the sales page after adding to cart, with visible confirmation (e.g. cart icon count updates), so that I can keep reading or shopping without losing my place.
12. As a mobile shopper, I want a sticky "Add to Cart" bar that appears once I've scrolled past the hero section, so that I can buy at any point in my reading without scrolling back up.
13. As a shopper on a screen narrower than ~860px, I want layouts (hero, ritual section, final close section) to stack sensibly (image above text), so that the page stays readable on my phone.
14. As a shopper with reduced-motion preferences enabled, I want scroll animations (count-ups, mask reveals, bar charts) to render in their final state immediately rather than animate, so that the page respects my OS accessibility setting.
15. As a shopper, I want the numeric claims (moisture +13.37%, water loss −10.67%, etc.) to animate into view once as I scroll past them, matching the approved design's motion direction, so that the page feels polished and on-brand.
16. As a shopper, I want the FAQ section to work as an accordion I can expand/collapse, so that I can read only the answers I'm interested in.
17. As a shopper, I want footnote markers in the copy to jump to their definitions at the bottom of the page, so that I can verify regulatory/evidence claims.
18. As the site owner/marketing editor, I want every static element of the sales page (headlines, body copy, images, proof cards, section backgrounds) editable in Elementor, so that I can update copy, swap photography, or restyle sections myself without a developer.
19. As the site owner, I want the buying block's live data (price, SKU, stock) to always come from the real WooCommerce products, so that the sales page can never show stale or incorrect pricing even when the rest of the page is edited by someone other than a developer.
20. As the site owner, I want the existing "Our Castile Soap" page under ABOUT left completely untouched, so that its existing content, URL, and SEO history aren't disrupted by this new page.
21. As the site owner, I want the new CASTILE menu item to appear consistently across every page template on the site (Home, Shop, Blog, Contact, single Product, single Post), so that shoppers can reach the sales page from anywhere on the site.
22. As a developer maintaining this site, I want the scent+size → product/variation/price/SKU resolution to be a single, well-defined function, so that the mapping logic isn't duplicated across the page template and the add-to-cart handler.
23. As a developer, I want that resolution function covered by an automated PHPUnit test, so that a future catalog change (e.g. a 4th scent, renumbered variations) can't silently break the buying block without a test failing.
24. As a developer, I want the sales page's supplementary CSS/JS for animation timing kept in the child theme's existing asset pipeline (`assets/css`, `assets/js`, conditionally enqueued), so that it fits the codebase's existing conventions (same pattern as the Single Product page).
25. As the site owner, I want this change committed to git in small, revertible steps, so that if something goes wrong on the live site after deployment, we can identify and roll back exactly what broke it.

## Implementation Decisions

- **Page**: new WordPress Page at slug `castile`, built with Elementor, using the site's existing "static" header/footer chrome (the same visual treatment as Shop/Blog/Contact/single-Product/single-Post — `get_header('shop')` / `get_footer('shop')`), not Home's hero-based header. This site has no sitewide header/footer system (see `amouriq-single-product.php`/`header-shop.php` comments) — every template calls `get_header('shop')` explicitly, so the Castile page template must do the same.
- **Menu — DB "Main Menu" (term_id 24)**: insert a new `nav_menu_item` titled `CASTILE`, linking to the new page, positioned between the existing SHOP (currently `menu_order` 1) and BLOG (currently `menu_order` 2) items; renumber subsequent items' `menu_order`. The existing "Our Castile Soap" item (currently a submenu item under ABOUT) is not moved, relabeled, or retargeted.
- **Menu — hardcoded nav**: `wp-content/themes/hello-elementor-child/header-shop.php` gets a `CASTILE` link added between SHOP and BLOG in the hardcoded `.amq-header-nav` markup (this nav is separate from the DB menu and used on Shop/Blog/Contact/Product/Post pages).
- **Menu — Home page**: if Home's Elementor-authored header (`#amq-header`) sources its nav from the DB "Main Menu" via a native Elementor Nav Menu widget, the DB change above is sufficient; confirm this at build time (vs. Elementor Pro Theme Builder header, or a widget placed directly on the Home page) rather than assuming.
- **Elementor build**: the page's content sections (per the design handoff's section table, 00–23, "Section 19" being the buying block) are built as native Elementor widgets/containers, matching the design tokens (colors, type, spacing) in `_ds/…/styles.css` from the supplied design bundle. No Elementor widget is used for the buying block section itself (below).
- **Buying block (Section 19)**: a custom Elementor widget (registered from a dedicated mu-plugin, following the `amouriq-single-product.php` pattern of AMOURIQ-specific logic living in `wp-content/mu-plugins/`) that:
  - Renders the scent selector (Lavender/Rosemary/Rose Geranium) and size selector (100/250/500 ML) client-side.
  - Resolves any (scent, size) selection via one PHP function, `amq_castile_resolve_selection(string $scent, string $size): array`, returning `product_id`, `variation_id`, `name`, `price`, `price_html`, `sku`, packshot URL, and stock status. Since there are only 3×3 = 9 combinations total, this can be pre-resolved server-side into a small JS data object on page load rather than requiring an AJAX round-trip per click.
  - "Add to Cart" submits `product_id` + `variation_id` + quantity through WooCommerce's standard AJAX add-to-cart action, reusing WooCommerce's own cart-fragments refresh so the header cart icon updates without a page reload — matching existing Add to Cart behavior elsewhere on the site.
  - The mobile sticky cart bar reuses the same resolved-selection data; shown only under 860px viewport width once `#hero` has scrolled out of view (IntersectionObserver + media query listener — not cached scroll offsets, per the design brief).
- **Animation/motion**: scoped CSS/JS assets in the child theme's `assets/css` / `assets/js`, conditionally enqueued only on the Castile page (mirroring the `is_product()`-gated conditional load in `amouriq-single-product.php`), targeting Elementor's own generated widget classes/IDs rather than introducing markup outside Elementor's control. Reproduces the design's specified easing/duration/stagger values; honors `prefers-reduced-motion: reduce`.
- **Old page 2366** ("Our Castile Soap"): no changes of any kind.
- `docs/adr/0001-castile-sales-page-built-in-elementor.md` already records why this page departs from the Single Product page's "no Elementor" precedent; implementation should follow it.

## Testing Decisions

- This repo has no existing automated test framework. This feature introduces the first one, scoped narrowly to the one function with real logic risk: `amq_castile_resolve_selection()`.
- Set up PHPUnit (composer.json + phpunit.xml, scope — repo root vs. mu-plugin-local — is an implementer call) sufficient to test this function in isolation, stubbing/mocking WooCommerce product/variation lookups rather than requiring a full WordPress+WooCommerce bootstrap or live DB, so the suite stays fast and portable.
- Test cases to cover: each of the 9 valid (scent, size) combinations resolves to the correct real `product_id`/`variation_id`/price/SKU; an invalid/unknown scent or size value is rejected predictably (never silently falls back to a wrong product); an out-of-stock variation is reported as such rather than treated as purchasable.
- Everything else — menu placement/order, Elementor page visual fidelity, end-to-end add-to-cart behavior, sticky bar behavior, animations, responsive stacking, accordion, footnote anchors — is verified by manual QA in a browser against the running site. No new automated coverage for these, matching how the rest of this codebase (including the Single Product page) is already tested.

## Out of Scope

- Merging the three Castile Soap WooCommerce products into a single product with a scent attribute — explicitly rejected as too large/risky a catalog change for this feature.
- Redirecting, editing, or removing the existing "Our Castile Soap" page (id 2366) — explicitly left untouched.
- A checkout-redirect ("funnel") add-to-cart flow — explicitly rejected in favor of staying on the page.
- Sourcing or generating final photography for Section 15/22 or the 100/250 ml packshots — ships with the interim placeholder assets already provided in the design handoff bundle.
- Any change to translation/TranslatePress configuration — the page follows the site's existing bilingual pattern (English UI labels, Thai body copy) with no special handling introduced.
- SEO/metadata work beyond what Elementor and the site's existing SEO plugin configuration already provide by default.
- Any change to the site's other two nav menus ("Sub menu" language switcher, "Sitelinks").

## Further Notes

- Source design: `AMOURIQ Lavender Soap Sales Page/` at the project root (a Claude Design handoff) — see `design_handoff_amouriq_sales_page/README.md` for reimplementation notes and `uploads/AMOURIQ_Castile_Soap_Lavender_SalesPage_V7_DesignBrief.md` for the full section-by-section content, copy, and design tokens. Treat this as the primary source for exact copy, colors, spacing, and animation timing — the Thai copy has been through regulatory review; do not paraphrase or "improve" it.
- Real WooCommerce IDs to wire against: Lavender = product 2278 (variations 2281/2282/2283 for 100/250/500 ml), Rosemary = product 2285 (2287/2288/2289), Rose Geranium = product 2293 (2295/2296/2297). Confirmed live SKUs are `SL-CT-NA-{LV|RM|RG}0{100|250|500}-2` — pull SKU from the live variation at render/resolve time rather than hardcoding these strings; they're listed here only so the implementer can sanity-check the resolver's output.
- Full requirements-gathering conversation and rationale live in `CONTEXT.md` and `docs/adr/0001-castile-sales-page-built-in-elementor.md` at the repo root.
