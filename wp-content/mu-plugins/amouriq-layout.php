<?php
/**
 * Loader for amq-layout.css — site-wide structural/layout rules (currently
 * just the overflow-x safety net; see that file's own docblock for what's
 * meant to migrate here later). Loaded unconditionally, on every page, same
 * as amq-header-static.php/amq-header-hover.php.
 */

if (!defined('ABSPATH')) exit;

add_action('wp_head', function () {
	$path = get_stylesheet_directory() . '/assets/css/amq-layout.css';
	if (!file_exists($path)) return;
	printf(
		'<link rel="stylesheet" id="amq-layout-css" href="%s" media="all" />',
		esc_url(get_stylesheet_directory_uri() . '/assets/css/amq-layout.css?ver=' . filemtime($path))
	);
}, 100);
