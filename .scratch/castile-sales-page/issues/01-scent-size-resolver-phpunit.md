# 01: Scent/size resolver function + PHPUnit harness

**What to build:** A single PHP function, `amq_castile_resolve_selection(string $scent, string $size): array`, that maps a (scent, size) selection to the real WooCommerce product/variation it corresponds to — product_id, variation_id, name, price, price_html, sku, packshot URL, and stock status. Covered by the repo's first PHPUnit suite, scoped to this function only.

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [ ] `amq_castile_resolve_selection()` exists and returns product_id, variation_id, name, price, price_html, sku, packshot URL, and stock status for a given scent + size pair
- [ ] Resolves all 9 valid (scent, size) combinations to the correct real product/variation: Lavender = product 2278 (variations 2281/2282/2283 for 100/250/500 ml), Rosemary = product 2285 (2287/2288/2289), Rose Geranium = product 2293 (2295/2296/2297)
- [ ] An invalid/unknown scent or size value is rejected predictably (does not silently fall back to a wrong product)
- [ ] An out-of-stock variation is reported as such in the returned data rather than treated as purchasable
- [ ] PHPUnit is set up in the repo (composer.json + phpunit.xml + bootstrap), running without requiring a live WordPress/WooCommerce bootstrap or DB connection (stub/mock the product/variation lookups)
- [ ] Test suite covers: all 9 valid combinations, an invalid scent, an invalid size, and an out-of-stock variation
- [ ] The suite runs via a documented command (e.g. `composer test`) and passes
