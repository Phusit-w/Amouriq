<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\Event\Service\Listeners;

use Webpros\WptkWpPlugin\WpToolkit\Event\Dto\PreUpgraderOptionsDto;
use Webpros\WptkWpPlugin\WpToolkit\Event\Dto\UpgraderOptionsDto;
use Webpros\WptkWpPlugin\WpToolkit\Event\Dto\WordPressEventDto;
use Webpros\WptkWpPlugin\WpToolkit\Event\Service\Notification\WpToolkitNotifier;

class ThemeEventListener
{
    /**
     * @var WpToolkitNotifier
     */
    private $notifier;

    /**
     * @var \WP_Theme[]
     */
    private $themesToUpdate = [];

    /**
     * @return void
     */
    public function __construct(WpToolkitNotifier $notifier)
    {
        $this->notifier = $notifier;
    }

    /**
     * @param string    $newName
     * @param \WP_Theme $newTheme
     *
     * @return void
     */
    public function switchedThemeHook($newName, $newTheme)
    {
        $oldThemeStylesheet = get_option('theme_switched');
        if ($oldThemeStylesheet === false) {
            return;
        }

        $oldTheme = wp_get_theme($oldThemeStylesheet);
        if (!$oldTheme->exists()) {
            return;
        }

        $this->notifier->notify(
            (new WordPressEventDto(WordPressEventDto::EVENT_THEME_SWITCH))
                ->setFrom($oldTheme->get_stylesheet())
                ->setTo($newTheme->get_stylesheet())
        );
    }

    /**
     * Since WordPress 5.8.0
     *
     * @param string $stylesheet
     * @param bool   $deleted
     *
     * @return void
     */
    public function deletedThemeHook($stylesheet, $deleted)
    {
        if (!$deleted) {
            return;
        }

        $this->notifier->notify(
            (new WordPressEventDto(WordPressEventDto::EVENT_THEME_DELETE))
                ->setSlug($stylesheet)
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

        $themeStylesheet = $preUpgraderOptions->getTheme();
        if ($themeStylesheet === null) {
            return;
        }

        $oldTheme = wp_get_theme($themeStylesheet);
        if (!$oldTheme->exists()) {
            return;
        }

        $this->themesToUpdate[$themeStylesheet] = $oldTheme;
    }

    /**
     * @param \Theme_Upgrader $themeUpgrader
     * @param array           $options
     *
     * @return void
     */
    public function upgraderProcessCompleteHook($themeUpgrader, $options)
    {
        $upgraderOptions = UpgraderOptionsDto::fromArray($options);
        if ($upgraderOptions->getType() !== UpgraderOptionsDto::TYPE_THEME) {
            return;
        }

        if ($upgraderOptions->getAction() === UpgraderOptionsDto::ACTION_INSTALL) {
            $theme = $themeUpgrader->theme_info();
            if ($theme === false) {
                return;
            }

            $this->notifier->notify(
                (new WordPressEventDto(WordPressEventDto::EVENT_THEME_INSTALL))
                    ->setSlug($theme->get_stylesheet())
                    ->setVersion($theme->get('Version'))
            );

            return;
        }

        if ($upgraderOptions->getAction() === UpgraderOptionsDto::ACTION_UPDATE) {
            $updateThemes = $upgraderOptions->getThemes();
            if (!\is_array($updateThemes)) {
                return;
            }

            foreach ($updateThemes as $themeStylesheet) {
                $newTheme = wp_get_theme($themeStylesheet);
                if (!$newTheme->exists()) {
                    continue;
                }

                if (!isset($this->themesToUpdate[$themeStylesheet])) {
                    continue;
                }
                $oldTheme = $this->themesToUpdate[$themeStylesheet];

                $oldVersion = $oldTheme->get('Version');
                $newVersion = $newTheme->get('Version');

                if (!\is_string($oldVersion) || !\is_string($newVersion)) {
                    continue;
                }

                $this->notifier->notify(
                    (new WordPressEventDto(WordPressEventDto::EVENT_THEME_UPDATE))
                        ->setSlug($themeStylesheet)
                        ->setFrom($oldVersion)
                        ->setTo($newVersion)
                );
            }
        }
    }
}
