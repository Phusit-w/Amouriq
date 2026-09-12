<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\Common;

use Webpros\WptkWpPlugin\WpToolkit\Common\Services\ApiTokenParser;

class Installation
{
    /**
     * @var int|null
     */
    private $installationId;

    /**
     * @var Installation|null
     */
    private static $instance = null;

    private function __construct()
    {
        try {
            $token = (new ApiTokenParser())->parse(ApiTokenParser::getTokenFromConfig());
            $this->installationId = $token->getInstallationId();
        } catch (\RuntimeException $e) {
            $this->installationId = null;
        }
    }

    /**
     * @return Installation
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @return int|null
     */
    public function getInstallationId()
    {
        return $this->installationId;
    }
}
