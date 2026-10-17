# 11: Proof sections (Skin Result charts through Testimonial)

**What to build:** The evidence sections read like the design: bar charts are table-style rows with hairline dividers, thin bars and large right-aligned values; each Skin Result label is numbered ("SKIN RESULT 01 — MOISTURE ↑") in orange; the Evidence note is a bordered card; key-takeaway headings match. Count-up and bar-fill animations still reach their final values.

**Blocked by:** 09 (Page-wide foundations)

**Status:** done (sections 04-11; see Comments for the split)

- [x] Chart rows: hairline-divided, thin bars, values right-aligned and sized as in the design
- [x] Eyebrows numbered and coloured as in the design
- [x] Evidence note rendered as the designed bordered card
- [x] Key-takeaway and result headings match the design
- [x] Final animated values still correct (compare with 08 harness, animations off)
- [x] Side-by-side crops show no unintended differences through Testimonial
- [x] Every text, number and colour editable in Elementor
- [x] This ticket may be split if it exceeds one context window; if so, record the split in Comments

## Comments

- **Split:** this ticket was done for design sections 04-11 (the dark intro "not enough", the three Skin Result sections with their charts, the dark "memory lock 2" strip, Free alkali, Finished-soap analysis and the proof bridge), which is where the chart rows, numbered eyebrows, evidence card and key-takeaway headings live. Design sections 12-18 (oils to soap, why we made it, not added, ritual, evidence, why trust, customer voice) were left for ticket 12, whose text covers "every section between the proof and the buy box". The Evidence *note* card is in section 06; there is no separate evidence-note card elsewhere in 04-11.
- Done with `tools/castile-foundations/proof-sections.mjs` on top of the shared `elementor-helpers.mjs` (ticket 10's script now uses the same helpers). Page data is DB state, written by `write-page.php` as in tickets 09/10.
- Chart rows: each row is a label / bar-on-track / value container with a hairline under it, a 10px track and 2px-radius fill, 22px bold right-aligned value, and the design's bar lengths (value ÷ largest value; pale fill, dark fill for the highlighted row). Bars and numbers still animate to the same final values (checked with the 08 harness: +7.42 / +8.48 / +13.37 / +9.27 and +0.81 / −5.34 / −3.60 / −10.67).
- Traps found: Elementor only honours a container's **Width** when its content width is "full" (the old chart bars never had their widths applied, so every bar rendered full length); `min-content` of "+13.37%" is wider than a 8.3% column, so the value column is 8.7%; a global rule in `amq-castile.css` now makes footnote markers (†, ‡) inherit the text colour (they were black on the dark sections).
- Text that was dimmed in the design (opacity, not a colour) is set as ink at 65-78% alpha: second paragraphs, quotes, evidence text. This is an eyeballed match, not a measured one.
- Rest-of-ticket differences left: body Thai glyphs still look slightly heavier than the design in places (font weight/rendering, not layout).
