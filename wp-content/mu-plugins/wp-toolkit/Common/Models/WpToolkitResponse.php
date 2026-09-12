<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\Common\Models;

class WpToolkitResponse
{
    /**
     * @var array
     */
    private $body;

    /**
     * @var int
     */
    private $code;

    public function __construct($body, $code)
    {
        $this->body = $body;
        $this->code = $code;
    }

    /**
     * @return array
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * @return int
     */
    public function getCode()
    {
        return $this->code;
    }
}
