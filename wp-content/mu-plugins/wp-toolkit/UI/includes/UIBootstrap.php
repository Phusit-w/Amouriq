<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\UI;

use Webpros\WptkWpPlugin\WpToolkit\Common\CliHelper;
use Webpros\WptkWpPlugin\WpToolkit\Common\Services\ApiTokenParser;
use Webpros\WptkWpPlugin\WpToolkit\Common\Services\I18n\FallbackFormatter;
use Webpros\WptkWpPlugin\WpToolkit\Common\Services\I18n\IntlFormatter;
use Webpros\WptkWpPlugin\WpToolkit\Common\Services\I18n\LocaleLoader;
use Webpros\WptkWpPlugin\WpToolkit\Common\Services\I18n\Translator;
use Webpros\WptkWpPlugin\WpToolkit\Common\Services\I18n\TranslatorInterface;
use Webpros\WptkWpPlugin\WpToolkit\Common\WordPressHelper;
use Webpros\WptkWpPlugin\WpToolkit\UI\Api\Controllers\AnalyticsSettingsController;
use Webpros\WptkWpPlugin\WpToolkit\UI\Api\Router;
use Webpros\WptkWpPlugin\WpToolkit\UI\Dto\AnalyticsParams;
use Webpros\WptkWpPlugin\WpToolkit\UI\Dto\PageParams;
use Webpros\WptkWpPlugin\WpToolkit\UI\Dto\PluginInitialParams;
use Webpros\WptkWpPlugin\WpToolkit\UI\Dto\UrlsParams;

final class UIBootstrap
{
    const MINIMAL_SUPPORTED_WP_VERSION = '4.4';
    const MODULE_PATH_SLUG = '/wp-toolkit/UI';
    const PLUGIN_MAIN_PAGE_SLUG = 'wp-toolkit';
    const PLUGIN_SETTINGS_PAGE_SLUG = 'wp-toolkit-settings';
    const PLUGIN_AUTO_UPDATES_SETTINGS_PAGE_SLUG = 'wp-toolkit-auto-updates-settings';
    const PLUGIN_SUBSCRIPTIONS_PAGE_SLUG = 'wp-toolkit-subscriptions';
    const PLUGIN_SECURITY_MEASURES_PAGE_SLUG = 'wp-toolkit-security-measures';
    const WP_OPTION_MODULE_STATUS = 'wp-toolkit_ui_status';
    const WP_OPTION_VIRTUAL_PATCHES_SUBSCRIPTION_GUID = 'virtual_patches_subscription_guid';

    /**
     * @var Translator
     */
    private static $translator;

    /**
     * @return void
     * @throws \Exception
     */
    public static function init()
    {
        if (CliHelper::is_cli() && class_exists(\WP_CLI::class)) {
            \WP_CLI::add_command('wp-toolkit ui', UICommand::class);
            return;
        }

        if (!WordPressHelper::isWpVersionSupported(self::MINIMAL_SUPPORTED_WP_VERSION)) {
            return;
        }

        if (!get_option(UIBootstrap::WP_OPTION_MODULE_STATUS, false)) {
            return;
        }

        add_action('rest_api_init', function () {
            Router::init();
        });

        $localeContainer = LocaleLoader::loadLocale(get_locale(), __DIR__ . '/locales');
        try {
            $messageFormatter = new IntlFormatter();
        } catch (\Exception $e) {
            // Fallback to a basic formatter if the intl extension is not available
            $messageFormatter = new FallbackFormatter();
        }
        self::$translator = new Translator($localeContainer, $messageFormatter);

        $config = Config::getInstance();
        (new AdminMenuIntegration(self::$translator))->init($config);
        (new DashboardIntegration(self::$translator))->init($config);
    }

    /**
     * @return void
     */
    public static function enqueueScripts(Config $config)
    {
        $scriptHandle = 'wp-toolkit-script';
        $jsBundleSlug = self::MODULE_PATH_SLUG . '/admin/js/main.bundle.js';
        self::registerAndEnqueueScript($config, $scriptHandle, $jsBundleSlug);
    }

    /**
     * @return void
     */
    public static function enqueueStyles()
    {
        $styleHandle = 'wp-toolkit-style';
        $cssBundleSlug = self::MODULE_PATH_SLUG . '/admin/css/host.css';
        self::registerAndEnqueueStyle($styleHandle, $cssBundleSlug);
    }

    /**
     * @return void
     */
    public static function enqueueDashboardScripts(Config $config)
    {
        $scriptHandle = 'wp-toolkit-dashboard-script';
        $jsBundleSlug = self::MODULE_PATH_SLUG . '/admin/js/widgets.bundle.js';
        self::registerAndEnqueueScript($config, $scriptHandle, $jsBundleSlug);
    }

    /**
     * @return void
     */
    public static function enqueueDashboardStyles()
    {
        $styleHandle = 'wp-toolkit-dashboard-style';
        $cssBundleSlug = self::MODULE_PATH_SLUG . '/admin/css/dashboardHost.css';
        self::registerAndEnqueueStyle($styleHandle, $cssBundleSlug);
    }

    /**
     * @param string $styleHandle
     * @param string $cssBundleSlug
     *
     * @return void
     */
    private static function registerAndEnqueueStyle($styleHandle, $cssBundleSlug)
    {
        wp_enqueue_style(
            $styleHandle,
            WPMU_PLUGIN_URL . $cssBundleSlug,
            [],
            (string)filemtime(WPMU_PLUGIN_DIR . $cssBundleSlug)
        );
    }

