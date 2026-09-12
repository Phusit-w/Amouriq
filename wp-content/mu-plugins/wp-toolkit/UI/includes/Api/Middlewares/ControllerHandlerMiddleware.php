<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\UI\Api\Middlewares;

use Webpros\WptkWpPlugin\WpToolkit\UI\Api\Exceptions\ServerException;
use Webpros\WptkWpPlugin\WpToolkit\UI\Api\RequestHandlerInterface;

class ControllerHandlerMiddleware implements RequestHandlerInterface
{
    /**
     * @var callable
     */
    private $controller;

    /**
     * @param callable $controller
     */
    public function __construct($controller)
    {
        $this->controller = $controller;
    }

    /**
     * @return \WP_REST_Response
     * @throws ServerException
     */
    public function handleRequest(\WP_REST_Request $request)
    {
        $response = \call_user_func($this->controller, $request);

        if ($response instanceof \WP_REST_Response) {
            return $response;
        }

        throw new ServerException('Response should be of type WP_REST_Response, ' . \get_class($response) . ' given');
    }
}
