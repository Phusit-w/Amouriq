# 07: Copying a Deal also makes a new coupon

**What to build:** The "ทำสำเนา" button on a Deal also creates a new coupon whose code is the original plus a suffix (`-2`, skipping codes already in use), so the copy can be edited right away.

**Blocked by:** 06

**Status:** ready-for-agent

- [ ] Copy keeps current behaviour (new id, label suffix, disabled, inserted after the original)
- [ ] The copy has its own new coupon code; copying twice never clashes (test on the decision function's copy mode)
- [ ] A Deal with the auto trigger copies as before, without a coupon
