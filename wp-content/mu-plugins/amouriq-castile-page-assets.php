<?php
/**
 * CASTILE sales page (slug: castile, ticket 03): conditional CSS/JS load
 * for the section 00-09 animations, plus the Prompt webfont this page's
 * Elementor content sets explicitly (typography_font_family: "Prompt" on
 * every heading/text widget — see build notes in
 * .scratch/castile-sales-page/issues/03-content-trust-bar-to-free-alkali.md).
 *
 * Loaded here rather than relying on amq-lang-font.php's sitewide Thai/English
 * font switch: that mu-plugin keys off Polylang's pll_current_language(),
 * which isn't installed on this site (TranslatePress is) — so it always
 * falls through to the English branch and never loads Prompt. Not fixed
 * here (separate, pre-existing issue, out of scope); this page loads its
 * own Prompt weights defensively instead.
 *
 * Modeled on amouriq-single-product.php's is_product()-gated conditional
 * enqueue.
 */

if (!defined('ABSPATH')) exit;

add_action('wp_enqueue_scripts', function () {
	if (!is_page('castile')) return;

	wp_enqueue_style(
		'amq-castile-prompt-font',
		'https://fonts.googleapis.com/css2?family=Prompt:wght@400;500;600;700&display=swap',
		[],
		null
	);

	$css_path = get_stylesheet_directory() . '/assets/css/amq-castile.css';
	if (file_exists($css_path)) {
		wp_enqueue_style(
			'amq-castile',
			get_stylesheet_directory_uri() . '/assets/css/amq-castile.css',
			['amq-woocommerce'],
			filemtime($css_path)
		);
	}

	$js_path = get_stylesheet_directory() . '/assets/js/amq-castile.js';
	if (file_exists($js_path)) {
		wp_enqueue_script(
			'amq-castile',
			get_stylesheet_directory_uri() . '/assets/js/amq-castile.js',
			[],
			filemtime($js_path),
			true
		);
	}
}, 20);
