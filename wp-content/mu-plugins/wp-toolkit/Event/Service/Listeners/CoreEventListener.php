<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\Event\Service\Listeners;

use Webpros\WptkWpPlugin\WpToolkit\Event\Dto\UpgraderOptionsDto;
use Webpros\WptkWpPlugin\WpToolkit\Event\Dto\WordPressEventDto;
use Webpros\WptkWpPlugin\WpToolkit\Event\Service\Notification\WpToolkitNotifier;

class CoreEventListener
{
    /**
     * @var WpToolkitNotifier
     */
    private $notifier;

    /**
     * @return void
     */
    public function __construct(WpToolkitNotifier $notifier)
    {
        $this->notifier = $notifier;
    }

    /**
     * @param \Core_Upgrader $coreUpgrader
     * @param array          $options
     *
     * @return void
     */
    public function upgraderProcessCompleteHook($coreUpgrader, $options)
    {
        $upgraderOptions = UpgraderOptionsDto::fromArray($options);
        if ($upgraderOptions->getAction() !== UpgraderOptionsDto::ACTION_UPDATE || $upgraderOptions->getType() !== UpgraderOptionsDto::TYPE_CORE) {
            return;
        }

        $oldVersion = get_bloginfo('version');

        $versionPath = ABSPATH . WPINC . '/version.php';
        if (!file_exists($versionPath)) {
            return;
        }

        include($versionPath);
        if (!isset($wp_version)) {
            return;
        }

        $newVersion = $wp_version;

        $this->notifier->notify(
            (new WordPressEventDto(WordPressEventDto::EVENT_CORE_UPDATE))
                ->setFrom($oldVersion)
                ->setTo($newVersion)
        );
    }
}
