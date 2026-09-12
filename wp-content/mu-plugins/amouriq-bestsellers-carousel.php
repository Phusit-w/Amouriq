<?php
/**
 * [amq_bestsellers] — "Our Most Loved" product carousel on the Home page.
 * Pulls real WooCommerce products marked "Featured" (WooCommerce's own
 * Featured flag — set/unset per product from the product list or the
 * product editor's Publish box), not a hardcoded id list.
 *
 * The top-left corner badge text and the ADD TO BAG button label are a
 * single shared value used on every card (Elementor free has no per-loop-
 * item widget, so true per-product Elementor editing isn't possible here).
 * They're sourced from two hidden "template" widgets placed next to the
 * [amq_bestsellers] shortcode widget on each home page — a Text Editor
 * (css class amq-bestsellers-badge-template) and a Button (css class
 * amq-bestsellers-cta-template) — editable via Elementor's own "Edit Text
 * Editor"/"Edit Button", findable in Navigator since they're visually
 * hidden. See amq-bestsellers-carousel.js for the copy-into-every-card
 * logic and amq-bestsellers-carousel.css for the display:none rule.
 *
 * Variable products (real WooCommerce variations) get a real Size dropdown
 * that swaps the shown price on change, defaulting to the smallest size.
 * Simple products show "One size only". ADD TO BAG adds the selected
 * variation (or the simple product) to the real WooCommerce cart via AJAX
 * (see amq_handle_add_to_cart() below). The bookmark icon is decorative
 * only for now — no wishlist system exists on this site yet.
 */

if (!defined('ABSPATH')) exit;

add_shortcode('amq_bestsellers', function ($atts) {
	if (!function_exists('wc_get_products')) return '';

	$atts = shortcode_atts([
		'limit' => -1,
	], $atts, 'amq_bestsellers');

	$products = wc_get_products([
		'status' => 'publish',
		'featured' => true,
		'limit' => (int) $atts['limit'],
		'orderby' => 'menu_order',
		'order' => 'ASC',
	]);
	if (empty($products)) return '';

	return amq_render_product_carousel($products);
});

/**
 * Render a `.amq-carousel` of `.amq-product-card`s for a given list of
 * WC_Product objects — shared by [amq_bestsellers] (Home) and the single
 * product page's "You might also like" related-products row, so both stay
 * visually/behaviorally identical (same CSS/JS) without duplicating markup.
 *
 * @param WC_Product[] $products
 * @return string
 */
