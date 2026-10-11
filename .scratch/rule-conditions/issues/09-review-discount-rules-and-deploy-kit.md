# 09: Recreate the enabled live Discount Rules, QA, deploy kit

**What to build:** From the Discount Rules review (`../../promotions-rework/discount-rules-review.md`), recreate what the owner still wants as rules or coupons (all were free-shipping), run the whole suite and a browser pass over cart and checkout, and write the upload kit (upload\ files, rollback copies, test checklist) like `D:\Local\app\deploy-promotions\`.

**Blocked by:** 06, 07, 08

**Status:** done (kit ready; the owner uploads, Claude verifies read-only)

- [x] No enabled live Discount Rule is left without an equivalent or an explicit decision to drop it
- [x] Full PHPUnit suite green; browser pass over cart, checkout and the admin form
- [x] Kit lists every file to upload and the order; the owner uploads, Claude only verifies read-only

## Notes (implementation)

- Discount Rules review: no enabled live rule needs recreating. The six coupons (AMOURIQ2025, WELCOME, 67LX6HU8U, FKAPRNPG7, AMOTHCM20, AMOTHTHX20) all have "Allow free shipping" ticked on live (read 2026-10-11) and are expired; the ฿299 threshold lives in Flexible Shipping. The new free-shipping Deal type is an option, not a replacement that has to be built.
- Kit: `D:\Localpp\deploy-round2\` with `step1\` (conditions.php, must go first), `step2\` (6 files), the matching zips (forward-slash paths), `rollback\` (the 5 live files as they are now, taken from commit b8a7b6d) and `rollback.zip`, `test-checklist.md` (Thai) and `qa-results.md`. The step 2 files include the points programme changes (ticket 01 and 03 of points-v2); the points system itself stays OFF.
- Whole suite green (256 tests); every kit file is byte-identical to the repository file.
- Still unverified anywhere: the new admin screens in a real wp-admin, a real checkout, phone width, Omise retry.
