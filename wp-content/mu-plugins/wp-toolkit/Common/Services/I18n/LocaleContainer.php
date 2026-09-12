<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\Common\Services\I18n;

final class LocaleContainer
{
    /**
     * @var string
     */
    private $localeCode;

    /**
     * @var array<string, string>
     */
    private $messagesBackend;

    /**
     * @var array<string, string>
     */
    private $messagesFrontend;

    /**
     * @param string                $localeCode
     * @param array<string, string> $messagesBackend
     * @param array<string, string> $messagesFrontend
     */
    public function __construct(
        $localeCode,
        array $messagesBackend,
        array $messagesFrontend
    ) {
        $this->localeCode = $localeCode;
        $this->messagesBackend = $messagesBackend;
        $this->messagesFrontend = $messagesFrontend;
    }

    /**
     * @return string
     */
    public function getLocaleCode()
    {
        return $this->localeCode;
    }

    /**
     * @return array<string, string>
     */
    public function getMessagesBackend()
    {
        return $this->messagesBackend;
    }

    /**
     * @return array<string, string>
     */
    public function getMessagesFrontend()
    {
        return $this->messagesFrontend;
    }
}
