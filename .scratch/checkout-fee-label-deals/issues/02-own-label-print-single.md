# 02: Own label print button (single order)

**What to build:** A print button on the single order screen that prints the existing shipping label (layout unchanged) at a configurable paper size, without going through the WebToffee plugin's filter or print window. Falls back to the billing address when there is no shipping address.

**Blocked by:** None (can start immediately). Before starting, confirm the paper size with the owner (assumed 100x150 mm).

**Status:** ready-for-agent

- [ ] Button on the order screen opens a print view of that order's label
- [ ] Page size is a setting on the existing label settings page (default 100x150 mm); the print view uses it
- [ ] Label content and layout are unchanged (sender, recipient, order number, items)
- [ ] Extend `ShippingLabelTest` for the page size and the address fallback
- [ ] Manual: print on the owner's printer
