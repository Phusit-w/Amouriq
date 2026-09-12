<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\Waf\Cli;

use Webpros\WptkWpPlugin\WpToolkit\Common\SwitchableCommand;
use Webpros\WptkWpPlugin\WpToolkit\Waf\FirewallEngine;
use Webpros\WptkWpPlugin\WpToolkit\Waf\Validator\RuleCollectionValidator;
use WP_CLI;

/**
 * Common structure of incoming data:
 * array<int, array{vulnerabilityId: string, engine: string, provider: string, rules: array}>
 */
class WafCommand extends SwitchableCommand
{
    /**
     * @param array $args
     * @param array $assoc_args
     *
     * @return void
     * @throws WP_CLI\ExitException
     */
    public function delete($args, $assoc_args)
    {
        $value = WP_CLI::get_value_from_arg_or_stdin($args, 0);
        $vulnerabilityIds = WP_CLI::read_value($value, $assoc_args);

        if (!\is_array($vulnerabilityIds)) {
            WP_CLI::error('Passed vulnerability ids has an incompatible format');
        }

        FirewallEngine::deleteRules($vulnerabilityIds);
    }

    /**
     * @subcommand list
     *
     * @param array $args
     * @param array $assoc_args
     *
     * @return void
     */
    public function list_($args, $assoc_args)
    {
        WP_CLI::print_value(FirewallEngine::listActiveRules(), $assoc_args);
    }

    /**
     * @param array $args
     * @param array $assoc_args
     *
     * @return void
     * @throws WP_CLI\ExitException
     */
    public function set($args, $assoc_args)
    {
        $value = WP_CLI::get_value_from_arg_or_stdin($args, 0);
        $rules = WP_CLI::read_value($value, $assoc_args);

        RuleCollectionValidator::validate($rules);

        FirewallEngine::setRules($rules);
    }

    /**
     * @param array $args
     * @param array $assoc_args
     *
     * @return void
     * @throws WP_CLI\ExitException
     */
    public function upsert($args, $assoc_args)
    {
        $value = WP_CLI::get_value_from_arg_or_stdin($args, 0);
        $rules = WP_CLI::read_value($value, $assoc_args);

        RuleCollectionValidator::validate($rules);

        FirewallEngine::upsertRules($rules);
    }

    /**
     * @return string
     */
    protected function getSwitchOption()
    {
        return 'wp-toolkit_waf_status';
    }
}
