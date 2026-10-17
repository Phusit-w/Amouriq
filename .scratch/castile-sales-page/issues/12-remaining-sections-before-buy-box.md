# 12: Remaining sections before the buy box

**What to build:** Every section between the Testimonial and the buy box, plus the sections after it up to the footnotes (how to use, comparison, FAQ-style blocks, lifestyle blocks, etc.), matches the design in structure, colour, type and spacing. Section 12 returns to the design's left-aligned baseline-row flow instead of centred stacked cards; card backgrounds use the design's hex values. Lifestyle photo slots (Sections 15 and 22) stay as placeholder boxes by decision; they are not defects. Complex animations may stay simplified (Section 09 mask reveal).

**Blocked by:** 09 (Page-wide foundations)

**Status:** done

- [x] Section 12 follows the design's left-aligned row layout
- [x] Card and section backgrounds match the design's colours
- [x] All other sections in range match the design in side-by-side crops
- [x] Lifestyle photo slots remain placeholders, still image-swappable in Elementor
- [x] Every text, image, colour, button and link editable in Elementor
- [x] This ticket may be split if it exceeds one context window; record any split in Comments

## Comments

- Done with `tools/castile-foundations/remaining-sections.mjs` (design sections 12, 13, 14, 16, 17, 18, 20, 21, 23) on top of the shared helpers; sections 15 and 22 (lifestyle placeholders) and 19 (buying block, ticket 13) untouched. Page data is DB state, written with `write-page.php` as in tickets 09-11 and read back byte-identical from a fresh process. Checked at 1440 / 1024 / 768 / 390: no horizontal overflow.
- **Root cause found, fixed once for the whole page:** the theme sets `font-family: Amouriq` on every `div`/`span`, so every text-editor widget's inner container and every inline span inside a heading ignored the DM Sans the widget was given (visible in all sections, not just this range). Two rules in `amq-castile.css` make them inherit.
- Section 12: left-aligned flow, cards with 30px padding and hairline border, thin arrows between them. The olive-grove photo (imported in ticket 04, not in the design) is kept above the cards by decision, not removed.
- Section 13: three stray duplicate stat text widgets removed; stats are three hairline-topped columns. Section 14: cards sit directly in the row (the wrapper columns are gone). Section 16: five cards in one wrapping row with 01-05 numbers. Section 18: card = label, stars, quote, attribution pinned to the bottom; rating line and its note share one row. Section 23: heading above the rule, notes left-aligned at 690px.
- Copy changed to the design's wording where it differed (section 12 oils list, 13 intro and label, 14 lavender paragraph, 16 title, 18 title and attributions, 23 heading). The footnote texts themselves were kept (the design merges the * / ** notes differently).
- Left: Thai glyphs still look slightly heavier than the design (as in ticket 11); the FDA card in section 16 is about 20px taller than the design; the FAQ rows are 1px off the design's pitch.
