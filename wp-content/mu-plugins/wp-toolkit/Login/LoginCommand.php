<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\Login;

use Webpros\WptkWpPlugin\WpToolkit\Common\SwitchableCommand;
use WP_CLI;

class LoginCommand extends SwitchableCommand
{
    const TTL_PARAM = 'ttl';
    const TTL_MAX_VALUE = 300;
    const TTL_MIN_VALUE = 0;
    const TTL_DEFAULT_VALUE = 60;

    /**
     * @var TokenGenerator
     */
    private $tokenGenerator;

    /**
     * @var TokenStorage
     */
    private $storage;

    /**
     * @var UserProvider
     */
    private $userProvider;

    /**
     * @param TokenGenerator $tokenGenerator
     * @param TokenStorage   $tokenStorage
     * @param UserProvider   $userProvider
     */
    public function __construct($tokenGenerator, $tokenStorage, $userProvider)
    {
        $this->tokenGenerator = $tokenGenerator;
        $this->storage = $tokenStorage;
        $this->userProvider = $userProvider;
        parent::__construct();
    }

    protected function getSwitchOption()
    {
        return LoginBootstrap::MODULE_STATUS_OPTION;
    }

    /**
     * @param array $args
     * @param array $assoc_args
     *
     * @return void
     * @throws WP_CLI\ExitException
     */
    public function link($args, $assoc_args)
    {
        $this->ensureCommandEnabled();

        $login = WP_CLI::get_value_from_arg_or_stdin($args, 0);
        $login = WP_CLI::read_value($login, $assoc_args);
        $user = $this->userProvider->getAdminByLogin($login);
        if (!$user instanceof \WP_User) {
            WP_CLI::error(\sprintf('Login name "%s" is not an administrator login', $login));
        }

        $ttl = self::TTL_DEFAULT_VALUE;
        if (isset($assoc_args[self::TTL_PARAM]) && $assoc_args[self::TTL_PARAM] !== '') {
            $ttl = (int)$assoc_args[self::TTL_PARAM];
        }

        if ($ttl < self::TTL_MIN_VALUE) {
            WP_CLI::error('Parameter --ttl can\'t be less than ' . self::TTL_MIN_VALUE);
        }

        if ($ttl > self::TTL_MAX_VALUE) {
            WP_CLI::error('Parameter --ttl can\'t be more than ' . self::TTL_MAX_VALUE);
        }

        $token = $this->tokenGenerator->generateToken();
        $this->storage->saveToken($token, $login, $ttl);

        WP_CLI::print_value($this->getAuthLink($token), $assoc_args);
    }

    /**
     * @param string $token
     *
     * @return string
     */
    private function getAuthLink($token)
    {
        return add_query_arg(TokenGenerator::TOKEN_QUERY_PARAM, $token, wp_login_url());
    }
}
