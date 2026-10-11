# 11: Integration check in a real browser and the live upload kit

**What to build:** Drive the local site in headless Chrome and confirm all the new promotions together: the combinations that matter (Member Privilege with each Deal type, with coupons, with Sale Price items), the gift and choice flows through to a placed order with real stock decrement, and a printed label. Then prepare the upload kit for the owner: files to upload, rollback copies and a test checklist, in the same shape as the earlier Deal kit. The owner performs the live upload.

**Blocked by:** 03, 04, 06, 08, 09, 10

**Status:** ready-for-agent

- [ ] A test pass covers each user story that cannot be checked by unit tests, recorded as a short results list
- [ ] Combination cases are exercised: Member only, Member with Deal, Member with Coupon, Deal with Coupon, Sale Price items, registering during checkout
- [ ] A gift and a chosen item both survive to a placed order with stock decremented
- [ ] The upload kit has upload and rollback folders and a test checklist usable by the owner
- [ ] The kit includes every new file the Deal engine now loads (it requires the pricing core file; leaving it out is a fatal error on live)
- [ ] The kit does not switch on any Deal on live by itself
