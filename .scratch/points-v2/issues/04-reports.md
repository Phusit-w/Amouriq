# 04: Points reports and CSV export

**What to build:** A "รายงาน" tab: per month, points earned, used, expired, adjusted, and the outstanding balance (the programme's liability, also in baht at 1 point = ฿1); top earners; points expiring in the next 30 days. A CSV export of the ledger for a date range, streamed, no file kept.

**Blocked by:** 03 (shares the ledger reader)

**Status:** done locally (not uploaded, not in the deploy-round2 kit). The report section was not opened in a browser

- [x] For a set of throwaway ledgers, the monthly totals equal the sum of the entries (PHPUnit on the pure summariser)
- [x] Outstanding balance equals the sum of every customer's balance
- [x] CSV opens in Excel with Thai text intact (UTF-8 with BOM) and has one row per ledger entry
- [ ] Fast enough on the store's size (no new table was added; speed not measured, it reads every member ledger on each page view)

## Notes (implementation)

- Pure: `amouriq_points_core_lots()` (split out of `amouriq_points_core_balance()`, same behaviour), `amouriq_points_core_report()`, `amouriq_points_core_csv_line()` in `points-core.php` (tests `tests/PointsReportTest.php`). Glue at the end of `amouriq-points.php`: `amouriq_points_admin_report_section()` on the points screen, `amouriq_points_csv_export()` on `admin_init` (needs `manage_woocommerce` and a nonce; streams the file, nothing stored).
- Months: earned (earn + bonuses), used, staff adjustments (net), taken back / given back (net), expired (in the month the lot expired). Also outstanding points (and their value at the redeem rate), points expiring within 30 days, the ten top earners. Months follow the site UTC offset.
- CSV: UTF-8 with BOM, one row per ledger entry, optional from/to dates (site time, both included), Thai type names.
- Run locally against real WordPress with two throwaway members (deleted after): outstanding 140 equals the sum of the balances, the month rows, the CSV (BOM, Thai text with commas and quotes quoted, dates shifted to site time, a February-only range gave 2 rows) and a wrong nonce refused.
- Still open: the report section in a browser; all numbers are computed from the ledgers on every view (no cache).
