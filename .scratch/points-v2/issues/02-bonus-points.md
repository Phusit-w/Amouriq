# 02: Bonus points (sign-up, first order, verified review)

**What to build:** Three optional one-time bonuses, each with its own on/off and amount in the admin screen: on creating an account, on a New customer's first Completed order, and on an approved verified-purchase review (once per product, with a yearly cap). Each is a ledger entry of its own kind and can never be given twice for the same trigger.

**Blocked by:** none

**Status:** ready-for-agent (review amount and cap wait for open question 2)

- [ ] Each bonus is given once and only once, also when the trigger event fires twice (order status flipped back and forth, review edited and re-approved)
- [ ] A review by someone who never bought the product, or an unapproved review, gives nothing
- [ ] Switching a bonus off stops new bonuses and leaves earned ones alone
- [ ] Worked in PHPUnit for the rules; the three triggers exercised against real WooCommerce locally with throwaway users, orders and reviews (deleted after)
