# 06: A Deal creates its own coupon

**What to build:** In the Deal form, typing a coupon code for a coupon-trigger Deal creates an empty WooCommerce coupon with that code on save, or links to it if it already exists. Expiry and usage limit are set in the Deal form. See ADR-0009.

**Blocked by:** None (can start immediately).

**Status:** ready-for-agent

- [ ] Pure decision function (code, existing coupon lookup, save/copy mode) returns link / create / refuse / suffixed copy; tests cover each outcome
- [ ] Unknown code creates an empty coupon (no discount of its own); an existing code is linked, nothing duplicated or overwritten
- [ ] Expiry and usage limit in the Deal form write to that coupon; finer settings stay on the WooCommerce coupon screen
- [ ] An invalid code or a clash with an unrelated coupon is refused with a clear Thai message
- [ ] Deleting or disabling a Deal leaves its coupon untouched
- [ ] Existing Deals that reference a coupon keep working
