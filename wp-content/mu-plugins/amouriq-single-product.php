<?php
/**
 * Single Product page support: the new product-page-only custom fields (Key
 * Ingredients, How to Store, full Ingredients list, How to Use — none of
 * these existed anywhere on this site before) and the conditional CSS/JS
 * load for the redesigned single-product template
 * (wp-content/themes/hello-elementor-child/woocommerce/single-product.php).
 *
 * Modeled on amouriq-journal.php's "Featured on Journal" metabox pattern:
 * add_meta_box() + save_post_{type}, nonce-guarded.
 */

if (!defined('ABSPATH')) exit;

// --- Product page content fields on the product edit screen ---
add_action('add_meta_boxes', function () {
	add_meta_box(
		'amq_product_page_content_box',
		__('AMOURIQ — Product Page Content', 'amouriq'),
		function ($post) {
			wp_nonce_field('amq_product_page_content_save', 'amq_product_page_content_nonce');
			$key_ingredients = get_post_meta($post->ID, '_amq_key_ingredients', true);
			$how_to_store = get_post_meta($post->ID, '_amq_how_to_store', true);
			$ingredients_full = get_post_meta($post->ID, '_amq_ingredients_full', true);
			$how_to_use = get_post_meta($post->ID, '_amq_how_to_use', true);
			?>
			<p>
				<label for="amq_key_ingredients"><strong><?php esc_html_e('Key Ingredients', 'amouriq'); ?></strong> — <?php esc_html_e('one per line. Shown right under the short description, above quantity/Add to Bag.', 'amouriq'); ?></label><br>
				<textarea id="amq_key_ingredients" name="amq_key_ingredients" rows="4" style="width:100%;"><?php echo esc_textarea($key_ingredients); ?></textarea>
			</p>
			<p>
				<label for="amq_how_to_store"><strong><?php esc_html_e('How to Store', 'amouriq'); ?></strong></label><br>
				<textarea id="amq_how_to_store" name="amq_how_to_store" rows="3" style="width:100%;"><?php echo esc_textarea($how_to_store); ?></textarea>
			</p>
			<p>
				<label for="amq_ingredients_full"><strong><?php esc_html_e('Full Ingredients List', 'amouriq'); ?></strong> — <?php esc_html_e('one per line (the expandable "Ingredients" section, separate from Key Ingredients above).', 'amouriq'); ?></label><br>
				<textarea id="amq_ingredients_full" name="amq_ingredients_full" rows="4" style="width:100%;"><?php echo esc_textarea($ingredients_full); ?></textarea>
			</p>
			<p>
				<label for="amq_how_to_use"><strong><?php esc_html_e('How to Use', 'amouriq'); ?></strong></label><br>
				<textarea id="amq_how_to_use" name="amq_how_to_use" rows="3" style="width:100%;"><?php echo esc_textarea($how_to_use); ?></textarea>
			</p>
			<p><em><?php esc_html_e('Any field left empty shows a "coming soon" placeholder on the product page instead of being hidden.', 'amouriq'); ?></em></p>
			<?php
		},
		'product',
		'normal',
		'default'
	);
});

add_action('save_post_product', function ($post_id) {
	if (!isset($_POST['amq_product_page_content_nonce']) || !wp_verify_nonce($_POST['amq_product_page_content_nonce'], 'amq_product_page_content_save')) return;
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (!current_user_can('edit_post', $post_id)) return;

	$fields = ['amq_key_ingredients' => '_amq_key_ingredients', 'amq_how_to_store' => '_amq_how_to_store', 'amq_ingredients_full' => '_amq_ingredients_full', 'amq_how_to_use' => '_amq_how_to_use'];
	foreach ($fields as $post_key => $meta_key) {
		$value = isset($_POST[$post_key]) ? sanitize_textarea_field(wp_unslash($_POST[$post_key])) : '';
		update_post_meta($post_id, $meta_key, $value);
	}
});

/**
 * @param int $product_id
 * @return string[] Trimmed, non-empty ingredient lines (empty array if none set).
 */
function amq_get_key_ingredients($product_id) {
	return amq_get_lines(get_post_meta($product_id, '_amq_key_ingredients', true));
}

/**
 * @param int $product_id
 * @return string[] Trimmed, non-empty lines of the full ingredients list.
 */
function amq_get_full_ingredients($product_id) {
	return amq_get_lines(get_post_meta($product_id, '_amq_ingredients_full', true));
}

function amq_get_how_to_store($product_id) {
	return trim(get_post_meta($product_id, '_amq_how_to_store', true));
}

function amq_get_how_to_use($product_id) {
	return trim(get_post_meta($product_id, '_amq_how_to_use', true));
}

/**
 * @param string $raw
 * @return string[]
 */
