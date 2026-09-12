<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\Common\Services\I18n;

final class Translator implements TranslatorInterface
{
    /**
     * @var LocaleContainer
     */
    private $localeContainer;

    /**
     * @var MessageFormatterInterface
     */
    private $formatter;

    public function __construct(
        LocaleContainer $localeContainer,
        MessageFormatterInterface $formatter
    ) {
        $this->localeContainer = $localeContainer;
        $this->formatter = $formatter;
    }

    /**
     * @inheritdoc
     */
    public function translate($id, array $context = [])
    {
        $messages = $this->localeContainer->getMessagesBackend();
        $message = isset($messages[$id]) ? $messages[$id] : null;

        return !\is_null($message) ? $this->formatMessage($message, $context) : "[[$id]]";
    }

    /**
     * @return string
     */
    public function getLocaleCode()
    {
        return $this->localeContainer->getLocaleCode();
    }

    /**
     * @return LocaleContainer
     */
    public function getLocaleContainer()
    {
        return $this->localeContainer;
    }

    /**
     * Substitutes params in localized message template
     *
     * @param string $messageTemplate
     * @param array  $params          Array of <name> => <value>
     *
     * @return string
     */
    private function formatMessage($messageTemplate, $params = [])
    {
        return $this->formatter->format($messageTemplate, $params, $this->getLocaleCode());
    }
}
