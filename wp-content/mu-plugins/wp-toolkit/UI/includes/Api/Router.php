<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\UI\Api;

use Webpros\WptkWpPlugin\WpToolkit\Common\Clients\HttpClientFactory;
use Webpros\WptkWpPlugin\WpToolkit\UI\Api\Controllers\AnalyticsSettingsController;
use Webpros\WptkWpPlugin\WpToolkit\UI\Api\Controllers\WpToolkitApiController;
use Webpros\WptkWpPlugin\WpToolkit\UI\Api\Middlewares\ControllerHandlerMiddleware;
use Webpros\WptkWpPlugin\WpToolkit\UI\Api\Middlewares\ErrorCatchingMiddleware;
use Webpros\WptkWpPlugin\WpToolkit\UI\Api\Permissions\UserRolePermissionHandler;

class Router
{
    const READABLE = 'GET';
    const EDITABLE = 'POST, PUT, PATCH';
    const DELETABLE = 'DELETE';
    const ALL_METHODS = 'GET, POST, PUT, PATCH, DELETE';

    /**
     * @return array[]
     */
    public static function getRoutes()
    {
        return [
            [
                'wp-toolkit/api',
                '/analytics/settings',
                self::EDITABLE,
                [new AnalyticsSettingsController(), 'updateOptInStatus'],
                [new UserRolePermissionHandler(['administrator']), 'handlePermissions'],
            ],
            [
                'wp-toolkit/api',
                '/(?P<slug>.+)/',
                self::ALL_METHODS,
                [new WpToolkitApiController(new HttpClientFactory()), 'handleRequest'],
                [new UserRolePermissionHandler(['administrator']), 'handlePermissions'],
            ],
        ];
    }

    /**
     * @return void
     */
    public static function init()
    {
        try {
            $routes = self::getRoutes();
            foreach ($routes as $route) {
                list($nameSpace, $path, $method, $controller, $permissionHandler) = $route;
                self::registerRoute($nameSpace, $path, $method, $controller, $permissionHandler);
            }
        } catch (\Exception $e) {
            error_log('Registering REST routes error: ' . $e->getMessage());
        }
    }

    /**
     * @param callable $controller
     *
     * @return RequestHandlerInterface
     */
    private static function middlewares($controller)
    {
        return new ErrorCatchingMiddleware(
            new ControllerHandlerMiddleware(
                $controller
            )
        );
    }

    /**
     * @param string   $nameSpace
     * @param string   $path
     * @param string   $method
     * @param callable $controller
     * @param callable $permissionHandler
     *
     * @return void
     */
    private static function registerRoute($nameSpace, $path, $method, $controller, $permissionHandler)
    {
        register_rest_route($nameSpace, $path, [
            'methods' => $method,
            'callback' => [self::middlewares($controller), 'handleRequest'],
            'permission_callback' => $permissionHandler,
        ]);
    }
}
