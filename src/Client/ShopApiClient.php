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
    /** Fetch all product pages by following Torob's `next` URL. A hard limit prevents endless loops. */
    public function allProducts(array $filters = [], array $query = [], int $maxPages = 1000): array
    {
        if ($maxPages < 1) throw new \InvalidArgumentException('maxPages must be positive.');
        $uri = 'products/';
        $options = $this->query($query) + $this->body($filters);
        $all = [];
        $seen = [];
        for ($page = 0; $page < $maxPages; $page++) {
            $response = $this->request('POST', $uri, $options);
            $results = $response['results'] ?? null;
            if (!is_array($results)) throw new \UnexpectedValueException('Torob products response must contain a results array.');
            array_push($all, ...array_values($results));
            $next = $response['next'] ?? null;
            if ($next === null) return ['count' => count($all), 'results' => $all];
            if (!is_string($next) || filter_var($next, FILTER_VALIDATE_URL) === false || strtolower((string) parse_url($next, PHP_URL_SCHEME)) !== 'https' || strtolower((string) parse_url($next, PHP_URL_HOST)) !== 'api.torob.com') {
                throw new \UnexpectedValueException('Torob returned an invalid or unexpected pagination URL.');
            }
            if (isset($seen[$next])) throw new \UnexpectedValueException('Torob pagination returned a loop.');
            $seen[$next] = true;
            $uri = $next;
            $options = $this->body($filters);
        }
        throw new \RuntimeException("Torob pagination exceeded the {$maxPages}-page safety limit.");
    }

    public function activateProducts(array $productIds, ?string $domain = null): array { return $this->productAction('activate/', $productIds, $domain); }
    public function deactivateProducts(array $productIds, ?string $domain = null): array { return $this->productAction('deactivate/', $productIds, $domain); }
    private function productAction(string $path, array $ids, ?string $domain): array
    {
        if ($ids === [] || count($ids) > 5000) throw new \InvalidArgumentException('productIds must contain 1 to 5000 IDs.');
        return $this->request('POST', 'products/' . $path, $this->query(['domain' => $domain]) + $this->body(['product_ids' => array_values($ids)]));
    }
    public function clicks(string $date, array $query = []): array
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts) || !checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) throw new \InvalidArgumentException('date must be a valid date in YYYY-MM-DD format.');
        return $this->request('GET', 'clicks/', $this->query(['date' => $date] + $query));
    }
    public function priceReports(array $query = []): array { return $this->request('GET', 'price-reports/', $this->query($query)); }
    public function answerPriceReports(array $reportIds, string $status, ?string $description = null, ?string $domain = null): array
    {
        if ($reportIds === [] || count($reportIds) > 1000) throw new \InvalidArgumentException('reportIds must contain 1 to 1000 IDs.');
        if (!in_array($status, ['claimed_to_be_corrected', 'claimed_to_be_invalid'], true)) throw new \InvalidArgumentException('Unsupported price report answer status.');
        $body = ['report_ids' => array_values($reportIds), 'status' => $status];
        if ($description !== null) $body['description'] = function_exists('mb_substr') ? mb_substr($description, 0, 200, 'UTF-8') : substr($description, 0, 200);
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