function amq_render_product_carousel(array $products) {
	if (empty($products)) return '';

	amq_bestsellers_assets_needed();

	$show_arrows = count($products) > 4;
	$nonce = wp_create_nonce('amq_add_to_cart');

	ob_start();
	?>
	<div class="amq-carousel" data-amq-carousel data-nonce="<?php echo esc_attr($nonce); ?>">
		<?php if ($show_arrows) : ?>
		<button type="button" class="amq-carousel__arrow amq-carousel__arrow--prev" aria-label="<?php esc_attr_e('Previous', 'amouriq'); ?>">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M15 5l-7 7 7 7"/></svg>
		</button>
		<?php endif; ?>

		<div class="amq-carousel__viewport">
			<div class="amq-carousel__track">
				<?php foreach ($products as $product) :
					$id = $product->get_id();
					$permalink = get_permalink($id);
					$name = $product->get_name();

					$img_id = $product->get_image_id();
					$primary_src = $img_id ? wp_get_attachment_image_url($img_id, 'medium_large') : wc_placeholder_img_src();

					$gallery_ids = $product->get_gallery_image_ids();
					$hover_id = !empty($gallery_ids) ? $gallery_ids[0] : 0;
					$hover_src = $hover_id ? wp_get_attachment_image_url($hover_id, 'medium_large') : '';

					$short_desc = wp_strip_all_tags($product->get_short_description());

					$is_variable = $product->is_type('variable');
					$variations_map = [];
					$default_variation_id = 0;
					$default_price_html = '';

					if ($is_variable) {
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

						foreach ($parsed as $p) {
							$variations_map[$p['variation_id']] = $p['price_html'];
						}
						if (!empty($parsed)) {
							$default_variation_id = $parsed[0]['variation_id'];
							$default_price_html = $parsed[0]['price_html'];
						}
					} else {
						$default_price_html = $product->get_price_html();
					}
					?>
					<div class="amq-carousel__slide">
						<div class="amq-product-card" data-product-id="<?php echo esc_attr($id); ?>">
							<a href="<?php echo esc_url($permalink); ?>" class="amq-product-card__media">
								<span class="amq-product-card__badge"></span>
								<img class="amq-product-card__image amq-product-card__image--primary" src="<?php echo esc_url($primary_src); ?>" alt="<?php echo esc_attr($name); ?>" loading="lazy">
								<?php if ($hover_src) : ?>
									<img class="amq-product-card__image amq-product-card__image--hover" src="<?php echo esc_url($hover_src); ?>" alt="" loading="lazy">
								<?php endif; ?>
							</a>
							<button type="button" class="amq-product-card__bookmark" aria-label="<?php esc_attr_e('Save', 'amouriq'); ?>" aria-pressed="false">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6 4.8A1.8 1.8 0 0 1 7.8 3h8.4A1.8 1.8 0 0 1 18 4.8V21l-6-4-6 4V4.8Z"/></svg>
							</button>

							<div class="amq-product-card__body">
								<a href="<?php echo esc_url($permalink); ?>" class="amq-product-card__name"><?php echo esc_html($name); ?></a>
								<p class="amq-product-card__desc"><?php echo $short_desc ? esc_html(wp_trim_words($short_desc, 20)) : ''; ?></p>

								<div class="amq-product-card__divider"></div>

								<?php if ($is_variable && !empty($variations_map)) : ?>
									<div class="amq-product-card__size-row">
										<span class="amq-product-card__size-label"><?php esc_html_e('Size', 'amouriq'); ?></span>
										<select class="amq-product-card__size-select" data-variations="<?php echo esc_attr(wp_json_encode($variations_map)); ?>">
											<?php foreach ($parsed as $p) : ?>
												<option value="<?php echo esc_attr($p['variation_id']); ?>" <?php selected($p['variation_id'], $default_variation_id); ?>><?php echo esc_html($p['label']); ?></option>
											<?php endforeach; ?>
										</select>
									</div>
								<?php else : ?>
									<div class="amq-product-card__size-row amq-product-card__size-row--fixed">
										<span class="amq-product-card__size-label"><?php esc_html_e('One size only', 'amouriq'); ?></span>
									</div>
								<?php endif; ?>

								<div class="amq-product-card__price"><?php echo wp_kses_post($default_price_html); ?></div>

								<div class="amq-product-card__actions">
									<button
										type="button"
										class="amq-product-card__add-to-bag"
										data-product-id="<?php echo esc_attr($id); ?>"
										data-variation-id="<?php echo esc_attr($default_variation_id); ?>"
									><?php esc_html_e('ADD TO BAG', 'amouriq'); ?></button>
									<a href="<?php echo esc_url($permalink); ?>" class="amq-product-card__details-link"><?php esc_html_e('Product Details', 'amouriq'); ?></a>
								</div>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<?php if ($show_arrows) : ?>
		<button type="button" class="amq-carousel__arrow amq-carousel__arrow--next" aria-label="<?php esc_attr_e('Next', 'amouriq'); ?>">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M9 5l7 7-7 7"/></svg>
		</button>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * AJAX: add the selected product/variation to the real WooCommerce cart.
 */
function amq_handle_add_to_cart() {
	check_ajax_referer('amq_add_to_cart', 'nonce');

	if (!function_exists('WC')) {
		wp_send_json_error(['message' => 'WooCommerce not active'], 400);
	}

	$product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
	$variation_id = isset($_POST['variation_id']) ? absint($_POST['variation_id']) : 0;
	$quantity = isset($_POST['quantity']) ? max(1, absint($_POST['quantity'])) : 1;

	if (!$product_id) {
		wp_send_json_error(['message' => __('Invalid product.', 'amouriq')], 400);
	}

	$variation_data = [];
	if ($variation_id) {
		$variation = wc_get_product($variation_id);
		if ($variation && $variation->is_type('variation')) {
			$variation_data = $variation->get_variation_attributes();
		}
	}

	$added = WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variation_data);

	if (!$added) {
		wp_send_json_error(['message' => __('Could not add this product to your bag.', 'amouriq')], 400);
	}

	wp_send_json_success([
		'cart_count' => WC()->cart->get_cart_contents_count(),
	]);
}
add_action('wp_ajax_amq_add_to_cart', 'amq_handle_add_to_cart');
add_action('wp_ajax_nopriv_amq_add_to_cart', 'amq_handle_add_to_cart');

function amq_bestsellers_assets_needed() {
	static $done = false;
	if ($done) return;
	$done = true;

	// CSS/JS live in the child theme (assets/css/amq-bestsellers-carousel.css,
	// assets/js/amq-bestsellers-carousel.js). Still printed here, in
	// wp_footer, gated behind this shortcode-conditional function — not via
	// wp_enqueue_style()/wp_enqueue_script(), because by the time this runs
	// (mid-shortcode-render) wp_head's wp_print_styles has already fired, so
	// a late-enqueued style would register but never print.
	// CSS must print before the JS (lower wp_footer priority number = earlier).
	// The arrow-scroll script measures scrollWidth/clientWidth on init, and a
	// classic <script src> placed before its stylesheet runs immediately
	// without waiting for that stylesheet to load, so it was measuring
	// pre-flexbox (unstyled) layout and getting stuck with disabled arrows.
	add_action('wp_footer', function () {
		$path = get_stylesheet_directory() . '/assets/css/amq-bestsellers-carousel.css';
		if (!file_exists($path)) return;
		printf(
			'<link rel="stylesheet" id="amq-bestsellers-carousel-css" href="%s" media="all" />',
			esc_url(get_stylesheet_directory_uri() . '/assets/css/amq-bestsellers-carousel.css?ver=' . filemtime($path))
		);
	}, 5);

	add_action('wp_footer', function () {
		$path = get_stylesheet_directory() . '/assets/js/amq-bestsellers-carousel.js';
		if (!file_exists($path)) return;
		printf(
			'<script id="amq-bestsellers-carousel-js" src="%s" data-ajax-url="%s"></script>',
			esc_url(get_stylesheet_directory_uri() . '/assets/js/amq-bestsellers-carousel.js?ver=' . filemtime($path)),
			esc_url(admin_url('admin-ajax.php'))
		);
	}, 20);
}
