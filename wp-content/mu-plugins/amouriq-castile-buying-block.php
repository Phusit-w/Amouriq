<?php
/**
 * CASTILE sales page, Section 19 (Buying Block) — ticket 06. The one
 * code-driven exception on this page (see
 * docs/adr/0001-castile-sales-page-built-in-elementor.md): a real Elementor
 * widget, but one whose content is live WooCommerce product/price/stock
 * data, not static Elementor fields — a scent × size selector, quantity
 * stepper, and Add to Cart, plus a mobile sticky cart bar.
 *
 * Reuses the site's existing `amq_add_to_cart` AJAX action (registered in
 * amouriq-bestsellers-carousel.php, also used by the Single Product page's
 * amq-single-product.js) rather than adding a second endpoint that does the
 * same thing — same nonce action name, same request shape, same
 * wc_fragment_refresh cart-count refresh convention.
 */

if (!defined('ABSPATH')) exit;

/**
 * Static per-scent copy (name/oils label/note) — not WooCommerce data, this
 * is marketing copy from the design handoff's own product-data table
 * (design_handoff_amouriq_sales_page/README.md, "Section 19 — Buying
 * block"). Live price/SKU/stock/image per scent+size combination comes from
 * amq_castile_resolve_selection() instead (see amouriq-castile-resolver.php)
 * since that's real catalog data, not static copy.
 *
 * @return array<string, array{name:string, note:string, oils:string}>
 */
function amq_castile_buying_block_scents() {
	return [
		'lavender' => ['name' => 'Lavender', 'note' => 'ดอกลาเวนเดอร์ นุ่ม สงบ', 'oils' => 'Lavender + Lavandin Essential Oils'],
		'rosemary' => ['name' => 'Rosemary', 'note' => 'สมุนไพร สดชื่น ปลอดโปร่ง', 'oils' => 'Rosemary Essential Oil'],
		'rose-geranium' => ['name' => 'Rose Geranium', 'note' => 'ดอกกุหลาบ อบอุ่น กลมกล่อม', 'oils' => 'Rose Geranium Essential Oil'],
	];
}

/**
 * Size slug => display label. Same three sizes amq_castile_size_attribute_values()
 * resolves, just the human label for the buying block's size buttons.
 *
 * @return array<string, string>
 */
function amq_castile_buying_block_sizes() {
	return ['100' => '100 ML', '250' => '250 ML', '500' => '500 ML'];
}

add_action('elementor/widgets/register', function ($widgets_manager) {
	// Class file lives in a subdirectory (amq-castile/), not this file's own
	// directory: WordPress's mu-plugins loader auto-includes every top-level
	// .php file in wp-content/mu-plugins/ unconditionally at boot, before
	// Elementor's classes exist — a class file extending \Elementor\Widget_Base
	// sitting at the top level fatals immediately at that phase. Subdirectory
	// files are never auto-loaded, so this require_once (deferred until
	// Elementor actually fires this action) is the only place it loads.
	require_once __DIR__ . '/amq-castile/class-amq-castile-buying-block-widget.php';
	$widgets_manager->register(new Amq_Castile_Buying_Block_Widget());
});

/**
 * CSS/JS for this widget, conditional on the CASTILE page only — same
 * is_page('castile') gate and pattern as amouriq-castile-page-assets.php,
 * kept as a separate enqueue here (rather than folded into that file) since
 * this pair of assets belongs to one widget, not the page's content
 * sections generally.
 *
 * Asset location and the dropped 'amq-woocommerce' dependency: same reasons
 * as amouriq-castile-page-assets.php — see
 * docs/adr/0005-castile-assets-live-under-the-active-theme.md.
 */
add_action('wp_enqueue_scripts', function () {
	if (!is_page('castile')) return;

	$css_path = get_stylesheet_directory() . '/assets/css/amq-castile-buying-block.css';
	if (file_exists($css_path)) {
		wp_enqueue_style(
			'amq-castile-buying-block',
			get_stylesheet_directory_uri() . '/assets/css/amq-castile-buying-block.css',
			[],
			filemtime($css_path)
		);
	}

	$js_path = get_stylesheet_directory() . '/assets/js/amq-castile-buying-block.js';
	if (file_exists($js_path)) {
		wp_enqueue_script(
			'amq-castile-buying-block',
			get_stylesheet_directory_uri() . '/assets/js/amq-castile-buying-block.js',
			[],
			filemtime($js_path),
			true
		);
		wp_localize_script('amq-castile-buying-block', 'amqCastileBuyingBlock', [
			'ajaxUrl' => admin_url('admin-ajax.php'),
		]);
	}
}, 20);
