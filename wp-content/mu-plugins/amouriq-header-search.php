<?php
/**
 * Loader for amq-header-search.css / amq-header-search.js — the header
 * search-icon -> expandable panel behavior, current-page nav highlight, and
 * mobile nav-wrap safety net, shared by every header variant (#amq-header,
 * #amq-header-static, .amq-shop-header). See
 * wp-content/mu-plugins/amouriq-header-hover.php for the sibling loader this
 * complements (transparent/solid header states).
 *
 * Hooked at a later priority (101) than amouriq-header-hover.php (100) so
 * this stylesheet prints after it in <head> — on an exact CSS specificity
 * tie (the active-nav rule vs. the hover/scrolled color rule, both scoped
 * to #amq-header + two classes with !important) source order decides, and
 * this one needs to win.
 */

if (!defined('ABSPATH')) exit;

add_action('wp_head', function () {
	$path = get_stylesheet_directory() . '/assets/css/amq-header-search.css';
	if (!file_exists($path)) return;
	printf(
		'<link rel="stylesheet" id="amq-header-search-css" href="%s" media="all" />',
		esc_url(get_stylesheet_directory_uri() . '/assets/css/amq-header-search.css?ver=' . filemtime($path))
	);
}, 101);

add_action('wp_footer', function () {
	$path = get_stylesheet_directory() . '/assets/js/amq-header-search.js';
	if (!file_exists($path)) return;
	printf(
		'<script id="amq-header-search-js" src="%s"></script>',
		esc_url(get_stylesheet_directory_uri() . '/assets/js/amq-header-search.js?ver=' . filemtime($path))
	);
});
