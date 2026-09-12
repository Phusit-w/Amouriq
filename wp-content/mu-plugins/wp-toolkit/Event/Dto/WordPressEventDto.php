<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\Event\Dto;

class WordPressEventDto
{
    const EVENT_PLUGIN_INSTALL = 'pluginInstall';
    const EVENT_PLUGIN_UPDATE = 'pluginUpdate';
    const EVENT_PLUGIN_DELETE = 'pluginDelete';
    const EVENT_PLUGIN_ACTIVATE = 'pluginActivate';
    const EVENT_PLUGIN_DEACTIVATE = 'pluginDeactivate';
    const EVENT_THEME_INSTALL = 'themeInstall';
    const EVENT_THEME_UPDATE = 'themeUpdate';
    const EVENT_THEME_DELETE = 'themeDelete';
    const EVENT_THEME_SWITCH = 'themeSwitch';
    const EVENT_CORE_UPDATE = 'coreUpdate';
    const EVENTS = [
        self::EVENT_PLUGIN_INSTALL,
        self::EVENT_PLUGIN_UPDATE,
        self::EVENT_PLUGIN_DELETE,
        self::EVENT_PLUGIN_ACTIVATE,
        self::EVENT_PLUGIN_DEACTIVATE,
        self::EVENT_THEME_INSTALL,
        self::EVENT_THEME_UPDATE,
        self::EVENT_THEME_DELETE,
        self::EVENT_THEME_SWITCH,
        self::EVENT_CORE_UPDATE,
    ];

    /**
     * @var string
     */
    private $event;

    /**
     * @var string|null
     */
    private $slug;

    /**
     * @var string|null
     */
    private $version;

    /**
     * @var string|null
     */
    private $from;

    /**
     * @var string|null
     */
    private $to;

    /**
     * @param string $event
     *
     * @return void
     */
    public function __construct($event)
    {
        if (!\in_array($event, self::EVENTS, true)) {
            throw new \InvalidArgumentException('Invalid event type');
        }

        $this->event = $event;
    }

    /**
     * @return string|null
     */
    public function getSlug()
    {
        return $this->slug;
    }

    /**
     * @param string $slug
     *
     * @return WordPressEventDto
     */
    public function setSlug($slug)
    {
        $this->slug = $slug;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * @param string $version
     *
     * @return WordPressEventDto
     */
    public function setVersion($version)
    {
        $this->version = $version;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getFrom()
    {
        return $this->from;
    }

    /**
     * @param string $from
     *
     * @return WordPressEventDto
     */
    public function setFrom($from)
    {
        $this->from = $from;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getTo()
    {
        return $this->to;
    }

    /**
     * @param string $to
     *
     * @return WordPressEventDto
     */
    public function setTo($to)
    {
        $this->to = $to;

        return $this;
    }

    /**
     * @return string
     */
    public function getEvent()
    {
        return $this->event;
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        $result = [];

        $result['event'] = $this->getEvent();
        if ($this->getSlug() !== null) {
            $result['slug'] = $this->getSlug();
        }
        if ($this->getVersion() !== null) {
            $result['version'] = $this->getVersion();
        }
        if ($this->getFrom() !== null) {
            $result['from'] = $this->getFrom();
        }
        if ($this->getTo() !== null) {
            $result['to'] = $this->getTo();
        }

        return $result;
    }
}
