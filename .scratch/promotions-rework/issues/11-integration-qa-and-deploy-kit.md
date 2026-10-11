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
