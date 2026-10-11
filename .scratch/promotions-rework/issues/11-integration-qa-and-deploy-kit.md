# 11: Integration check in a real browser and the live upload kit

**What to build:** Drive the local site in headless Chrome and confirm all the new promotions together: the combinations that matter (Member Privilege with each Deal type, with coupons, with Sale Price items), the gift and choice flows through to a placed order with real stock decrement, and a printed label. Then prepare the upload kit for the owner: files to upload, rollback copies and a test checklist, in the same shape as the earlier Deal kit. The owner performs the live upload.

**Blocked by:** 03, 04, 06, 08, 09, 10

**Status:** ready-for-human (kit prepared; ticket 10 and the live upload are the owner's)

- [x] A test pass covers each user story that cannot be checked by unit tests, recorded as a short results list
- [x] Combination cases are exercised: Member only, Member with Deal, Member with Coupon, Deal with Coupon, Sale Price items, registering during checkout
- [x] A gift and a chosen item both survive to a placed order with stock decremented
- [x] The upload kit has upload and rollback folders and a test checklist usable by the owner
- [x] The kit includes every new file the Deal engine now loads (it requires the pricing core file; leaving it out is a fatal error on live)
- [x] The kit does not switch on any Deal on live by itself

## Notes (implementation)

- Results of the local pass: `D:\Local\app\deploy-promotions\qa-results.md` (15 cases). Kit: `D:\Local\app\deploy-promotions\` with `upload\` (8 files, including `amouriq-bogo/pricing-core.php`), `rollback\` (the previous kit's 3 files), `test-checklist.md`.
- To keep "the kit switches nothing on by itself", Member Privilege has an owner switch (WooCommerce > ดีล BOGO, option `amouriq_member_privilege`, **off until switched on**). It is not a Deal, so without the switch uploading the files would have changed every member's price at once. The local site has it off too.
- Not run: placing an order through the real checkout page while registering; the cart choice box on a phone.

## Review follow-up (code review of tickets 02 to 11)

Fixed: Thai label for Member Privilege is **สิทธิพิเศษสมาชิก** everywhere (ส่วนลดสมาชิก is "member discount", an Avoid term; glossary updated), its switch row is no longer labelled as a Deal and reads "เปิดอยู่/ปิดอยู่"; the pick link's nonce is bound to the Deal and the item; a gift's stock check counts paid units of the same stock; a gift order line carries the note "ของแถม = ฟรี"; per-customer/per-order limits and "skip Sale items" are no longer offered or stored for gift Deals (they could never fire); Deal edit/delete/toggle act only on the Deal the page showed (stale second tab is refused); the product-id matcher and the shown-price helper are written once in the core.
Left on purpose, for the owner to decide or later: story 24 notices for an unmet spend/gift/mix-and-match trigger or a Deal beating Member Privilege (only the fee label says it); story 8 admin reasons "trigger unmet / limit reached"; a pending order counts for the New customer rule (per the glossary; an abandoned card payment blocks the retry); logged-out total on the cart differs from checkout (deliberate); overlapping Deals on one line are not capped (as before); admin pickers load every product (fine for ~200 variations); per-type switches in several files.

## Cart explanations (story 24), added after the review

The core returns a `hint` for a live Deal that is not met (`amouriq_bogo_core_hint()`: how much more to spend for a spend-threshold or gift Deal, how many more pieces for the next mix-and-match tier; `tests/BogoHintTest.php`). The cart and checkout pages show it ("ซื้อเพิ่มอีก ฿541.00 เพื่อรับส่วนลด ...", "เพิ่มสินค้าในดีล ... อีก 1 ชิ้น เพื่อรับส่วนลด 8%"), and a notice when a Deal beat สิทธิพิเศษสมาชิก on every line. Checked in headless Chrome with a throwaway member and three Deals (removed afterwards): 1 piece shows three hints, 3 pieces shows the gift hint and the "Deal is larger" notice; the checkout showed each message twice because its AJAX refresh adds it again, so these notices are now skipped during AJAX. Not covered: Deals that need a coupon (no hint until the coupon is in the cart), and the story 8 admin reasons.
