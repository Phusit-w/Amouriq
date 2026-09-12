<?php
/**
 * Hello Elementor Child — functions.php
 *
 * Responsibilities of this file, and only these:
 *   1. Enqueue the parent theme's stylesheet (WordPress child-theme convention).
 *   2. Enqueue this child theme's own stylesheet, after the parent's.
 *   3. Enqueue the AMOURIQ project CSS/JS asset files under /assets/.
 *
 * No shortcodes, WooCommerce/Elementor logic, or dynamic markup live here —
 * that stays in wp-content/mu-plugins/ so it keeps working regardless of
 * which theme is active. This file only loads presentational assets.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Enqueue parent stylesheet, child stylesheet, and AMOURIQ asset files.
 *
 * @return void
 */
function amq_child_enqueue_assets() {

	// 1. Parent theme's style.css. In Hello Elementor this file only contains
	// the theme header comment block (the theme's real CSS lives in
	// reset.css/theme.css, already enqueued by the parent's own
	// hello_elementor_scripts_styles()). We still enqueue it here because
	// that is the standard, documented WordPress child-theme pattern, and it
	// costs nothing if the parent ever adds real rules to style.css later.
	wp_enqueue_style(
		'hello-elementor-parent-style',
		get_template_directory_uri() . '/style.css',
		[],
		wp_get_theme( get_template() )->get( 'Version' )
	);

	// 2. This child theme's own style.css, loaded after the parent's so it
	// can override parent rules if ever needed.
	wp_enqueue_style(
		'hello-elementor-child-style',
		get_stylesheet_uri(),
		[ 'hello-elementor-parent-style' ],
		wp_get_theme()->get( 'Version' )
	);

	// 3. AMOURIQ project CSS files, in deliberate cascade order (tokens
	// first, then layout, then components, then WooCommerce-specific
	// overrides last so they can override component defaults for shop
	// pages). Each is currently a placeholder — see the comment header
	// inside each file for what will eventually move there from mu-plugins.
	//
	// filemtime() is used as the version string so the browser is forced to
	// fetch a fresh copy the moment any of these files actually changes,
	// without needing to remember to bump a version number by hand.
	$amq_css_files = [
		'amq-tokens'      => 'assets/css/amq-tokens.css',
		'amq-layout'      => 'assets/css/amq-layout.css',
		'amq-components'  => 'assets/css/amq-components.css',
		'amq-footer-signup' => 'assets/css/amq-footer-signup.css',
		'amq-woocommerce' => 'assets/css/amq-woocommerce.css',
	];

	$deps = [ 'hello-elementor-child-style' ];

	foreach ( $amq_css_files as $handle => $rel_path ) {
		$file_path = get_stylesheet_directory() . '/' . $rel_path;

		if ( ! file_exists( $file_path ) ) {
			continue;
		}

		wp_enqueue_style(
			$handle,
			get_stylesheet_directory_uri() . '/' . $rel_path,
			$deps,
			filemtime( $file_path )
		);

		// Each file cascades on top of the previous one.
		$deps = [ $handle ];
	}

	// 4. AMOURIQ animations JS, loaded in the footer.
	$js_rel_path = 'assets/js/amq-animations.js';
	$js_path     = get_stylesheet_directory() . '/' . $js_rel_path;

	if ( file_exists( $js_path ) ) {
		wp_enqueue_script(
			'amq-animations',
			get_stylesheet_directory_uri() . '/' . $js_rel_path,
			[],
			filemtime( $js_path ),
			true
		);
	}
}
add_action( 'wp_enqueue_scripts', 'amq_child_enqueue_assets' );
