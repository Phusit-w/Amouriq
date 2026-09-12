<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin;

use Webpros\WptkWpPlugin\WpToolkit\Event\EventBootstrap;
use Webpros\WptkWpPlugin\WpToolkit\Login\LoginBootstrap;
use Webpros\WptkWpPlugin\WpToolkit\Panel\PanelBootstrap;
use Webpros\WptkWpPlugin\WpToolkit\UI\UIBootstrap;

// Do not access this file directly
if (!\defined('ABSPATH')) {
    header('Content-Type: text/plain');
    die(<<<MSG
 _________
< Go away >
 ---------
        \   ^__^
         \  (oo)\_______
            (__)\       )\/\
                ||----w |
                ||     ||
MSG
    );
}

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/Waf/waf-bootstrap.php';
LoginBootstrap::init();
PanelBootstrap::init();
UIBootstrap::init();
EventBootstrap::init();