function amq_get_lines($raw) {
	if (!$raw) return [];
	$lines = preg_split('/\r\n|\r|\n/', $raw);
	$lines = array_map('trim', $lines);
	return array_values(array_filter($lines));
}

/**
 * Variable products only: variations sorted by leading number in their
 * attribute label (e.g. "30 ml" before "100 ml"), same approach used for
 * the [amq_bestsellers] size dropdown (amouriq-bestsellers-carousel.php),
 * kept as a separate small helper here rather than sharing that inline
 * logic, since this page's square-box selector needs its own shape (label +
 * variation_id + price_html per box, plus a resolved default).
 *
 * @param WC_Product $product
 * @return array[] Each: ['variation_id'=>int, 'label'=>string, 'price_html'=>string]
 */
function amq_get_sorted_variations($product) {
	if (!$product->is_type('variable')) return [];

	$raw_variations = $product->get_available_variations();
	$parsed = [];
	foreach ($raw_variations as $v) {
		$attrs = $v['attributes'];
		$label = !empty($attrs) ? reset($attrs) : '';
		$sort_key = preg_match('/([\d.]+)/', $label, $m) ? (float) $m[1] : $v['display_price'];
		$parsed[] = [
			'variation_id' => $v['variation_id'],
			'label' => $label,
			'price_html' => $v['price_html'],
			'sort_key' => $sort_key,
		];
	}
	usort($parsed, function ($a, $b) { return $a['sort_key'] <=> $b['sort_key']; });

	return $parsed;
}

// --- Conditional CSS/JS, only on real single product pages ---
add_action('wp_enqueue_scripts', function () {
	if (!function_exists('is_product') || !is_product()) return;

	$css_path = get_stylesheet_directory() . '/assets/css/amq-single-product.css';
	if (file_exists($css_path)) {
		wp_enqueue_style(
			'amq-single-product',
			get_stylesheet_directory_uri() . '/assets/css/amq-single-product.css',
			['amq-woocommerce'],
			filemtime($css_path)
		);
	}

	$js_path = get_stylesheet_directory() . '/assets/js/amq-single-product.js';
	if (file_exists($js_path)) {
		wp_enqueue_script(
			'amq-single-product',
			get_stylesheet_directory_uri() . '/assets/js/amq-single-product.js',
			[],
			filemtime($js_path),
			true
		);
		wp_localize_script('amq-single-product', 'amqSingleProduct', [
			'ajaxUrl' => admin_url('admin-ajax.php'),
		]);
	}
}, 20);

/**
 * --- Product page style controls (Customizer) ---
 *
 * Owner decision 2026-07-27 (see memo/tbc.md → "Open questions", option 3):
 * the Single Product page stays plain PHP (no Elementor Pro / third-party
 * builder plugin), but every color/font/spacing/button knob the owner asked
 * for is exposed here as a normal WordPress Customizer control instead of
 * true Elementor drag-and-drop. Each control writes one CSS custom property
 * (--amq-pdp-*), consumed by assets/css/amq-single-product.css — see that
 * file's header comment for the full variable list. Adding a new control
 * here is: one entry in amq_pdp_style_controls() + reference the same
 * --amq-pdp-* var name in the CSS.
 */

/**
 * Product Name / Price / Short Description / Ingredient Pill share this
 * same set of 12 fields (2026-07-27 owner request, extended same day to
 * also cover Ingredient Pill "like Short Description"): italic toggle,
 * line-height, letter-spacing, word-spacing, and margin/padding on all 4
 * sides independently — all using the number+unit-dropdown+stepper widget
 * (AMQ_Length_Control) except italic (a plain Normal/Italic dropdown, same
 * pattern as font weight). Kept as one helper instead of writing dozens of
 * near-identical array entries by hand.
 *
 * @param string $key_prefix            e.g. 'name', 'price', 'short_desc', 'pill'.
 * @param string $label_prefix          e.g. 'Product Name', 'Ingredient Pill'.
 * @param string $section               Customizer section slug (e.g. 'name_price', 'pills').
 * @param string $margin_bottom_default Preserves this element's original
 *   hardcoded spacing so defaults don't shift the layout.
 * @param string $line_height_default   Unitless multiplier or em value;
 *   approximates each element's previous unset (browser-default) line-height
 *   unless it already hardcoded one (Short Description: 1.6em).
 * @param array|null $padding_defaults  ['top'=>..,'right'=>..,'bottom'=>..,'left'=>..],
 *   defaults to all '0px' if omitted — pass explicit values for elements
 *   that already had non-zero/non-uniform padding (e.g. Ingredient Pill's
 *   existing 7px/14px pill shape).
 * @return array[]
 */
