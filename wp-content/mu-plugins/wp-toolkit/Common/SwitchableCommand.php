<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\Common;

use WP_CLI;
use WP_CLI\ExitException;
use WP_CLI_Command;

abstract class SwitchableCommand extends WP_CLI_Command
{
    /**
     * @return string
     */
    abstract protected function getSwitchOption();

    /**
     * @subcommand enable
     *
     * @return void
     */
    public function enable()
    {
        WordPressHelper::upsert_option($this->getSwitchOption(), true);
    }

    /**
     * @subcommand disable
     *
     * @return void
     */
    public function disable()
    {
        WordPressHelper::upsert_option($this->getSwitchOption(), false);
    }

    /**
     * @subcommand status
     *
     * @return void
     */
    public function status()
    {
        $status = get_option($this->getSwitchOption(), false);
        WP_CLI::print_value((int)$status);
    }

    private function isEnabled()
    {
        return (bool) get_option($this->getSwitchOption(), false);
    }

    /**
     * @throws ExitException
     */
    protected function ensureCommandEnabled()
    {
        if (!$this->isEnabled()) {
            WP_CLI::error(\sprintf("Command `%s` isn't enabled", \get_class($this)));
        }
    }
}
