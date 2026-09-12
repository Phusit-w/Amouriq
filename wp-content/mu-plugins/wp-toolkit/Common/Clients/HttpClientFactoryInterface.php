<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\Common\Clients;

interface HttpClientFactoryInterface
{
    /**
     * @return HttpClientInterface
     */
    public function getClient();
}