function amq_pdp_typography_spacing_controls($key_prefix, $label_prefix, $section, $margin_bottom_default, $line_height_default = '1.2', $padding_defaults = null) {
	if ($padding_defaults === null) {
		$padding_defaults = ['top' => '0px', 'right' => '0px', 'bottom' => '0px', 'left' => '0px'];
	}

	$var_prefix = '--amq-pdp-' . str_replace('_', '-', $key_prefix);
	$controls = [
		['key' => "{$key_prefix}_font_style", 'section' => $section, 'label' => sprintf(__('%s — Style', 'amouriq'), $label_prefix), 'type' => 'style', 'var' => "{$var_prefix}-font-style", 'default' => 'normal'],
		['key' => "{$key_prefix}_line_height", 'section' => $section, 'label' => sprintf(__('%s — Line Height', 'amouriq'), $label_prefix), 'type' => 'stepper', 'var' => "{$var_prefix}-line-height", 'default' => $line_height_default, 'units' => ['', 'em']],
		['key' => "{$key_prefix}_letter_spacing", 'section' => $section, 'label' => sprintf(__('%s — Letter Spacing', 'amouriq'), $label_prefix), 'type' => 'stepper', 'var' => "{$var_prefix}-letter-spacing", 'default' => '0em', 'units' => ['px', 'em'], 'allow_negative' => true],
		['key' => "{$key_prefix}_word_spacing", 'section' => $section, 'label' => sprintf(__('%s — Word Spacing', 'amouriq'), $label_prefix), 'type' => 'stepper', 'var' => "{$var_prefix}-word-spacing", 'default' => '0em', 'units' => ['px', 'em'], 'allow_negative' => true],
	];

	$margin_defaults = ['top' => '0px', 'right' => '0px', 'bottom' => $margin_bottom_default, 'left' => '0px'];
	foreach ($margin_defaults as $side => $default) {
		$controls[] = ['key' => "{$key_prefix}_margin_{$side}", 'section' => $section, 'label' => sprintf(__('%1$s — Margin %2$s', 'amouriq'), $label_prefix, ucfirst($side)), 'type' => 'stepper', 'var' => "{$var_prefix}-margin-{$side}", 'default' => $default, 'units' => ['px', 'em'], 'allow_negative' => true];
	}

	foreach (['top', 'right', 'bottom', 'left'] as $side) {
		$controls[] = ['key' => "{$key_prefix}_padding_{$side}", 'section' => $section, 'label' => sprintf(__('%1$s — Padding %2$s', 'amouriq'), $label_prefix, ucfirst($side)), 'type' => 'stepper', 'var' => "{$var_prefix}-padding-{$side}", 'default' => $padding_defaults[$side], 'units' => ['px', 'em']];
	}

	return $controls;
}

/**
 * @return array[] Each: key (used for both the setting id and the CSS var
 *   suffix), section (Customizer section slug suffix), label, type
 *   (color|color_optional|length|weight|font_size|stepper|style), css var
 *   name, default value. 'stepper' entries also carry 'units' (array) and
 *   optionally 'allow_negative' (bool, default false).
 */
