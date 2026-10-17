# 14: Whole-page check, Elementor editability audit, live upload kit (desktop)

**What to build:** The full desktop page is checked top to bottom against the design with no unintended differences left, every field is confirmed editable in Elementor, and an upload kit is prepared so the owner can put the result on amouriq.co.th: a single mu-plugin that writes the page content with `$wpdb` (never `update_post_meta()`), the asset files, rollback copies of what they replace, and a test checklist. The owner uploads; Claude verifies read-only afterwards.

**Blocked by:** 10, 11, 12, 13

**Status:** ready-for-agent

- [ ] Full-page side-by-side check from 08 shows only deliberate differences (placeholders, simplified animations), each listed
- [ ] Audit: every text, image, colour, button and link is editable in the Elementor panel; the custom buy box is covered by its own fields; exceptions listed
- [ ] Upload kit includes mu-plugin, assets, rollback files, test checklist under a deploy folder alongside the earlier kits
- [ ] Kit verified locally from a fresh process (page data intact after write)
- [ ] Page-title/hero postmeta for page 5309 included or confirmed already live
- [ ] CASTILE menu item is NOT part of this kit
- [ ] After owner uploads, read-only check of live page against the design
