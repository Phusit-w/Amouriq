# 09: Recreate the enabled live Discount Rules, QA, deploy kit

**What to build:** From the Discount Rules review (`../../promotions-rework/discount-rules-review.md`), recreate what the owner still wants as rules or coupons (all were free-shipping), run the whole suite and a browser pass over cart and checkout, and write the upload kit (upload\ files, rollback copies, test checklist) like `D:\Local\app\deploy-promotions\`.

**Blocked by:** 06, 07, 08

**Status:** ready-for-agent

- [ ] No enabled live Discount Rule is left without an equivalent or an explicit decision to drop it
- [ ] Full PHPUnit suite green; browser pass over cart, checkout and the admin form
- [ ] Kit lists every file to upload and the order; the owner uploads, Claude only verifies read-only
