<?php
/**
 * Single blog post template — the "Read+" detail page for [amq_journal]
 * entries. Hello Elementor ships no single.php (blank-slate theme meant for
 * full Elementor building), and there's no sitewide header/footer system
 * (see memo/learning.md), so this reuses the same static header/footer
 * chrome as the Shop archive (get_header('shop')/get_footer('shop') —
 * generic nav, not actually Shop-specific despite the filename).
 *
 * @package HelloElementorChild
 */

if (!defined('ABSPATH')) exit;

get_header('shop');
?>

<article class="amq-article">
	<?php while (have_posts()) : the_post(); ?>

		<?php if (has_post_thumbnail()) : ?>
			<div class="amq-article__hero">
				<?php the_post_thumbnail('full', ['class' => 'amq-article__hero-img']); ?>
			</div>
		<?php endif; ?>

		<div class="amq-article__inner">
			<a href="<?php echo esc_url(home_url('/blog/')); ?>" class="amq-article__back">&larr; <?php esc_html_e('All journal posts', 'amouriq'); ?></a>

			<?php $cats = get_the_category(); ?>
			<div class="amq-article__eyebrow">
				<?php echo esc_html(!empty($cats) ? $cats[0]->name : __('Journal', 'amouriq')); ?>
				&middot; <?php echo esc_html(get_the_date()); ?>
			</div>

			<h1 class="amq-article__title"><?php the_title(); ?></h1>

			<div class="amq-article__content">
				<?php the_content(); ?>
			</div>

			<a href="<?php echo esc_url(home_url('/blog/')); ?>" class="amq-article__back amq-article__back--bottom">&larr; <?php esc_html_e('All journal posts', 'amouriq'); ?></a>
		</div>

	<?php endwhile; ?>
</article>

<?php get_footer('shop'); ?>
