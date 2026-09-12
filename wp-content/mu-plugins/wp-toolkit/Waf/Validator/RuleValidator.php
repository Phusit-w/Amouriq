<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\Waf\Validator;

use WP_CLI;

final class RuleValidator
{
    const STRING_FIELDS = [
        'vulnerabilityId',
        'engine',
        'provider',
    ];

    /**
     * @param array $rule
     *
     * @return void
     * @throws \WP_CLI\ExitException
     */
    public static function validate($rule)
    {
        if (\is_array($rule) === false) {
            WP_CLI::error('Rule is not an array');
        }

        foreach (self::STRING_FIELDS as $field) {
            self::validateStringField($field, $rule);
        }

        self::validateArrayField('rules', $rule);
    }

    /**
     * @param string $field
     * @param array  $rule
     *
     * @return void
     * @throws \WP_CLI\ExitException
     */
    private static function validateArrayField($field, $rule)
    {
        $value = isset($rule[$field]) ? $rule[$field] : null;

        if ($value === null) {
            WP_CLI::error($field . ' is not set');
        }

        if (\is_array($value) === false) {
            WP_CLI::error($field . ' is not an array');
        }

        if (empty($value)) {
            WP_CLI::error($field . ' is empty');
        }
    }

    /**
     * @param string $field
     * @param array  $rule
     *
     * @return void
     * @throws \WP_CLI\ExitException
     */
    private static function validateStringField($field, $rule)
    {
        $value = isset($rule[$field]) ? $rule[$field] : null;

        if ($value === null) {
            WP_CLI::error($field . ' is not set');
        }

        if (\is_string($value) === false) {
            WP_CLI::error($field . ' is not a string');
        }

        if (empty($value)) {
            WP_CLI::error($field . ' is empty');
        }
    }
}
