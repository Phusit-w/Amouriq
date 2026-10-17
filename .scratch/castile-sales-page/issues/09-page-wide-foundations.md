# 09: Page-wide foundations (width, type, alignment, eyebrow)

**What to build:** The shared look of the whole **Sales page** matches the design: content sits in a centred column of the design's width instead of stretching edge to edge, section headings are left-aligned, heading/body typography (family, weight, scale) matches, and every eyebrow label uses one style (small, letter-spaced, uppercase, orange). All of it is set through Elementor controls, not hard-coded.

**Blocked by:** 08 (Visual compare harness)

**Status:** ready-for-agent

- [ ] Content column width on desktop matches the design across all sections
- [ ] Headings are left-aligned wherever the design is left-aligned
- [ ] Heading and body font family, weight and size scale match the design
- [ ] Eyebrow labels share one style, editable per widget in Elementor
- [ ] Side-by-side crops from 08 show the first sections' frame matching the design
- [ ] Respects ADR-0003 (element IDs, not CSS classes control), ADR-0004 (cache clear) and ADR-0005 (assets under the active theme)
- [ ] Page 5309 `_elementor_data` written only via raw `$wpdb->update()` + cache clear, never `update_post_meta()`, verified from a fresh process
