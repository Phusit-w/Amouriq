# 05: Content — How to Use through Footnotes (Sections 20–23)

**What to build:** The close of the sales page — the 3-step how-to-use, the FAQ accordion, the final-close CTA panel, and the regulatory footnotes — as fully Elementor-editable content matching the Claude Design handoff, with correct footnote cross-references back into earlier sections.

**Blocked by:** 02 (CASTILE page + navigation wiring)

**Status:** ready-for-agent

- [ ] Sections 20 (How to use), 21 (FAQ), 22 (Final close), and 23 (Footnotes) are built as native Elementor widgets/containers on `/castile/`, matching the design handoff's copy, layout, and design tokens
- [ ] Every text, image, and background field in these sections is a real Elementor field, editable in the Elementor editor
- [ ] FAQ (Section 21) works as a native `<details>`/`<summary>` accordion — no JS required — with the default disclosure marker removed
- [ ] Footnote markers (`*`, `**`, `†`, `‡`) anywhere on the whole page — including those authored in tickets 03 and 04 — link to their correct definition in Section 23, with `scroll-margin-top` accounting for the sticky header
- [ ] Section 22's 50/50 split (Roasted Umber panel) stacks image-above-text under ~860px viewport width
- [ ] Smooth in-page anchor scrolling works for the page's named anchors (`#results`, `#evidence`, `#faq`, `#buy`)
- [ ] Section 22's placeholder lifestyle photography (flagged as interim in the design brief) is used as-is
