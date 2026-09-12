<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\UI;

final class Config implements \JsonSerializable
{
    const CONFIG_CUSTOMIZATIONS_FILE_NAME = 'customizations.json';
    const CHAPTER_BRANDING = 'branding';
    const OPTION_BRANDING_DISPLAY_NAME = 'displayName';
    const OPTION_BRANDING_DISPLAY_NAME_DEFAULT = 'WP Toolkit';
    const OPTION_BRANDING_DISPLAY_NAME_IS_CUSTOMIZED = 'isDisplayNameCustomized';
    const CHAPTER_MENU = 'menu';
    const OPTION_MENU_POSITION = 'position';
    const OPTION_MENU_POSITION_DEFAULT = 3;

    /**
     * @var Config|null
     */
    private static $instance = null;

    /**
     * @var array<string, array<string, mixed>>
     */
    private static $data = [
        self::CHAPTER_BRANDING => [
            self::OPTION_BRANDING_DISPLAY_NAME => self::OPTION_BRANDING_DISPLAY_NAME_DEFAULT,
            self::OPTION_BRANDING_DISPLAY_NAME_IS_CUSTOMIZED => false,
        ],
        self::CHAPTER_MENU => [
            self::OPTION_MENU_POSITION => self::OPTION_MENU_POSITION_DEFAULT,
        ],
    ];

    private function __construct()
    {
        $this->loadCustomizations();
    }

    /**
     * @return Config
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new Config();
        }
        return self::$instance;
    }

    /**
     * @return string[][]
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return self::$data;
    }

    /**
     * @return string
     */
    public function getDisplayName()
    {
        return self::$data[self::CHAPTER_BRANDING][self::OPTION_BRANDING_DISPLAY_NAME];
    }

    /**
     * @return int|null
     */
    public function getMenuPosition()
    {
        return self::$data[self::CHAPTER_MENU][self::OPTION_MENU_POSITION];
    }

    /**
     * @return void
     */
    private function loadCustomizations()
    {
        $dataSanitizers = [
            self::CHAPTER_BRANDING => [
                self::OPTION_BRANDING_DISPLAY_NAME =>
                    /**
                     * @param mixed $value
                     *
                     * @return string
                     */
                    function ($value) {
                        return sanitize_text_field($value);
                    },
            ],
            self::CHAPTER_MENU => [
                self::OPTION_MENU_POSITION =>
                    /**
                     * @param mixed $value
                     *
                     * @return int|null
                     */
                    function ($value) {
                        if ($value === null || (is_numeric($value) && (int) $value >= 0)) {
                            return $value === null ? null : (int) $value;
                        }
                        return self::OPTION_MENU_POSITION_DEFAULT;
                    },
            ],
        ];

        $customizationsFilePath = WPMU_PLUGIN_DIR
            . UIBootstrap::MODULE_PATH_SLUG . DIRECTORY_SEPARATOR
            . self::CONFIG_CUSTOMIZATIONS_FILE_NAME;
        if (file_exists($customizationsFilePath)) {
            $jsonData = file_get_contents($customizationsFilePath);
            if ($jsonData === false) {
                return;
            }
            $customizations = json_decode($jsonData, true);
            if ($customizations === null && json_last_error() !== JSON_ERROR_NONE) {
                error_log(
                    \sprintf(
                        '[WP Toolkit] Invalid JSON in customizations file "%s": %s',
                        $customizationsFilePath,
                        json_last_error_msg()
                    )
                );
            } elseif (\is_array($customizations)) {
                foreach (self::$data as $chapter => $options) {
                    foreach ($options as $option => $value) {
                        if (isset($customizations[$chapter]) && \array_key_exists($option, $customizations[$chapter])) {
                            self::$data[$chapter][$option] = isset($dataSanitizers[$chapter][$option])
                                ? $dataSanitizers[$chapter][$option]($customizations[$chapter][$option])
                                : $customizations[$chapter][$option];
                        }
                    }
                }
            }
        }

        $currentDisplayName = self::$data[self::CHAPTER_BRANDING][self::OPTION_BRANDING_DISPLAY_NAME];
        self::$data[self::CHAPTER_BRANDING][self::OPTION_BRANDING_DISPLAY_NAME_IS_CUSTOMIZED]
            = ($currentDisplayName !== self::OPTION_BRANDING_DISPLAY_NAME_DEFAULT);
    }
}
