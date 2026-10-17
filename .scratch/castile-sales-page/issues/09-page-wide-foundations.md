# 09: Page-wide foundations (width, type, alignment, eyebrow)

**What to build:** The shared look of the whole **Sales page** matches the design: content sits in a centred column of the design's width instead of stretching edge to edge, section headings are left-aligned, heading/body typography (family, weight, scale) matches, and every eyebrow label uses one style (small, letter-spaced, uppercase, orange). All of it is set through Elementor controls, not hard-coded.

**Blocked by:** 08 (Visual compare harness)

**Status:** done

- [x] Content column width on desktop matches the design across all sections
- [x] Headings are left-aligned wherever the design is left-aligned
- [x] Heading and body font family, weight and size scale match the design
- [x] Eyebrow labels share one style, editable per widget in Elementor
- [x] Side-by-side crops from 08 show the first sections' frame matching the design
- [x] Respects ADR-0003 (element IDs, not CSS classes control), ADR-0004 (cache clear) and ADR-0005 (assets under the active theme)
- [x] Page 5309 `_elementor_data` written only via raw `$wpdb->update()` + cache clear, never `update_post_meta()`, verified from a fresh process

## Comments

- Done by measuring the design (computed values at 1440 / 768 / 390) and applying them to page 5309's Elementor data with `tools/castile-foundations/foundations.mjs`; the page data is DB state, so the workflow is in `tools/castile-foundations/README.md` and git shows only the tools, fonts and enqueue change.
- **What changed on the page:** every top-level section except 15 and 22 (grid sections, left to ticket 12) is now a boxed container with the design's content width (1000 / 1120 / 1080 / 840), the design's section padding at desktop, tablet and mobile, and `align_items: stretch`. About 190 of ~200 heading/text widgets matched design text (by text) and got the design's font (DM Sans), weight, size (+tablet/mobile where it differs), line height, letter spacing, case, colour, alignment and, where the design has an explicit max-width, a custom width. Eyebrows are covered by the same matching (11.5px, 0.14em tracking, uppercase, accent or on-dark colour).
- **Fonts:** DM Sans (Latin) and IBM Plex Sans Thai (Thai) self-hosted under `pcoursewebbs/assets/fonts` (ADR-0005) with `amq-castile-fonts.css`; Thai is registered under the name "DM Sans" so Elementor's single font field gives the design's pairing. Found that Chrome falls back to Tahoma if the Thai faces' weight descriptors differ from the Latin faces' (a 300-700 range face shadowed them), so every weight is declared as its own set. Verified with the DevTools platform-fonts query: design and build both resolve Thai to IBM Plex Sans Thai and Latin to DM Sans.
- **Left for later tickets (not frame):** spacing between widgets (the build still uses a 20px flex gap plus per-widget margins, the design uses margins), nested rows/cards (hero stat cards, chart rows, evidence cards, comparison boxes), dividers and quote bars, text-editor widgets that hold raw inline-styled HTML or SVG (chart rows, icons), and the numbered/arrowed eyebrow text ("SKIN RESULT 01 — MOISTURE ↑", ticket 11). Some max-width line wraps still differ (e.g. section 02 and 11 headings).
- 16 text widgets did not match any design text (mostly footnotes, the hero eyebrow text variant and the Section 13 stat suffixes); they keep their old Prompt styling. The list is in the transform report; tickets 10-12 pick them up per section. The buying block keeps Prompt until ticket 13.
- Checked: no horizontal overflow at 1440, 1024, 768 and 390; page data read back from a fresh wp-cli process is byte-identical to what was written; written with raw `$wpdb->update` + cache clear; Elementor's element cache did not interfere this time (no theme switch was needed).
- Side-by-side crops from ticket 08 (sections 01, 02, 03, 05, 11, 21 reviewed): frame, left edge, headings, body type and the FAQ rows now line up with the design.
