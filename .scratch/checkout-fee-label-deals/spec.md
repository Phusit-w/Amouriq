Status: ready-for-agent

# Spec: payment fee, own shipping label printing, easier Deals screen

Language note: owner-facing text on the site is Thai. Vocabulary follows `CONTEXT.md` (Deal, Trigger, Coupon, Payment fee, Gift choice, Member Privilege, Points). See ADR-0009.

## Problem Statement

The owner has three problems.

1. Cash-on-delivery orders cost the shop money to handle, but there is no way to charge for it.
2. The parcel shipping label (ใบปะหน้าพัสดุ) layout is ours, but printing still depends on the WebToffee packing-slip plugin, which the owner wants to stop using for labels.
3. The admin who manages Deals finds the BOGO screen hard to use. Creating a coupon-trigger Deal means leaving the screen to create a coupon first. A customer who earns a free gift picks it on the cart page only, without seeing what each gift is worth. Copying an existing Deal does not give a ready new coupon.

## Solution

1. When the customer chooses cash on delivery, the checkout adds a Payment fee (default ฿30). The owner can switch it on or off and change the amount.
2. Labels print through our own button, from a single order or from a selection of orders (one label per page), at the paper size the owner uses. The WebToffee label button is hidden; the plugin itself stays for invoices.
3. The Deals screen creates the Deal's coupon itself, offers a copy button that also makes a fresh coupon, shows the Gift choice on both cart and checkout with prices, and gets a simpler layout (designed from a mockup first).

## User Stories

Payment fee
1. As a shop owner, I want a fixed charge when the customer pays on delivery, so that handling cost is covered.
2. As a shop owner, I want the charge to apply even when shipping is free, so that free shipping does not hide the cost.
3. As a shop owner, I want to switch the charge on or off, so that I can run a no-fee period without a developer.
4. As a shop owner, I want to change the amount, so that I can adjust it later.
5. As a customer, I want to see the charge named "ค่าธรรมเนียมเก็บเงินปลายทาง" before I confirm, so that the total holds no surprise.
6. As a customer paying by card or PromptPay, I want no such charge, so that I only pay it when it applies.
7. As a customer who changes payment method on the checkout page, I want the total to update at once, so that I see the real amount.
8. As a customer, I want the charge to be exactly the amount shown, with no extra tax, so that the total is predictable.
9. As a shop owner, I want the charge to come after all discounts, so that coupons and Deals do not reduce it.
10. As a shop owner, I want the charge not to count as purchase value for Points or "spend X" tests, so that it cannot unlock rewards.
11. As a shop owner, I want the charge to appear on the order and in order emails, so that the records match what was collected.
12. As a customer with a Points redemption or Member Privilege, I want those to keep working with the charge present, so that discounts are not broken.

Shipping label
13. As a packer, I want a print button on a single order page, so that I can print its label.
14. As a packer, I want to select several orders in the list and print all their labels in one go, so that I save time.
15. As a packer, I want each order on its own page at the paper size my printer uses, so that labels do not run together.
16. As a shop owner, I want the label layout to stay exactly as it is now (sender, recipient, order number, items), so that nothing changes for the packers.
17. As a shop owner, I want the WebToffee label button hidden while invoices keep working, so that no one prints the wrong label.
18. As a shop owner, I want the label settings page (logo, sender, font, note) to keep working, so that I can still edit the label.
19. As a packer, I want an order with no shipping address to fall back to the billing address, so that the label is never blank.

Deals: coupon
20. As an admin, I want to type a coupon code in the Deal form and have the coupon made for me, so that I do not visit another screen.
21. As an admin, I want a code that already exists to be linked and not duplicated, so that I never create a clash.
22. As an admin, I want to set the coupon's expiry and usage limit in the Deal form, so that I manage one place.
23. As an admin, I want finer coupon settings to stay on the WooCommerce coupon screen, so that nothing is lost.
24. As an admin, I want a code that is invalid or taken by an unrelated coupon type to be refused with a clear Thai message, so that I understand why.
25. As an admin, I want deleting or disabling a Deal to leave its coupon alone, so that no coupon vanishes unexpectedly.
26. As an admin, I want the copy button on a Deal to also make a new coupon with the code plus a suffix (for example `-2`), so that I can edit the copy right away.
27. As an admin, I want the suffix to skip codes already in use, so that copying twice does not clash.

Deals: Gift choice
28. As a customer who earned a free gift, I want to choose it on the cart page, so that I can decide early.
29. As a customer, I want to choose it on the checkout page as well, so that I do not miss it when I go straight to checkout from the mini-cart.
30. As a customer, I want to see each gift's regular price struck through with "ฟรี", so that I know what I am getting.
31. As a customer, I want to pick the product option (size etc.) in the same place and see that option's price, so that I get the right variant.
32. As a customer, I want sold-out gifts shown as unavailable, so that I do not pick one that cannot be sent.
33. As a customer, I want to be able to check out without choosing a gift, so that nothing blocks my order.
34. As a customer, I want a gift I picked on the cart to still show as picked on checkout, so that the two boxes agree.
35. As a customer, I want a gift removed with a clear notice when the Deal stops applying, so that I am not confused.

