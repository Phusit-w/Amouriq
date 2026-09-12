# 07: Full-page integration & QA

**What to build:** No new behaviour — verify the whole `/castile/` page (navigation, all content sections, and the buying block) works and reads as one coherent page, with no seams between the tickets that built it.

**Blocked by:** 03 (Content: Trust Bar–Free Alkali), 04 (Content: Proof–Testimonial), 05 (Content: How to Use–Footnotes), 06 (Buying block, sticky cart bar, Add to Cart)

**Status:** ready-for-agent

- [ ] Full page scrolled top to bottom on desktop and mobile viewport widths matches the Claude Design reference for layout, spacing, and color usage across every section boundary, including the handoffs between tickets 03/04/05
- [ ] All footnote markers across the whole page resolve to the correct definition in Section 23
- [ ] The sticky mobile cart bar never visually overlaps the footnotes section or other bottom content
- [ ] `prefers-reduced-motion: reduce` verified across the entire page in one pass, not just per-section
- [ ] No responsive breakage at the ~860px sticky-bar breakpoint or at standard mobile/tablet/desktop widths
- [ ] No PHP warnings/notices or JS console errors across a full page load and full interaction pass (scent/size switching, Add to Cart, FAQ accordion, footnote anchors)
- [ ] The full purchase path — browse, scroll, select scent/size, add to cart, view cart — verified end-to-end on the running site
- [ ] Navigation (ticket 02) confirmed consistent across the whole site, not just linking correctly but rendering identically wherever it appears
