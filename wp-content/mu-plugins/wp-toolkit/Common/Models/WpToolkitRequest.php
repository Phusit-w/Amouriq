<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\Common\Models;

use Webpros\WptkWpPlugin\WpToolkit\Common\Clients\HttpClientInterface;
use Webpros\WptkWpPlugin\WpToolkit\UI\Api\Exceptions\BadRequestException;

class WpToolkitRequest
{
    /**
     * @var string
     */
    private $endpoint;

    /**
     * @var string
     */
    private $method;

    /**
     * @var array
     */
    private $body;

    /**
     * @var array
     */
    private $headers;

    /**
     * @var array
     */
    private $query;

    /**
     * @param string $endpoint
     * @param string $method
     * @param array  $body
     * @param array  $headers
     * @param array  $query
     *
     * @throws BadRequestException
     */
    public function __construct(
        $endpoint,
        $method = 'GET',
        $body = [],
        $headers = [],
        $query = []
    ) {
        if (!\is_string($endpoint) || empty($endpoint)) {
            throw new BadRequestException('Invalid wp-toolkit api endpoint');
        }

        $allowedMethodTypes = [
            HttpClientInterface::METHOD_GET,
            HttpClientInterface::METHOD_POST,
            HttpClientInterface::METHOD_PATCH,
            HttpClientInterface::METHOD_PUT,
            HttpClientInterface::METHOD_DELETE
        ];

        if (!\in_array($method, $allowedMethodTypes, true)) {
            throw new BadRequestException(
                'Allowed method types are: ' . implode(', ', $allowedMethodTypes) . ', ' . $method . ' given'
            );
        }

        $this->endpoint = $endpoint;
        $this->method = $method;
        $this->body = $body;
        $this->headers = $headers;
        $this->query = $query;
    }

    /**
     * @return string
     */
    public function getEndpoint()
    {
        return $this->endpoint;
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @return array
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @return array
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * @param string $endpoint
     *
     * @return $this
     */
    public function setEndpoint($endpoint)
    {
        $this->endpoint = $endpoint;

        return $this;
    }

    /**
     * @param string $method
     *
     * @return $this
     */
    public function setMethod($method)
    {
        $this->method = $method;

        return $this;
    }

    /**
     * @param array $body
     *
     * @return $this
     */
    public function setBody($body)
    {
        $this->body = $body;

        return $this;
    }

    /**
     * @param array $headers
     *
     * @return $this
     */
    public function setHeaders($headers)
    {
        $this->headers = $headers;

        return $this;
    }

    /**
     * @param array $query
     *
     * @return $this
     */
    public function setQuery($query)
    {
        $this->query = $query;

        return $this;
    }
}
