<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\Panel\UI;

use Webpros\WptkWpPlugin\WpToolkit\Common\Services\I18n\TranslatorInterface;
use Webpros\WptkWpPlugin\WpToolkit\Panel\Services\PanelInfoService;

class BackToPanelMenuIntegration
{
    const PAGE_SLUG = 'wp-toolkit-back-to-panel';

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var PanelInfoService
     */
    private $panelInfoService;

    public function __construct(TranslatorInterface $translator, PanelInfoService $panelInfoService)
    {
        $this->translator       = $translator;
        $this->panelInfoService = $panelInfoService;
    }

    /**
     * @return void
     */
    public function register()
    {
        add_menu_page(
            $this->translator->translate('backToPanel.redirecting'),
            $this->translator->translate('menu.item.backToPanel'),
            'manage_options',
            self::PAGE_SLUG,
            function () {
                $this->render();
            },
            'dashicons-arrow-left-alt',
            1
        );
    }

    /**
     * Renders a minimal page that immediately redirects the browser to the
     * panel URL.
     *
     * @return void
     */
    public function render()
    {
        $backUrl = $this->panelInfoService->getPanelUrl();
        $heading = esc_html($this->translator->translate('backToPanel.redirecting'));
        $loading = esc_html($this->translator->translate('backToPanel.loading'));
        echo "<h1>{$heading}</h1>";
        echo "<span>{$loading}</span>";

        $sanitizedBackUrl = null;
        if (!\is_null($backUrl)) {
            $sanitizedBackUrl = esc_url_raw($backUrl, ['http', 'https']);
            if ($sanitizedBackUrl === '') {
                $sanitizedBackUrl = null;
            }
        }
        $encoded = \is_null($sanitizedBackUrl) ? false : json_encode($sanitizedBackUrl);
        if ($encoded !== false) {
            echo '<script>window.location = ' . $encoded . ';</script>';
        } else {
            $errorMessage = esc_html($this->translator->translate('backToPanel.unableToRedirect'));
            echo "<p>{$errorMessage}</p>";
        }
    }
}
