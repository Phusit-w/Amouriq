# 10: Hero, trust bar, "หลังล้าง" and Real Soap definition sections

**What to build:** The opening of the page matches the design: the Hero has its eyebrow line and the 100% REAL SOAP badge above the headline, the stat cards span the row as in the design, the "หลังล้าง" section has its divider rule and orange quote bar, and the dark definition section matches in layout and spacing.

**Blocked by:** 09 (Page-wide foundations)

**Status:** done

- [x] Hero eyebrow line and badge present and editable
- [x] Stat cards sized and laid out as in the design
- [x] "หลังล้าง" section has divider rule and orange left-bar quote
- [x] Dark definition section matches headline size, widths and spacing
- [x] Side-by-side crops from 08 show no unintended differences for these sections
- [x] Every text, colour and link remains editable in Elementor

## Comments

- Done with `tools/castile-foundations/opening-sections.mjs` (page data is DB state, written via `write-page.php` as in ticket 09; git shows the script and the JS fix). Sections 00-03 re-structured and spaced from the design's measured values: trust bar (four editable items, centred, bottom rule), hero (equal 532px columns, image 665px tall in a rounded surface frame, eyebrow + badge row + design copy, stat cards as equal thirds with the design's padding/border/radius/gap, button 15.5px), "หลังล้าง" (heading with design line break and quotes, divider rule above the three columns, icon+title rows, orange 2px left-bar quote), definition (heading wraps as in the design, bold terms, a 1px divider rule, outlined real Elementor button instead of raw inline-styled HTML).
- Traps found: Elementor gives every nested container 10px padding (it was shrinking the hero by 20px a side); container margin is `margin`, not `_margin`; container alignment keys are `flex_align_items` / `flex_justify_content` (plain `align_items` is silently ignored); and `amq-castile.js`'s count-up replaced the whole widget's `textContent`, wiping the heading element that carries the typography, so hero stat numbers rendered as small body text. It now writes into the heading element (also fixes the other count-up numbers).
- Left out on purpose: the hero image caption "ภาพชั่วคราว... รอภาพ hero จริง" (a placeholder note in the design, not customer copy). Small known differences: the † link colour in the trust bar and eyebrow, and the text column sits ~8px higher because there is no caption under the image.
