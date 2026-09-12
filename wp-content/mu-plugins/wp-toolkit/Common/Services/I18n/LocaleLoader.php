<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\Common\Services\I18n;

final class LocaleLoader
{
    // Class contains only static methods
    private function __construct()
    {
    }

    /**
     * @param string $wpLocale            WordPress locale code like en_US, fr_FR
     * @param string $availableLocalesDir Path to directory containing locale PHP files (e.g. en-US.php)
     *
     * @return LocaleContainer
     */
    public static function loadLocale($wpLocale, $availableLocalesDir)
    {
        // Normalize directory path
        $dir = rtrim($availableLocalesDir, DIRECTORY_SEPARATOR);
        $localeContainer = null;

        // Basic sanitation
        if (!\is_string($wpLocale) || $wpLocale === '') {
            return self::loadFallbackLocale($dir);
        }

        // Convert WP style (en_US) to plugin style (en-US)
        $convertedCode = str_replace('_', '-', $wpLocale);
        $parts = explode('-', $convertedCode);
        if (\count($parts) >= 2) {
            // Keep only first two segments (language + region) if more provided
            $language = strtolower($parts[0]);
            $region = strtoupper($parts[1]);
            $convertedCode = $language . '-' . $region;

            $localeContainer = self::loadLocaleByCode($dir, $convertedCode);
        } else {
            $language = strtolower($convertedCode); // single part language code (e.g. 'en')
        }

        if ($localeContainer) {
            return $localeContainer;
        }

        if (!preg_match('/^[a-zA-Z]{2,3}$/', $language)) {
            return self::loadFallbackLocale($dir);
        }

        // Try relaxed match: only language part with any region present in directory
        /** @psalm-suppress RiskyTruthyFalsyComparison */
        $languageMatches = glob($dir . DIRECTORY_SEPARATOR . $language . '.php') ?: [];
        /** @psalm-suppress RiskyTruthyFalsyComparison */
        $languageWithRegionMatches = glob($dir . DIRECTORY_SEPARATOR . $language . '-*.php') ?: [];
        $matches = array_merge($languageMatches, $languageWithRegionMatches);
        if (!empty($matches)) {
            sort($matches, SORT_STRING);
            $code = basename($matches[0], '.php');

            $localeContainer = self::loadLocaleByCode($dir, $code);
        }

        return $localeContainer ?: self::loadFallbackLocale($dir);
    }

    /**
     * @param string $dir
     * @param string $code
     *
     * @return ?LocaleContainer
     */
    private static function loadLocaleByCode($dir, $code)
    {
        if (!preg_match('/^[a-zA-Z]{2,3}(-[a-zA-Z0-9]{2,4})?$/', $code)) {
            return null;
        }

        $file = $dir . DIRECTORY_SEPARATOR . $code . '.php';
        if (!is_readable($file)) {
            return null;
        }

        /** @psalm-suppress UnresolvableInclude */
        require $file;

        /** @psalm-suppress UndefinedVariable */
        if (!isset($messagesBackend)
            || !\is_array($messagesBackend)
            || !isset($messagesFrontend)
            || !\is_array($messagesFrontend)
        ) {
            throw new \RuntimeException("Locale file {$file} must define \$messagesBackend and \$messagesFrontend as arrays.");
        }

        return new LocaleContainer(
            $code,
            $messagesBackend,
            $messagesFrontend
        );
    }

    /**
     * @param string $dir
     * @param string $fallbackCode
     *
     * @return LocaleContainer
     */
    private static function loadFallbackLocale($dir, $fallbackCode = 'en-US')
    {
        $localeContainer = self::loadLocaleByCode($dir, $fallbackCode);
        if (!$localeContainer) {
            $dirSeparator = DIRECTORY_SEPARATOR;
            throw new \RuntimeException("Cannot load fallback locale file: {$dir}{$dirSeparator}{$fallbackCode}.php");
        }
        return $localeContainer;
    }
}
