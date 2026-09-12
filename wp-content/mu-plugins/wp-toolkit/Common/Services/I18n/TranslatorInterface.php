<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\Common\Services\I18n;

interface TranslatorInterface
{
    /**
     * Translates the given message
     *
     * @param string $id      The message id
     * @param array  $context An array of parameters for the message
     *
     * @return string The translated string
     */
    public function translate($id, array $context = []);
}
