<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\UI;

use Webpros\WptkWpPlugin\WpToolkit\Common\SwitchableCommand;
use Webpros\WptkWpPlugin\WpToolkit\Common\WordPressHelper;
use WP_CLI;
use WP_CLI\ExitException;

final class UICommand extends SwitchableCommand
{
    /**
     * @throws ExitException
     */
    public function enable()
    {
        if (WordPressHelper::isWpVersionSupported(UIBootstrap::MINIMAL_SUPPORTED_WP_VERSION)) {
            parent::enable();
        } else {
            WP_CLI::error("UI module can't be enabled on this WordPress version. Minimal supported version is " . UIBootstrap::MINIMAL_SUPPORTED_WP_VERSION);
        }
    }

    /**
     * @return string
     */
    protected function getSwitchOption()
    {
        return UIBootstrap::WP_OPTION_MODULE_STATUS;
    }
}
