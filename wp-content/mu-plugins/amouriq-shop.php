<?php
/**
 * Shop archive (/shop/) catalog behavior — things Elementor/WooCommerce
 * defaults don't already do: 12 products/page in 3 columns (to match the
 * "All Products" design reference), and combining the result-count text +
 * sorting dropdown into one row so amq-woocommerce.css can lay them out
 * side by side.
 *
 * The header/footer/hero/sidebar markup itself lives in the child theme's
 * header-shop.php / footer-shop.php / woocommerce/archive-product.php /
 * template-parts/shop-sidebar.php (see wpe-kit conventions: markup + CSS in
 * the child theme, behavior here).
 */

if (!defined('ABSPATH')) exit;

add_filter('loop_shop_per_page', function () {
	return 12;
}, 20);

add_filter('loop_shop_columns', function () {
	return 3;
});

// Combine "Showing 1-12 of 59 results" + the sort-by dropdown into one row.
// Must run after WooCommerce registers its default hooks (wc-template-hooks.php
// loads on 'after_setup_theme'/'init', which is after mu-plugins run), otherwise
// remove_action() below no-ops and both the default output AND ours print.
add_action('wp_loaded', function () {
	remove_action('woocommerce_before_shop_loop', 'woocommerce_result_count', 20);
	remove_action('woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30);
	add_action('woocommerce_before_shop_loop', function () {
		echo '<div class="amq-shop-toolbar">';
		woocommerce_result_count();
		woocommerce_catalog_ordering();
		echo '</div>';
	}, 20);
});

// JS for the sidebar's category/attribute dropdown+arrow-button forms.
add_action('wp_enqueue_scripts', function () {
	if (!function_exists('is_shop') || !is_shop()) return;
	$path = get_stylesheet_directory() . '/assets/js/amq-shop.js';
	if (!file_exists($path)) return;
	wp_enqueue_script('amq-shop', get_stylesheet_directory_uri() . '/assets/js/amq-shop.js', [], filemtime($path), true);
});

// amq-woocommerce.css: header-shop.php/footer-shop.php chrome + shop archive
// hero/sidebar/grid styling. This used to be registered by
// hello-elementor-child/functions.php, which only runs when that theme is
// active — it isn't (pcoursewebbs is), so the handle was never registered
// and every style that depended on it (e.g. amq-single-product.css) silently
// never loaded either. Registering it here (a normal WooCommerce-scoped page
// mu-plugin) keeps it working regardless of which theme is active, matching
// every other AMOURIQ asset on this site. Loaded on any WooCommerce-context
// page, since header-shop.php/footer-shop.php render on all of them, not
// just the shop archive.
add_action('wp_enqueue_scripts', function () {
	if (!function_exists('is_woocommerce') || !(is_woocommerce() || is_cart() || is_checkout() || is_account_page())) return;
	$path = get_stylesheet_directory() . '/assets/css/amq-woocommerce.css';
	if (!file_exists($path)) return;
	wp_enqueue_style('amq-woocommerce', get_stylesheet_directory_uri() . '/assets/css/amq-woocommerce.css', [], filemtime($path));
});
