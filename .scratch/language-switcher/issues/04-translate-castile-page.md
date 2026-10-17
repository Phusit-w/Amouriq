# 04: Translate the CASTILE sales page into English

**What to build:** The CASTILE sales page has an English version covering every section, drafted by Claude and reviewed by the owner. The Thai page's stored Elementor data must stay intact: it has been destroyed twice before by the wrong write method, so every step is verified from a fresh process.

**Blocked by:** 02 (Language switcher flips one page end to end)

**Status:** in progress (drafts done and loaded on Local; checks and owner review left, see "Next session")

- [x] Draft English text delivered to the owner (`translations/castile-page.json`, 178 lines; the readable list for the owner is still to be generated, see below)
- [ ] Owner's corrections applied
- [x] Every section of the English page shows no leftover visible Thai (`english-leftover.e2e.mjs /en/castile/`: desktop and mobile pass)
- [x] The Thai page is unchanged: `_elementor_data` of page 5309 is 257861 bytes, hash `6c46de0f...`, identical before and after (the translations are database rows in TranslatePress's own tables; no page was written)
- [x] The buying block (Scent and Size choice, price, add to cart) works in English mode (`castile-buying.e2e.mjs`: 6 checks pass)
- [ ] Desktop and mobile layouts hold (not looked at yet: take full-page screenshots of `/en/castile/` and check long English text in the table, FAQ and buying block)

## Next session

1. Generate the owner's reviewable list from `translations/castile-page.json` (same format as `home-menu-footer.md`, with the flags below), then ask the owner to review.
2. Screenshot `/en/castile/` at 1440 and 390 width and check the layout.
3. Check the Thai `/castile/` page shows no English text from the translations (it should not).
4. Apply the owner's corrections with `apply-translations.php <file> 2`, re-run the three browser checks.
5. Run `/code-review`.

## Flags for the owner (put these at the top of the review list)

- **Internal drafting notes are visible on the Thai page itself** and were translated as they are: "[ ภาพ Lifestyle — รอภาพถ่ายจริง ]" ("[ Lifestyle photo — waiting for the real photo ]"), "(ตรวจสอบตัวเลขล่าสุดก่อนเผยแพร่จริง ...)" (check the rating figures before publishing) and "วิธีใช้ final ควรตรวจให้ตรงฉลาก..." (final directions to be checked against the approved label). Customers see these on both languages unless they are removed from the page.
- **Claims and regulatory wording** (TIS S 14-2562 definitions, test percentages, "NOT DETECTED", Thai FDA notification numbers, "Organic" explanation) were translated faithfully. The owner should confirm each is still accurate and allowed in English.
- **Customer reviews** from Shopee are translated; consider marking them "(translated from Thai)".
- "มอก. เอส 14-2562" is rendered "TIS S 14-2562" (Thai Industrial Standard).
- The buying block's add-to-cart error pop-ups are hard-coded Thai in the page's script (shown only if adding fails). A small follow-up to make them language-aware; not done.
