<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\Common\Clients;

use Webpros\WptkWpPlugin\WpToolkit\Common\Models\WpToolkitRequest;
use Webpros\WptkWpPlugin\WpToolkit\Common\Models\WpToolkitResponse;
use Webpros\WptkWpPlugin\WpToolkit\UI\Api\Exceptions\BadRequestException;
use Webpros\WptkWpPlugin\WpToolkit\UI\Api\Exceptions\ServerException;

interface HttpClientInterface
{
    const METHOD_GET = 'GET';
    const METHOD_POST = 'POST';
    const METHOD_PUT = 'PUT';
    const METHOD_DELETE = 'DELETE';
    const METHOD_PATCH = 'PATCH';

    /**
     * @return WpToolkitResponse
     * @throws ServerException
     * @throws BadRequestException
     */
    public function request(WpToolkitRequest $request);
}
