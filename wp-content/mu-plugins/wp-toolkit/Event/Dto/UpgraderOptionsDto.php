<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\Event\Dto;

class UpgraderOptionsDto
{
    const ACTION_INSTALL = 'install';
    const ACTION_UPDATE = 'update';
    const TYPE_PLUGIN = 'plugin';
    const TYPE_THEME = 'theme';
    const TYPE_CORE = 'core';

    private function __construct()
    {
    }

    /**
     * @var string|null
     */
    private $action;

    /**
     * @var string|null
     */
    private $type;

    /**
     * @var bool|null
     */
    private $bulk;

    /**
     * @var string[]|null
     */
    private $plugins;

    /**
     * @var string[]|null
     */
    private $themes;

    /**
     * @return string|null
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * @param string|null $action
     *
     * @return void
     */
    public function setAction($action)
    {
        $this->action = $action;
    }

    /**
     * @return string|null
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string|null $type
     *
     * @return void
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * @return bool|null
     */
    public function getBulk()
    {
        return $this->bulk;
    }

    /**
     * @param bool|null $bulk
     *
     * @return void
     */
    public function setBulk($bulk)
    {
        $this->bulk = $bulk;
    }

    /**
     * @return string[]|null
     */
    public function getPlugins()
    {
        return $this->plugins;
    }

    /**
     * @param string[]|null $plugins
     *
     * @return void
     */
    public function setPlugins($plugins)
    {
        $this->plugins = $plugins;
    }

    /**
     * @return string[]|null
     */
    public function getThemes()
    {
        return $this->themes;
    }

    /**
     * @param string[]|null $themes
     *
     * @return void
     */
    public function setThemes($themes)
    {
        $this->themes = $themes;
    }

    /**
     * @return UpgraderOptionsDto
     */
    public static function fromArray(array $options)
    {
        $upgraderOptions = new UpgraderOptionsDto();
        if (isset($options['action']) && \is_string($options['action'])) {
            $upgraderOptions->setAction($options['action']);
        }
        if (isset($options['type']) && \is_string($options['type'])) {
            $upgraderOptions->setType($options['type']);
        }
        if (isset($options['bulk']) && \is_bool($options['bulk'])) {
            $upgraderOptions->setBulk($options['bulk']);
        }
        if (isset($options['plugins']) && \is_array($options['plugins'])) {
            $upgraderOptions->setPlugins($options['plugins']);
        }
        if (isset($options['themes']) && \is_array($options['themes'])) {
            $upgraderOptions->setThemes($options['themes']);
        }

        return $upgraderOptions;
    }
}
