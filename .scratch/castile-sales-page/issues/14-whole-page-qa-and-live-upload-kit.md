# 14: Whole-page check, Elementor editability audit, live upload kit (desktop)

**What to build:** The full desktop page is checked top to bottom against the design with no unintended differences left, every field is confirmed editable in Elementor, and an upload kit is prepared so the owner can put the result on amouriq.co.th: a single mu-plugin that writes the page content with `$wpdb` (never `update_post_meta()`), the asset files, rollback copies of what they replace, and a test checklist. The owner uploads; Claude verifies read-only afterwards.

**Blocked by:** 10, 11, 12, 13

**Status:** done

- [x] Full-page side-by-side check from 08 shows only deliberate differences (placeholders, simplified animations), each listed
- [x] Audit: every text, image, colour, button and link is editable in the Elementor panel; the custom buy box is covered by its own fields; exceptions listed
- [x] Upload kit includes mu-plugin, assets, rollback files, test checklist under a deploy folder alongside the earlier kits
- [x] Kit verified locally from a fresh process (page data intact after write)
- [x] Page-title/hero postmeta for page 5309 included or confirmed already live
- [x] CASTILE menu item is NOT part of this kit
- [x] After owner uploads, read-only check of live page against the design

## Comments

- **Full-page check** (compare harness, design vs local, 24 sections): the first pass found two unintended gaps, both in the two-column sections the frame step had skipped. Sections 15 and 22 are now full-bleed halves with the design's panel padding and type, section 15's REAL SOAP / MOISTURE CARE as two columns, and section 22's CTA is a real orange button to #buy (it was bold plain text). Done with `tools/castile-foundations/lifestyle-sections.mjs` (page data is DB state, written with `write-page.php`, read back identical). Run once against the data as it stood after ticket 13.
- **Deliberate differences left (each one):**
  1. Photo slots in 15 and 22: plain placeholder text on a grey panel, no icon, until real photos arrive.
  2. Section 12 keeps the olive-grove photo above the cards (kept by decision, not in the design).
  3. The hero image caption is omitted (a placeholder note in the design).
  4. Footnote texts in 23 are kept as built (separate * and ** notes for moisture and TEWL); the design merges them into one * volunteer-test note.
  5. Buy box: the Lavender variation images show a Rosemary bottle (WooCommerce catalog images); the three benefit columns have no icons (the design has them); the "images reuse the 500 ML bottle" line is omitted (false for this build); SKU and price are live WooCommerce data.
  6. Animations simplified (section 09 mask reveal is one stage); counters and bars reach the same final values.
  7. Thai glyphs a little heavier; section heights within about 20px except 12 (+386, the photo), 22 (-68, slightly tighter paragraph spacing) and 23 (+70, longer footnote texts).
- **Elementor editability audit** (page 5309, 24 sections): 123 heading, 118 text-editor, 2 image, 3 button (hero, definition, section 22), 1 custom buy-box widget. Every heading, paragraph, image, button text/link and colour is editable in the panel. Exceptions:
  - (a) the buy box and sticky bar have no Elementor controls (ADR-0001: labels, notes, trust line and links are hardcoded in the widget class; price, SKU and image come from WooCommerce);
  - (b) 16 icons are inline SVG inside text-editor widgets (edit in the editor's Text tab; colour is the SVG stroke);
  - (c) 28 text-editors hold inline-styled HTML (footnote markers, chart rows, FAQ details): the text is editable, their styling is not a panel control;
  - (d) animation targets (count-ups, bar fills, mask reveal) are bound to element ids in `amq-castile.css/js` (ADR-0003), so deleting and recreating one of those widgets breaks its animation;
  - (e) the 15/22 photo slots are text placeholders: to add a photo, put an Image widget in the panel (or a background image on that container) and delete the text.
- **Kit:** `D:\Local\app\deploy-castile\` (`upload\` + `rollback\` + `castile.zip` + `rollback.zip` + `test-checklist.md`, extracted at the WordPress root `amouriq.co.th/`). The page content is written by a new mu-plugin tool (Tools > CASTILE page, `amouriq-castile-page-install.php`) using raw `$wpdb` with a read-back check, never `update_post_meta()`; the first install keeps the live content as a backup row and the same screen rolls back. The page data file is not in git (DB state); it is in the kit. Rollback files are the exact versions live serves now (css/js md5 matched against the live files; php from commit f33b080). The two page images (ids 5311/5312) are already on live; the installer swaps the local image host for the site's.
- **Verified locally from fresh wp-cli processes:** old content -> install -> content identical to the shipped file and backup identical to the old content -> roll back -> identical to the old content -> install again. Test rows removed afterwards.
- **Page-title/hero postmeta:** already live (the live /castile/ HTML has no theme hero or title block); the installer only fills it if missing.
- **Open:** the owner uploads and clicks Install; then the read-only live check against the design (last box). Mobile is ticket 15.
- **Live check (2026-10-17, after the owner uploaded and clicked Install):** all 12 asset files on live match the kit by md5; the installer screen showed "Same as the install file: yes" and a backup of the previous content (24 sections, 186 KB); the live page has no PHP errors, the section 22 button, images by id 5311/5312 and no local host in the HTML. The compare harness against live gives section heights identical to the local build for all 24 sections; hero, buy box and section 22 crops looked right (fonts, images). Live differences from local are catalog data only: the live Lavender 250 ML SKU reads `SL-CT-OR-LV0250-1`. The install tool needed the owner's click (the auto-mode classifier blocks Claude from clicking production write buttons).