    /**
     * @return PluginInitialParams
     */
    private static function prepareInitialParams(Config $config)
    {
        $analyticsParams = new AnalyticsParams(
            (bool) get_option(AnalyticsSettingsController::OPTION_NAME, true),
            self::getCustomAnalyticsKey(),
            self::getCustomAnalyticsHost()
        );

        $urlParams = new UrlsParams(
            rest_url('/wp-toolkit/api'),
            [
                new PageParams(
                    'vulnerabilityPage',
                    menu_page_url(self::PLUGIN_MAIN_PAGE_SLUG, false),
                    AdminMenuIntegration::isPageActive(AdminMenuIntegration::PLUGIN_MAIN_PAGE_ID)
                ),
                new PageParams(
                    'autoUpdatesSettingsPage',
                    menu_page_url(self::PLUGIN_AUTO_UPDATES_SETTINGS_PAGE_SLUG, false),
                    AdminMenuIntegration::isPageActive(AdminMenuIntegration::PLUGIN_AUTO_UPDATES_SETTINGS_PAGE_ID)
                ),
                new PageParams(
                    'subscriptionsPage',
                    menu_page_url(self::PLUGIN_SUBSCRIPTIONS_PAGE_SLUG, false),
                    AdminMenuIntegration::isPageActive(AdminMenuIntegration::PLUGIN_SUBSCRIPTIONS_PAGE_ID)
                ),
                new PageParams(
                    'settingsPage',
                    menu_page_url(self::PLUGIN_SETTINGS_PAGE_SLUG, false),
                    AdminMenuIntegration::isPageActive(AdminMenuIntegration::PLUGIN_SETTINGS_PAGE_ID)
                ),
                new PageParams(
                    'securityMeasuresPage',
                    menu_page_url(self::PLUGIN_SECURITY_MEASURES_PAGE_SLUG, false),
                    AdminMenuIntegration::isPageActive(AdminMenuIntegration::PLUGIN_SECURITY_MEASURES_PAGE_ID)
                ),
            ]
        );

        if (!self::$translator instanceof TranslatorInterface) {
            throw new \RuntimeException('Translator is not initialized');
        }

        $pluginInitialParams = new PluginInitialParams(
            wp_create_nonce('wp_rest'),
            self::isIntegrationPluginDebug(),
            self::$translator->getLocaleContainer(),
            $analyticsParams,
            $urlParams,
            $config,
            null,
            null
        );

        try {
            $apiToken = (new ApiTokenParser())->parse(ApiTokenParser::getTokenFromConfig());
            $pluginInitialParams->setInstallationId($apiToken->getInstallationId());
            $pluginInitialParams->setTokenIssuedAt($apiToken->getNotBefore());
            $analyticsParams->setState(self::getAnalyticsState($apiToken->getAnalyticsId()));
            $analyticsParams->setId($apiToken->getAnalyticsId());
        } catch (\RuntimeException $e) {
            error_log($e->getMessage());
        }

        $subscriptionGuid = get_option(self::WP_OPTION_VIRTUAL_PATCHES_SUBSCRIPTION_GUID) ?: null;
        if (\is_string($subscriptionGuid)) {
            $pluginInitialParams->setVirtualPatchesSubscriptionGuid($subscriptionGuid);
        }

        return $pluginInitialParams;
    }

    /**
     * @param string $scriptHandle Script identifier
     * @param string $jsBundleSlug Relative path to the script file
     *
     * @return void
     */
    private static function registerAndEnqueueScript(Config $config, $scriptHandle, $jsBundleSlug)
    {
        wp_register_script(
            $scriptHandle,
            WPMU_PLUGIN_URL . $jsBundleSlug,
            [],
            (string)filemtime(WPMU_PLUGIN_DIR . $jsBundleSlug),
            true
        );

        $pluginInitialParams = self::prepareInitialParams($config);

        add_filter('script_loader_tag', function ($tag, $handle) use ($scriptHandle, $pluginInitialParams) {
            if ($scriptHandle === $handle) {
                $jsonObject = json_encode($pluginInitialParams);
                $inlineScript = <<<HTML
                    <script>window.wpToolkitApp = {$jsonObject};</script>
HTML;
                return $inlineScript . $tag;
            }
            return $tag;
        }, 10, 2);

        wp_enqueue_script($scriptHandle);
    }

    /**
     * @param ?string $analyticsId
     *
     * @return bool
     */
    private static function getAnalyticsState($analyticsId)
    {
        return $analyticsId !== null && $analyticsId !== '';
    }

    /**
     * @return string | null
     */
    private static function getCustomAnalyticsHost()
    {
        return \defined('WP_TOOLKIT_ANALYTICS_HOST') ? \WP_TOOLKIT_ANALYTICS_HOST : null;
    }

    /**
     * @return string | null
     */
    private static function getCustomAnalyticsKey()
    {
        return \defined('WP_TOOLKIT_ANALYTICS_KEY') ? \WP_TOOLKIT_ANALYTICS_KEY : null;
    }

    /**
     * @return bool
     */
    private static function isIntegrationPluginDebug()
    {
        return \defined('WP_TOOLKIT_DEBUG') && filter_var(\WP_TOOLKIT_DEBUG, FILTER_VALIDATE_BOOLEAN);
    }
}
