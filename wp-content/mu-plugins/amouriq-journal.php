<?php
/**
 * [amq_journal] — the /blog/ page's journal listing: 2 large "featured"
 * hero cards (post meta `_amq_journal_featured`, editable per-post from a
 * checkbox on the post edit screen — see the metabox below) followed by an
 * "All journal posts" grid of every other published post. Real WordPress
 * posts/categories, not a custom post type, so the owner keeps using the
 * normal Posts screen.
 */

if (!defined('ABSPATH')) exit;

// --- "Featured on Journal" checkbox on the post edit screen ---
add_action('add_meta_boxes', function () {
	add_meta_box(
		'amq_journal_featured_box',
		__('AMOURIQ Journal', 'amouriq'),
		function ($post) {
			$checked = get_post_meta($post->ID, '_amq_journal_featured', true);
			wp_nonce_field('amq_journal_featured_save', 'amq_journal_featured_nonce');
			echo '<label><input type="checkbox" name="amq_journal_featured" value="1" ' . checked($checked, '1', false) . '> ';
			esc_html_e('Show as a large featured card at the top of /blog/', 'amouriq');
			echo '</label>';
		},
		'post',
		'side',
		'default'
	);
});

add_action('save_post_post', function ($post_id) {
	if (!isset($_POST['amq_journal_featured_nonce']) || !wp_verify_nonce($_POST['amq_journal_featured_nonce'], 'amq_journal_featured_save')) return;
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
	if (!current_user_can('edit_post', $post_id)) return;
	update_post_meta($post_id, '_amq_journal_featured', isset($_POST['amq_journal_featured']) ? '1' : '');
});

add_shortcode('amq_journal', function () {
	amq_journal_assets_needed();

	$featured_query = new WP_Query([
		'post_type' => 'post',
		'post_status' => 'publish',
		'posts_per_page' => 2,
		'meta_key' => '_amq_journal_featured',
		'meta_value' => '1',
		'orderby' => 'date',
		'order' => 'DESC',
	]);
	$featured_ids = wp_list_pluck($featured_query->posts, 'ID');

	// Backfill with the latest posts if fewer than 2 are marked featured.
	if (count($featured_ids) < 2) {
		$fill = new WP_Query([
			'post_type' => 'post',
			'post_status' => 'publish',
			'posts_per_page' => 2 - count($featured_ids),
			'post__not_in' => $featured_ids,
			'orderby' => 'date',
			'order' => 'DESC',
		]);
		$featured_ids = array_merge($featured_ids, wp_list_pluck($fill->posts, 'ID'));
	}

	$rest_query = new WP_Query([
		'post_type' => 'post',
		'post_status' => 'publish',
		'posts_per_page' => -1,
		'post__not_in' => $featured_ids,
		'orderby' => 'date',
		'order' => 'DESC',
	]);
	$rest_posts = $rest_query->posts;

	if (empty($featured_ids) && empty($rest_posts)) return '';

	ob_start();
	?>
	<div class="amq-journal">

		<?php if (!empty($featured_ids)) : ?>
			<div class="amq-journal__featured">
				<?php foreach ($featured_ids as $i => $post_id) :
					$panel_side = ($i % 2 === 0) ? 'left' : 'right';
					amq_journal_render_hero($post_id, $panel_side);
				endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if (!empty($rest_posts)) : ?>
			<div class="amq-journal__all">
				<h2 class="amq-journal__all-heading"><?php esc_html_e('All journal posts', 'amouriq'); ?></h2>

				<div class="amq-journal__list">
					<?php
					$first = array_shift($rest_posts);
					amq_journal_render_row($first);
					?>

					<?php if (!empty($rest_posts)) : ?>
						<div class="amq-journal__grid">
							<?php foreach ($rest_posts as $post_id) : amq_journal_render_card($post_id); endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>

	</div>
	<?php
	return ob_get_clean();
});

function amq_journal_eyebrow($post_id) {
	$cats = get_the_category($post_id);
	return !empty($cats) ? $cats[0]->name : __('Journal', 'amouriq');
}

