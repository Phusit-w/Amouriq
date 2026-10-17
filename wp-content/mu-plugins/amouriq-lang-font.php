<?php
/**
 * AMOURIQ global font.
 *
 * Every page, Thai and English, loads the English font set (Plus Jakarta Sans).
 * This is deliberate: the live Thai pages have always rendered with it, because
 * the earlier per-language code asked Polylang, which is not installed, and so
 * always fell through to English. Owner's decision (2026-10-17, ticket 02): the
 * Thai pages stay exactly as they look today. A Thai (Prompt) set exists as
 * assets/css/amq-lang-font-th.css; to use it, select it when TranslatePress's
 * `$TRP_LANGUAGE` is 'th' and load the Prompt family from Google Fonts.
 *
 * Follows the same hand-printed wp_head asset pattern as the other AMOURIQ
 * mu-plugins (e.g. amouriq-header-hover.php) — a Google Fonts <link> plus a
 * theme stylesheet (assets/css/amq-lang-font-en.css) that sets font-family on body.
 */
add_action('wp_head', function () {
	// Extra family loaded in the same Google Fonts request (own &family= param,
	// same css2 URL) — 300/400/500/600/700, normal + italic (10 variants).
	$extra_family = 'Cormorant+Garamond:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700';

	printf('<link rel="preconnect" href="https://fonts.googleapis.com" />');
	printf('<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />');
	printf(
		'<link rel="stylesheet" id="amq-lang-font-google-css" href="%s" />',
		esc_url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=' . $extra_family . '&display=swap')
	);

	$path = get_stylesheet_directory() . '/assets/css/amq-lang-font-en.css';
	if (!file_exists($path)) return;
	printf(
		'<link rel="stylesheet" id="amq-lang-font-css" href="%s" media="all" />',
		esc_url(get_stylesheet_directory_uri() . '/assets/css/amq-lang-font-en.css?ver=' . filemtime($path))
	);
}, 100);
