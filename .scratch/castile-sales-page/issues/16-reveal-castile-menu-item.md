# 16: Reveal the CASTILE menu item on the live site

**What to build:** Once the owner confirms the page is finished, add CASTILE to the main menu so it reads SHOP · CASTILE · BLOG · ABOUT · CONTACT on amouriq.co.th. The item was deliberately hidden on live while the page was unfinished.

**Blocked by:** 15 (Mobile round), and explicit go-ahead from the owner. Do not start without it.

**Status:** ready-for-human

- [x] Owner has said the page is finished and the menu item may go live (asked to start this ticket, 2026-10-17)
- [x] Prepare the exact change (where the live menu is edited, rollback step); owner applies it on live
- [ ] After it is applied, read-only check that the menu order is right and the item opens /castile/ on desktop and mobile

## Comments

- **Prepared (2026-10-17):** read-only check of live: Main Menu (Header Menu 1, location menu_1, menu id 24) holds SHOP, BLOG, ABOUT (child: Our Castile Soap), CONTACT; there is no CASTILE item on live (it was never added, not hidden), and the live page 5309 `/castile/` is published. The change is done in WP admin > Appearance > Menus (add the page, label CASTILE, drag between SHOP and BLOG, save), no file to upload; steps, checks and rollback in `D:Localappdeploy-castile-menusteps.md`. The owner applies it, then Claude checks read-only on desktop and mobile.
