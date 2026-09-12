<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\Waf\Validator;

use WP_CLI;

final class RuleCollectionValidator
{
    /**
     * @param array $rules
     *
     * @return void
     * @throws \WP_CLI\ExitException
     */
    public static function validate($rules)
    {
        if (\is_array($rules) === false) {
            WP_CLI::error('Rules is not an array');
        }

        foreach ($rules as $rule) {
            RuleValidator::validate($rule);
        }
    }
}
