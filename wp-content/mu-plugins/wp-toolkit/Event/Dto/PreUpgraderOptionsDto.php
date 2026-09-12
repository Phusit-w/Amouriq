<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\Event\Dto;

class PreUpgraderOptionsDto
{
    /**
     * @var string|null
     */
    private $theme;

    /**
     * @var string|null
     */
    private $plugin;

    /**
     * @return string|null
     */
    public function getTheme()
    {
        return $this->theme;
    }

    /**
     * @param string|null $theme
     *
     * @return void
     */
    public function setTheme($theme)
    {
        $this->theme = $theme;
    }

    /**
     * @return string|null
     */
    public function getPlugin()
    {
        return $this->plugin;
    }

    /**
     * @param string|null $plugin
     *
     * @return void
     */
    public function setPlugin($plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * @return PreUpgraderOptionsDto|null
     */
    public static function fromArray(array $options)
    {
        if (!\is_string(isset($options['theme']) ? $options['theme'] : null)
            && !\is_string(isset($options['plugin']) ? $options['plugin'] : null)
        ) {
            return null;
        }

        $preUpgraderOptions = new PreUpgraderOptionsDto();
        if (isset($options['plugin'])) {
            $preUpgraderOptions->setPlugin($options['plugin']);
        } elseif (isset($options['theme'])) {
            $preUpgraderOptions->setTheme($options['theme']);
        }

        return $preUpgraderOptions;
    }
}
