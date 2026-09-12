<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

// Do not access this file directly
if (!\defined('ABSPATH')) {
    header('Content-Type: text/plain');
    die(<<<MSG
 __________
< Go away >
 ----------
        \   ^__^
         \  (oo)\_______
            (__)\       )\/\
                ||----w |
                ||     ||
MSG
    );
}

use Webpros\WptkWpPlugin\WpToolkit\Common\CliHelper;
use Webpros\WptkWpPlugin\WpToolkit\Waf\Cli\WafCommand;
use Webpros\WptkWpPlugin\WpToolkit\Waf\FirewallEngine;

if (CliHelper::is_cli()) {
    if (class_exists(\WP_CLI::class, false)) {
        WP_CLI::add_command('wp-toolkit waf', WafCommand::class);
    }

    return;
}
if (!get_option('wp-toolkit_waf_status', false)) {
    return;
}

FirewallEngine::launch(true);
\define('PS_FW_MU_RAN', true);
add_action('init', [FirewallEngine::class, 'launch'], 10, 0);
