<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\Event;

use Webpros\WptkWpPlugin\WpToolkit\Common\SwitchableCommand;

class EventCommand extends SwitchableCommand
{
    /**
     * @return string
     */
    protected function getSwitchOption()
    {
        return EventBootstrap::MODULE_STATUS_OPTION;
    }
}
