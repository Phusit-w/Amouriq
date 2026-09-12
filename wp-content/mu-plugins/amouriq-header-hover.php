<?php
/**
 * AMOURIQ header — 2 visual states, same solid look for both triggers:
 *  1. Top of page, not hovered: #amq-header is position:absolute over the
 *     hero, transparent, ivory text (nav-left / logo-center / icons-right).
 *  2. Hovered OR scrolled: solid porcelain bg + ink text. Hover uses
 *     Elementor's own Background "Hover" tab; .is-scrolled (added by the
 *     JS below) forces the identical look while scrolled, and also swaps
 *     position to fixed so the bar stays pinned to the viewport.
 * Elementor's container Position control has no "sticky" and can't react
 * to scroll position at all — hence this small stylesheet + listener.
 */
add_action('wp_head', function () {
	// CSS now lives in the child theme (assets/css/amq-header-hover.css).
	// Still printed here, unconditionally, in wp_head — not via
	// wp_enqueue_style(), to keep this sitewide asset's loading mechanism
	// consistent with the other AMOURIQ mu-plugin asset pairs. Hand-printing
	// the tag here preserves the exact same hook, priority, and output
	// position as before.
	$path = get_stylesheet_directory() . '/assets/css/amq-header-hover.css';
	if (!file_exists($path)) return;
	printf(
		'<link rel="stylesheet" id="amq-header-hover-css" href="%s" media="all" />',
		esc_url(get_stylesheet_directory_uri() . '/assets/css/amq-header-hover.css?ver=' . filemtime($path))
	);
}, 100);

add_action('wp_footer', function () {
	// JS now lives in the child theme (assets/js/amq-header-hover.js).
	// Still printed here, unconditionally, in wp_footer — see the CSS
	// hook above for why this stays a hand-printed tag rather than
	// wp_enqueue_script().
	$path = get_stylesheet_directory() . '/assets/js/amq-header-hover.js';
	if (!file_exists($path)) return;
	printf(
		'<script id="amq-header-hover-js" src="%s"></script>',
		esc_url(get_stylesheet_directory_uri() . '/assets/js/amq-header-hover.js?ver=' . filemtime($path))
	);
});
