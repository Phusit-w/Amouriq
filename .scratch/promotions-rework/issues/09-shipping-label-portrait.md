# 09: Portrait shipping label

**What to build:** The printed shipping label becomes a portrait, top-to-bottom document: sender block (AMOURIQ logo, sender or shop name, address, phone), recipient block (name, delivery address, phone), the order number, then an item table with number, product name, option (size or scent) and quantity. Many items continue in the same table. Long addresses wrap onto new lines. No prices, discounts or totals appear. Free gifts are listed like any other item. The existing label comes from the shipping-label module of the WebToffee packing-slip plugin, so this ticket changes its template; first confirm that plugin is the one actually used and active (check the database once the local site is running; if not, ask the owner where labels are printed).

**Blocked by:** None (can start immediately; needs the local site's database running to confirm the plugin)

**Status:** ready-for-agent

- [ ] Confirmed which plugin prints the label, and that it is active
- [ ] The label matches the layout above, with the logo and sender details
- [ ] A long address and an order with many items both render without cutting anything off
- [ ] No prices, discounts or totals appear anywhere on the label
- [ ] Items, including free gifts, show with name, option and quantity
- [ ] A printed or PDF sample from a real local order is checked by eye