function amq_pdp_style_controls() {
	return [
		// Layout & Spacing
		['key' => 'padding_y', 'section' => 'layout', 'label' => __('Hero Section — Vertical Padding', 'amouriq'), 'type' => 'length', 'var' => '--amq-pdp-padding-y', 'default' => '48px'],
		['key' => 'padding_x', 'section' => 'layout', 'label' => __('Hero Section — Horizontal Padding', 'amouriq'), 'type' => 'length', 'var' => '--amq-pdp-padding-x', 'default' => '40px'],
		['key' => 'gap', 'section' => 'layout', 'label' => __('Gallery ↔ Info Column Gap', 'amouriq'), 'type' => 'length', 'var' => '--amq-pdp-gap', 'default' => '56px'],
		['key' => 'lower_max_width', 'section' => 'layout', 'label' => __('Description Section — Max Width', 'amouriq'), 'type' => 'length', 'var' => '--amq-pdp-lower-max-width', 'default' => '800px'],

		// Section Backgrounds
		['key' => 'hero_bg', 'section' => 'backgrounds', 'label' => __('Hero Section Background', 'amouriq'), 'type' => 'color_optional', 'var' => '--amq-pdp-hero-bg', 'default' => ''],
		['key' => 'lower_bg', 'section' => 'backgrounds', 'label' => __('Description Section Background', 'amouriq'), 'type' => 'color_optional', 'var' => '--amq-pdp-lower-bg', 'default' => ''],
		['key' => 'related_bg', 'section' => 'backgrounds', 'label' => __('"You Might Also Like" Background', 'amouriq'), 'type' => 'color_optional', 'var' => '--amq-pdp-related-bg', 'default' => ''],

		// Product Gallery
		['key' => 'image_bg', 'section' => 'gallery', 'label' => __('Main Image Background', 'amouriq'), 'type' => 'color', 'var' => '--amq-pdp-image-bg', 'default' => '#FAF7F0'],
		['key' => 'thumb_size', 'section' => 'gallery', 'label' => __('Thumbnail Size', 'amouriq'), 'type' => 'length', 'var' => '--amq-pdp-thumb-size', 'default' => '72px'],
		['key' => 'thumb_bg', 'section' => 'gallery', 'label' => __('Thumbnail Background', 'amouriq'), 'type' => 'color', 'var' => '--amq-pdp-thumb-bg', 'default' => '#FAF7F0'],
		['key' => 'thumb_border', 'section' => 'gallery', 'label' => __('Thumbnail Border Color', 'amouriq'), 'type' => 'color', 'var' => '--amq-pdp-thumb-border', 'default' => '#E8DED2'],
		['key' => 'thumb_active_border', 'section' => 'gallery', 'label' => __('Active Thumbnail Border Color', 'amouriq'), 'type' => 'color', 'var' => '--amq-pdp-thumb-active-border', 'default' => '#242622'],

		// Product Name & Price
		['key' => 'name_size', 'section' => 'name_price', 'label' => __('Product Name — Font Size', 'amouriq'), 'type' => 'font_size', 'var' => '--amq-pdp-name-size', 'default' => '32px'],
		['key' => 'name_weight', 'section' => 'name_price', 'label' => __('Product Name — Font Weight', 'amouriq'), 'type' => 'weight', 'var' => '--amq-pdp-name-weight', 'default' => '600'],
		['key' => 'name_color', 'section' => 'name_price', 'label' => __('Product Name — Color', 'amouriq'), 'type' => 'color', 'var' => '--amq-pdp-name-color', 'default' => '#242622'],
		...amq_pdp_typography_spacing_controls('name', __('Product Name', 'amouriq'), 'name_price', '12px'),

		['key' => 'price_size', 'section' => 'name_price', 'label' => __('Price — Font Size', 'amouriq'), 'type' => 'font_size', 'var' => '--amq-pdp-price-size', 'default' => '22px'],
		['key' => 'price_weight', 'section' => 'name_price', 'label' => __('Price — Font Weight', 'amouriq'), 'type' => 'weight', 'var' => '--amq-pdp-price-weight', 'default' => '600'],
		['key' => 'price_color', 'section' => 'name_price', 'label' => __('Price — Color', 'amouriq'), 'type' => 'color', 'var' => '--amq-pdp-price-color', 'default' => '#242622'],
		...amq_pdp_typography_spacing_controls('price', __('Price', 'amouriq'), 'name_price', '24px'),

		['key' => 'short_desc_size', 'section' => 'name_price', 'label' => __('Short Description — Font Size', 'amouriq'), 'type' => 'font_size', 'var' => '--amq-pdp-short-desc-size', 'default' => '16px'],
		['key' => 'short_desc_color', 'section' => 'name_price', 'label' => __('Short Description — Color', 'amouriq'), 'type' => 'color', 'var' => '--amq-pdp-short-desc-color', 'default' => '#62665E'],
		...amq_pdp_typography_spacing_controls('short_desc', __('Short Description', 'amouriq'), 'name_price', '28px', '1.6'),

		// Section Headings ("Key Ingredients" / "Size" / "Description" labels)
		['key' => 'heading_size', 'section' => 'headings', 'label' => __('Section Heading — Font Size', 'amouriq'), 'type' => 'font_size', 'var' => '--amq-pdp-heading-size', 'default' => '13px'],
		['key' => 'heading_weight', 'section' => 'headings', 'label' => __('Section Heading — Font Weight', 'amouriq'), 'type' => 'weight', 'var' => '--amq-pdp-heading-weight', 'default' => '700'],
		['key' => 'heading_color', 'section' => 'headings', 'label' => __('Section Heading — Color', 'amouriq'), 'type' => 'color', 'var' => '--amq-pdp-heading-color', 'default' => '#242622'],
		['key' => 'heading_letter_spacing', 'section' => 'headings', 'label' => __('Section Heading — Letter Spacing', 'amouriq'), 'type' => 'stepper', 'var' => '--amq-pdp-heading-letter-spacing', 'default' => '.08em', 'units' => ['px', 'em'], 'allow_negative' => true],
		['key' => 'heading_word_spacing', 'section' => 'headings', 'label' => __('Section Heading — Word Spacing', 'amouriq'), 'type' => 'stepper', 'var' => '--amq-pdp-heading-word-spacing', 'default' => '0em', 'units' => ['px', 'em'], 'allow_negative' => true],

		// Ingredient Pills
		['key' => 'pill_size', 'section' => 'pills', 'label' => __('Ingredient Pill — Font Size', 'amouriq'), 'type' => 'font_size', 'var' => '--amq-pdp-pill-size', 'default' => '13px'],
		['key' => 'pill_bg', 'section' => 'pills', 'label' => __('Pill Background', 'amouriq'), 'type' => 'color', 'var' => '--amq-pdp-pill-bg', 'default' => '#FAF7F0'],
		['key' => 'pill_border', 'section' => 'pills', 'label' => __('Pill Border Color', 'amouriq'), 'type' => 'color', 'var' => '--amq-pdp-pill-border', 'default' => '#E8DED2'],
		['key' => 'pill_color', 'section' => 'pills', 'label' => __('Pill Text Color', 'amouriq'), 'type' => 'color', 'var' => '--amq-pdp-pill-color', 'default' => '#242622'],
		...amq_pdp_typography_spacing_controls('pill', __('Ingredient Pill', 'amouriq'), 'pills', '0px', '1.4', ['top' => '7px', 'right' => '14px', 'bottom' => '7px', 'left' => '14px']),

		// Size Selector
		['key' => 'sizebox_size', 'section' => 'sizebox', 'label' => __('Size Box — Width/Height', 'amouriq'), 'type' => 'length', 'var' => '--amq-pdp-sizebox-size', 'default' => '64px'],
		['key' => 'sizebox_bg', 'section' => 'sizebox', 'label' => __('Size Box — Background', 'amouriq'), 'type' => 'color', 'var' => '--amq-pdp-sizebox-bg', 'default' => '#FFFDF8'],
		['key' => 'sizebox_border', 'section' => 'sizebox', 'label' => __('Size Box — Border Color', 'amouriq'), 'type' => 'color', 'var' => '--amq-pdp-sizebox-border', 'default' => '#E8DED2'],
		['key' => 'sizebox_color', 'section' => 'sizebox', 'label' => __('Size Box — Text Color', 'amouriq'), 'type' => 'color', 'var' => '--amq-pdp-sizebox-color', 'default' => '#242622'],
		['key' => 'sizebox_selected_border', 'section' => 'sizebox', 'label' => __('Selected Size Box — Border Color', 'amouriq'), 'type' => 'color', 'var' => '--amq-pdp-sizebox-selected-border', 'default' => '#D8A94E'],
		['key' => 'sizebox_selected_bg', 'section' => 'sizebox', 'label' => __('Selected Size Box — Background', 'amouriq'), 'type' => 'color', 'var' => '--amq-pdp-sizebox-selected-bg', 'default' => '#FAF7F0'],

		// Quantity Box
		['key' => 'qty_border', 'section' => 'qty', 'label' => __('Quantity Box — Border Color', 'amouriq'), 'type' => 'color', 'var' => '--amq-pdp-qty-border', 'default' => '#E8DED2'],
		['key' => 'qty_bg', 'section' => 'qty', 'label' => __('Quantity Box — Background', 'amouriq'), 'type' => 'color', 'var' => '--amq-pdp-qty-bg', 'default' => '#FFFDF8'],
		['key' => 'qty_color', 'section' => 'qty', 'label' => __('Quantity Box — Text Color', 'amouriq'), 'type' => 'color', 'var' => '--amq-pdp-qty-color', 'default' => '#242622'],

		// Add to Bag Button
		['key' => 'btn_bg', 'section' => 'button', 'label' => __('Button — Background', 'amouriq'), 'type' => 'color', 'var' => '--amq-pdp-btn-bg', 'default' => '#2D3536'],
		['key' => 'btn_hover_bg', 'section' => 'button', 'label' => __('Button — Hover Background', 'amouriq'), 'type' => 'color', 'var' => '--amq-pdp-btn-hover-bg', 'default' => '#40453d'],
		['key' => 'btn_added_bg', 'section' => 'button', 'label' => __('Button — "Added" State Background', 'amouriq'), 'type' => 'color', 'var' => '--amq-pdp-btn-added-bg', 'default' => '#5D5F4B'],
		['key' => 'btn_color', 'section' => 'button', 'label' => __('Button — Text Color', 'amouriq'), 'type' => 'color', 'var' => '--amq-pdp-btn-color', 'default' => '#FFFDF8'],
		['key' => 'btn_radius', 'section' => 'button', 'label' => __('Button — Border Radius', 'amouriq'), 'type' => 'length', 'var' => '--amq-pdp-btn-radius', 'default' => '0px'],
		['key' => 'btn_font_size', 'section' => 'button', 'label' => __('Button — Font Size', 'amouriq'), 'type' => 'font_size', 'var' => '--amq-pdp-btn-font-size', 'default' => '13px'],
		['key' => 'btn_min_height', 'section' => 'button', 'label' => __('Button — Min Height', 'amouriq'), 'type' => 'length', 'var' => '--amq-pdp-btn-min-height', 'default' => '50px'],

		// Description & Details Text
		['key' => 'fulldesc_size', 'section' => 'lower', 'label' => __('Full Description — Font Size', 'amouriq'), 'type' => 'font_size', 'var' => '--amq-pdp-fulldesc-size', 'default' => '16px'],
		['key' => 'fulldesc_color', 'section' => 'lower', 'label' => __('Full Description — Color', 'amouriq'), 'type' => 'color', 'var' => '--amq-pdp-fulldesc-color', 'default' => '#62665E'],
		['key' => 'inline_heading_color', 'section' => 'lower', 'label' => __('"How to Store" Heading — Color', 'amouriq'), 'type' => 'color', 'var' => '--amq-pdp-inline-heading-color', 'default' => '#242622'],
		['key' => 'lowerbody_size', 'section' => 'lower', 'label' => __('Detail Text — Font Size', 'amouriq'), 'type' => 'font_size', 'var' => '--amq-pdp-lowerbody-size', 'default' => '15px'],
		['key' => 'lowerbody_color', 'section' => 'lower', 'label' => __('Detail Text — Color', 'amouriq'), 'type' => 'color', 'var' => '--amq-pdp-lowerbody-color', 'default' => '#62665E'],
		['key' => 'divider_color', 'section' => 'lower', 'label' => __('Divider Line Color', 'amouriq'), 'type' => 'color', 'var' => '--amq-pdp-divider-color', 'default' => '#E8DED2'],

		// Accordion (Ingredients / How to Use)
		['key' => 'accordion_toggle_color', 'section' => 'accordion', 'label' => __('Accordion Title — Color', 'amouriq'), 'type' => 'color', 'var' => '--amq-pdp-accordion-toggle-color', 'default' => '#242622'],
		['key' => 'accordion_toggle_size', 'section' => 'accordion', 'label' => __('Accordion Title — Font Size', 'amouriq'), 'type' => 'font_size', 'var' => '--amq-pdp-accordion-toggle-size', 'default' => '18px'],
		['key' => 'accordion_toggle_weight', 'section' => 'accordion', 'label' => __('Accordion Title — Font Weight', 'amouriq'), 'type' => 'weight', 'var' => '--amq-pdp-accordion-toggle-weight', 'default' => '600'],

		// "You Might Also Like" Heading
		['key' => 'related_heading_size', 'section' => 'related', 'label' => __('"You Might Also Like" — Font Size', 'amouriq'), 'type' => 'font_size', 'var' => '--amq-pdp-related-heading-size', 'default' => '22px'],
		['key' => 'related_heading_color', 'section' => 'related', 'label' => __('"You Might Also Like" — Color', 'amouriq'), 'type' => 'color', 'var' => '--amq-pdp-related-heading-color', 'default' => '#242622'],
	];
}

