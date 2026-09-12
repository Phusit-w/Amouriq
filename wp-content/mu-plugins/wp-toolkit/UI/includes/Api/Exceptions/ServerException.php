<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\UI\Api\Exceptions;

class ServerException extends HttpException
{
    /**
     * @param string $message
     */
    public function __construct($message = "")
    {
        parent::__construct($message, 500);
    }
}
