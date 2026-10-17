# castile-foundations

Record of ticket 09 (page-wide foundations) and the workflow later tickets in the design-match round reuse.

1. `node tools/castile-compare/measure.mjs --target design --out design-measure.json` measures the design (computed padding, content width, per-text typography at 1440 / 768 / 390).
2. `wp post meta get 5309 _elementor_data > page.json` (current page data).
3. `node tools/castile-foundations/foundations.mjs --page page.json --design design-measure.json --out new.json --report report.txt` applies the frame (boxed width, section padding at three breakpoints, stretch), and per matched text widget the typography, colour, alignment and max-width. Widgets are matched to design text by normalised text; unmatched ones are listed in the report and left untouched.
4. `wp eval-file tools/castile-foundations/write-page.php new.json` writes the data with a raw `$wpdb->update` (never `update_post_meta`, which has destroyed this page's content before) and clears Elementor/WP caches. Verify from a fresh process: `wp post meta get 5309 _elementor_data` must equal `new.json`.
5. `rm -rf wp-content/cache/all/castile`, then `node tools/castile-compare/compare.mjs --out <dir> --only local`.

`fetch-fonts.mjs` downloaded DM Sans and IBM Plex Sans Thai into the theme's `assets/fonts/` and generated `amq-castile-fonts.css`. Thai glyphs are registered under the family name "DM Sans" so the single font field in Elementor gives the design's Latin + Thai pairing.

Sections 15 and 22 (two-column grids with no content wrapper) are skipped by the frame step; ticket 12 covers them.
