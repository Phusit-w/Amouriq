<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\Common\Services\I18n;

/**
 * A simple fallback message formatter that replaces placeholders in the form of {key}
 * or {key, ...} with corresponding values from the parameters array.
 * It does not support complex formatting rules, but doesn't require php-intl extension.
 */
final class FallbackFormatter implements MessageFormatterInterface
{
    /**
     * @param string $messageTemplate
     * @param string $localeCode
     *
     * @return string
     */
    public function format($messageTemplate, array $parameters, $localeCode)
    {
        if (!$messageTemplate) {
            return '';
        }

        $result = $messageTemplate;
        if ($parameters) {
            foreach ($parameters as $key => $value) {
                if (\is_array($value) || \is_object($value)) {
                    continue;
                }
                $safeValue = (string) $value;
                // {key} substitution
                $patternSimple = '/\{' . preg_quote($key, '/') . '\}/u';
                $result = (string) preg_replace($patternSimple, $safeValue, $result);
                // {key, ...} substitution (e.g. for plural forms)
                $patternExtended = '/\{' . preg_quote($key, '/') . ',[^{}]*\}/u';
                $result = (string) preg_replace($patternExtended, $safeValue, $result);
            }
        }

        return $result;
    }
}
