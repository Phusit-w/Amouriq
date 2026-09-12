<?php
/**
 * AMOURIQ language-driven global font.
 *
 * Polylang's current language decides the sitewide font:
 *  - English (en): Plus Jakarta Sans
 *  - Thai (th):    Prompt
 *
 * Follows the same hand-printed wp_head asset pattern as the other AMOURIQ
 * mu-plugins (e.g. amouriq-header-hover.php) — a Google Fonts <link> plus a
 * child-theme stylesheet (assets/css/amq-lang-font-en.css or
 * amq-lang-font-th.css) that sets font-family on body.
 */
add_action('wp_head', function () {
	$lang = function_exists('pll_current_language') ? pll_current_language() : 'en';
	$lang = ($lang === 'th') ? 'th' : 'en';

	$fonts = [
		'en' => [
			'google_family' => 'Plus+Jakarta+Sans:wght@400;500;600;700',
			'css_file'      => 'amq-lang-font-en.css',
		],
		'th' => [
			'google_family' => 'Prompt:wght@400;500;600;700',
			'css_file'      => 'amq-lang-font-th.css',
		],
	];
	$font = $fonts[$lang];

	// Extra family loaded in the same Google Fonts request regardless of
	// language (own &family= param, same css2 URL) — 300/400/500/600/700,
	// normal + italic (10 variants).
	$extra_family = 'Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700';

	printf('<link rel="preconnect" href="https://fonts.googleapis.com" />');
	printf('<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />');
	printf(
		'<link rel="stylesheet" id="amq-lang-font-google-css" href="%s" />',
		esc_url('https://fonts.googleapis.com/css2?family=' . $font['google_family'] . '&family=' . $extra_family . '&display=swap')
	);

	$path = get_stylesheet_directory() . '/assets/css/' . $font['css_file'];
	if (!file_exists($path)) return;
	printf(
		'<link rel="stylesheet" id="amq-lang-font-css" href="%s" media="all" />',
		esc_url(get_stylesheet_directory_uri() . '/assets/css/' . $font['css_file'] . '?ver=' . filemtime($path))
	);
}, 100);
