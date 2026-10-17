# 15: Mobile round

**What to build:** The page on a phone matches the design's mobile layout, including a root-cause fix (not another guess) for the text clipping seen at the right edge in plain paragraph sections. `overflow-wrap` and `overflow-x: hidden` were already tried and ruled out, so this needs real inspection in a real browser (driven over CDP). Ends with a second upload kit.

**Blocked by:** 14 (Whole-page check and live upload kit)

**Status:** done

- [x] Root cause of the clipping identified by inspecting the actual layout, and fixed
- [x] Mobile side-by-side crops match the design's mobile layout
- [x] No horizontal scroll at common phone widths
- [x] Sticky cart bar and buy box work on a phone-width viewport
- [x] Second upload kit prepared (additive to kit 14) with rollback copies
- [x] All fields remain editable in Elementor at mobile breakpoints

## Comments

- **Root cause of the narrow text column:** Blocksy's `.ct-elementor-default-template .e-con > .e-con-inner { width: var(--theme-block-width, var(--theme-container-width)) }` beats Elementor's `width: 100%`, so every boxed section's content box was about 88% of the viewport (343px of 358px available at 390px, centred by auto margins). Found by walking the ancestors of a paragraph in real Chrome (CDP) and listing the matching rules; no element was wider than the viewport, which is why the earlier overflow guesses did nothing. Fix: one rule in `amq-castile.css` scoped to `.elementor-5309 > .e-con > .e-con-inner`.
- **Second cause, found by the same side-by-side crops:** the build had only desktop sizes on most widgets (tickets 10-12 rebuilt them without the tablet/mobile keys), and Elementor gives a child container 100% width on a phone unless it has its own mobile width. `tools/castile-foundations/mobile-round.mjs` fixes both from the design measured at 390/768: font size, line height and alignment per matched text (count-up numbers, split stat spans and mixed-markup paragraphs by the design's size mapping for that section), card padding 24/20px, chart rows kept as label | bar | value (including the bar fill's own width), 320px minimum height for the 15/22 photo slots. It only sets `*_tablet` / `*_mobile` keys and mobile padding, so desktop is untouched (final desktop render heights identical to before). `text-match.mjs` is the matcher extracted from `foundations.mjs`, now shared.
- **Buy box on a phone** (`amq-castile-buying-block.css`): name 24px as in the design, scent buttons and the three benefit columns in two columns under 520px.
- **Checked in real Chrome (mobile emulation):** no horizontal overflow at 320, 360, 390, 414 and 768 (no element past the viewport, scrollWidth equals width). Buy box at 390: Rosemary + 500 ML + qty 2 shows ฿2,398 and SKU `SL-CT-NA-RM0500-2`; Add to Cart shows "เพิ่มลงตะกร้าแล้ว" and sets the WooCommerce cart cookie; the sticky bar is hidden away from the buy box and shown fixed at the bottom at it (logic untouched since ticket 13).
- **Mobile section heights vs the design** (390px): within about 60px for most sections; larger: 12 (+163, the olive-grove photo), 11 (+120, an arrow glyph between two cards is a horizontal → in the build, a vertical placement in the design), 08 (+84), 06 (+81), 02 (+76), 18 (+73), 19 (-92, the build has no icon row in the buy box benefits), 22 (-167 in the crop only; the design crop includes the footnotes).
- **Kit:** `D:\Local\app\deploy-castile-mobile\` (`castile-mobile.zip`, `rollback.zip`, `test-checklist.md`): new `page-data.json` and the two CSS files; the installer from kit 14 writes it (second Install keeps the original backup). Rollback restores kit 14's CSS and its `page-data.json` (then Install again). Kit 14 must be on the site first.
- **Not done:** a real phone and a real live check (after the owner uploads). The unmatched text widgets (footnotes 23, card numbers 01-05, stars) keep their desktop size, which equals the design's on a phone.
- **Live check (2026-10-17, after the owner uploaded kit 15 and clicked Install):** CSS and `page-data.json` on live match the kit; installer showed "Same as the install file: yes" with the original backup kept; real Chrome phone emulation on amouriq.co.th shows no horizontal overflow at 320, 360, 390, 414 and 768, the section content box is viewport minus 32px (the Blocksy override fix works live), and all 24 mobile section heights equal the local build. Not tested: a real phone; Add to Cart on live (kept off the live cart). The owner closed the ticket.
