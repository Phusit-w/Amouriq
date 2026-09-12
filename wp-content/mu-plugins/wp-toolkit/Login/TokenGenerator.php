<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\Login;

class TokenGenerator
{
    const TOKEN_QUERY_PARAM = 'token';
    const SALT_NAME = 'auth';
    const HASH_ALGO = 'sha384';
    const TOKEN_BYTES_LENGTH = 32;

    /**
     * @return string
     */
    public function generateToken()
    {
        if (\function_exists('random_bytes')) {
            return bin2hex(random_bytes(self::TOKEN_BYTES_LENGTH));
        }

        return wp_generate_password(self::TOKEN_BYTES_LENGTH);
    }

    /**
     * @param string $token
     *
     * @return string
     */
    public function generateTokenHash($token)
    {
        return hash(self::HASH_ALGO, $token . wp_salt(self::SALT_NAME));
    }

    /**
     * @param int $ttl
     *
     * @return string
     */
    public function getTokenEndDate($ttl)
    {
        $endDate = new \DateTime();
        $endDate->modify('+' . $ttl . ' seconds');

        return $endDate->format('Y-m-d\TH:i:sO');
    }
}
