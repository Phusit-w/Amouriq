<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\UI;

use Webpros\WptkWpPlugin\WpToolkit\Common\Services\I18n\TranslatorInterface;

final class DashboardIntegration
{
    const WP_DASHBOARD_SCREEN_ID = 'dashboard';
    const WIDGET_ATTACKS_ID = 'wp_toolkit_attacks_widget';
    const WIDGET_DASHBOARD_SECURITY_RISK_ID = 'wp_toolkit_dashboard_security_risk_widget';

    /**
     * @var TranslatorInterface
     */
    private $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    /**
     * @return void
     */
    public function init(Config $config)
    {
        add_action('wp_dashboard_setup', function () use ($config) {
            wp_add_dashboard_widget(
                self::WIDGET_DASHBOARD_SECURITY_RISK_ID,
                $this->translator->translate('dashboard.widget.securityRisk.title', ['productName' => esc_html($config->getDisplayName())]),
                function () {
                    $this->renderDashboardSecurityRiskWidget();
                },
                null,
                null,
                'normal',
                'high'
            );

            wp_add_dashboard_widget(
                self::WIDGET_ATTACKS_ID,
                $this->translator->translate('dashboard.widget.attacks.title'),
                function () {
                    $this->renderAttacksWidget();
                },
                null,
                null,
                'side',
                'high'
            );
        });

        add_action('admin_enqueue_scripts', function () use ($config) {
            if ($this->isDashboardPage()) {
                UIBootstrap::enqueueDashboardScripts($config);
                UIBootstrap::enqueueDashboardStyles();
            }
        });
    }

    /**
     * @return void
     */
    public function renderDashboardSecurityRiskWidget()
    {
        $text = $this->translator->translate('dashboard.widget.noJsText');
        echo <<<HTML
            <div>
                <noscript>{$text}</noscript>
                <div id="wp-toolkit-dashboard-security-risk-widget-root"></div>
            </div>
HTML;
    }

    /**
     * @return void
     */
    public function renderAttacksWidget()
    {
        $text = $this->translator->translate('dashboard.widget.noJsText');
        echo <<<HTML
            <div>
                <noscript>{$text}</noscript>
                <div id="wp-toolkit-attacks-widget-root"></div>
            </div>
HTML;
    }

    /**
     * @return bool
     */
    private function isDashboardPage()
    {
        $screen = get_current_screen();
        return $screen && $screen->id === self::WP_DASHBOARD_SCREEN_ID;
    }
}
