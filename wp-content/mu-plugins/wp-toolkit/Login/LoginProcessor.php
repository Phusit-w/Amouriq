<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\Login;

class LoginProcessor
{
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
    }

    /**
     * @return void
     */
    public function init()
    {
        add_action('login_init', [$this, 'processAdminLogin'], 10, 0);
        add_action('login_init', [$this, 'maybeDeactivateLogin'], 10, 0);
    }

    /**
     * Deactivates login module if one time login tokens expired
     */
    public function maybeDeactivateLogin()
    {
        $tokenEndDateString = $this->storage->getTtl();
        $tokenEndDate = $tokenEndDateString
            ? new \DateTime($tokenEndDateString)
            : new \DateTime('-1 day')
        ;

        if (new \DateTime() > $tokenEndDate) {
            $this->storage->removeTokenData();
        }
    }

    /**
     * @return void
     */
    public function processAdminLogin()
    {
        if (!isset($_GET[TokenGenerator::TOKEN_QUERY_PARAM]) || !\is_string($_GET[TokenGenerator::TOKEN_QUERY_PARAM])) {
            return;
        }

        $requestToken = $_GET[TokenGenerator::TOKEN_QUERY_PARAM];
        if ($this->isTokenValid($requestToken)) {
            $login = $this->storage->getLogin();
            if (!$this->storage->removeTokenData()) {
                wp_die('User token is invalid, please generate a new token');
            }
            $this->makeAuth($login);
        } else {
            wp_die('User token is invalid, please generate a new token');
        }
    }

    /**
     * @param string $requestToken
     *
     * @return bool
     */
    private function isTokenValid($requestToken)
    {
        $tokenHash = $this->storage->getTokenHash();
        $login = $this->storage->getLogin();
        $tokenEndDate = $this->storage->getTtl();

        if (empty($tokenHash) || empty($login) || empty($tokenEndDate)) {
            return false;
        }

        if (!hash_equals($tokenHash, $this->tokenGenerator->generateTokenHash($requestToken))) {
            return false;
        }

        $currentDate = new \DateTime();
        $tokenEndDate = new \DateTime($tokenEndDate);

        if ($currentDate > $tokenEndDate) {
            $this->storage->removeTokenData();
            return false;
        }

        return true;
    }

    /**
     * @param string $login
     *
     * @return void
     */
    private function makeAuth($login)
    {
        $user = $this->userProvider->getAdminByLogin($login);
        if ($user instanceof \WP_User) {
            wp_set_current_user($user->ID, $user->user_login);
            wp_set_auth_cookie($user->ID);
            do_action('wp_login', $user->user_login, $user);
            wp_safe_redirect(admin_url());
            exit;
        } else {
            wp_die(\sprintf('Login name "%s" is not an administrator login', $login));
        }
    }
}
