<?php

declare(strict_types=1);

namespace Torob\Client;

/** Sends product URL notifications to Torob's update webhook. */
final class ProductWebhookClient extends HttpClient
{
    public function __construct(string $token, array $options = [], ?\GuzzleHttp\ClientInterface $http = null)
    {
        parent::__construct($token, 'https://api.torob.com/', $options, $http);
    }

    /** @param list<array{page_url:string,page_unique:string}> $items */
    public function notify(array $items): array
    {
        if ($items === [] || count($items) > 100) throw new \InvalidArgumentException('Webhook batches must contain 1 to 100 items.');
        $host = null;
        foreach ($items as $item) {
            if (empty($item['page_url']) || empty($item['page_unique']) || !filter_var($item['page_url'], FILTER_VALIDATE_URL)) {
                throw new \InvalidArgumentException('Each item needs a valid absolute page_url and page_unique.');
            }
            $currentHost = parse_url($item['page_url'], PHP_URL_HOST);
            if ($host !== null && strcasecmp($host, (string) $currentHost) !== 0) throw new \InvalidArgumentException('All webhook items must use the same domain.');
            $host = $currentHost;
        }
        return $this->request('POST', 'update/webhook/v1/', ['json' => ['items' => array_values($items)]]);
    }
}