Deals: easier admin screen
36. As an admin, I want a simpler Deals screen with the common choices first and the advanced ones (Conditions, Product scope, Exclusive) tucked away, so that I am not overwhelmed.
37. As an admin, I want templates and a plain-language sentence for what a Deal does, so that I can tell it is right.
38. As an admin, I want the Payment fee switch and amount on the same admin area, so that I find it.
39. As a shop owner, I want a mockup of the new screen to approve before it is built, so that it fits what the admin finds easy.
40. As an admin, I want everything that already works (Active status reasons, toggles, ordering, Member Privilege and free-shipping tabs) to keep working, so that no Deal changes behaviour.

## Implementation Decisions

- **Payment fee** is a positive cart fee added after all discount fees, keyed on the chosen payment method being cash on delivery. It is not taxable, and must be checked against WooCommerce's behaviour of taxing positive fees regardless of the flag (the same quirk found for negative fees). If tax cannot be suppressed through the flag, back the tax out of the fee so the customer pays exactly the configured amount.
- The fee is excluded from every "spend X" measure, from Points earning and from Minimum spend tests. Those measures read the item total, which already excludes fees, but a test must lock this in.
- The fee amount and on/off switch are stored as owner settings, default on at ฿30, edited in the Deals admin area.
- The fee decision is a pure function of (payment method, setting, cart context) so it can be tested without WordPress. The WordPress side only gathers the chosen payment method.
- The existing "payment method" Condition named in CONTEXT.md and ADR-0008 does not exist in code. That is a separate gap and is not built here; CONTEXT.md is left as it is for that item.
- **Shipping label** keeps the existing HTML builder and settings page. Printing moves to our own entry points: a button on the single order screen, and a bulk action in the order list that prints the chosen orders one per page. The page size is a setting (default 100x150 mm, to be confirmed by the owner before that ticket is worked). The WebToffee label button and its filter hook are switched off; the plugin stays active for invoices.
- **Gift choice** reuses the existing choice list builder. The list gains the regular price (and per-option price) for every candidate. The box renders on the cart page and on the checkout page, both reading the same picked state. Picking from checkout returns to checkout. Not choosing never blocks checkout.
- **Coupon creation from a Deal** follows ADR-0009. A pure decision function takes (code, existing coupon lookup, mode: save or copy) and returns link / create / refuse / suffixed-copy. The WooCommerce side performs the result. A coupon the Deal did not create is never overwritten.
- **Deal copy** keeps the current behaviour (new id, label suffix, disabled, inserted after the original) and also resolves a new coupon code per the decision function.
- **Admin screen redesign** is preceded by a prototype/mockup the owner approves. The stored shape of Deals does not change; only presentation and the order of fields.
- Order of delivery to the live site, each as its own upload kit: (1) Gift choice with prices on cart and checkout, (2) easier admin screen plus copy button, (3) coupon created by the Deal. The Payment fee and the shipping label are independent of these and ship on their own.

## Testing Decisions

- A good test checks external behaviour only: the fee amount for a given cart, the HTML of a label, the list of choices a customer sees, the decision for a coupon code. It does not assert on internal helpers or call order.
- Highest seams, one per feature:
  - Payment fee: the pure fee function (new file in the style of the pricing core tests).
  - Shipping label: `amouriq_label_html()` and the label data builder, extended in the existing `ShippingLabelTest`.
  - Gift choice: the choice-list builder, extended in the existing `BogoGiftTest`.
  - Coupon from a Deal: the pure coupon decision function (new test file).
  - Admin screen: form-save behaviour through `BogoConditionsAdminTest`-style tests; layout is checked with the mockup and a headless-Chrome (raw CDP) run, not by assertion.
- Prior art: `tests/BogoGiftTest.php`, `tests/ShippingLabelTest.php`, `tests/BogoPricingTest.php`, `tests/BogoConditionsAdminTest.php`.
- Manual checks that automated tests cannot cover: a real cash-on-delivery order, a real Omise checkout, a real print on the owner's printer, and the checkout page refreshing totals when the payment method changes.

## Out of Scope

- The "payment method" Condition (listed in CONTEXT.md but not in code).
- A fee for any payment method other than cash on delivery.
- Changing label content or layout, or replacing the WebToffee invoice features.
- New Deal types, or changes to Stacking, Member Privilege, or Points rules.
- Duplicating WooCommerce coupons from the coupon screen itself.
- Uploading to the live site (the owner does that by hand from the upload kits).
- The unresolved live Omise missing-order incident.

## Further Notes

- The owner could not say yet which step of the Deals screen the admin finds hardest; ask the admin if possible before the mockup, otherwise propose a mockup anyway.
- The paper size was assumed (100x150 mm) when the owner accepted the recommendation; confirm before the label ticket.
- Glossary (`CONTEXT.md`) and ADR-0009 were updated during the grilling session for this spec.
