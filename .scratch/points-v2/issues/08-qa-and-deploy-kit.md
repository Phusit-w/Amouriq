# 08: QA across the programme and the upload kit

**What to build:** Run the whole suite, a browser pass over the points box at cart and checkout, My Account tab (phone too), every admin tab, and a full lifecycle on throwaway data (earn with a rule, bonus, redeem, use at cart, refund, expire, adjust, report). Write the upload kit in `D:\Local\app\deploy-points\` (upload\ files, rollback copies, test checklist) as the first round did.

**Blocked by:** 01 to 07 (06 and 07 are NOT built; this ticket covers what exists: 01 to 05)

**Status:** kit built (`D:\Local\app\deploy-round3\`); the browser pass and a single end-to-end lifecycle run are NOT done

- [ ] Ledger totals agree with the reports after the lifecycle test (each piece was checked on its own: the report's outstanding total equals the sum of the balances; no single run chained earn, bonus, redeem, use, refund, expire, adjust and report)
- [x] Switching the programme off hides every box and tab and gives no points, with balances kept (the bonus and plan hooks check `amouriq_points_enabled()`; the admin tabs stay visible to the owner on purpose)
- [x] The kit lists every new or changed file and the order; the owner uploads, Claude only verifies read-only
- [ ] The real-checkout and Omise-retry paths that the first round could not test are listed as the owner's live checks (listed in `test-checklist.md`; still untested)

## Notes (implementation)

- Kit `deploy-round3`: `upload\` (2 files: `amouriq-points.php`, `amouriq-points/points-core.php`), `points-round3.zip` (forward-slash paths), `rollback\` + `rollback.zip` (the files that were live before round 2), `test-checklist.md` (Thai), `qa-results.md`. These two files replace the points files in `deploy-round2\step2`, which are older; the rest of round 2 is unchanged.
- Read-only check of live on 2026-10-12: round 2 was not uploaded yet (the points admin page had no earning-rules section); all checked pages answered 200 with no fatal error. The Plesk session had expired, so file sizes could not be read.
- Suite: 279 tests, 691 assertions, green. Every kit file is byte-identical to the repository file and lints clean.
- Not built: gift rewards (06) and the expiry reminder email (07).
