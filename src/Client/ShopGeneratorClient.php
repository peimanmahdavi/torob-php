<?php

declare(strict_types=1);

namespace Torob\Client;

/** Merchant approval workflow for shop-generator platforms. */
final class ShopGeneratorClient extends HttpClient
{
    public function __construct(string $partnerToken, array $options = [], ?\GuzzleHttp\ClientInterface $http = null)
    {
        parent::__construct($partnerToken, 'https://api.torob.com/shop-generator/v1/', $options, $http);
    }
    public function requestConnection(string $shopDomain, string $returnUrl): array
    {
        return $this->request('POST', 'connections/', ['json' => ['shop_domain' => $shopDomain, 'return_url' => $returnUrl]]);
    }
    public function connection(string $connectionId): array { return $this->request('GET', 'connections/' . rawurlencode($connectionId) . '/'); }
    public function confirmConnection(string $connectionId, string $code): array
    {
        return $this->request('POST', 'connections/' . rawurlencode($connectionId) . '/confirm/', ['json' => ['code' => $code]]);
    }
    public function disconnect(string $connectionId): array
    {
        return $this->request('DELETE', 'connections/' . rawurlencode($connectionId) . '/');
    }
}
