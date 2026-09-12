<?php
/**
 * [amq_ingredient_slider] — manually-navigated 2-slide banner that replaced
 * the old static "Ingredients we know well" section. Full width, fixed height
 * (4/5 of the homepage hero's 680px = 544px). Content is picked by current
 * Polylang language so the same shortcode works on both EN and TH homepages.
 */

if (!defined('ABSPATH')) exit;

function amq_hero_slider_slides() {
	$lang = function_exists('pll_current_language') ? pll_current_language() : 'en';

	if ($lang === 'th') {
		return [
			[
				'headline' => 'พลังแห่งน้ำมันละหุ่ง',
				'body' => 'อุดมด้วยกรดริซิโนเลอิก น้ำมันละหุ่งสกัดเย็นของเราบำรุงผมและผิวได้อย่างล้ำลึก เพียงหยดเดียวก็ให้ผลลัพธ์ที่คุ้มค่า',
				'button' => 'ช้อปเลย',
				'link' => get_term_link(24, 'product_cat'),
				'image' => 72,
				'bg' => '#C7C2BA',
			],
			[
				'headline' => 'ความสงบ ที่กลั่นจากลาเวนเดอร์',
				'body' => 'น้ำมันหอมระเหยลาเวนเดอร์สกัดด้วยไอน้ำ มอบกลิ่นหอมอ่อนโยนผ่อนคลาย ปลอบประโลมทั้งผิวและจิตใจในทุกวัน',
				'button' => 'ช้อปเลย',
				'link' => get_term_link(25, 'product_cat'),
				'image' => 73,
				'bg' => '#F0EEEC',
			],
		];
	}

	return [
		[
			'headline' => 'The Power of Castor Oil',
			'body' => 'Rich in ricinoleic acid, our cold-pressed castor oil deeply nourishes hair and skin — one drop goes a long way.',
			'button' => 'Shop Now',
			'link' => get_term_link(24, 'product_cat'),
			'image' => 72,
			'bg' => '#C7C2BA',
		],
		[
			'headline' => 'Calm, Captured in Lavender',
			'body' => 'Steam-distilled lavender essential oil brings a gentle, grounding scent to your daily ritual — soothing to skin and mind alike.',
			'button' => 'Shop Now',
			'link' => get_term_link(25, 'product_cat'),
			'image' => 73,
			'bg' => '#F0EEEC',
		],
	];
}

add_shortcode('amq_ingredient_slider', function ($atts) {
	$slides = amq_hero_slider_slides();
	if (empty($slides)) return '';

	amq_hero_slider_assets_needed();

	ob_start();
	?>
	<div class="amq-hero-slider" data-amq-hero-slider>
		<div class="amq-hero-slider__track">
			<?php foreach ($slides as $slide) :
				$link = is_wp_error($slide['link']) ? '#' : $slide['link'];
				$img_src = wp_get_attachment_image_url($slide['image'], 'large');
				?>
				<div class="amq-hero-slide" style="background-color:<?php echo esc_attr($slide['bg']); ?>;">
					<div class="amq-hero-slide__text">
						<h2 class="amq-hero-slide__headline"><?php echo esc_html($slide['headline']); ?></h2>
						<p class="amq-hero-slide__body"><?php echo esc_html($slide['body']); ?></p>
						<a href="<?php echo esc_url($link); ?>" class="amq-hero-slide__button"><?php echo esc_html($slide['button']); ?></a>
					</div>
					<div class="amq-hero-slide__media">
						<img src="<?php echo esc_url($img_src); ?>" alt="<?php echo esc_attr($slide['headline']); ?>" loading="lazy">
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<button type="button" class="amq-hero-slider__arrow amq-hero-slider__arrow--prev" aria-label="<?php esc_attr_e('Previous', 'amouriq'); ?>">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M15 5l-7 7 7 7"/></svg>
		</button>
		<button type="button" class="amq-hero-slider__arrow amq-hero-slider__arrow--next" aria-label="<?php esc_attr_e('Next', 'amouriq'); ?>">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M9 5l7 7-7 7"/></svg>
		</button>
	</div>
	<?php
	return ob_get_clean();
});

function amq_hero_slider_assets_needed() {
	static $done = false;
	if ($done) return;
	$done = true;

	// CSS/JS now live in the child theme (assets/css/amq-hero-slider.css,
	// assets/js/amq-hero-slider.js). They are still printed here, in
	// wp_footer, gated behind this shortcode-conditional function — not via
	// wp_enqueue_style()/wp_enqueue_script(), because by the time this runs
	// (mid-shortcode-render) wp_head's wp_print_styles has already fired, so
	// a late-enqueued style would register but never print. Hand-printing
	// the tags here preserves the exact same conditional trigger and output
	// position as before.
	add_action('wp_footer', function () {
		$path = get_stylesheet_directory() . '/assets/css/amq-hero-slider.css';
		if (!file_exists($path)) return;
		printf(
			'<link rel="stylesheet" id="amq-hero-slider-css" href="%s" media="all" />',
			esc_url(get_stylesheet_directory_uri() . '/assets/css/amq-hero-slider.css?ver=' . filemtime($path))
		);
	}, 100);

	add_action('wp_footer', function () {
		$path = get_stylesheet_directory() . '/assets/js/amq-hero-slider.js';
		if (!file_exists($path)) return;
		printf(
			'<script id="amq-hero-slider-js" src="%s"></script>',
			esc_url(get_stylesheet_directory_uri() . '/assets/js/amq-hero-slider.js?ver=' . filemtime($path))
		);
	});
}
