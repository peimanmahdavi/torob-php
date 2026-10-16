<?php

declare(strict_types=1);

namespace Torob\Client;

/** Calls https://api.torob.com/shop-api/v1/ */
final class ShopApiClient extends HttpClient
{
    public function __construct(string $token, array $options = [], ?\GuzzleHttp\ClientInterface $http = null)
    {
        parent::__construct($token, 'https://api.torob.com/shop-api/v1/', $options, $http);
    }

    private function query(array $query = []): array { return ['query' => array_filter($query, static fn($v) => $v !== null)]; }
    private function body(array $body): array { return ['json' => $body]; }

    public function shop(?string $domain = null): array { return $this->request('GET', 'shop/', $this->query(['domain' => $domain])); }
    public function products(array $filters = [], array $query = []): array { return $this->request('POST', 'products/', $this->query($query) + $this->body($filters)); }
    public function activateProducts(array $productIds, ?string $domain = null): array { return $this->productAction('activate/', $productIds, $domain); }
    public function deactivateProducts(array $productIds, ?string $domain = null): array { return $this->productAction('deactivate/', $productIds, $domain); }
    private function productAction(string $path, array $ids, ?string $domain): array
    {
        if ($ids === [] || count($ids) > 5000) throw new \InvalidArgumentException('productIds must contain 1 to 5000 IDs.');
        return $this->request('POST', 'products/' . $path, $this->query(['domain' => $domain]) + $this->body(['product_ids' => array_values($ids)]));
    }
    public function clicks(string $date, array $query = []): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) throw new \InvalidArgumentException('date must use YYYY-MM-DD.');
        return $this->request('GET', 'clicks/', $this->query($query + ['date' => $date]));
    }
    public function priceReports(array $query = []): array { return $this->request('GET', 'price-reports/', $this->query($query)); }
    public function answerPriceReports(array $reportIds, string $status, ?string $description = null, ?string $domain = null): array
    {
        if ($reportIds === [] || count($reportIds) > 1000) throw new \InvalidArgumentException('reportIds must contain 1 to 1000 IDs.');
        if (!in_array($status, ['claimed_to_be_corrected', 'claimed_to_be_invalid'], true)) throw new \InvalidArgumentException('Unsupported price report answer status.');
        $body = ['report_ids' => array_values($reportIds), 'status' => $status];
        if ($description !== null) $body['description'] = mb_substr($description, 0, 200);
        return $this->request('POST', 'price-reports/answer/', $this->query(['domain' => $domain]) + $this->body($body));
    }
    public function previewChargeTransfer(string $destination, ?string $domain = null): array { return $this->request('GET', 'charge-transfer/', $this->query(['destination' => $destination, 'domain' => $domain])); }
    public function transferCharge(string $destination, int $amount, string $requestId, ?string $domain = null): array
    {
        if ($amount < 1 || $amount > 10000000) throw new \InvalidArgumentException('amount must be between 1 and 10000000 tomans.');
        if (!preg_match('/^[A-Za-z0-9_-]{1,40}$/', $requestId)) throw new \InvalidArgumentException('requestId must be 1-40 letters, numbers, underscores or hyphens.');
        return $this->request('POST', 'charge-transfer/', $this->query(['domain' => $domain]) + $this->body(['destination' => $destination, 'amount' => $amount, 'request_id' => $requestId]));
    }
}
