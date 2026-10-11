# 08: QA across the programme and the upload kit

**What to build:** Run the whole suite, a browser pass over the points box at cart and checkout, My Account tab (phone too), every admin tab, and a full lifecycle on throwaway data (earn with a rule, bonus, redeem, use at cart, refund, expire, adjust, report). Write the upload kit in `D:\Local\app\deploy-points\` (upload\ files, rollback copies, test checklist) as the first round did.

**Blocked by:** 01 to 07

**Status:** ready-for-agent

- [ ] Ledger totals agree with the reports after the lifecycle test
- [ ] Switching the programme off hides every box and tab and gives no points, with balances kept
- [ ] The kit lists every new or changed file and the order; the owner uploads, Claude only verifies read-only
- [ ] The real-checkout and Omise-retry paths that the first round could not test are listed as the owner's live checks
