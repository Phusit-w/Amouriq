<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\Common\Models;

class ApiToken
{
    /**
     * @var int
     */
    private $installationId;

    /**
     * @var string
     */
    private $apiUrl;

    /**
     * @var ?string
     */
    private $analyticsId;

    /**
     * @var float
     */
    private $notBefore;

    /**
     * @param int     $installationId
     * @param string  $apiUrl
     * @param ?string $analyticsId
     * @param float   $notBefore
     */
    public function __construct(
        $installationId,
        $apiUrl,
        $analyticsId,
        $notBefore
    ) {
        $this->installationId = $installationId;
        $this->apiUrl = $apiUrl;
        $this->analyticsId = $analyticsId;
        $this->notBefore = $notBefore;
    }

    /**
     * @return int
     */
    public function getInstallationId()
    {
        return $this->installationId;
    }

    /**
     * @return string
     */
    public function getApiUrl()
    {
        return $this->apiUrl;
    }

    /**
     * @return ?string
     */
    public function getAnalyticsId()
    {
        return $this->analyticsId;
    }

    /**
     * @return float
     */
    public function getNotBefore()
    {
        return $this->notBefore;
    }
}
