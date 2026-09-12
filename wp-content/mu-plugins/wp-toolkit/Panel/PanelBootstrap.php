<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\Panel;

use Webpros\WptkWpPlugin\WpToolkit\Common\Clients\HttpClientFactory;
use Webpros\WptkWpPlugin\WpToolkit\Common\CliHelper;
use Webpros\WptkWpPlugin\WpToolkit\Common\Services\ApiTokenParser;
use Webpros\WptkWpPlugin\WpToolkit\Common\Services\I18n\FallbackFormatter;
use Webpros\WptkWpPlugin\WpToolkit\Common\Services\I18n\IntlFormatter;
use Webpros\WptkWpPlugin\WpToolkit\Common\Services\I18n\LocaleLoader;
use Webpros\WptkWpPlugin\WpToolkit\Common\Services\I18n\Translator;
use Webpros\WptkWpPlugin\WpToolkit\Panel\Services\PanelInfoService;
use Webpros\WptkWpPlugin\WpToolkit\Panel\UI\BackToPanelMenuIntegration;

class PanelBootstrap
{
    const MODULE_STATUS_OPTION = 'wp-toolkit_panel_status';

    /**
     * @return void
     */
    public static function init()
    {
        if (CliHelper::is_cli() && class_exists(\WP_CLI::class)) {
            \WP_CLI::add_command('wp-toolkit panel', PanelCommand::class);
            return;
        }

        if (!get_option(self::MODULE_STATUS_OPTION, false)) {
            return;
        }

        $localeContainer = LocaleLoader::loadLocale(get_locale(), __DIR__ . '/locales');
        try {
            $messageFormatter = new IntlFormatter();
        } catch (\Exception $e) {
            $messageFormatter = new FallbackFormatter();
        }
        $translator = new Translator($localeContainer, $messageFormatter);

        $panelInfoService = new PanelInfoService(new HttpClientFactory(), new ApiTokenParser());

        $menuIntegration = new BackToPanelMenuIntegration($translator, $panelInfoService);
        add_action('admin_menu', function () use ($menuIntegration) {
            $menuIntegration->register();
        });
    }
}
