<?php
/**
 * Shop archive sidebar — Filter by Category / Filter by price / Filter by
 * attribute, matching the "All Products" design reference.
 *
 * Category + attribute dropdowns are hand-built (real term data, navigates
 * to the term's own archive URL on submit — same behavior as WooCommerce's
 * core dropdown category widget, just with an explicit arrow button instead
 * of auto-submit-on-change). Price uses WooCommerce's own core Price Filter
 * widget so the slider stays fully functional out of the box.
 *
 * The "ปริมาณ/Volume" attribute (pa_volume) exists but currently has no
 * terms assigned to any product — see memo/tbc.md. Until products get
 * volume values, this box still renders (placeholder option only) rather
 * than disappearing, matching the design reference.
 *
 * @package HelloElementorChild
 */

if (!defined('ABSPATH')) exit;

$amq_product_cats = get_terms([
	'taxonomy'   => 'product_cat',
	'hide_empty' => true,
	'exclude'    => [get_option('default_product_cat')],
]);
?>
<aside class="amq-shop-sidebar">

	<div class="amq-shop-filter">
		<h3 class="amq-shop-filter__title">Filter by Category</h3>
		<form class="amq-shop-filter__form" data-amq-nav-select>
			<select>
				<option value="">Select category</option>
				<?php foreach ($amq_product_cats as $amq_cat) : ?>
					<option value="<?php echo esc_url(get_term_link($amq_cat)); ?>"><?php echo esc_html($amq_cat->name); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="submit" aria-label="Go to category">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6l6 6-6 6"></path></svg>
			</button>
		</form>
	</div>

	<div class="amq-shop-filter">
		<h3 class="amq-shop-filter__title">Filter by price</h3>
		<?php the_widget('WC_Widget_Price_Filter', ['title' => '']); ?>
	</div>

	<?php
	$amq_volume_terms = get_terms(['taxonomy' => 'pa_volume', 'hide_empty' => true]);
	if (!is_wp_error($amq_volume_terms)) :
	?>
	<div class="amq-shop-filter">
		<h3 class="amq-shop-filter__title">Filter by attribute</h3>
		<form class="amq-shop-filter__form" data-amq-nav-select>
			<select <?php echo empty($amq_volume_terms) ? 'disabled' : ''; ?>>
				<option value="">Select ปริมาณ</option>
				<?php foreach ($amq_volume_terms as $amq_term) : ?>
					<option value="<?php echo esc_url(get_term_link($amq_term)); ?>"><?php echo esc_html($amq_term->name); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="submit" aria-label="Go to attribute value">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 6l6 6-6 6"></path></svg>
			</button>
		</form>
	</div>
	<?php endif; ?>

</aside>
