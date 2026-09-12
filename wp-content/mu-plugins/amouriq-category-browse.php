<?php
/**
 * [amq_browse_categories] — "Browse by category" section.
 * Reads real WooCommerce product categories (thumbnail_id termmeta = the
 * category image, set from Products > Categories in wp-admin). Excludes
 * "Uncategorized". Ordered by term_id (creation order) since core has no
 * built-in category ordering UI.
 *
 * Layout math: side margins equal one block's width. With N visible blocks
 * and a fixed gap g, block width b solves (N+2)*b + (N-1)*g = 100%.
 */

if (!defined('ABSPATH')) exit;

add_shortcode('amq_browse_categories', function ($atts) {
	if (!taxonomy_exists('product_cat')) return '';

	$terms = get_terms([
		'taxonomy' => 'product_cat',
		'hide_empty' => false,
		'exclude' => [get_option('default_product_cat', 0)],
		'orderby' => 'id',
		'order' => 'ASC',
	]);

	if (empty($terms) || is_wp_error($terms)) return '';

	amq_browse_categories_assets_needed();

	ob_start();
	?>
	<div class="amq-cat-carousel" data-amq-cat-carousel>
		<button type="button" class="amq-carousel__arrow amq-carousel__arrow--prev" aria-label="<?php esc_attr_e('Previous', 'amouriq'); ?>">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M15 5l-7 7 7 7"/></svg>
		</button>

		<div class="amq-cat-carousel__viewport">
			<div class="amq-cat-carousel__track">
				<?php foreach ($terms as $term) :
					$link = get_term_link($term);
					if (is_wp_error($link)) continue;
					$thumb_id = get_term_meta($term->term_id, 'thumbnail_id', true);
					$img_src = $thumb_id ? wp_get_attachment_image_url($thumb_id, 'medium_large') : wc_placeholder_img_src();
					?>
					<div class="amq-cat-tile">
						<a href="<?php echo esc_url($link); ?>" class="amq-cat-tile__media">
							<img src="<?php echo esc_url($img_src); ?>" alt="<?php echo esc_attr($term->name); ?>" loading="lazy">
						</a>
						<a href="<?php echo esc_url($link); ?>" class="amq-cat-tile__label"><?php echo esc_html($term->name); ?></a>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<button type="button" class="amq-carousel__arrow amq-carousel__arrow--next" aria-label="<?php esc_attr_e('Next', 'amouriq'); ?>">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M9 5l7 7-7 7"/></svg>
		</button>
	</div>
	<?php
	return ob_get_clean();
});

function amq_browse_categories_assets_needed() {
	static $done = false;
	if ($done) return;
	$done = true;

	// CSS/JS now live in the child theme (assets/css/amq-category-browse.css,
	// assets/js/amq-category-browse.js). They are still printed here, in
	// wp_footer, gated behind this shortcode-conditional function — not via
	// wp_enqueue_style()/wp_enqueue_script(), because by the time this runs
	// (mid-shortcode-render) wp_head's wp_print_styles has already fired, so
	// a late-enqueued style would register but never print. Hand-printing
	// the tags here preserves the exact same conditional trigger and output
	// position as before.
	add_action('wp_footer', function () {
		$path = get_stylesheet_directory() . '/assets/css/amq-category-browse.css';
		if (!file_exists($path)) return;
		printf(
			'<link rel="stylesheet" id="amq-category-browse-css" href="%s" media="all" />',
			esc_url(get_stylesheet_directory_uri() . '/assets/css/amq-category-browse.css?ver=' . filemtime($path))
		);
	}, 100);

	add_action('wp_footer', function () {
		$path = get_stylesheet_directory() . '/assets/js/amq-category-browse.js';
		if (!file_exists($path)) return;
		printf(
			'<script id="amq-category-browse-js" src="%s"></script>',
			esc_url(get_stylesheet_directory_uri() . '/assets/js/amq-category-browse.js?ver=' . filemtime($path))
		);
	});
}
