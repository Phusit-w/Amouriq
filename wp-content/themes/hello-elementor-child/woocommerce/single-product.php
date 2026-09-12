<?php
/**
 * Single Product page template override.
 *
 * WooCommerce's single-product.php is loaded via the same `template_include`
 * mechanism as archive-product.php (see woocommerce/archive-product.php in
 * this theme + memo/learning.md "The Shop page can't be built with
 * Elementor") — it bypasses page.php/_elementor_data entirely, so this page
 * can't be an Elementor page either. Plain PHP + CSS, same pattern as Shop.
 *
 * Header/footer reuse header-shop.php / footer-shop.php (the static
 * Shop/Contact-style chrome) rather than Home's real #amq-header, since
 * Home's header is transparent-over-hero (JS-driven solid state on scroll)
 * and this page has no hero image behind it.
 *
 * @package HelloElementorChild
 */

defined('ABSPATH') || exit;

get_header('shop');

while (have_posts()) :
	the_post();

	global $product;
	$product = wc_get_product(get_the_ID());
	if (!$product) continue;

	$product_id = $product->get_id();
	$name = $product->get_name();
	$price_html = $product->get_price_html();
	$short_description = $product->get_short_description();
	$full_description = $product->get_description();

	// --- Gallery: main image + thumbnails only if there's more than one distinct image ---
	$main_image_id = $product->get_image_id();
	$gallery_ids = $product->get_gallery_image_ids();
	$image_ids = $main_image_id ? array_values(array_unique(array_merge([$main_image_id], $gallery_ids))) : array_values(array_unique($gallery_ids));
	if (empty($image_ids)) {
		$main_image_src = wc_placeholder_img_src('large');
	} else {
		$main_image_src = wp_get_attachment_image_url($image_ids[0], 'large');
	}

	// --- Key Ingredients (new field, see amouriq-single-product.php) ---
	$key_ingredients = amq_get_key_ingredients($product_id);
	$how_to_store = amq_get_how_to_store($product_id);
	$full_ingredients = amq_get_full_ingredients($product_id);
	$how_to_use = amq_get_how_to_use($product_id);

	// --- Size selector: only for real Variable products with real variations ---
	$is_variable = $product->is_type('variable');
	$variations = $is_variable ? amq_get_sorted_variations($product) : [];
	$default_variation_id = !empty($variations) ? $variations[0]['variation_id'] : 0;

	$nonce = wp_create_nonce('amq_add_to_cart');
	?>

	<div class="amq-pdp">
		<div class="amq-pdp__gallery">
			<div class="amq-pdp__main-image">
				<img src="<?php echo esc_url($main_image_src); ?>" alt="<?php echo esc_attr($name); ?>" id="amq-pdp-main-image">
			</div>
			<?php if (count($image_ids) > 1) : ?>
				<div class="amq-pdp__thumbs">
					<?php foreach ($image_ids as $img_id) :
						$thumb_src = wp_get_attachment_image_url($img_id, 'thumbnail');
						$full_src = wp_get_attachment_image_url($img_id, 'large');
						?>
						<button type="button" class="amq-pdp__thumb" data-full-src="<?php echo esc_url($full_src); ?>">
							<img src="<?php echo esc_url($thumb_src); ?>" alt="">
						</button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>

		<div class="amq-pdp__info">
			<h1 class="amq-pdp__name"><?php echo esc_html($name); ?></h1>
			<div class="amq-pdp__price"><?php echo wp_kses_post($price_html); ?></div>

			<?php if ($short_description) : ?>
				<div class="amq-pdp__short-description"><?php echo wp_kses_post(apply_filters('woocommerce_short_description', $short_description)); ?></div>
			<?php endif; ?>

			<div class="amq-pdp__key-ingredients">
				<h2 class="amq-pdp__section-heading"><?php esc_html_e('Key Ingredients', 'amouriq'); ?></h2>
				<?php if (!empty($key_ingredients)) : ?>
					<ul class="amq-pdp__ingredient-pills">
						<?php foreach ($key_ingredients as $ingredient) : ?>
							<li class="amq-pdp__ingredient-pill"><?php echo esc_html($ingredient); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php else : ?>
					<p class="amq-pdp__ingredients-placeholder"><?php esc_html_e('Key ingredients coming soon.', 'amouriq'); ?></p>
				<?php endif; ?>
			</div>

			<?php if ($is_variable && !empty($variations)) : ?>
				<div class="amq-pdp__size">
					<h2 class="amq-pdp__section-heading"><?php esc_html_e('Size', 'amouriq'); ?></h2>
					<div class="amq-size-box-row">
						<?php foreach ($variations as $i => $v) : ?>
							<button
								type="button"
								class="amq-size-box<?php echo $i === 0 ? ' is-selected' : ''; ?>"
								data-variation-id="<?php echo esc_attr($v['variation_id']); ?>"
								data-price-html="<?php echo esc_attr($v['price_html']); ?>"
							><?php echo esc_html($v['label']); ?></button>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<div class="amq-pdp__purchase-row" data-nonce="<?php echo esc_attr($nonce); ?>">
				<div class="amq-pdp__qty">
					<button type="button" class="amq-pdp__qty-btn" data-step="-1" aria-label="<?php esc_attr_e('Decrease quantity', 'amouriq'); ?>">&minus;</button>
					<input type="number" class="amq-pdp__qty-input" value="1" min="1" step="1" aria-label="<?php esc_attr_e('Quantity', 'amouriq'); ?>">
					<button type="button" class="amq-pdp__qty-btn" data-step="1" aria-label="<?php esc_attr_e('Increase quantity', 'amouriq'); ?>">+</button>
				</div>
				<button
					type="button"
					class="amq-pdp__add-to-bag"
					data-product-id="<?php echo esc_attr($product_id); ?>"
					data-variation-id="<?php echo esc_attr($default_variation_id); ?>"
				><?php esc_html_e('ADD TO BAG', 'amouriq'); ?></button>
			</div>
		</div>
	</div>

	<div class="amq-pdp__lower">
		<?php if ($full_description) : ?>
			<div class="amq-pdp__full-description">
				<h2 class="amq-pdp__section-heading"><?php esc_html_e('Description', 'amouriq'); ?></h2>
				<div class="amq-pdp__full-description-content"><?php echo apply_filters('the_content', $full_description); ?></div>
			</div>
		<?php endif; ?>

		<div class="amq-pdp__how-to-store">
			<h3 class="amq-pdp__inline-heading"><?php esc_html_e('How to store:', 'amouriq'); ?></h3>
			<p><?php echo $how_to_store ? esc_html($how_to_store) : esc_html__('How to store — coming soon.', 'amouriq'); ?></p>
		</div>

		<div class="amq-pdp__divider"></div>

		<div class="amq-pdp__accordion" data-amq-accordion>
			<button type="button" class="amq-pdp__accordion-toggle" aria-expanded="false">
				<span><?php esc_html_e('Ingredients', 'amouriq'); ?></span>
				<span class="amq-pdp__accordion-icon">+</span>
			</button>
			<div class="amq-pdp__accordion-panel">
				<?php if (!empty($full_ingredients)) : ?>
					<ul class="amq-pdp__ingredients-list">
						<?php foreach ($full_ingredients as $ingredient) : ?>
							<li><?php echo esc_html($ingredient); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php else : ?>
					<p><?php esc_html_e('Full ingredients list coming soon.', 'amouriq'); ?></p>
				<?php endif; ?>
			</div>
		</div>

		<div class="amq-pdp__divider"></div>

		<div class="amq-pdp__accordion" data-amq-accordion>
			<button type="button" class="amq-pdp__accordion-toggle" aria-expanded="false">
				<span><?php esc_html_e('How to use', 'amouriq'); ?></span>
				<span class="amq-pdp__accordion-icon">+</span>
			</button>
			<div class="amq-pdp__accordion-panel">
				<p><?php echo $how_to_use ? esc_html($how_to_use) : esc_html__('How to use — coming soon.', 'amouriq'); ?></p>
			</div>
		</div>

		<div class="amq-pdp__divider"></div>
	</div>

	<?php
	$related_ids = wc_get_related_products($product_id, 4);
	if (!empty($related_ids)) {
		$related_products = array_filter(array_map('wc_get_product', $related_ids));
		if (!empty($related_products)) :
			?>
			<div class="amq-pdp__related">
				<h2 class="amq-pdp__section-heading amq-pdp__related-heading"><?php esc_html_e('You Might Also Like', 'amouriq'); ?></h2>
				<?php echo amq_render_product_carousel($related_products); ?>
			</div>
			<?php
		endif;
	}

endwhile;

get_footer('shop');
