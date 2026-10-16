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
        $host = parse_url($returnUrl, PHP_URL_HOST);
        $scheme = strtolower((string) parse_url($returnUrl, PHP_URL_SCHEME));
        $isLocalHttp = $scheme === 'http' && in_array(strtolower((string) $host), ['localhost', '127.0.0.1'], true);
        if (!filter_var($returnUrl, FILTER_VALIDATE_URL) || ($scheme !== 'https' && !$isLocalHttp)) {
            throw new \InvalidArgumentException('returnUrl must use HTTPS (HTTP is allowed only for localhost or 127.0.0.1).');
        }
        $shopDomain = strtolower(trim($shopDomain));
        if (str_starts_with($shopDomain, 'www.')) $shopDomain = substr($shopDomain, 4);
        $domainUrl = 'https://' . $shopDomain;
        $parsedDomain = parse_url($domainUrl);
        if ($shopDomain === '' || filter_var($shopDomain, FILTER_VALIDATE_IP) || filter_var($domainUrl, FILTER_VALIDATE_URL) === false
            || !is_array($parsedDomain) || strtolower((string) ($parsedDomain['host'] ?? '')) !== $shopDomain
            || array_intersect_key($parsedDomain, array_flip(['port', 'user', 'pass', 'query', 'fragment', 'path'])) !== []) {
            throw new \InvalidArgumentException('shopDomain must be a domain name, not a URL, IP address or domain with a path/port.');
        }
        return $this->request('POST', 'connections/', ['json' => ['shop_domain' => $shopDomain, 'return_url' => $returnUrl]]);
    }
    public function connection(string $connectionId): array
    {
        $this->validateConnectionId($connectionId);
        return $this->request('GET', 'connections/' . rawurlencode($connectionId) . '/');
    }
    public function confirmConnection(string $connectionId, string $code): array
    {
        $this->validateConnectionId($connectionId);
        if (!preg_match('/^[A-Za-z0-9_-]{43}$/', $code)) throw new \InvalidArgumentException('code must be a 43-character URL-safe confirmation code.');
        return $this->request('POST', 'connections/' . rawurlencode($connectionId) . '/confirm/', ['json' => ['code' => $code]]);
    }
    public function disconnect(string $connectionId): array
    {
        $this->validateConnectionId($connectionId);
        return $this->request('DELETE', 'connections/' . rawurlencode($connectionId) . '/');
    }

    private function validateConnectionId(string $connectionId): void
    {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $connectionId)) {
            throw new \InvalidArgumentException('connectionId must be a UUID.');
        }
    }
}
