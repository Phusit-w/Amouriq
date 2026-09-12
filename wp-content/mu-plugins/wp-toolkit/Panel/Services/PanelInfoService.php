<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\Panel\Services;

use Webpros\WptkWpPlugin\WpToolkit\Common\Clients\HttpClientFactoryInterface;
use Webpros\WptkWpPlugin\WpToolkit\Common\Clients\HttpClientInterface;
use Webpros\WptkWpPlugin\WpToolkit\Common\Models\WpToolkitRequest;
use Webpros\WptkWpPlugin\WpToolkit\Common\Services\ApiTokenParser;

class PanelInfoService
{
    /**
     * Maps WP Toolkit API ports to panel UI ports.
     * cPanel WP Toolkit API runs on 2087, but the panel UI is on 2083.
     * All other known ports (e.g. Plesk 8443) are identical for API and UI.
     *
     * @var array<int, int>
     */
    private static $apiPortToPanelPort = [
        2087 => 2083,
    ];

    /**
     * @var HttpClientFactoryInterface
     */
    private $httpClientFactory;

    /**
     * @var ApiTokenParser
     */
    private $apiTokenParser;

    public function __construct(HttpClientFactoryInterface $httpClientFactory, ApiTokenParser $apiTokenParser)
    {
        $this->httpClientFactory = $httpClientFactory;
        $this->apiTokenParser = $apiTokenParser;
    }

    /**
     * @return string|null
     */
    public function getPanelUrl()
    {
        $url = $this->fetchPanelUrl();
        if ($url !== null) {
            return $url;
        }

        return $this->buildFallbackUrl();
    }

    /**
     * @return string|null
     */
    private function fetchPanelUrl()
    {
        try {
            $response = $this->httpClientFactory->getClient()->request(
                new WpToolkitRequest('/v1/panel-info', HttpClientInterface::METHOD_GET)
            );
        } catch (\Exception $e) {
            error_log('WP Toolkit: Failed to fetch panel info: ' . $e->getMessage());
            return null;
        }

        if ($response->getCode() !== 200) {
            return null;
        }

        $body = $response->getBody();
        if (!\is_array($body) || empty($body['url']) || !\is_string($body['url'])) {
            return null;
        }

        $url = rtrim($body['url'], '/') . '/';

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!\in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        return $url;
    }

    /**
     * @return string|null
     */
    private function buildFallbackUrl()
    {
        try {
            $apiToken = $this->apiTokenParser->parse(ApiTokenParser::getTokenFromConfig());
        } catch (\Exception $e) {
            return null;
        }

        $apiUrl = $apiToken->getApiUrl();
        $port = (int)parse_url($apiUrl, PHP_URL_PORT);
        if ($port <= 0) {
            return null;
        }

        $panelPort = isset(self::$apiPortToPanelPort[$port])
            ? self::$apiPortToPanelPort[$port]
            : $port;

        $host = parse_url(home_url(), PHP_URL_HOST);
        if (!\is_string($host) || $host === '') {
            return null;
        }

        $scheme = parse_url($apiUrl, PHP_URL_SCHEME) === 'https' ? 'https' : 'http';

        return $scheme . '://' . $host . ':' . $panelPort . '/';
    }
}
