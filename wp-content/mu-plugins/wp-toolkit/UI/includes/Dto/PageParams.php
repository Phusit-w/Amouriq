<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\UI\Dto;

final class PageParams implements \JsonSerializable
{
    /**
     * @var string
     */
    private $type;

    /**
     * @var string
     */
    private $url;

    /**
     * @var bool
     */
    private $active;

    /**
     * @param string $type
     * @param string $url
     * @param bool   $isActive
     */
    public function __construct($type, $url, $isActive)
    {
        $this->type = $type;
        $this->url = $url;
        $this->active = $isActive;
    }

    /**
     * @return array<string, mixed>
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return [
            'type' => $this->type,
            'url' => $this->url,
            'active' => $this->active,
        ];
    }
}