function amq_pdp_sanitize_hex($value) {
	$hex = sanitize_hex_color(trim((string) $value));
	return $hex !== null ? $hex : '';
}

function amq_pdp_sanitize_color_optional($value) {
	$value = trim((string) $value);
	if ($value === '') return '';
	$hex = sanitize_hex_color($value);
	return $hex !== null ? $hex : '';
}

function amq_pdp_sanitize_length($value) {
	$value = trim((string) $value);
	return preg_match('/^-?(\d+\.?\d*|\.\d+)(px|em|rem|%)$/', $value) ? $value : '';
}

/**
 * Font-size fields only: positive number + px|em, matching the two units
 * AMQ_Length_Control's dropdown offers when used for font sizes (no
 * negative sizes, no rem/% here — those stay available on the plain
 * 'length' fields like letter-spacing/padding via amq_pdp_sanitize_length
 * above).
 */
function amq_pdp_sanitize_font_size($value) {
	$value = trim((string) $value);
	return preg_match('/^(\d+\.?\d*|\.\d+)(px|em)$/', $value) ? $value : '';
}

/**
 * Generic sanitizer behind every 'stepper' control (AMQ_Length_Control):
 * a number + one of $units, where '' in $units means "unitless is also
 * allowed" (used for line-height). $allow_negative permits a leading '-'
 * (needed for margin/letter-spacing/word-spacing, which can sensibly go
 * negative; not for padding or line-height).
 *
 * @param string $value
 * @param array  $units e.g. ['px','em'] or ['', 'em'].
 * @param bool   $allow_negative
 * @return string The value if valid, '' otherwise (Customizer keeps the
 *   previous saved value when sanitize returns '').
 */
