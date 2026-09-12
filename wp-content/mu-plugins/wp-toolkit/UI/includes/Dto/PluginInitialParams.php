<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\UI\Dto;

use Webpros\WptkWpPlugin\WpToolkit\Common\Services\I18n\LocaleContainer;
use Webpros\WptkWpPlugin\WpToolkit\UI\Config;

final class PluginInitialParams implements \JsonSerializable
{
    /**
     * @var ?string
     */
    private $nonce;

    /**
     * @var bool
     */
    private $debug = false;

    /**
     * @var ?int
     */
    private $installationId;

    /**
     * @var ?float
     */
    private $tokenIssuedAt;

    /**
     * @var AnalyticsParams
     */
    private $analytics;

    /**
     * @var UrlsParams
     */
    private $urls;

    /**
     * @var LocaleParams
     */
    private $locale;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var ?string
     */
    private $virtualPatchesSubscriptionGuid;

    /**
     * @param string $nonce
     * @param bool   $debug
     * @param ?int   $installationId
     * @param ?float $tokenIssuedAt
     */
    public function __construct(
        $nonce,
        $debug,
        LocaleContainer $localeContainer,
        AnalyticsParams $analytics,
        UrlsParams $urls,
        Config $config,
        $installationId = null,
        $tokenIssuedAt = null
    ) {
        $this->nonce = $nonce;
        $this->debug = $debug;

        $this->locale = new LocaleParams(
            $localeContainer->getLocaleCode(),
            $localeContainer->getMessagesFrontend()
        );

        $this->analytics = $analytics;
        $this->urls = $urls;
        $this->config = $config;

        $this->installationId = $installationId;
        $this->tokenIssuedAt = $tokenIssuedAt;
    }

    /**
     * @param int $installationId
     *
     * @return self
     */
    public function setInstallationId($installationId)
    {
        $this->installationId = $installationId;

        return $this;
    }

    /**
     * @param float $tokenIssuedAt
     *
     * @return self
     */
    public function setTokenIssuedAt($tokenIssuedAt)
    {
        $this->tokenIssuedAt = $tokenIssuedAt;

        return $this;
    }

    /**
     * @param ?string $virtualPatchesSubscriptionGuid
     *
     * @return self
     */
    public function setVirtualPatchesSubscriptionGuid($virtualPatchesSubscriptionGuid)
    {
        $this->virtualPatchesSubscriptionGuid = $virtualPatchesSubscriptionGuid;

        return $this;
    }

    /**
     * @return self
     */
    public function setAnalytics(AnalyticsParams $analytics)
    {
        $this->analytics = $analytics;

        return $this;
    }

    /**
     * @return self
     */
    public function setUrls(UrlsParams $urls)
    {
        $this->urls = $urls;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        $result = [
            'nonce' => $this->nonce,
            'locale' => $this->locale,
            'analytics' => $this->analytics,
            'urls' => $this->urls,
        ];

        if ($this->debug) {
            $result['debug'] = $this->debug;
        }

        if ($this->installationId !== null) {
            $result['installationId'] = $this->installationId;
        }

        if ($this->tokenIssuedAt !== null) {
            $result['tokenIssuedAt'] = $this->tokenIssuedAt;
        }

        if ($this->virtualPatchesSubscriptionGuid !== null) {
            $result['virtualPatchesSubscriptionGuid'] = $this->virtualPatchesSubscriptionGuid;
        }

        $result['config'] = $this->config;

        return $result;
    }
}
