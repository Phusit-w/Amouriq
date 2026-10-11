# 04: Points reports and CSV export

**What to build:** A "รายงาน" tab: per month, points earned, used, expired, adjusted, and the outstanding balance (the programme's liability, also in baht at 1 point = ฿1); top earners; points expiring in the next 30 days. A CSV export of the ledger for a date range, streamed, no file kept.

**Blocked by:** 03 (shares the ledger reader)

**Status:** ready-for-agent

- [ ] For a set of throwaway ledgers, the monthly totals equal the sum of the entries (PHPUnit on the pure summariser)
- [ ] Outstanding balance equals the sum of every customer's balance
- [ ] CSV opens in Excel with Thai text intact (UTF-8 with BOM) and has one row per ledger entry
- [ ] Fast enough on the store's size; no new table is added
