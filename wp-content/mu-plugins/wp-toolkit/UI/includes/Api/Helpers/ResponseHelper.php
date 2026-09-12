<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\UI\Api\Helpers;

class ResponseHelper
{
    /**
     * @param string|array $body
     * @param int          $statusCode
     *
     * @return \WP_REST_Response
     */
    public static function metaResponse($body, $statusCode = 200)
    {
        return new \WP_REST_Response([
            'meta' => [
                'status' => $statusCode,
                'message' => $body,
            ],
        ], $statusCode);
    }

    /**
     * @param array|null $body
     * @param int        $statusCode
     *
     * @return \WP_REST_Response
     */
    public static function proxyResponse($body, $statusCode = 200)
    {
        return new \WP_REST_Response($body, $statusCode);
    }

    /**
     * @param array $data
     * @param int   $statusCode
     *
     * @return \WP_REST_Response
     */
    public static function success($data = [], $statusCode = 200)
    {
        return new \WP_REST_Response($data, $statusCode);
    }

    /**
     * @param string $message
     * @param int    $statusCode
     *
     * @return \WP_REST_Response
     */
    public static function error($message, $statusCode = 400)
    {
        return new \WP_REST_Response([
            'message' => $message
        ], $statusCode);
    }
}
