<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

/*
 * Plugin Name:       WP Toolkit plugin
 * Plugin URI:        https://www.plesk.com/wp-toolkit/
 * Description:       WP Toolkit plugin is installed by WP Toolkit or WP Guardian to provide functionality that can only work within WordPress itself
 * Version:           6.11.2-10791
 * Requires at least: 4.0
 * Requires PHP:      5.6
 */

// Do not access this file directly
if (!\defined('ABSPATH')) {
    header('Content-Type: text/plain');
    die(<<<MSG
 _________
< Go away >
 ---------
        \   ^__^
         \  (oo)\_______
            (__)\       )\/\
                ||----w |
                ||     ||
MSG
    );
}

$phpVersion = phpversion();
if ($phpVersion === false || version_compare($phpVersion, '5.6', '<')) {
    return;
}

// wp_get_wp_version return an unmodified WordPress version and is available since WordPress 6.7.0
if (\function_exists('wp_get_wp_version')) {
    $currentWpVersion = wp_get_wp_version();
} else {
    $currentWpVersion = $GLOBALS['wp_version'];
}

if (!\is_null($currentWpVersion) && version_compare($currentWpVersion, '4.0', '>=')) {
    require_once __DIR__ . '/wp-toolkit/bootstrap.php';
}
