<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\Common\Services\I18n;

final class IntlFormatter implements MessageFormatterInterface
{
    public function __construct()
    {
        if (!class_exists(\MessageFormatter::class)) {
            throw new \RuntimeException('The PHP intl extension is required to use the IntlFormatter.');
        }
    }

    /**
     * @param string $messageTemplate
     * @param string $localeCode
     *
     * @return string
     */
    public function format($messageTemplate, array $parameters, $localeCode)
    {
        static $cache = [];
        if (!$messageTemplate) {
            return '';
        }

        $formatter = null;
        if (!isset($cache[$localeCode][$messageTemplate])
            || !$formatter = $cache[$localeCode][$messageTemplate]
        ) {
            try {
                $cache[$localeCode][$messageTemplate] = $formatter = new \MessageFormatter($localeCode, $messageTemplate);
            } catch (\IntlException $e) {
                throw new \InvalidArgumentException(\sprintf('Invalid message format (error #%d): %s', intl_get_error_code(), intl_get_error_message()), 0, $e);
            }
        }

        $message = $messageTemplate;
        if ($formatter instanceof \MessageFormatter
            && ($message = $formatter->format($parameters)) === false
        ) {
            throw new \InvalidArgumentException(\sprintf('Unable to format message (error #%s): %s', $formatter->getErrorCode(), $formatter->getErrorMessage()));
        }

        return $message;
    }
}
