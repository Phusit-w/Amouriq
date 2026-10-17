# 01: Confirm the translation system on Local and record the ADR

**What to build:** Install TranslatePress on the Local copy and prove it works with what the site actually uses: Elementor pages, the Blocksy header, and WooCommerce variable products. Thai stays the default language at the original addresses and English lives under `/en/`. Each page declares its alternate language (hreflang). Then write the ADR recording the real result. If TranslatePress fails any of these, try Polylang instead and record why. The live site has no multilingual system at all today (`/en/` returns 404), so this is a fresh install, not a migration.

**Blocked by:** None (can start immediately)

**Status:** done

- [x] The free tier is confirmed (from the vendor's current documentation) to cover one extra language, English
- [x] On Local, `/en/` serves a translated copy of a page and the Thai address is unchanged
- [x] An Elementor page, a variable product page and the Blocksy header all render correctly in both languages
- [x] Each page carries hreflang links to its alternate
- [x] Nothing in the CASTILE page's stored Elementor data is written by the plugin's install or activation (checked from a fresh process)
- [x] An ADR records the chosen system, the alternatives and the reasons

## Comments

**Finding that changes the plan:** the plugin was already present on Local (and its tables with it), installed and set up earlier but switched off. Its settings: Thai default, English (en_US) published under `/en/`, floating language selector off. Its dictionary has 4,228 Thai strings, of which only 67 have a human-checked English text; the other 4,161 are untranslated. Activating it was therefore all that Local needed. Tickets 03-05 are about filling in that dictionary.

**Done on Local:** activated `translatepress-multilingual` (3.1.9 files; stored version 3.1.7) with WP-CLI. Local-only, no download. To undo: deactivate it.

**Verified (read-only, 2026-10-17):**
- Thai home page: `lang="th"`, no change. `/en/`: 200, `lang="en-US"`, hreflang `th`, `en-US` and `en` present.
- Product (Lavender, id 2278) and CASTILE page: 200 in both languages. Elementor element count identical (429 on CASTILE), the variation form is present on the product in both languages.
- CASTILE `_elementor_data`: 257861 bytes, hash `6c46de0f...` before and after activation (identical).
- Free tier = default language plus one extra (vendor documentation); no licence installed.
- The Blocksy header exists in both languages; its switcher is added in ticket 02.

**Facts for ticket 02:**
- The sitewide font stylesheet is currently the English one on Thai pages too, because the language-font code asks Polylang, which is absent, and falls back to English. Moving it to TranslatePress fixes that.
- The `/en/shop/` address is a 404 only because `/shop/` does not exist in Thai on Local either.
- The stored plugin version (3.1.7) differs from the files (3.1.9); the plugin did not complain. Worth a look at the live site's version before upload (ticket 06).
