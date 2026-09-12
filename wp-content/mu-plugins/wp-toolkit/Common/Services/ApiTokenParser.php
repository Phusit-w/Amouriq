<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\Common\Services;

use Webpros\WptkWpPlugin\WpToolkit\Common\Models\ApiToken;

class ApiTokenParser
{
    /**
     * @param string $token
     *
     * @return ApiToken
     * @throws \RuntimeException
     */
    public function parse($token)
    {
        if (!\is_string($token)) {
            throw new \RuntimeException('Token should be of type string, ' . \gettype($token) . ' given');
        }

        $parts = explode('.', $token);
        if (\count($parts) !== 3) {
            throw new \RuntimeException("Token should have 3 parts, " . \count($parts) . " given");
        }

        $payload = base64_decode(strtr($parts[1], '-_', '+/'));
        if ($payload === false) {
            throw new \RuntimeException("Failed to base64 decode token data: " . $parts[1]);
        }

        $jsonData = json_decode($payload, true);
        if ($jsonData === false) {
            throw new \RuntimeException("Failed to json decode token data: " . $payload);
        }

        if (!isset($jsonData['installationId'])) {
            throw new \RuntimeException("Failed to get installationId from token data");
        }

        if (!isset($jsonData['apiUrl'])) {
            throw new \RuntimeException("Failed to get apiUrl from token data");
        }

        if (!isset($jsonData['nbf'])) {
            throw new \RuntimeException("Failed to get notBefore from token data");
        }

        return new ApiToken(
            (int)$jsonData['installationId'],
            (string)$jsonData['apiUrl'],
            isset($jsonData['analyticsId']) ? (string) $jsonData['analyticsId'] : null,
            (float)$jsonData['nbf']
        );
    }

    /**
     * @return string
     * @throws \RuntimeException
     */
    public static function getTokenFromConfig()
    {
        if (!\defined('WP_TOOLKIT_API_TOKEN')) {
            throw new \RuntimeException('Wp-toolkit API token is not defined');
        }

        return \WP_TOOLKIT_API_TOKEN;
    }
}
