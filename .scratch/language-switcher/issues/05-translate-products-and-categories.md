# 05: Translate products and category pages into English

**What to build:** Every product (all are variable products whose only choice is the Size, 10 to 1000 ml; each Scent, e.g. the Lavender castile soap, is its own product) and the category pages show English names, descriptions and Size labels. A customer in English mode can add a Size to the cart. Prices stay in baht. The cart and checkout stay out of scope for this phase: they need not be translated, but they must keep working after an English-mode add to cart.

**Blocked by:** 02 (Language switcher flips one page end to end)

**Status:** ready-for-human (drafts loaded on Local and checked; waiting for the owner's corrections)

- [ ] Draft English text delivered to the owner and corrections applied (delivered: `translations/products-categories.md`, 414 lines, flags at the top; corrections not yet)
- [x] Product and category pages show no leftover Thai in English mode on Local (`english-leftover.e2e.mjs` over the 63 paths in `translations/products-categories.paths.txt`, desktop and mobile: 126 pass)
- [x] Size selection, price and add to cart work in English mode (`products-buying.e2e.mjs`: castor oil and the Lavender castile soap; the category grid's "Select options" buttons lead to the English product pages)
- [x] Adding to the cart in English mode and continuing to checkout still works, with prices in baht (same test: the English cart and checkout list both items, prices in ฿)

## Comments

**How it works:** same as ticket 03. `translations/products-categories.json` holds `{th, en}` pairs keyed by the exact stored Thai; `apply-translations.php <file> [1|2]` loads them. The review list is generated with `node tools/language-switcher/review-list.mjs <file.json>` (the table part; the flags are written by hand above it).

**After the owner's review:** put the corrections into the JSON, re-run `apply-translations.php products-categories.json 2`, clear the page cache (below), re-run `node tools/language-switcher/english-leftover.e2e.mjs $(cat .scratch/language-switcher/translations/products-categories.paths.txt)` (in Git Bash set `MSYS_NO_PATHCONV=1` first, or it rewrites the `/en/...` paths) and `products-buying.e2e.mjs`.

**Correction of the spec:** the original text said "three Scents, each with 100/250/500 ML Sizes". The real catalogue has one Size choice per product and a separate product per Scent; the text above now says so.

**Findings (matter for ticket 06, the live kit):**
- **Page cache:** the site keeps a full-page cache in `wp-content/cache/all/` (served to browsers, not to URLs with a query string). Pages cached before the translations were loaded keep showing Thai. After loading translations, the cached `en/` pages must be cleared. The live site has its own cache to clear the same way.
- **English gettext rows that were Thai:** 181 rows in `wp_trp_gettext_en_us` (WooCommerce strings, recorded around the plugin's install) held Thai as their "English", e.g. "N/A" → "ไม่ระบุ" on every product's Additional information tab. `tools/language-switcher/clear-thai-gettext-en.php clear <backup.json>` empties them so the plugin's own English shows (`restore <backup.json>` puts them back; it refuses to overwrite an existing backup). Done on Local; the previous values are in `.scratch/language-switcher/gettext-en-backup.json` (ids of the Local database, so it restores only Local). On live, check the MySQL version first: the Thai-character REGEXP needs MySQL 8 or MariaDB. Check the live tables for the same rows before switching on.
- **Matching:** TranslatePress stores each `<br>`-separated line as its own string, with dashes as `&#8211;`. Product descriptions also exist as older whole-text rows that are no longer shown; those were translated too and do no harm.
- **Checkout** shows the Thai "ฟรี" in the free-gift line in English mode (own code, out of scope here).
