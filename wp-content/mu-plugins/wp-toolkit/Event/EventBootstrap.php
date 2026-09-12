<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\Event;

use Webpros\WptkWpPlugin\WpToolkit\Common\Clients\HttpClientFactory;
use Webpros\WptkWpPlugin\WpToolkit\Common\CliHelper;
use Webpros\WptkWpPlugin\WpToolkit\Common\WordPressHelper;
use Webpros\WptkWpPlugin\WpToolkit\Event\Service\Listeners\CoreEventListener;
use Webpros\WptkWpPlugin\WpToolkit\Event\Service\Listeners\PluginEventListener;
use Webpros\WptkWpPlugin\WpToolkit\Event\Service\Listeners\ThemeEventListener;
use Webpros\WptkWpPlugin\WpToolkit\Event\Service\Notification\WpToolkitNotifier;

class EventBootstrap
{
    const MODULE_STATUS_OPTION = 'wp-toolkit_event_status';
    const STORAGE_FILE_NAME = '.wp-toolkit-event-stash';
    const WAF_REQUEST_BLOCKED_EVENT = 'WAF_REQUEST_BLOCKED_EVENT';

    /**
     * @return void
     * @throws \Exception
     */
    public static function init()
    {
        // @TODO move into the module
        add_action('wp-toolkit_waf_http_request_action_taken', [__CLASS__, 'storeWafRequest'], 10, 1);

        if (CliHelper::is_cli() && class_exists(\WP_CLI::class)) {
            \WP_CLI::add_command('wp-toolkit event', EventCommand::class);
            return;
        }

        if (!get_option(EventBootstrap::MODULE_STATUS_OPTION, false)) {
            return;
        }

        self::registerWordpressEvents();
    }

    /**
     * @param string $vulnerabilityId
     *
     * @return void
     */
    public static function storeWafRequest($vulnerabilityId)
    {
        self::store(self::WAF_REQUEST_BLOCKED_EVENT, [
            'vulnerabilityId' => $vulnerabilityId,
        ]);
    }

    /**
     * @param string $event
     * @param array  $body
     *
     * @return void
     */
    public static function store($event, $body)
    {
        $filePath = join(DIRECTORY_SEPARATOR, [
            WP_CONTENT_DIR,
            self::STORAGE_FILE_NAME,
        ]);

        $data = [
            'event' => $event,
            'body' => $body,
            'timestamp' => time(),
        ];

        $encoded = json_encode($data);
        if ($encoded === false) {
            error_log('Failed to store event, unable to encode JSON');
            return;
        }
        file_put_contents($filePath, $encoded . PHP_EOL, FILE_APPEND);
    }

    /**
     * @return void
     */
    public static function registerWordpressEvents()
    {
        $notifier = new WpToolkitNotifier(new HttpClientFactory());

        $pluginEventListener = new PluginEventListener($notifier);
        WordPressHelper::addAction('activated_plugin', [$pluginEventListener, 'activatedPluginHook'], 10, 2);
        WordPressHelper::addAction('deactivated_plugin', [$pluginEventListener, 'deactivatedPluginHook'], 10, 2);
        WordPressHelper::addAction('deleted_plugin', [$pluginEventListener, 'deletedPluginHook'], 10, 2);
        WordPressHelper::addAction('upgrader_pre_install', [$pluginEventListener, 'upgraderPreUpdateHook'], 10, 2);
        WordPressHelper::addAction('upgrader_process_complete', [$pluginEventListener, 'upgraderProcessCompleteHook'], 10, 2);

        $themeEventListener = new ThemeEventListener($notifier);
        WordPressHelper::addAction('switch_theme', [$themeEventListener, 'switchedThemeHook'], 10, 2);
        // Since WordPress 5.8.0
        WordPressHelper::addAction('deleted_theme', [$themeEventListener, 'deletedThemeHook'], 10, 2);
        WordPressHelper::addAction('upgrader_pre_install', [$themeEventListener, 'upgraderPreUpdateHook'], 10, 2);
        WordPressHelper::addAction('upgrader_process_complete', [$themeEventListener, 'upgraderProcessCompleteHook'], 10, 2);

        $coreEventListener = new CoreEventListener($notifier);
        WordPressHelper::addAction('upgrader_process_complete', [$coreEventListener, 'upgraderProcessCompleteHook'], 10, 2);

        WordPressHelper::addAction('shutdown', [$notifier, 'flush'], 10, 0);
    }
}
