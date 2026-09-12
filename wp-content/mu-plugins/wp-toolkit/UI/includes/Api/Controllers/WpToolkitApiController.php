<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\UI\Api\Controllers;

use Webpros\WptkWpPlugin\WpToolkit\Common\Clients\HttpClientFactoryInterface;
use Webpros\WptkWpPlugin\WpToolkit\Common\Models\WpToolkitRequest;
use Webpros\WptkWpPlugin\WpToolkit\UI\Api\Exceptions\BadRequestException;
use Webpros\WptkWpPlugin\WpToolkit\UI\Api\Helpers\ResponseHelper;

class WpToolkitApiController
{
    /**
     * @var HttpClientFactoryInterface
     */
    private $httpClientFactory;

    public function __construct(HttpClientFactoryInterface $httpClientFactory)
    {
        $this->httpClientFactory = $httpClientFactory;
    }

    /**
     * @return \WP_REST_Response
     * @throws BadRequestException
     * @throws \Webpros\WptkWpPlugin\WpToolkit\UI\Api\Exceptions\ServerException
     * @throws \RuntimeException
     */
    public function handleRequest(\WP_REST_Request $request)
    {
        $response = $this->httpClientFactory->getClient()->request(
            $this->createWpToolkitRequest($request)
        );

        return ResponseHelper::proxyResponse($response->getBody(), $response->getCode());
    }

    /**
     * @return WpToolkitRequest
     * @throws BadRequestException
     */
    private function createWpToolkitRequest(\WP_REST_Request $request)
    {
        $endpoint = $request->get_param('slug');
        if ($endpoint === null) {
            throw new BadRequestException('Unknown wp-toolkit api endpoint');
        }

        $jsonBody = $request->get_json_params() ?: [];
        $query = $request->get_query_params();
        unset($query['rest_route']);
        $wpRequestHeaders = $request->get_headers();

        $method = strtoupper($request->get_method());
        if (isset($wpRequestHeaders['x_wpt_method_override'][0])) {
            $overrideMethod = strtoupper($wpRequestHeaders['x_wpt_method_override'][0]);
            if (!\in_array($overrideMethod, ['PUT', 'PATCH', 'DELETE'], true)) {
                throw new BadRequestException('Invalid X-WPT-Method-Override value');
            }
            $method = $overrideMethod;
        }

        $headers = [];
        if (isset($wpRequestHeaders['accept'][0])) {
            $headers['Accept'] = $wpRequestHeaders['accept'][0];
        }
        if (isset($wpRequestHeaders['x_wpt_ui'][0])) {
            $headers['X-Wpt-Ui'] = $wpRequestHeaders['x_wpt_ui'][0];
        }
        if (isset($wpRequestHeaders['cache_control'][0])) {
            $headers['Cache-Control'] = $wpRequestHeaders['cache_control'][0];
        }
        if (isset($wpRequestHeaders['content_type'][0])) {
            $headers['Content-Type'] = $wpRequestHeaders['content_type'][0];
        }

        return new WpToolkitRequest($endpoint, $method, $jsonBody, $headers, $query);
    }
}
