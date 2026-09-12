<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\Login;

use Webpros\WptkWpPlugin\WpToolkit\Common\CliHelper;

class LoginBootstrap
{
    const MODULE_STATUS_OPTION = 'wp-toolkit_login_status';

    /**
     * @return void
     */
    public static function init()
    {
        $tokenGenerator = new TokenGenerator();
        $tokenStorage = new TokenStorage($tokenGenerator);
        $userProvider = new UserProvider();
        if (CliHelper::is_cli() && class_exists(\WP_CLI::class)) {
            \WP_CLI::add_command('wp-toolkit login', new LoginCommand($tokenGenerator, $tokenStorage, $userProvider));
            return;
        }
        if (!get_option(LoginBootstrap::MODULE_STATUS_OPTION, false)
            || empty($tokenStorage->getTtl())
        ) {
            return;
        }

        (new LoginProcessor($tokenGenerator, $tokenStorage, $userProvider))->init();
    }
}
