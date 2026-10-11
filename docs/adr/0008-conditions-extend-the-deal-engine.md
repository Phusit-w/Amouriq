# 0008: Conditions extend the Deal engine; no second rules engine

Date: 2026-10-11. Status: accepted (pending the owner's answers in `.scratch/rule-conditions/spec.md`).

## Context

The owner wants Discount Rules PRO's style of configuration (who, when, cart contents, product scope, several discount types) without that plugin. We already have a tested Deal engine (`amouriq-bogo/pricing-core.php`) that computes every discount per cart line and settles Member Privilege against it (ADR-0006). Coupons are native WooCommerce.

## Decision

Add Conditions, Product scope and the new discount types **inside the existing Deal engine**, as plain data evaluated by the pricing core, and reuse the same Condition evaluator for native Coupons (via `woocommerce_coupon_is_valid`) and for points Earning rules. Do not write a second, general rules engine beside it, and do not reintroduce a third-party rules plugin.

## Consequences

- One place decides what a discount is, so ADR-0006 (larger discount wins, per line against Deals, whole cart against Coupons) keeps holding for every new type.
- Deals already saved keep their stored fields and meaning; new fields default to "no extra condition".
- The pricing core must stay free of WordPress calls; anything the conditions need (role, order history, payment method) is gathered by the WooCommerce side into a context array first.
- The cost is that the admin form and the core grow together; the rule-builder redesign is therefore its own ticket, after the core pieces are tested.
