<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\UI\Dto;

final class UrlsParams implements \JsonSerializable
{
    /**
     * @var string
     */
    private $restApiUrl;

    /**
     * @var PageParams[]
     */
    private $pages = [];

    /**
     * @param string       $restApiUrl
     * @param PageParams[] $pages
     */
    public function __construct($restApiUrl, $pages = [])
    {
        $this->restApiUrl = $restApiUrl;
        foreach ($pages as $pageParams) {
            $this->pages[] = $pageParams;
        }
    }

    /**
     * @return self
     */
    public function addPage(PageParams $pageParams)
    {
        $this->pages[] = $pageParams;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return [
            'restApiUrl' => $this->restApiUrl,
            'pages' => $this->pages
        ];
    }
}
