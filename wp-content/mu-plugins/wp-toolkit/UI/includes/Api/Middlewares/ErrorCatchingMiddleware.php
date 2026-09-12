<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\UI\Api\Middlewares;

use Webpros\WptkWpPlugin\WpToolkit\UI\Api\Exceptions\HttpException;
use Webpros\WptkWpPlugin\WpToolkit\UI\Api\Helpers\ResponseHelper;
use Webpros\WptkWpPlugin\WpToolkit\UI\Api\RequestHandlerInterface;

class ErrorCatchingMiddleware implements RequestHandlerInterface
{
    /**
     * @var RequestHandlerInterface
     */
    private $nextHandler;

    public function __construct(RequestHandlerInterface $nextHandler)
    {
        $this->nextHandler = $nextHandler;
    }

    /**
     * @return \WP_REST_Response
     */
    public function handleRequest(\WP_REST_Request $request)
    {
        try {
            return $this->nextHandler->handleRequest($request);
        } catch (HttpException $e) {
            return ResponseHelper::metaResponse($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return ResponseHelper::metaResponse($e->getMessage(), 500);
        }
    }
}
