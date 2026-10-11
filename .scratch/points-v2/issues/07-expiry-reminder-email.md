# 07: Points expiry reminder email

**What to build:** An optional daily check that emails customers whose points expire within N days (default 14, owner-editable), once per batch of expiring points, through WooCommerce's mailer in Thai with the balance, the date and a link to My Account. Off by default. An optional "you earned N points" note on Completed uses the same switch row.

**Blocked by:** 03 (reads expiry from the same ledger helper)

**Status:** ready-for-agent (after the owner answers open question 4)

- [ ] Nobody is emailed twice for the same expiring batch; a customer with nothing expiring gets nothing
- [ ] Off by default; switching it on never sends a backlog for points that already expired
- [ ] Locally mail is blocked, so the send path is checked with a captured `wp_mail`; the first live send is the owner's to confirm
- [ ] Text is Thai, readable on a phone, and has no price or order detail beyond points
