<?php
/**
 * Shop / product archive template override.
 *
 * Overrides WooCommerce's default templates/archive-product.php to add the
 * "All Products" hero banner and the Filter-sidebar + product-grid layout
 * from the design reference. Elementor never touches this page (WooCommerce
 * renders archive-product.php, not the page's own _elementor_data — see
 * memo/learning.md), so the layout is plain PHP + template-parts/
 * shop-sidebar.php, styled in amq-woocommerce.css.
 *
 * @package HelloElementorChild
 */

defined('ABSPATH') || exit;

get_header('shop');
?>

<section class="amq-shop-hero">
	<div class="amq-shop-hero__inner">
		<h1><?php woocommerce_page_title(); ?></h1>
	</div>
</section>

<div class="amq-shop-layout">

	<?php get_template_part('template-parts/shop-sidebar'); ?>

	<main class="amq-shop-main">
		<?php
		if (woocommerce_product_loop()) {

			/**
			 * Hook: woocommerce_before_shop_loop.
			 *
			 * @hooked woocommerce_output_all_notices - 10
			 * @hooked amq combined result-count + ordering toolbar - 20 (see mu-plugins/amouriq-shop.php)
			 */
			do_action('woocommerce_before_shop_loop');

			woocommerce_product_loop_start();

			if (wc_get_loop_prop('total')) {
				while (have_posts()) {
					the_post();
					do_action('woocommerce_shop_loop');
					wc_get_template_part('content', 'product');
				}
			}

			woocommerce_product_loop_end();

			/**
			 * Hook: woocommerce_after_shop_loop.
			 *
			 * @hooked woocommerce_pagination - 10
			 */
			do_action('woocommerce_after_shop_loop');
		} else {
			do_action('woocommerce_no_products_found');
		}
		?>
	</main>

</div>

<?php get_footer('shop'); ?>