function amq_journal_thumb($post_id, $size = 'large') {
	if (has_post_thumbnail($post_id)) {
		return get_the_post_thumbnail($post_id, $size, ['class' => 'amq-journal__img']);
	}
	return '<div class="amq-journal__img amq-journal__img--placeholder" aria-hidden="true"></div>';
}

function amq_journal_render_hero($post_id, $panel_side) {
	?>
	<article class="amq-journal__hero">
		<a href="<?php echo esc_url(get_permalink($post_id)); ?>" class="amq-journal__hero-media">
			<?php echo amq_journal_thumb($post_id, 'large'); ?>
		</a>
		<div class="amq-journal__hero-panel amq-journal__hero-panel--<?php echo esc_attr($panel_side); ?>">
			<div class="amq-journal__eyebrow"><?php echo esc_html(amq_journal_eyebrow($post_id)); ?></div>
			<h3 class="amq-journal__hero-title"><?php echo esc_html(get_the_title($post_id)); ?></h3>
			<a href="<?php echo esc_url(get_permalink($post_id)); ?>" class="amq-journal__read">
				<?php esc_html_e('Read', 'amouriq'); ?> <span aria-hidden="true">+</span>
			</a>
		</div>
	</article>
	<?php
}

function amq_journal_render_row($post_id) {
	?>
	<article class="amq-journal__row">
		<a href="<?php echo esc_url(get_permalink($post_id)); ?>" class="amq-journal__row-media">
			<?php echo amq_journal_thumb($post_id, 'large'); ?>
		</a>
		<div class="amq-journal__row-body">
			<div class="amq-journal__eyebrow"><?php echo esc_html(amq_journal_eyebrow($post_id)); ?></div>
			<h3 class="amq-journal__row-title"><?php echo esc_html(get_the_title($post_id)); ?></h3>
			<a href="<?php echo esc_url(get_permalink($post_id)); ?>" class="amq-journal__read">
				<?php esc_html_e('Read', 'amouriq'); ?> <span aria-hidden="true">+</span>
			</a>
		</div>
	</article>
	<?php
}

function amq_journal_render_card($post_id) {
	?>
	<article class="amq-journal__card">
		<a href="<?php echo esc_url(get_permalink($post_id)); ?>" class="amq-journal__card-media">
			<?php echo amq_journal_thumb($post_id, 'medium_large'); ?>
		</a>
		<div class="amq-journal__card-body">
			<div class="amq-journal__eyebrow"><?php echo esc_html(amq_journal_eyebrow($post_id)); ?></div>
			<h3 class="amq-journal__card-title"><?php echo esc_html(get_the_title($post_id)); ?></h3>
			<a href="<?php echo esc_url(get_permalink($post_id)); ?>" class="amq-journal__read">
				<?php esc_html_e('Read', 'amouriq'); ?> <span aria-hidden="true">+</span>
			</a>
		</div>
	</article>
	<?php
}

function amq_journal_assets_needed() {
	static $done = false;
	if ($done) return;
	$done = true;

	// Same conditional, hand-printed-in-wp_footer pattern as
	// amouriq-bestsellers-carousel.php — see that file's comment for why.
	add_action('wp_footer', function () {
		$path = get_stylesheet_directory() . '/assets/css/amq-journal.css';
		if (!file_exists($path)) return;
		printf(
			'<link rel="stylesheet" id="amq-journal-css" href="%s" media="all" />',
			esc_url(get_stylesheet_directory_uri() . '/assets/css/amq-journal.css?ver=' . filemtime($path))
		);
	}, 100);
}

// Single post ("Read+" detail page, single.php) article styling — enqueued
// the normal way since single.php calls get_header() itself (wp_head hasn't
// printed yet when 'wp_enqueue_scripts' fires), unlike the shortcode case above.
add_action('wp_enqueue_scripts', function () {
	if (!is_singular('post')) return;
	$path = get_stylesheet_directory() . '/assets/css/amq-article.css';
	if (!file_exists($path)) return;
	wp_enqueue_style('amq-article', get_stylesheet_directory_uri() . '/assets/css/amq-article.css', [], filemtime($path));
});
