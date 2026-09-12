<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\UI\Api;

use Exception;
use Webpros\WptkWpPlugin\WpToolkit\UI\Api\Exceptions\HttpException;

interface RequestHandlerInterface
{
    /**
     * @return \WP_REST_Response
     * @throws HttpException
     * @throws Exception
     */
    public function handleRequest(\WP_REST_Request $request);
}
