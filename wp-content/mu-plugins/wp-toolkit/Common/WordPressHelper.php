<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\Common;

use Webpros\WptkWpPlugin\WpToolkit\Common\Dto\PluginDataDto;

class WordPressHelper
{
    public static function upsert_option($option, $value)
    {
        if (!add_option($option, $value)) {
            update_option($option, $value);
        }
    }

    /**
     * @param string $pluginFile
     *
     * @return PluginDataDto|null
     */
    public static function getInstalledPluginMeta($pluginFile)
    {
        $path = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $pluginFile;
        if (!file_exists($path)) {
            return null;
        }

        $pluginData = get_plugin_data($path);
        if (empty($pluginData)) {
            return null;
        }

        return PluginDataDto::fromArrayWithFile($pluginFile, $pluginData);
    }

    /**
     * @param string   $tag
     * @param callable $callback
     * @param int      $priority
     * @param int      $acceptedArgs
     *
     * @return void
     */
    public static function addAction($tag, $callback, $priority, $acceptedArgs)
    {
        // @TODO improve in scope of EXTWPTOOLK-13017
        /**
         * @return void
         */
        $callbackErrorHandler = function () use ($callback, $tag) {
            try {
                \call_user_func_array($callback, \func_get_args());
            } catch (\Exception $e) {
                error_log("WP Toolkit error in action hook callback $tag: {$e->getMessage()}");
            }
        };

        add_action($tag, $callbackErrorHandler, $priority, $acceptedArgs);
    }

    /**
     * Function was copied from https://github.com/WordPress/wordpress-develop/blob/6.8.1/src/wp-includes/class-wp-plugin-dependencies.php#L872
     *
     * @param string $pluginFile
     *
     * @return string
     */
    public static function convertFileNameToSlug($pluginFile)
    {
        return strpos($pluginFile, DIRECTORY_SEPARATOR) !== false
            ? \dirname($pluginFile)
            : str_replace('.php', '', $pluginFile);
    }

    /**
     * @param string $minimalVersion
     *
     * @return bool
     */
    public static function isWpVersionSupported($minimalVersion)
    {
        // wp_get_wp_version return an unmodified WordPress version and is available since WordPress 6.7.0
        // @link https://developer.wordpress.org/reference/functions/wp_get_wp_version/
        if (\function_exists('wp_get_wp_version')) {
            $currentWpVersion = wp_get_wp_version();
        } else {
            $currentWpVersion = $GLOBALS['wp_version'];
        }

        return !\is_null($currentWpVersion) && version_compare($currentWpVersion, $minimalVersion, '>=');
    }
}
