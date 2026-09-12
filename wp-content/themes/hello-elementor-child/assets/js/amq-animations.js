/**
 * amq-animations.js
 *
 * Purpose: frontend interaction/animation JS — carousel arrow click
 * handlers, scroll-position listeners, slide navigation. Loaded in the
 * footer (see functions.php: wp_enqueue_script(..., true)).
 *
 * Currently empty. Candidate code to migrate here later (from
 * wp-content/mu-plugins/*.php inline <script> blocks) includes:
 *   - #amq-header scroll listener that toggles .is-scrolled (amouriq-header-hover.php)
 *   - initCarousel() for the Best Sellers carousel (amouriq-bestsellers-carousel.php)
 *   - initCarousel() for the Browse-by-category carousel (amouriq-category-browse.php)
 *   - initSlider() for the ingredient hero slider (amouriq-hero-slider.php)
 *
 * When migrated, each should stay guarded by a DOM-ready / element-existence
 * check exactly as it is in mu-plugins today, so this file is safe to load
 * on every page even where a given component isn't present.
 */
