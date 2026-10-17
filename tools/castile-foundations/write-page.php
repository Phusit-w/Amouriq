<?php
// usage: wp eval-file write-page.php <json-file>
// Writes page 5309 _elementor_data with a raw $wpdb->update (no wp_slash, never update_post_meta) and clears caches.
global $wpdb;
$file = $args[0];
$json = file_get_contents($file);
if (json_decode($json, true) === null) { WP_CLI::error('invalid JSON'); }
$n = $wpdb->update($wpdb->postmeta, ['meta_value' => $json], ['post_id' => 5309, 'meta_key' => '_elementor_data']);
WP_CLI::log("rows updated: " . var_export($n, true));
$wpdb->delete($wpdb->postmeta, ['post_id' => 5309, 'meta_key' => '_elementor_css']);
clean_post_cache(5309);
wp_cache_flush();
if (class_exists('\Elementor\Plugin')) { \Elementor\Plugin::$instance->files_manager->clear_cache(); }
WP_CLI::success('written, caches cleared');
