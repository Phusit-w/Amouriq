# 17: Decisions on the code-review leftovers from tickets 14 and 15

**What to build:** The items the 2026-10-17 review of tickets 14-15 raised beyond the code fixes (those shipped as `deploy-castile-review\`). Each item below has a decision; the content ones change page 5309 data (raw `$wpdb` write only, never `update_post_meta()`) and need another kit that the owner uploads and installs.

**Blocked by:** none

**Status:** ready-for-human

## Decisions

| Item | Decision | Why |
|---|---|---|
| Lavender variation images show a Rosemary bottle | Owner task, no code: set the Lavender variation images in WooCommerce | It is catalog data on live; the page only displays it |
| Section 23 footnotes split into * and ** (design has one merged * volunteer-test note) | Follow the design: merge into the design's single note, copy taken verbatim from the design | Spec: Thai copy has been through regulatory review, do not paraphrase |
| Buy-box benefit icons (three columns) | Add them, as inline SVG like the other 16 icons | The design has them; only the build was missing them |
| Hero image caption omitted | Keep omitted | The design's caption is a placeholder note, not real copy |
| Section 11 arrow is horizontal, design is vertical on a phone | Fix on mobile (vertical arrow below 768px) | Cheap, and it is most of that section's +120px |
| Scent buttons in two columns under 520px leave an orphan | Measure the design's buy box at 390px first; match it. Measured: the design also shows two columns plus one, so no change | Nothing in the notes shows the design does two columns |
| Olive-grove photo in section 12 (+386px / +163px) | Keep | Already kept by the owner's earlier decision |
| Other mobile height gaps (08, 06, 02, 18 at 60-85px; 19 -92px from the missing icons; 22 crop only) | Accept; 19 closes when the benefit icons are added | Within tolerance, no unintended difference left |
| Installer extras (backup, rollback, postmeta fill-in, full cache clear) | Keep | Safety for a repeat install; the cache clear is needed for the page cache, the postmeta fill is guarded to "only a gap" |
| Section 22 h2 / CTA text changed | Keep | Done to follow the design |
| `text-match.mjs` extraction | Keep | Removed real duplication, used by two scripts |
| `page-data.json` not in git | Keep out of git (DB state); the kit checklist records its sha256 | It is state, not source |
| "All fields editable at mobile breakpoints" had no recorded audit | Accepted by construction: the round only sets `*_tablet` / `*_mobile` keys of standard panel controls | Nothing outside the panel was added |
| Add to Cart from the sticky bar never tested | Test locally in a real browser (bar button adds the chosen scent/size/qty) before the next kit | Only the buy box button was tested |

## Steps

- [x] Footnotes merged verbatim from the design (section 23)
- [x] Benefit icons added to the buy box; section 19 height gap closes
- [x] Section 11 vertical arrow on mobile
- [x] Scent buttons match the design's mobile layout
- [x] Sticky-bar Add to Cart tested locally
- [x] Kit prepared (page-data.json + widget + CSS) with rollback and sha256
- [ ] Owner uploads and installs; read-only live check
