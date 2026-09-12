<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\Login;

class UserProvider
{
    /**
     * @param string $login
     *
     * @return \WP_User|null
     */
    public function getAdminByLogin($login)
    {
        $user = get_user_by('login', $login);
        if (!$user instanceof \WP_User) {
            return null;
        }

        if (!\in_array('administrator', $user->roles)) {
            return null;
        }

        return $user;
    }
}
