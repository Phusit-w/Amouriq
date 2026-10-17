# 15: Mobile round

**What to build:** The page on a phone matches the design's mobile layout, including a root-cause fix (not another guess) for the text clipping seen at the right edge in plain paragraph sections. `overflow-wrap` and `overflow-x: hidden` were already tried and ruled out, so this needs real inspection in a real browser (driven over CDP). Ends with a second upload kit.

**Blocked by:** 14 (Whole-page check and live upload kit)

**Status:** ready-for-agent

- [ ] Root cause of the clipping identified by inspecting the actual layout, and fixed
- [ ] Mobile side-by-side crops match the design's mobile layout
- [ ] No horizontal scroll at common phone widths
- [ ] Sticky cart bar and buy box work on a phone-width viewport
- [ ] Second upload kit prepared (additive to kit 14) with rollback copies
- [ ] All fields remain editable in Elementor at mobile breakpoints
