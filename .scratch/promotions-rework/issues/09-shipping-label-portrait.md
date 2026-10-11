# 09: Portrait shipping label

**What to build:** The printed shipping label becomes a portrait, top-to-bottom document: sender block (AMOURIQ logo, sender or shop name, address, phone), recipient block (name, delivery address, phone), the order number, then an item table with number, product name, option (size or scent) and quantity. Many items continue in the same table. Long addresses wrap onto new lines. No prices, discounts or totals appear. Free gifts are listed like any other item. The existing label comes from the shipping-label module of the WebToffee packing-slip plugin, so this ticket changes its template; first confirm that plugin is the one actually used and active (check the database once the local site is running; if not, ask the owner where labels are printed).

**Blocked by:** None (can start immediately; needs the local site's database running to confirm the plugin)

**Status:** ready-for-human (checked by eye on a local sample; a real print and paper size on the warehouse printer not checked)

- [x] Confirmed which plugin prints the label, and that it is active
- [x] The label matches the layout above, with the logo and sender details
- [x] A long address and an order with many items both render without cutting anything off
- [x] No prices, discounts or totals appear anywhere on the label
- [x] Items, including free gifts, show with name, option and quantity
- [x] A printed or PDF sample from a real local order is checked by eye

## Notes (implementation)

- Confirmed: the plugin is "Print Invoices, Packing Slips, Labels for WooCommerce" (WebToffee) 4.9.6, active on the local site, with its Shipping Label module; its sender name, address, phone and logo are set in its own settings and are reused. The PDF Invoices plugin is installed but inactive.
- Instead of editing the plugin's template (lost on a plugin update, and the stored template differs per site) the new `amouriq-shipping-label.php` replaces the printed HTML through the plugin's `wt_pklist_alter_final_order_template_html` filter for the `shippinglabel` document. The layout is `amouriq-shipping-label/label-html.php` (plain data in, HTML out, no amounts ever passed in). Tests: `tests/ShippingLabelTest.php` (order of blocks, numbering, gift listed, no price words or currency, long address and 60 items, escaping).
- The option column is the variation's attribute values (for example "250 ml"); a simple product has none. The recipient is the shipping address (billing when the order has none) and its phone.
- Checked by eye: a local order with 13 lines (one a ฿0 gift), a long Thai address with a second line, rendered through the plugin's own generation path and screenshotted from headless Chrome. The order was deleted afterwards. Not checked: the plugin's actual print window/button flow and paper size on the real printer; label size setting "full page" is the plugin default.
- Live: upload `amouriq-shipping-label.php` and the `amouriq-shipping-label/` folder to `wp-content/mu-plugins/`; the plugin and its settings must be present there (confirm it is active on live first).
