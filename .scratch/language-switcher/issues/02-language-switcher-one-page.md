# 02: Language switcher flips one page end to end

**What to build:** The **Language switcher** ("EN / TH") appears in the site header on desktop and in the mobile menu. The current language shows as plain text, the other as a link to that page's translation. The site always opens in Thai and changes language only when the customer clicks the switcher, never from the browser's language. The sitewide font follows the language (Prompt for Thai, Plus Jakarta Sans for English). The existing switcher and font code are tied to Polylang and must be moved to the system chosen in ticket 01. The active theme's header builder places the switcher, not the inactive redesign theme.

**Blocked by:** 01 (Confirm the translation system on Local and record the ADR)

**Status:** done

- [x] Switcher visible in the header (desktop) and the mobile menu on Local
- [x] Clicking EN on a translated page lands on its English version, and TH returns to the same Thai page
- [x] ~~A page with no English version sends EN to the English home page~~ Not applicable with TranslatePress: every page has an `/en/` address, and text not yet translated simply shows in Thai. The switcher therefore always keeps the customer on the same page.
- [x] A fresh visit with an English browser still opens in Thai
- [x] Font changes with the language
- [x] Verified in a real headless Chrome session, both languages, desktop and mobile width

## Comments

**Proof:** `node tools/language-switcher/switcher.e2e.mjs` (real headless Chrome over CDP against Local): 14 checks, all pass. Existing PHP suite: 373 tests OK. CASTILE `_elementor_data` hash unchanged.

**What changed:**
- The shortcode now asks TranslatePress for the current language and for the same page in the other language. TranslatePress rewrites every internal link on the page to the current language; the shortcode keeps TranslatePress's own "already processed" marker on the link so that rewriting does not turn the TH link on an English page back into `/en/`.
- The font code reads the language from TranslatePress.
- Placement (Local only so far): the `[lang_switcher]` shortcode sits inside the existing header HTML element (the promo bar, which Blocksy shows on desktop and mobile), at the right edge, with side padding of 56px so the centred Thai text never touches it. Done by editing the header theme setting in the Local database. **For live (ticket 06):** the owner edits the same HTML element in Appearance > Customize > Header: padding `7px 56px` on the promo bar, and before its closing `</div>` add `<span style="position: absolute; right: 16px; top: 50%; transform: translateY(-50%);">[lang_switcher]</span>`.

**Needs the owner's decision (visible on live Thai pages):** the live site loads the English font stylesheet on every page today, Thai included, because the old code asked a plugin that is not there. With the fix, Thai pages load the Thai (Prompt) stylesheet. Measured on Local: this changes the font of about 180 text elements on the Thai home page (about 30-40 on CASTILE and product pages) compared with the Thai stylesheet not loading, and those 180 elements are rendered with the English-font stylesheet today. Because the theme sets its own font (Amouriq) on many elements, the effect is only partial. If the owner wants Thai pages exactly as they look today, the one-line option is to keep the English stylesheet for Thai pages; say so and ticket 06 will carry that instead.
