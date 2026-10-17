# 03: Bulk label print and hide the WebToffee label button

**What to build:** In the order list, the packer selects several orders and prints all their labels at once, one order per page. The WebToffee label button is hidden so only ours remains; the plugin stays active for invoices.

**Blocked by:** 02

**Status:** ready-for-agent

- [ ] Bulk action in the order list prints the chosen orders, each starting a new page
- [ ] WebToffee's label print button is no longer shown; its invoice and packing-slip features still work
- [ ] Test: several orders produce one page break per order
- [ ] Manual: bulk print of 3+ orders
