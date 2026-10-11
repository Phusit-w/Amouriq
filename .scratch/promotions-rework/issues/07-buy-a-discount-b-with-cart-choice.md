# 07: Buy A, discount B, with the customer choosing B in the cart

**What to build:** The product-pair Deal becomes "buy N of product A, then M of product B get a percent or baht discount". The admin ticks several variations as B. When the customer qualifies, the cart lists every eligible B (out-of-stock ones cannot be chosen) and lets the customer pick up to M of them; the pick becomes a normal cart line and gets the discount. Not choosing never blocks checkout, but a prominent notice shows in the cart and at checkout. For the chosen B, Member Privilege is not added: the larger discount wins on that line, and other lines keep their 5%. Example: buy 1 of A, choose one Castile Soap 250 ml scent at 20% off.

**Blocked by:** 05

**Status:** ready-for-human (tested locally; cart box not checked on a phone)

- [x] Admin labels read "buy how many of product A" and "how many of product B get the discount", with percent or baht
- [x] Several B variations can be ticked in one Deal
- [x] The cart shows all eligible B items when the Deal is met, out-of-stock ones disabled, with live stock checks
- [x] The chosen B gets the discount; the customer can change or remove the choice
- [x] Checkout is not blocked when no choice was made; a notice shows in the cart and at checkout
- [x] The larger-wins rule holds per line against Member Privilege
- [x] PHPUnit covers the calculation; the cart choice is checked in a real browser

## Notes (implementation)

- A product-pair Deal with `get_product_ids` (and `get_mode` percent|baht, `get_value`) is the choose-B kind; the old one-B Deal keeps its code path. `amouriq_bogo_core_pair_choice()` gives the discount per line (to the first B units in cart order, up to buy/get allowance) and how many choices are left; `amouriq_bogo_price()` now returns `choices_left`. Tests: `tests/BogoPairChoiceTest.php`.
- New `wp-content/mu-plugins/amouriq-bogo-cart-choice.php`: the box on the cart page (all eligible B, out-of-stock disabled), a nonce-protected pick link that re-checks the Deal, the item, stock and the choices left before adding a normal cart line, and the notice at checkout. Removing the line (cart bin) returns the choice. Not choosing never blocks checkout.
- Admin: A stays a dropdown, B is a tick list with stock status, "how many B get the discount", percent or baht per piece. A legacy pair Deal opens with its B ticked.
- Also fixed in the admin form: fields of a type that is not selected are now disabled so they are not posted (several types share field names such as `product_ids` and `get_qty`).
- Local run (throwaway admin and Deal, removed afterwards), real admin form and real cart in headless Chrome: checkout notice with A only; cart box lists 3 in stock + 1 out of stock (disabled); pick adds the line with 20% off, member 5% stays on A, box disappears; removing the pick brings the box back; a forged link for an out-of-stock item and a bad nonce are both refused.
- Live: needs `amouriq-bogo-cart-choice.php` uploaded together with the changed admin, deals and core files.
