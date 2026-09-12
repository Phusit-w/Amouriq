<?php
/**
 * CASTILE sales page: resolves a (scent, size) selection from the buying
 * block (see .scratch/castile-sales-page/spec.md, Section 19) to the real
 * WooCommerce product/variation it refers to. The three scents are separate
 * products, not one product with a scent attribute — see CONTEXT.md
 * ("Scent" vs "Size") — so this is a product lookup keyed by scent, then a
 * variation lookup within it keyed by size.
 *
 * Covered by tests/CastileResolverTest.php (run via PHPUnit — see
 * tests/bootstrap.php for how wc_get_product() is stubbed there).
 */

if (!defined('ABSPATH')) exit;

/**
 * Scent slug => real WooCommerce product id.
 *
 * @return array<string, int>
 */
function amq_castile_products() {
	return [
		'lavender' => 2278,
		'rosemary' => 2285,
		'rose-geranium' => 2293,
	];
}

/**
 * Size slug (ml) => the pa_quantity attribute value the live variations
 * actually use. Not a uniform "{n}ml" pattern — recorded as-is from the
 * catalog (attribute_pa_quantity on variations 2281-2283/2287-2289/
 * 2295-2297), not reformatted, so a catalog edit that changes these
 * attribute values needs a matching edit here.
 *
 * @return array<string, string>
 */
function amq_castile_size_attribute_values() {
	return [
		'100' => '100ml',
		'250' => '250-ml',
		'500' => '500-ml',
	];
}

/**
 * Resolve a scent + size selection from the CASTILE buying block to the
 * real WooCommerce product/variation it refers to.
 *
 * @param string $scent One of amq_castile_products()'s keys (e.g. 'lavender').
 * @param string $size  One of amq_castile_size_attribute_values()'s keys (e.g. '250').
 * @return array{product_id:int, variation_id:int, name:string, price:float, price_html:string, sku:string, image_url:string, in_stock:bool}|null
 *   Null when the scent/size is unrecognized, the product doesn't exist or
 *   isn't a variable product, or no variation matches the requested size.
 */
function amq_castile_resolve_selection($scent, $size) {
	$products = amq_castile_products();
	$size_values = amq_castile_size_attribute_values();

	if (!isset($products[$scent]) || !isset($size_values[$size])) return null;

	$product = wc_get_product($products[$scent]);
	if (!$product || !$product->is_type('variable')) return null;

	$target_attribute_value = $size_values[$size];
	$match = null;

	foreach ($product->get_available_variations() as $variation) {
		if (($variation['attributes']['attribute_pa_quantity'] ?? null) === $target_attribute_value) {
			$match = $variation;
			break;
		}
	}

	if ($match === null) return null;

	$image_id = !empty($match['image_id']) ? $match['image_id'] : $product->get_image_id();

	return [
		'product_id' => $product->get_id(),
		'variation_id' => (int) $match['variation_id'],
		'name' => $product->get_name(),
		'price' => (float) $match['display_price'],
		'price_html' => $match['price_html'],
		'sku' => $match['sku'],
		'image_url' => $image_id ? (string) wp_get_attachment_image_url($image_id, 'full') : '',
		'in_stock' => !empty($match['is_in_stock']),
	];
}
