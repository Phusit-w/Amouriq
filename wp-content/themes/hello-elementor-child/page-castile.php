<?php
/**
 * CASTILE sales page template — a WordPress Page (slug: castile) built
 * entirely in Elementor, using the site's static header/footer chrome —
 * the same treatment as Shop/Blog/Contact/single-Product/single-Post
 * (get_header('shop') / get_footer('shop')) — rather than Home's
 * hero-based header, since this page opens with a light trust-bar strip,
 * not a dark hero. See amouriq-single-product.php's and header-shop.php's
 * header comments for why this theme has no sitewide header/footer system
 * and each template calls get_header('shop') / get_footer('shop')
 * explicitly instead. See docs/adr/0001-castile-sales-page-built-in-elementor.md
 * and docs/adr/0002-build-against-hello-elementor-child-despite-inactive.md
 * for why this page is Elementor-built and why it lives in this theme.
 *
 * Content lives entirely in Elementor (_elementor_edit_mode=builder on this
 * page) — the_content() below is what Elementor's content filter replaces
 * with the built page.
 *
 * @package HelloElementorChild
 */

if (!defined('ABSPATH')) exit;

get_header('shop');
?>

<main class="amq-castile-page">
	<?php while (have_posts()) : the_post(); ?>
		<?php the_content(); ?>
	<?php endwhile; ?>
</main>

<?php get_footer('shop'); ?>
