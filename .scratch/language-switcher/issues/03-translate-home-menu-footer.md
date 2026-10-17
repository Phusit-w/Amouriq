# 03: Translate the home page, menu and footer into English

**What to build:** Claude drafts the English text for the home page, the header menu and the footer. The owner reviews every line. Once approved, clicking EN on Local shows these three areas fully in English with no leftover Thai.

**Blocked by:** 02 (Language switcher flips one page end to end)

**Status:** ready-for-human (the owner reviews the draft list; then run the apply step with status 2)

- [x] Draft English text delivered to the owner in one reviewable list (`translations/home-menu-footer.md`, 43 lines)
- [ ] Owner's corrections applied
- [x] Home page, menu and footer show no visible Thai text in English mode on Local (`english-leftover.e2e.mjs`, desktop and mobile; the page title, meta description and image alt text are not covered, see below)
- [x] Layout holds at desktop and mobile width (longer English text does not clip or overflow; full-page screenshots checked, no sideways scrolling)

## Comments

**How it works:** the drafts live in `translations/home-menu-footer.json` as `{th, en}` pairs keyed by the exact Thai text TranslatePress stores, so the same file can be loaded on any copy of the site. `tools/language-switcher/apply-translations.php <file> [1|2]` writes them into the Local TranslatePress dictionary (1 = draft, 2 = reviewed). Local only: the English text is database content, so how it reaches the live site is decided in ticket 06.

**After the owner's review:** put the corrections into the JSON (or tell Claude the line numbers), re-run the apply step with status 2, re-run `english-leftover.e2e.mjs /en/`.

**Findings:**
- The free TranslatePress does not translate the page `<title>`, the meta description or image `alt` text (they belong to its paid SEO Pack). English pages therefore keep the Thai title and description in the browser tab and in Google. Two drafted lines for them are in the list, ready for a small own-code fix. Suggest a new ticket (not created; owner decides).
- The cookie banner's first sentence uses the site name "Castil's" from the cookie plugin settings; looks wrong, same on the Thai site today.
- The Thai source has the typo "Certifired".
- Cookie banner text is in the list too because it shows on every first visit.
- The whole 43-line set also covers the footer links and the search box; menu items were already English.
- Two entries in the first attempt had to be corrected: TranslatePress stores quotes as `&#8220;` and compares case-sensitively, and has a near-duplicate "Essential OIl" row of its own; the apply step now matches exactly and reports unknown strings instead of inventing them.
