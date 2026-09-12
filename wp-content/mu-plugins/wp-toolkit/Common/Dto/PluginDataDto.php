<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\Common\Dto;

use Webpros\WptkWpPlugin\WpToolkit\Common\WordPressHelper;

class PluginDataDto
{
    /**
     * @param string $slug
     * @param string $name
     * @param string $version
     */
    private function __construct($slug, $name, $version)
    {
        $this->slug = $slug;
        $this->name = $name;
        $this->version = $version;
    }

    /**
     * @var string
     */
    private $slug;

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $version;

    /**
     * @return string
     */
    public function getSlug()
    {
        return $this->slug;
    }

    /**
     * @param string $slug
     *
     * @return void
     */
    public function setSlug($slug)
    {
        $this->slug = $slug;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     *
     * @return void
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * @param string $version
     *
     * @return void
     */
    public function setVersion($version)
    {
        $this->version = $version;
    }

    /**
     * @param string $pluginFile
     *
     * @return PluginDataDto
     */
    public static function fromArrayWithFile($pluginFile, array $data)
    {
        return self::fromArray(
            WordPressHelper::convertFileNameToSlug($pluginFile),
            $data
        );
    }

    /**
     * @param string $slug
     *
     * @return PluginDataDto
     */
    public static function fromArray($slug, array $data)
    {
        $name = isset($data['Name']) ? $data['Name'] : '';
        $name = \is_string($name) ? $name : '';

        $version = isset($data['Version']) ? $data['Version'] : '';
        $version = \is_string($version) ? $version : '';

        return new PluginDataDto(
            $slug,
            $name,
            $version
        );
    }
}
