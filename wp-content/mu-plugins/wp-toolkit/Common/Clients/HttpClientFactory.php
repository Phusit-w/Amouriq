<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\Common\Clients;

use Webpros\WptkWpPlugin\WpToolkit\Common\Services\ApiTokenParser;

class HttpClientFactory implements HttpClientFactoryInterface
{
    /**
     * @return HttpClientInterface
     */
    public function getClient()
    {
        $apiTokenParser = new ApiTokenParser();
        return new PleskHttpClient($apiTokenParser->parse(ApiTokenParser::getTokenFromConfig()));
    }
}
