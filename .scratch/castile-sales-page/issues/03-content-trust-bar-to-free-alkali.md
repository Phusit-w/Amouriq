# 03: Content — Trust Bar through Free Alkali (Sections 00–09)

**What to build:** The opening argument of the sales page — trust bar, hero with stat cards, thesis restatement, "why real soap matters," the two lab-data charts (moisture, water loss), barrier claim, results triad, and the Free Alkali "NOT DETECTED" hero numeral — as fully Elementor-editable content matching the Claude Design handoff.

**Blocked by:** 02 (CASTILE page + navigation wiring)

**Status:** ready-for-agent

- [ ] Sections 00 (Trust bar) through 09 (Free Alkali) are built as native Elementor widgets/containers on `/castile/`, matching the design handoff's copy, layout, and design tokens (colors/type/spacing from `_ds/…/styles.css` in the supplied bundle)
- [ ] Every text, image, and background field in these sections is a real Elementor field, editable in the Elementor editor — no static HTML/custom code blocks for content
- [ ] Scroll-triggered count-up numerals (Hero stats, Free Alkali) animate per the design's specified easing/duration and run once only, using an IntersectionObserver that unobserves on fire
- [ ] Chart row animations (Sections 05, 06) reproduce the specified track/bar-fill, per-row stagger, and the single emphasized-row treatment
- [ ] Mask-reveal animations on headings/key lines in this range reproduce the specified timing and stagger (e.g. Section 09's eyebrow → headline → evidence-line sequence)
- [ ] All animations in this range honor `prefers-reduced-motion: reduce` by rendering the final state immediately
- [ ] Supplementary CSS/JS for this range is conditionally enqueued only on the Castile page, targeting Elementor's generated widget classes/IDs rather than introducing markup outside Elementor's control
