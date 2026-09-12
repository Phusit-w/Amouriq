<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\Event\Service\Listeners;

use Webpros\WptkWpPlugin\WpToolkit\Common\Dto\PluginDataDto;
use Webpros\WptkWpPlugin\WpToolkit\Common\WordPressHelper;
use Webpros\WptkWpPlugin\WpToolkit\Event\Dto\PreUpgraderOptionsDto;
use Webpros\WptkWpPlugin\WpToolkit\Event\Dto\UpgraderOptionsDto;
use Webpros\WptkWpPlugin\WpToolkit\Event\Dto\WordPressEventDto;
use Webpros\WptkWpPlugin\WpToolkit\Event\Service\Notification\WpToolkitNotifier;

class PluginEventListener
{
    /**
     * @var WpToolkitNotifier
     */
    private $notifier;

    /**
     * @var PluginDataDto[]
     */
    private $pluginsToUpdate = [];

    /**
     * @return void
     */
    public function __construct(WpToolkitNotifier $notifier)
    {
        $this->notifier = $notifier;
    }

    /**
     * @param string $pluginFile
     * @param bool   $networkWide
     *
     * @return void
     */
    public function activatedPluginHook($pluginFile, $networkWide)
    {
        $pluginData = WordPressHelper::getInstalledPluginMeta($pluginFile);
        if ($pluginData === null) {
            return;
        }

        $this->notifier->notify(
            (new WordPressEventDto(WordPressEventDto::EVENT_PLUGIN_ACTIVATE))
                ->setSlug($pluginData->getSlug())
                ->setVersion($pluginData->getVersion())
        );
    }

    /**
     * @param string $pluginFile
     * @param bool   $networkWide
     *
     * @return void
     */
    public function deactivatedPluginHook($pluginFile, $networkWide)
    {
        $pluginData = WordPressHelper::getInstalledPluginMeta($pluginFile);
        if ($pluginData === null) {
            return;
        }

        $this->notifier->notify(
            (new WordPressEventDto(WordPressEventDto::EVENT_PLUGIN_DEACTIVATE))
                ->setSlug($pluginData->getSlug())
                ->setVersion($pluginData->getVersion())
        );
    }

    /**
     * @param string $pluginFile
     * @param bool   $deleted
     *
     * @return void
     */
    public function deletedPluginHook($pluginFile, $deleted)
    {
        if (!$deleted) {
            return;
        }

        $this->notifier->notify(
            (new WordPressEventDto(WordPressEventDto::EVENT_PLUGIN_DELETE))
                ->setSlug(WordPressHelper::convertFileNameToSlug($pluginFile))
        );
    }

    /**
     * @param bool|\WP_Error $response
     * @param array          $options
     *
     * @return void
     */
    public function upgraderPreUpdateHook($response, $options)
    {
        if ($response instanceof \WP_Error) {
            return;
        }

        $preUpgraderOptions = PreUpgraderOptionsDto::fromArray($options);
        if ($preUpgraderOptions === null) {
            return;
        }

        $pluginPath = $preUpgraderOptions->getPlugin();
        if ($pluginPath === null) {
            return;
        }

        $pluginData = WordPressHelper::getInstalledPluginMeta($pluginPath);
        if ($pluginData === null) {
            return;
        }

        $this->pluginsToUpdate[$pluginData->getSlug()] = $pluginData;
    }

    /**
     * @param \Plugin_Upgrader $pluginUpgrader
     * @param array            $options
     *
     * @return void
     */
    public function upgraderProcessCompleteHook($pluginUpgrader, $options)
    {
        $upgraderOptions = UpgraderOptionsDto::fromArray($options);
        if ($upgraderOptions->getType() !== UpgraderOptionsDto::TYPE_PLUGIN) {
            return;
        }

        if ($upgraderOptions->getAction() === UpgraderOptionsDto::ACTION_INSTALL) {
            $pluginFile = $pluginUpgrader->plugin_info();
            if ($pluginFile === false) {
                return;
            }

            $pluginData = WordPressHelper::getInstalledPluginMeta($pluginFile);
            if ($pluginData === null) {
                return;
            }

            $this->notifier->notify(
                (new WordPressEventDto(WordPressEventDto::EVENT_PLUGIN_INSTALL))
                    ->setSlug($pluginData->getSlug())
                    ->setVersion($pluginData->getVersion())
            );

            return;
        }

        if ($upgraderOptions->getAction() === UpgraderOptionsDto::ACTION_UPDATE) {
            $pluginFiles = $upgraderOptions->getPlugins();
            if ($pluginFiles === null) {
                return;
            }

            foreach ($pluginFiles as $pluginFile) {
                $newPluginData = WordPressHelper::getInstalledPluginMeta($pluginFile);
                if ($newPluginData === null) {
                    continue;
                }

                if (!isset($this->pluginsToUpdate[$newPluginData->getSlug()])) {
                    continue;
                }
                $oldPluginData = $this->pluginsToUpdate[$newPluginData->getSlug()];

                $this->notifier->notify(
                    (new WordPressEventDto(WordPressEventDto::EVENT_PLUGIN_UPDATE))
                        ->setSlug($newPluginData->getSlug())
                        ->setFrom($oldPluginData->getVersion())
                        ->setTo($newPluginData->getVersion())
                );
            }
        }
    }
}
