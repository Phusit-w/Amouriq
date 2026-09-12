<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\Waf\Extensions;

use Patchstack\Extensions\ExtensionInterface;

class HttpRequestExtension implements ExtensionInterface
{
    private $activeRulesVulnerabilityMap;

    public function __construct(ActiveRulesVulnerabilityMap $activeRulesVulnerabilityMap)
    {
        $this->activeRulesVulnerabilityMap = $activeRulesVulnerabilityMap;
    }

    /**
     * @param int    $ruleId
     * @param string $bodyData
     * @param string $blockType
     *
     * @return void
     */
    public function logRequest($ruleId, $bodyData, $blockType)
    {
        do_action(
            'wp-toolkit_waf_http_request_action_taken',
            $this->activeRulesVulnerabilityMap->getVulnerabilityId($ruleId)
        );
    }

    /**
     * @return false
     */
    public function canBypass($isMuCall)
    {
        return false;
    }

    /**
     * @param int $minutes
     * @param int $blockTime
     * @param int $attempts
     *
     * @return false
     */
    public function isBlocked($minutes, $blockTime, $attempts)
    {
        return false;
    }

    /**
     * @param int $ruleId
     *
     * @return never
     */
    public function forceExit($ruleId)
    {
        exit;
    }

    /**
     * @return string
     */
    public function getIpAddress()
    {
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    }

    /**
     * @return string
     */
    public function getHostName()
    {
        return isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
    }

    /**
     * @param array $whitelistRules
     * @param array $request
     *
     * @return false
     */
    public function isWhitelisted($whitelistRules, $request)
    {
        return false;
    }

    /**
     * @return bool
     */
    public function isFileUploadRequest()
    {
        return isset($_FILES) && \count($_FILES) > 0;
    }
}
