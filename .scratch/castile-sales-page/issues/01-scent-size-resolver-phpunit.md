# 01: Scent/size resolver function + PHPUnit harness

**What to build:** A single PHP function, `amq_castile_resolve_selection(string $scent, string $size): array`, that maps a (scent, size) selection to the real WooCommerce product/variation it corresponds to — product_id, variation_id, name, price, price_html, sku, packshot URL, and stock status. Covered by the repo's first PHPUnit suite, scoped to this function only.

**Blocked by:** None (can start immediately)

**Status:** done

- [x] `amq_castile_resolve_selection()` exists and returns product_id, variation_id, name, price, price_html, sku, packshot URL, and stock status for a given scent + size pair
- [x] Resolves all 9 valid (scent, size) combinations to the correct real product/variation: Lavender = product 2278 (variations 2281/2282/2283 for 100/250/500 ml), Rosemary = product 2285 (2287/2288/2289), Rose Geranium = product 2293 (2295/2296/2297)
- [x] An invalid/unknown scent or size value is rejected predictably (does not silently fall back to a wrong product)
- [x] An out-of-stock variation is reported as such in the returned data rather than treated as purchasable
- [x] PHPUnit is set up in the repo (composer.json + phpunit.xml + bootstrap), running without requiring a live WordPress/WooCommerce bootstrap or DB connection (stub/mock the product/variation lookups)
- [x] Test suite covers: all 9 valid combinations, an invalid scent, an invalid size, and an out-of-stock variation
- [x] The suite runs via a documented command and passes

## Comments

- Implemented `amq_castile_resolve_selection()` in `wp-content/mu-plugins/amouriq-castile-resolver.php`, following the existing `amq_get_sorted_variations()` pattern in `amouriq-single-product.php` (reads `get_available_variations()` rather than per-variation `wc_get_product()` calls); guard clauses use the repo's existing single-line no-brace early-return style.
- 12 tests / 55 assertions, all green: `tests/CastileResolverTest.php` (fixtures in `tests/Fixtures/FakeWcProduct.php`, WP/WC stubs in `tests/bootstrap.php`).
- `composer install` could not run in this sandbox (the bundled portable PHP's SSL trust store rejects Packagist's cert — an environment quirk, not a code issue). `composer.json` declares `phpunit/phpunit ^10.5` as a dev dependency; `composer install && composer test` (or `vendor/bin/phpunit`) is the documented, reproducible path on a normal machine — see `tests/bootstrap.php`'s header for the exact commands, including the local-only `phpunit.phar` workaround used to verify green in this specific sandbox (that phar is gitignored, not committed — vendoring a 5.2MB binary into this repo isn't something the ticket asked for).
- Reviewed via `/code-review` (Standards + Spec axes) before commit; both the style-consistency and scope-creep findings above are addressed in this version.
