<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\Panel;

use Webpros\WptkWpPlugin\WpToolkit\Common\SwitchableCommand;

class PanelCommand extends SwitchableCommand
{
    /**
     * @return string
     */
    protected function getSwitchOption()
    {
        return PanelBootstrap::MODULE_STATUS_OPTION;
    }
}
