<?php
/**
 * Loader for amq-cart-dropdown-fix.js — see that file's own docblock for the
 * root cause (a Blocksy core detection bug that silently disables the header
 * mini-cart dropdown sitewide whenever the cart has items). Loaded
 * unconditionally, on every page, same as amouriq-header-hover.php: the
 * header cart is a global element, not scoped to any one template.
 */

if (!defined('ABSPATH')) exit;

add_action('wp_footer', function () {
	$path = get_stylesheet_directory() . '/assets/js/amq-cart-dropdown-fix.js';
	if (!file_exists($path)) return;
	printf(
		'<script id="amq-cart-dropdown-fix-js" src="%s"></script>',
		esc_url(get_stylesheet_directory_uri() . '/assets/js/amq-cart-dropdown-fix.js?ver=' . filemtime($path))
	);
});
