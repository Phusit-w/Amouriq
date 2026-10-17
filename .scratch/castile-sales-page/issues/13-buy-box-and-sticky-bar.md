# 13: Buy box and sticky cart bar match the design

**What to build:** The buying block (scent buttons with notes, size buttons with prices, quantity, live total, Add to Cart) and the sticky cart bar match the design's layout and styling, while still adding the right variation to the real WooCommerce cart. The buy box stays a custom widget with its own editable fields.

**Blocked by:** 09 (Page-wide foundations)

**Status:** done

- [x] Layout, spacing, typography and colours match the design in side-by-side crops
- [x] Add to Cart still puts the chosen scent + size + quantity in the WooCommerce cart (re-verify one purchase path)
- [x] Sticky bar behaviour unchanged and does not overlap footnotes
- [x] Labels, notes and prices remain editable via the widget's own fields in Elementor

## Comments

- Layout is CSS/markup in `amq-castile-buying-block.css` and the widget class: two 532px columns (bordered image box + info), chips, three-column benefits between hairlines, 3-column scent and size buttons (selected = tinted with orange border), qty + total row, full-width Add to Cart, uppercase trust line, accent links, SKU last. Whole-baht prices (฿779, no .00) from PHP and the JS total; the page opens on 250 ML as the design does; the duplicate unit-price line was dropped (the size buttons and total carry it). Fonts: the theme's blanket `div/span/button { font-family: Amouriq }` is overridden for the widget and sticky bar.
- Purchase path re-verified in a real browser session: Rosemary + 500 ML + qty 2 showed ฿2,398 and put SKU `SL-CT-NA-RM0500-2` in the WooCommerce cart (cart quantity itself not read back; ฿2,398 = 2 x ฿1,199).
- Sticky bar logic untouched (restyled font only); body padding rule for the footnotes is unchanged.
- The widget still has no Elementor controls of its own (ADR-0001: copy is hardcoded, price/SKU/image are live WooCommerce data), so the 4th acceptance box holds only as far as it did after ticket 06.
- Open: the Lavender product's variation images show a ROSEMARY-labelled bottle (WooCommerce catalog image, not this ticket); the design's "ภาพขนาด 100/250 ML ใช้ภาพขวด 500 ML" disclosure stays omitted (false for this build).
