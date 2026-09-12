# 02: CASTILE page + navigation wiring

**What to build:** The `/castile/` page itself (Elementor-enabled, using the site's existing static header/footer chrome), plus a new CASTILE item in the site's navigation — reachable from every page template.

**Blocked by:** None (can start immediately)

**Status:** ready-for-agent

- [ ] A new WordPress Page exists at slug `castile`, Elementor-enabled, using the site's "static" header/footer chrome (the same treatment as Shop/Blog/Contact/single-Product/single-Post — `get_header('shop')` / `get_footer('shop')`), not Home's hero-based header
- [ ] The DB "Main Menu" has a new item titled `CASTILE` linking to this page, positioned between the existing SHOP and BLOG items; the existing "Our Castile Soap" item (submenu under ABOUT) is untouched — not moved, relabeled, or retargeted
- [ ] `header-shop.php`'s hardcoded nav has a `CASTILE` link added between SHOP and BLOG
- [ ] The Home page header nav also shows CASTILE in the same position (confirm whether it already inherits from the DB Main Menu via a native Elementor Nav Menu widget; wire it directly if it doesn't)
- [ ] Clicking CASTILE from Home, Shop, Blog, About, Contact, a single Product page, and a single Post all land on `/castile/`
- [ ] Visiting `/castile/` directly renders with the correct static header/footer chrome and no PHP errors or warnings, even with placeholder/empty body content
