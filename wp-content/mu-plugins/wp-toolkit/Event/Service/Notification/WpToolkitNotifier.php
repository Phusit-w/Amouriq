<?php
// Copyright 1999-2026. WebPros International GmbH. All rights reserved.

namespace Webpros\WptkWpPlugin\WpToolkit\Event\Service\Notification;

use Webpros\WptkWpPlugin\WpToolkit\Common\Clients\HttpClientFactoryInterface;
use Webpros\WptkWpPlugin\WpToolkit\Common\Clients\HttpClientInterface;
use Webpros\WptkWpPlugin\WpToolkit\Common\Installation;
use Webpros\WptkWpPlugin\WpToolkit\Common\Models\WpToolkitRequest;
use Webpros\WptkWpPlugin\WpToolkit\Event\Dto\WordPressEventDto;

class WpToolkitNotifier
{
    /**
     * @var HttpClientFactoryInterface
     */
    private $httpClientFactory;

    /**
     * @var WordPressEventDto[]
     */
    private $events = [];

    /**
     * @return void
     */
    public function __construct(
        HttpClientFactoryInterface $httpClientFactory
    ) {
        $this->httpClientFactory = $httpClientFactory;
    }

    /**
     * @return void
     */
    public function notify(WordPressEventDto $event)
    {
        $this->events[] = $event;
    }

    /**
     * @return void
     */
    public function flush()
    {
        if (empty($this->events)) {
            return;
        }

        try {
            $client = $this->httpClientFactory->getClient();
            $jsonSerializedEvents = array_map(function (WordPressEventDto $event) {
                return $event->jsonSerialize();
            }, $this->events);

            $installationId = Installation::getInstance()->getInstallationId();
            $client->request(
                new WpToolkitRequest(
                    "/v1/installations/$installationId/wordpress/events",
                    HttpClientInterface::METHOD_POST,
                    $jsonSerializedEvents,
                    [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ]
                )
            );
        } catch (\Exception $e) {
            error_log("Failed to notify wp-toolkit about new events: {$e->getMessage()}");
        }
    }
}
