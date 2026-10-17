<?php
/**
 * [lang_switcher] — "EN / TH" text toggle. The current language renders as
 * plain (non-clickable) text; the other language is a link to the same page in
 * that language (TranslatePress, ADR-0010). Placed through the header's HTML
 * element (Blocksy header builder), which runs shortcodes.
 */
add_shortcode('lang_switcher', function () {
	global $TRP_LANGUAGE;
	if (empty($TRP_LANGUAGE) || !class_exists('TRP_Translate_Press')) {
		return '';
	}
	// TranslatePress locale => label shown in the switcher.
	$labels = ['en_US' => 'EN', 'th' => 'TH'];
	if (!isset($labels[$TRP_LANGUAGE])) {
		return '';
	}
	$converter = TRP_Translate_Press::get_trp_instance()->get_component('url_converter');

	$current = $TRP_LANGUAGE;
	$lang_part = function ($locale) use ($labels, $converter, $current) {
		$label = $labels[$locale];
		if ($current === $locale) {
			return '<span style="opacity:1;">' . esc_html($label) . '</span>';
		}
		// Keep TranslatePress's default link marker: without it the page's link rewriting
		// would add the current language back onto this link.
		$url = $converter->get_url_for_language($locale);
		return '<a href="' . esc_url($url) . '" style="opacity:0.5;color:inherit;text-decoration:none;">' . esc_html($label) . '</a>';
	};

	return '<span class="amq-lang-switch" style="display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:700;letter-spacing:0.04em;">'
		. $lang_part('en_US')
		. '<span style="opacity:0.35;">/</span>'
		. $lang_part('th')
		. '</span>';
});
