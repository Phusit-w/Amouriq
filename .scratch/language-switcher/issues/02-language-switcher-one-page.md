# 02: Language switcher flips one page end to end

**What to build:** The **Language switcher** ("EN / TH") appears in the site header on desktop and in the mobile menu. The current language shows as plain text, the other as a link to that page's translation, or to the English home page when the page has no translation. The site always opens in Thai and changes language only when the customer clicks the switcher, never from the browser's language. The sitewide font follows the language (Prompt for Thai, Plus Jakarta Sans for English). The existing switcher and font code are tied to Polylang and must be moved to the system chosen in ticket 01. The active theme's header builder places the switcher, not the inactive redesign theme.

**Blocked by:** 01 (Confirm the translation system on Local and record the ADR)

**Status:** ready-for-agent

- [ ] Switcher visible in the header (desktop) and the mobile menu on Local
- [ ] Clicking EN on a translated page lands on its English version, and TH returns to the same Thai page
- [ ] A page with no English version sends EN to the English home page
- [ ] A fresh visit with an English browser still opens in Thai
- [ ] Font changes with the language
- [ ] Verified in a real headless Chrome session, both languages, desktop and mobile width
