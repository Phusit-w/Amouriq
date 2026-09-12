<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\Common\Clients;

use Webpros\WptkWpPlugin\WpToolkit\Common\Models\ApiToken;
use Webpros\WptkWpPlugin\WpToolkit\Common\Models\WpToolkitRequest;
use Webpros\WptkWpPlugin\WpToolkit\Common\Models\WpToolkitResponse;
use Webpros\WptkWpPlugin\WpToolkit\Common\Services\ApiTokenParser;
use Webpros\WptkWpPlugin\WpToolkit\UI\Api\Exceptions\ServerException;

class PleskHttpClient implements HttpClientInterface
{
    const PLESK_API_TIMEOUT_SECONDS = 30;

    /**
     * @var ApiToken
     */
    private $apiToken;

    public function __construct(ApiToken $apiToken)
    {
        $this->apiToken = $apiToken;
    }

    /**
     * @return WpToolkitResponse
     * @throws ServerException
     */
    public function request(WpToolkitRequest $request)
    {
        $url = rtrim($this->apiToken->getApiUrl(), '/') . '/' . ltrim($request->getEndpoint(), '/');

        $args = [
            'method' => $request->getMethod(),
            'headers' => array_merge($request->getHeaders(), [
                'Authorization' => 'Bearer ' . ApiTokenParser::getTokenFromConfig(),
            ]),
            'sslverify' => false,
            'timeout' => self::PLESK_API_TIMEOUT_SECONDS,
        ];

        if (!empty($request->getBody())) {
            $args['body'] = $request->getBody();
            if ($args['headers']['Content-Type'] === 'application/json') {
                $args['body'] = json_encode($request->getBody());
            }

        }
        if (!empty($request->getQuery())) {
            $url = add_query_arg($request->getQuery(), $url);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            throw new \RuntimeException('Wordpress request error: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        return new WpToolkitResponse($body, $code);
    }
}
