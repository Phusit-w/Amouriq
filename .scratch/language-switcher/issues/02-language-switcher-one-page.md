# 02: Language switcher flips one page end to end

**What to build:** The **Language switcher** ("EN / TH") appears in the site header on desktop and in the mobile menu. The current language shows as plain text, the other as a link to that page's translation. The site always opens in Thai and changes language only when the customer clicks the switcher, never from the browser's language. The Thai pages must stay exactly as they look today, so the sitewide font does not change with the language (see Comments). The existing switcher code is tied to Polylang and must be moved to the system chosen in ticket 01. The active theme's header builder places the switcher, not the inactive redesign theme.

**Blocked by:** 01 (Confirm the translation system on Local and record the ADR)

**Status:** done

- [x] Switcher visible in the header (desktop) and the mobile menu on Local
- [x] Clicking EN on a translated page lands on its English version, and TH returns to the same Thai page
- [x] ~~A page with no English version sends EN to the English home page~~ Not applicable with TranslatePress: every page has an `/en/` address, and text not yet translated simply shows in Thai. The switcher therefore always keeps the customer on the same page.
- [x] A fresh visit with an English browser still opens in Thai
- [x] Thai pages keep exactly today's font set (owner's decision); English pages use the same set
- [x] Verified in a real headless Chrome session, both languages, desktop and mobile width

## Comments

**Proof:** `node tools/language-switcher/switcher.e2e.mjs` (real headless Chrome over CDP against Local): 14 checks, all pass. Existing PHP suite: 373 tests OK. CASTILE `_elementor_data` hash unchanged.

**What changed:**
- The shortcode now asks TranslatePress for the current language and for the same page in the other language. TranslatePress rewrites every internal link on the page to the current language; the shortcode keeps TranslatePress's own "already processed" marker on the link so that rewriting does not turn the TH link on an English page back into `/en/`.
- The font code reads the language from TranslatePress.
- Placement (Local only so far): the `[lang_switcher]` shortcode sits inside the existing header HTML element (the promo bar, which Blocksy shows on desktop and mobile), at the right edge, with side padding of 56px so the centred Thai text never touches it. Done by editing the header theme setting in the Local database. **For live (ticket 06):** the owner edits the same HTML element in Appearance > Customize > Header: padding `7px 56px` on the promo bar, and before its closing `</div>` add `<span style="position: absolute; right: 16px; top: 50%; transform: translateY(-50%);">[lang_switcher]</span>`.

**Decided by the owner (2026-10-17): Thai pages stay exactly as they are today.** The live site loads the English font stylesheet on every page, Thai included, because the old code asked a plugin that is not there. So the font code now loads that same set on every page, Thai and English, and says so in its comment; the unused Thai (Prompt) stylesheet is kept for later. The Thai page's font tags on Local match the live page's.

**Code review follow-ups (done):** fixed a stale comment in the CASTILE assets plugin, read the current language once in the switcher, aligned `CONTEXT.md` and the spec with "same page, other language", and added a test that revisiting `/` after `/en/` in the same browser profile still opens Thai.

**Not the same as the ticket's wording:** on mobile the switcher sits in the bar above the header (the same HTML element as on desktop, visible at phone width), not inside the hamburger menu. If the owner wants it inside the off-canvas menu as well, that is an extra placement for ticket 06 or later.

**Open for the owner (carried to tickets 06 and 07):**
1. The home page hero slider (`amouriq-hero-slider.php`) still asks Polylang for the language, so on the live site it shows its English slides even on the Thai home page. Per the owner's rule that Thai pages stay as they are, it is left untouched. It also means the `/en/` home page shows the same English slides, which suits the English page.
2. Nothing yet hides the switcher on live: the switch belongs to ticket 06, and the live header edit is a manual paste in the Customizer.
3. What TranslatePress itself adds to Thai pages is invisible (alternate-language links in the page head and its own small stylesheet), but it is the one difference from live besides the switcher, to be listed in ticket 06's read-only check.
