<?php
/**
 * [lang_switcher] — "EN / TH" text toggle. The current language renders as
 * plain (non-clickable) text; the other language is a link to the Polylang
 * translation of the current page. Placed via a native Elementor Shortcode
 * widget (no plugin ships a ready-made widget for this look).
 */
add_shortcode('lang_switcher', function () {
	if (!function_exists('pll_current_language')) {
		return '';
	}
	$cur = pll_current_language();
	$post_id = get_the_ID();

	$lang_part = function ($code) use ($cur, $post_id) {
		if ($cur === $code) {
			return '<span style="opacity:1;">' . esc_html(strtoupper($code)) . '</span>';
		}
		$target_id = $post_id ? pll_get_post($post_id, $code) : 0;
		$url = $target_id ? get_permalink($target_id) : pll_home_url($code);
		return '<a href="' . esc_url($url) . '" style="opacity:0.5;color:inherit;text-decoration:none;">' . esc_html(strtoupper($code)) . '</a>';
	};

	return '<span class="amq-lang-switch" style="display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:700;letter-spacing:0.04em;">'
		. $lang_part('en')
		. '<span style="opacity:0.35;">/</span>'
		. $lang_part('th')
		. '</span>';
});
