# 06: Live upload kit with the switcher hidden

**What to build:** A kit folder (`deploy-i18n`, same shape as earlier kits) holds the files to upload, rollback copies, the steps to install the translation plugin on the live site, and a switch that keeps the **Language switcher** hidden. The owner installs it by hand. Afterwards Claude checks the live site read-only: Thai pages are unchanged and `/en/` answers with correct alternate-language links, while customers still see no switcher.

**Blocked by:** 02 (Language switcher flips one page end to end)

**Status:** ready-for-agent

- [ ] Kit contains upload files, rollback copies and a step-by-step checklist
- [ ] After the owner's install, the live home page and a product page are unchanged in Thai (read-only check)
- [ ] `/en/` returns a page on the live site and hreflang links are present
- [ ] The switcher is not visible to customers while the switch is off
- [ ] Rollback steps tested on Local
