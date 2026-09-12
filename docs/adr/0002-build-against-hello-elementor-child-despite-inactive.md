---
status: accepted
---

# New feature work targets `hello-elementor-child`, even though it isn't the currently active theme

The site's active theme (`wp_options.stylesheet`) is `pcoursewebbs`, a mostly-empty Blocksy child theme — that's what amouriq.co.th actually renders with today, including the live Home page (Elementor-built page 1016, last saved 2025-12-25). Meanwhile `wp-content/themes/hello-elementor-child` — where all the AMOURIQ-specific header/footer/single-product code and Customizer work already live (see `amouriq-single-product.php`'s "owner decision 2026-07-27" comment, dated *after* that Home page save) — is marked `inactive`. There's also a draft, unpublished Elementor Theme Builder header (post id 1067, condition "entire site") that isn't live anywhere yet. Taken together, `hello-elementor-child` is an in-progress site redesign that hasn't been cut over to production.

**Decision**: Build the CASTILE sales page (and any further work referencing this ADR) against `hello-elementor-child`, not `pcoursewebbs`/Blocksy — confirmed with the owner. This is a bet that the in-progress redesign is where the site is headed, consistent with the substantial, more-recent custom work already invested there.

**Consequence**: until `hello-elementor-child` is switched live, pages built this way (e.g. `/castile/`) are reachable and functionally correct, but render with Blocksy's default page chrome instead of the intended AMOURIQ header/footer, since `pcoursewebbs` has no template of its own for them and falls back to its Blocksy parent. Verifying a page's *intended* chrome requires temporarily activating `hello-elementor-child` in this local dev copy and switching back afterward — switching it and leaving it active site-wide is a separate decision outside any individual feature ticket's scope.
