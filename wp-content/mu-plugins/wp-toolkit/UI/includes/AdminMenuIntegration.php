<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\UI;

use Webpros\WptkWpPlugin\WpToolkit\Common\Services\I18n\TranslatorInterface;

final class AdminMenuIntegration
{
    const PLUGIN_MAIN_PAGE_ID = 'main';
    const PLUGIN_VULNERABILITIES_PAGE_ID = 'vulnerabilities';
    const PLUGIN_SETTINGS_PAGE_ID = 'settings';
    const PLUGIN_AUTO_UPDATES_SETTINGS_PAGE_ID = 'autoUpdatesSettings';
    const PLUGIN_SUBSCRIPTIONS_PAGE_ID = 'subscriptions';
    const PLUGIN_SECURITY_MEASURES_PAGE_ID = 'securityMeasures';

    /**
     * @var array<string, bool|string>
     */
    private static $pageIds = [];

    /**
     * @var TranslatorInterface
     */
    private $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    /**
     * @return void
     */
    public function init(Config $config)
    {
        add_action('admin_menu', function () use ($config) {
            $this->addTopLevelMenuItem(
                esc_html($config->getDisplayName()),
                $config->getMenuPosition()
            );
        });
        add_action('admin_enqueue_scripts', function () use ($config) {
            if ($this->isPluginPage()) {
                UIBootstrap::enqueueScripts($config);
                UIBootstrap::enqueueStyles();
            }
        });

        // Remove the update nag message on the plugin's page
        add_action('admin_head', function () {
            if ($this->isPluginPage()) {
                remove_action('admin_notices', 'update_nag', 3);
            }
        }, 10, 0);
    }

    /**
     * @return bool
     */
    private function isPluginPage()
    {
        return self::isPageActive(self::PLUGIN_MAIN_PAGE_ID)
            || self::isPageActive(self::PLUGIN_VULNERABILITIES_PAGE_ID)
            || self::isPageActive(self::PLUGIN_SETTINGS_PAGE_ID)
            || self::isPageActive(self::PLUGIN_AUTO_UPDATES_SETTINGS_PAGE_ID)
            || self::isPageActive(self::PLUGIN_SUBSCRIPTIONS_PAGE_ID)
            || self::isPageActive(self::PLUGIN_SECURITY_MEASURES_PAGE_ID);
    }

    /**
     * @param string $productDisplayName
     * @param ?int   $menuPosition
     *
     * @return void
     */
    public function addTopLevelMenuItem($productDisplayName, $menuPosition)
    {
        self::$pageIds[self::PLUGIN_MAIN_PAGE_ID] = add_menu_page(
            $productDisplayName,
            $productDisplayName,
            'manage_options',
            UIBootstrap::PLUGIN_MAIN_PAGE_SLUG,
            '',
            'dashicons-star-filled',
            $menuPosition
        );

        self::$pageIds[self::PLUGIN_VULNERABILITIES_PAGE_ID] = add_submenu_page(
            UIBootstrap::PLUGIN_MAIN_PAGE_SLUG,
            $this->translator->translate('menu.item.vulnerabilities'),
            $this->translator->translate('menu.item.vulnerabilities'),
            'manage_options',
            UIBootstrap::PLUGIN_MAIN_PAGE_SLUG,
            function () {
                $this->renderApp();
            }
        );

        self::$pageIds[self::PLUGIN_AUTO_UPDATES_SETTINGS_PAGE_ID] = add_submenu_page(
            UIBootstrap::PLUGIN_MAIN_PAGE_SLUG,
            $this->translator->translate('menu.item.autoUpdatesSettings'),
            $this->translator->translate('menu.item.autoUpdatesSettings'),
            'manage_options',
            UIBootstrap::PLUGIN_AUTO_UPDATES_SETTINGS_PAGE_SLUG,
            function () {
                $this->renderApp();
            }
        );

        self::$pageIds[self::PLUGIN_SETTINGS_PAGE_ID] = add_submenu_page(
            UIBootstrap::PLUGIN_MAIN_PAGE_SLUG,
            $this->translator->translate('menu.item.settings'),
            $this->translator->translate('menu.item.settings'),
            'manage_options',
            UIBootstrap::PLUGIN_SETTINGS_PAGE_SLUG,
            function () {
                $this->renderApp();
            }
        );

        self::$pageIds[self::PLUGIN_SUBSCRIPTIONS_PAGE_ID] = add_submenu_page(
            UIBootstrap::PLUGIN_MAIN_PAGE_SLUG,
            $this->translator->translate('menu.item.subscriptions'),
            $this->translator->translate('menu.item.subscriptions'),
            'manage_options',
            UIBootstrap::PLUGIN_SUBSCRIPTIONS_PAGE_SLUG,
            function () {
                $this->renderApp();
            }
        );

        self::$pageIds[self::PLUGIN_SECURITY_MEASURES_PAGE_ID] = add_submenu_page(
            UIBootstrap::PLUGIN_MAIN_PAGE_SLUG,
            $this->translator->translate('menu.item.securityMeasures'),
            $this->translator->translate('menu.item.securityMeasures'),
            'manage_options',
            UIBootstrap::PLUGIN_SECURITY_MEASURES_PAGE_SLUG,
            function () {
                $this->renderApp();
            }
        );
    }

    /**
     * @return void
     */
    public function renderApp()
    {
        $text = $this->translator->translate('plugin.noJsText');
        echo <<<HTML
            <div class="wrap">
                <noscript>{$text}</noscript>
                <div id="wp-toolkit-root"></div>
            </div>
HTML;
    }

    /**
     * @param string $pageId
     *
     * @return bool
     */
    public static function isPageActive($pageId)
    {
        $current_screen = get_current_screen();
        foreach (self::$pageIds as $id => $value) {
            if ($current_screen && $current_screen->id === $value && $pageId === $id) {
                return true;
            }
        }

        return false;
    }
}
