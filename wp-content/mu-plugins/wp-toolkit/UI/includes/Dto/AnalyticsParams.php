<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\UI\Dto;

final class AnalyticsParams implements \JsonSerializable
{
    /**
     * @var bool
     */
    private $state = false;

    /**
     * @var bool
     */
    private $optedIn;

    /**
     * @var ?string
     */
    private $key;

    /**
     * @var ?string
     */
    private $host;

    /**
     * @var ?string
     */
    private $id;

    /**
     * @param bool    $optedIn
     * @param ?string $key
     * @param ?string $host
     */
    public function __construct($optedIn, $key = null, $host = null)
    {
        $this->optedIn = $optedIn;
        $this->key = $key;
        $this->host = $host;
    }

    /**
     * @param ?string $key
     *
     * @return self
     */
    public function setKey($key)
    {
        $this->key = $key;

        return $this;
    }

    /**
     * @param ?string $host
     *
     * @return self
     */
    public function setHost($host)
    {
        $this->host = $host;

        return $this;
    }

    /**
     * @param ?string $id
     *
     * @return self
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @param bool $state
     *
     * @return self
     */
    public function setState($state)
    {
        $this->state = $state;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        $result = [
            'state' => $this->state,
            'optedIn' => $this->optedIn,
        ];

        if ($this->key !== null && $this->key !== '') {
            $result['key'] = $this->key;
        }

        if ($this->host !== null && $this->host !== '') {
            $result['host'] = $this->host;
        }

        if ($this->id !== null && $this->id !== '') {
            $result['id'] = $this->id;
        }

        return $result;
    }
}
