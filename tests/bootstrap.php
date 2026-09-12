<?php
/**
 * PHPUnit bootstrap for the CASTILE resolver tests. Stubs the minimum
 * WordPress/WooCommerce surface amq_castile_resolve_selection() touches
 * (wc_get_product(), wp_get_attachment_image_url()) so the suite runs
 * without a WordPress/WooCommerce bootstrap or a database. See
 * tests/Fixtures/FakeWcProduct.php for the fake product shape and
 * tests/CastileResolverTest.php for how fixtures are registered per test.
 *
 * Run with: `composer install && composer test` (or `vendor/bin/phpunit`
 * directly) on any normal PHP install with standard extensions.
 *
 * On this project's Local by Flywheel dev environment specifically: the
 * bundled portable PHP's SSL trust store rejects Packagist's certificate,
 * so `composer install` can't run there. Workaround used for that machine
 * only: download a standalone phpunit.phar (not committed — see
 * .gitignore) and run it with mbstring explicitly enabled, e.g.
 *   "<Local PHP bin>/php.exe" -d extension_dir="<Local PHP bin>/ext" \
 *     -d extension=php_mbstring.dll phpunit.phar
 * (mbstring is present in that PHP build but not enabled by default; every
 * other extension PHPUnit needs is already compiled in).
 */

define('ABSPATH', __DIR__ . '/');

/** @var array<int, FakeWcProduct> Registry of product_id => fake product, set per test. */
$GLOBALS['amq_test_products'] = [];

function wc_get_product($id) {
	return $GLOBALS['amq_test_products'][$id] ?? null;
}

function wp_get_attachment_image_url($attachment_id, $size = 'thumbnail') {
	return $attachment_id ? "https://example.test/uploads/{$attachment_id}-{$size}.jpg" : false;
}

require __DIR__ . '/Fixtures/FakeWcProduct.php';
require dirname(__DIR__) . '/wp-content/mu-plugins/amouriq-castile-resolver.php';
