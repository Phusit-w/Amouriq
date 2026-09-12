<?php
/**
 * Loader for amq-header-static.css — the sticky, solid-from-the-start
 * header used on pages with no dark hero behind their top (e.g. Contact).
 * See wp-content/mu-plugins/amouriq-header-hover.php for the hero-page
 * variant this complements (#amq-header, transparent + JS scroll toggle).
 */

if (!defined('ABSPATH')) exit;

add_action('wp_head', function () {
	$path = get_stylesheet_directory() . '/assets/css/amq-header-static.css';
	if (!file_exists($path)) return;
	printf(
		'<link rel="stylesheet" id="amq-header-static-css" href="%s" media="all" />',
		esc_url(get_stylesheet_directory_uri() . '/assets/css/amq-header-static.css?ver=' . filemtime($path))
	);
}, 100);
