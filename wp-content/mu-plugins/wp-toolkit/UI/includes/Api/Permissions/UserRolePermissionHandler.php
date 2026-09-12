<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\UI\Api\Permissions;

use Webpros\WptkWpPlugin\WpToolkit\UI\Api\PermissionHandlerInterface;

class UserRolePermissionHandler implements PermissionHandlerInterface
{
    /**
     * @var string[]
     */
    private $allowedRoles;

    /**
     * @param string[] $allowedRoles
     */
    public function __construct($allowedRoles = [])
    {
        $this->allowedRoles = $allowedRoles;
    }

    /**
     * @return bool
     */
    public function handlePermissions(\WP_REST_Request $request)
    {
        if (empty($this->allowedRoles)) {
            return false;
        }

        $user = wp_get_current_user();
        foreach ($this->allowedRoles as $role) {
            if (\in_array($role, $user->roles)) {
                return true;
            }
        }

        return false;
    }
}
