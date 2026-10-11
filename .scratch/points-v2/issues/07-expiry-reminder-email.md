# 07: Points expiry reminder email

**What to build:** An optional daily check that emails customers whose points expire within N days (default 14, owner-editable), once per batch of expiring points, through WooCommerce's mailer in Thai with the balance, the date and a link to My Account. Off by default. An optional "you earned N points" note on Completed uses the same switch row.

**Blocked by:** 03 (reads expiry from the same ledger helper)

**Status:** done locally (not uploaded, not in a kit yet). A real send on live is the owner's to confirm

- [x] Nobody is emailed twice for the same expiring batch; a customer with nothing expiring gets nothing
- [x] Off by default; switching it on never sends a backlog for points that already expired
- [x] Locally mail is blocked, so the send path is checked with a captured `wp_mail` (done); the first live send is the owner's to confirm (not done)
- [x] Text is Thai, readable on a phone, and has no price or order detail beyond points

## Notes (implementation)

- Pure: `amouriq_points_core_expiry_mail_settings()`, `amouriq_points_core_expiring()`, `amouriq_points_core_should_notify()`, `amouriq_points_core_expiry_message()` in `points-core.php` (tests `tests/PointsExpiryEmailTest.php`). Glue at the end of `amouriq-points.php`: the admin section (switch + days, default off and 14), `init` keeps the daily event `amouriq_points_expiry_cron` scheduled only while the reminder and the programme are both on, `amouriq_points_expiry_run()` sends through the WooCommerce mailer (`wrap_message` + `send`) and remembers the soonest-expiry date of the batch in user meta `_amouriq_points_expiry_notified`.
- Only points that can still be spent count, so already-expired points are never mentioned and nothing is backfilled when it is switched on; a batch already announced is not repeated, a new batch (a lot that expires sooner) is. The text is plain Thai with the balance, the date and a link to My Account, no prices.
- The optional "you earned N points" note from the ticket was NOT built.
- Run locally against real WordPress/WooCommerce with four throwaway members and a captured `wp_mail` (deleted after): off = 0 sent; on = one email to the member with 100 points expiring in 5 days (none for 60 days away, already expired, or all used); a second run sent none; a new sooner lot sent one more; switching off removed the schedule.
