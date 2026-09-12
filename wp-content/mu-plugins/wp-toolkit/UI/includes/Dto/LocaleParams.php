<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\UI\Dto;

final class LocaleParams implements \JsonSerializable
{
    /**
     * @var string
     */
    private $code;

    /**
     * @var array<string, string>
     */
    private $messages = [];

    /**
     * @param string                $code
     * @param array<string, string> $messages
     */
    public function __construct($code, $messages = [])
    {
        $this->code = $code;
        foreach ($messages as $key => $message) {
            $this->messages[$key] = $message;
        }
    }

    /**
     * @return array<string, mixed>
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return [
            'code' => $this->code,
            'messages' => $this->messages,
        ];
    }
}
