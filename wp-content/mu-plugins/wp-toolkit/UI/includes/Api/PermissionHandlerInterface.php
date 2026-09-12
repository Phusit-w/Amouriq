<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\UI\Api;

interface PermissionHandlerInterface
{
    /**
     * @return bool
     */
    public function handlePermissions(\WP_REST_Request $request);
}
