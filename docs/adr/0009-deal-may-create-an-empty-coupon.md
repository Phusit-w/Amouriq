# 0009: A coupon-trigger Deal may create its own empty coupon

Date: 2026-10-13. Status: accepted.

## Context

Until now a coupon-trigger Deal only referenced a WooCommerce coupon that the owner had already created under Marketing > Coupons (CONTEXT.md: "Deals reference coupon codes but never own them"). Admins found this hard: they had to leave the Deal form, create the coupon on another screen, then come back and type the same code.

## Decision

When a Deal is saved with a coupon code that does not exist, the Deal creates an empty WooCommerce coupon (no discount of its own) with that code. If the code exists, the Deal links to it and creates nothing. The Deal form edits only the code, expiry and usage limit of such a coupon; every finer setting stays on the WooCommerce coupon screen. A code is refused when it belongs to something unrelated: a points-programme reward coupon (one per member), a coupon another Deal created (editing one Deal's expiry must not change another), or a code that is invalid or in the trash. A coupon the owner made by hand is linked and left as it is. Deleting or disabling a Deal leaves its coupon untouched.

## Consequences

- The coupon is still native WooCommerce and remains the source of truth for everything except those three fields, so the free-shipping and Stacking rules keep working unchanged.
- The Deal now writes to coupon posts, so the save handler must not overwrite a coupon it did not create.
- Orphan empty coupons can remain after a Deal is removed; they are harmless (no discount) and can be deleted from the coupon screen.
- Duplicating a Deal also creates a new coupon with a suffixed code (for example `-2`).