function amq_pdp_sanitize_stepper($value, $units, $allow_negative = false) {
	$value = trim((string) $value);
	$sign = $allow_negative ? '-?' : '';
	$named_units = array_filter($units, function ($u) { return $u !== ''; });
	$unit_group = implode('|', array_map(function ($u) { return preg_quote($u, '/'); }, $named_units));
	$unit_optional = in_array('', $units, true);
	$pattern = '/^' . $sign . '(\d+\.?\d*|\.\d+)(' . $unit_group . ')' . ($unit_optional ? '?' : '') . '$/';
	return preg_match($pattern, $value) ? $value : '';
}

function amq_pdp_sanitize_weight($value) {
	$allowed = ['300', '400', '500', '600', '700', '800'];
	return in_array((string) $value, $allowed, true) ? (string) $value : '600';
}

function amq_pdp_sanitize_font_style($value) {
	$allowed = ['normal', 'italic'];
	return in_array((string) $value, $allowed, true) ? (string) $value : 'normal';
}

add_action('customize_register', function ($wp_customize) {
	/**
	 * A number-spinner input + a unit <select> (units configurable per
	 * instance, default px/em), combined into one "<number><unit>" string
	 * setting (e.g. "26px", or bare "1.6" when '' is one of the allowed
	 * units — used for line-height). Backs every 'font_size' AND 'stepper'
	 * control (margin/padding/letter-spacing/word-spacing/line-height,
	 * 2026-07-27). Must be defined here (inside customize_register), not at
	 * file top level: WP_Customize_Control's class file hasn't been
	 * required yet when mu-plugins load (mu-plugins run before
	 * wp-admin/customize.php ever does), so referencing it any earlier
	 * would fatal on every request, not just Customizer ones.
	 */
	if (!class_exists('AMQ_Length_Control')) {
		class AMQ_Length_Control extends WP_Customize_Control {
			public $type = 'amq_length';
			public $units = ['px', 'em'];
			public $allow_negative = false;

			public function enqueue() {
				$js_path = get_stylesheet_directory() . '/assets/js/amq-pdp-customizer-controls.js';
				wp_enqueue_script(
					'amq-pdp-customizer-controls',
					get_stylesheet_directory_uri() . '/assets/js/amq-pdp-customizer-controls.js',
					['jquery', 'customize-controls'],
					file_exists($js_path) ? filemtime($js_path) : false,
					true
				);

				$css_path = get_stylesheet_directory() . '/assets/css/amq-pdp-customizer-controls.css';
				wp_enqueue_style(
					'amq-pdp-customizer-controls',
					get_stylesheet_directory_uri() . '/assets/css/amq-pdp-customizer-controls.css',
					[],
					file_exists($css_path) ? filemtime($css_path) : false
				);
			}

			public function render_content() {
				$value = (string) $this->value();
				$named_units = array_filter($this->units, function ($u) { return $u !== ''; });
				$unit_group = implode('|', array_map(function ($u) { return preg_quote($u, '/'); }, $named_units));
				$unit_optional = in_array('', $this->units, true);
				$pattern = '/^(-?\d+\.?\d*|-?\.\d+)(' . $unit_group . ')' . ($unit_optional ? '?' : '') . '$/';

				if (!preg_match($pattern, $value, $m)) {
					$m = [null, $this->units[0] === '' ? '1' : '16', $this->units[0]];
				}
				$number = $m[1];
				$unit = isset($m[2]) && $m[2] !== '' ? $m[2] : $this->units[0];
				$step = in_array('', $this->units, true) ? '0.1' : '1';
				?>
				<label class="amq-length-control">
					<span class="customize-control-title"><?php echo esc_html($this->label); ?></span>
					<?php if (!empty($this->description)) : ?>
						<span class="description customize-control-description"><?php echo esc_html($this->description); ?></span>
					<?php endif; ?>
					<span class="amq-length-control__row">
						<input type="number" step="<?php echo esc_attr($step); ?>" <?php echo $this->allow_negative ? '' : 'min="0"'; ?> class="amq-length-control__number" value="<?php echo esc_attr($number); ?>">
						<select class="amq-length-control__unit">
							<?php foreach ($this->units as $u) : ?>
								<option value="<?php echo esc_attr($u); ?>" <?php selected($unit, $u); ?>><?php echo esc_html($u === '' ? __('none (×)', 'amouriq') : $u); ?></option>
							<?php endforeach; ?>
						</select>
						<input type="hidden" <?php $this->link(); ?> class="amq-length-control__combined" value="<?php echo esc_attr($value); ?>">
					</span>
				</label>
				<?php
			}
		}
	}

	$wp_customize->add_panel('amq_pdp_panel', [
		'title' => __('AMOURIQ — Product Page', 'amouriq'),
		'description' => __('Colors, fonts, spacing, and button style for the Single Product page (woocommerce/single-product.php) — this page is plain PHP, not Elementor, so these controls are its equivalent of Elementor\'s Style/Advanced tabs.', 'amouriq'),
		'priority' => 160,
	]);

	$sections = [
		'layout' => __('Layout & Spacing', 'amouriq'),
		'backgrounds' => __('Section Backgrounds', 'amouriq'),
		'gallery' => __('Product Gallery', 'amouriq'),
		'name_price' => __('Product Name & Price', 'amouriq'),
		'headings' => __('Section Headings', 'amouriq'),
		'pills' => __('Ingredient Pills', 'amouriq'),
		'sizebox' => __('Size Selector', 'amouriq'),
		'qty' => __('Quantity Box', 'amouriq'),
		'button' => __('Add to Bag Button', 'amouriq'),
		'lower' => __('Description & Details Text', 'amouriq'),
		'accordion' => __('Accordion (Ingredients / How to Use)', 'amouriq'),
		'related' => __('"You Might Also Like" Heading', 'amouriq'),
	];
	foreach ($sections as $slug => $title) {
		$wp_customize->add_section('amq_pdp_' . $slug, [
			'title' => $title,
			'panel' => 'amq_pdp_panel',
		]);
	}

	$weight_choices = [
		'300' => __('300 — Light', 'amouriq'),
		'400' => __('400 — Regular', 'amouriq'),
		'500' => __('500 — Medium', 'amouriq'),
		'600' => __('600 — Semibold', 'amouriq'),
		'700' => __('700 — Bold', 'amouriq'),
		'800' => __('800 — Extrabold', 'amouriq'),
	];

	foreach (amq_pdp_style_controls() as $control) {
		$setting_id = 'amq_pdp_style_' . $control['key'];
		$section_id = 'amq_pdp_' . $control['section'];

		switch ($control['type']) {
			case 'color_optional':
				$sanitize = 'amq_pdp_sanitize_color_optional';
				break;
			case 'weight':
				$sanitize = 'amq_pdp_sanitize_weight';
				break;
			case 'style':
				$sanitize = 'amq_pdp_sanitize_font_style';
				break;
			case 'font_size':
				$sanitize = 'amq_pdp_sanitize_font_size';
				break;
			case 'stepper':
				$units = isset($control['units']) ? $control['units'] : ['px', 'em'];
				$allow_negative = !empty($control['allow_negative']);
				$sanitize = function ($value) use ($units, $allow_negative) {
					return amq_pdp_sanitize_stepper($value, $units, $allow_negative);
				};
				break;
			case 'length':
				$sanitize = 'amq_pdp_sanitize_length';
				break;
			default:
				$sanitize = 'amq_pdp_sanitize_hex';
		}

		$wp_customize->add_setting($setting_id, [
			'default' => $control['default'],
			'sanitize_callback' => $sanitize,
			'transport' => 'refresh',
		]);

		if ($control['type'] === 'color' || $control['type'] === 'color_optional') {
			$wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, $setting_id, [
				'label' => $control['label'],
				'section' => $section_id,
			]));
		} elseif ($control['type'] === 'weight') {
			$wp_customize->add_control($setting_id, [
				'label' => $control['label'],
				'section' => $section_id,
				'type' => 'select',
				'choices' => $weight_choices,
			]);
		} elseif ($control['type'] === 'style') {
			$wp_customize->add_control($setting_id, [
				'label' => $control['label'],
				'section' => $section_id,
				'type' => 'select',
				'choices' => ['normal' => __('Normal', 'amouriq'), 'italic' => __('Italic', 'amouriq')],
			]);
		} elseif ($control['type'] === 'font_size' || $control['type'] === 'stepper') {
			$wp_customize->add_control(new AMQ_Length_Control($wp_customize, $setting_id, [
				'label' => $control['label'],
				'section' => $section_id,
				'units' => isset($control['units']) ? $control['units'] : ['px', 'em'],
				'allow_negative' => !empty($control['allow_negative']),
			]));
		} else {
			$wp_customize->add_control($setting_id, [
				'label' => $control['label'],
				'section' => $section_id,
				'type' => 'text',
				'description' => __('e.g. 16px, 1.2em, .08em', 'amouriq'),
			]);
		}
	}
});

add_action('wp_head', function () {
	if (!function_exists('is_product') || !is_product()) return;

	$lines = '';
	foreach (amq_pdp_style_controls() as $control) {
		$value = get_theme_mod('amq_pdp_style_' . $control['key'], $control['default']);
		if ($control['type'] === 'color_optional' && $value === '') {
			$value = 'transparent';
		}
		if ($value === '') continue;
		$lines .= esc_attr($control['var']) . ':' . esc_attr($value) . ';';
	}
	if ($lines === '') return;

	echo '<style id="amq-pdp-custom-style">:root{' . $lines . '}</style>' . "\n";
}, 25);
