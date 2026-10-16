<?php

declare(strict_types=1);

namespace Torob\ProductSync;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/** Framework-neutral handler for a store's POST Product API v3 endpoint. */
final class ProductApiV3Service
{
    public const PUBLIC_KEY = "-----BEGIN PUBLIC KEY-----\nMCowBQYDK2VwAyEAt6Mu4T0pBORY11W+QeM35UsmLO3vsf+6yKpFDEImFk0=\n-----END PUBLIC KEY-----";
    private const SORTS = ['date_added_desc', 'date_updated_desc', 'product_id_desc'];

    public function __construct(
        private readonly ProductRepository $products,
        private readonly string $audience,
        private readonly string $publicKey = self::PUBLIC_KEY,
    ) {
        if ($audience === '') {
            throw new InvalidArgumentException('Audience must be the API host, including port when present.');
        }
    }

    public function authenticate(?string $token, ?string $version = null): void
    {
        if (!$token || $version !== '1') {
            throw new RuntimeException('Unauthorized', 401);
        }

        try {
            $claims = (array) JWT::decode($token, new Key($this->publicKey, 'EdDSA'));
        } catch (Throwable $e) {
            throw new RuntimeException('Unauthorized', 401, $e);
        }

        if (($claims['aud'] ?? null) !== $this->audience) {
            throw new RuntimeException('Unauthorized', 401);
        }
    }

    /** @return array<string,mixed> */
    public function handle(array $input): array
    {
        if ($input === []) {
            throw new InvalidArgumentException('Request body must not be empty.');
        }

        $lookupModes = (int) array_key_exists('page_urls', $input) + (int) array_key_exists('page_uniques', $input);
        if ($lookupModes > 1) {
            throw new InvalidArgumentException('Use either page_urls or page_uniques, not both.');
        }
        if (array_key_exists('page_urls', $input)) {
            $this->rejectUnknownKeys($input, ['page_urls']);
            $values = $this->stringList($input['page_urls'], 'page_urls');
            foreach ($values as $url) {
                if (strlen($url) > 1500 || !self::isHttpUrl($url)) {
                    throw new InvalidArgumentException('Every page_urls value must be an absolute HTTP(S) URL up to 1500 characters.');
                }
            }
            $products = $this->products->findByUrls($values);
            return $this->lookupResponse($products);
        }
        if (array_key_exists('page_uniques', $input)) {
            $this->rejectUnknownKeys($input, ['page_uniques']);
            $values = $this->stringList($input['page_uniques'], 'page_uniques');
            foreach ($values as $unique) {
                if (strlen($unique) > 200) {
                    throw new InvalidArgumentException('Each page_unique must be at most 200 characters.');
                }
            }
            return $this->lookupResponse($this->products->findByUniques($values));
        }

        $this->rejectUnknownKeys($input, ['page', 'sort', 'cursor']);
        if (!isset($input['sort']) || !is_string($input['sort'])) {
            throw new InvalidArgumentException('sort parameter is not provided');
        }
        $sort = $input['sort'];
        if (!in_array($sort, self::SORTS, true)) {
            throw new InvalidArgumentException('Invalid sort parameter');
        }

        // Torob's cursor mode is selected by product_id_desc without page; the first request has no cursor.
        if ($sort === 'product_id_desc' && !array_key_exists('page', $input)) {
            if (array_intersect(['limit', 'size'], array_keys($input)) !== []) {
                throw new InvalidArgumentException('Cursor pagination does not accept page, limit or size.');
            }
            if (array_key_exists('cursor', $input) && (!is_string($input['cursor']) || $input['cursor'] === '')) {
                throw new InvalidArgumentException('cursor must be a non-empty string when provided.');
            }
            if (!$this->products instanceof CursorProductRepository) {
                throw new RuntimeException('Cursor pagination requires a CursorProductRepository implementation.', 501);
            }
            $result = $this->products->cursorPage($input['cursor'] ?? null, $sort, 100);
            $items = $this->validateProducts($result['products'] ?? []);
            $total = $result['total'] ?? null;
            $next = $result['next_cursor'] ?? null;
            if ($total !== null && (!is_int($total) || $total < 0)) {
                throw new RuntimeException('Repository returned an invalid total count.');
            }
            if ($next !== null && !is_string($next)) {
                throw new RuntimeException('Repository returned an invalid next_cursor.');
            }
            return [
                'api_version' => 'torob_api_v3',
                'total' => $total,
                'max_pages' => $total === null ? null : max(1, (int) ceil($total / 100)),
                'next_cursor' => $next,
                'products' => $items,
            ];
        }

        if (array_key_exists('cursor', $input)) {
            throw new InvalidArgumentException('Cursor pagination requires sort=product_id_desc and must not include page.');
        }
        $page = filter_var($input['page'] ?? null, FILTER_VALIDATE_INT);
        if ($page === false || $page === null || $page < 1) {
            throw new InvalidArgumentException('page must be a positive integer');
        }
        if (array_intersect(['limit', 'size'], array_keys($input)) !== []) {
            throw new InvalidArgumentException('Page-based pagination does not accept limit or size.');
        }
        $result = $this->products->page($page, $sort, 100);
        $total = $result['total'] ?? null;
        if (!is_int($total) || $total < 0) {
            throw new RuntimeException('Repository must return a non-negative integer total.');
        }
        $items = $this->validateProducts($result['products'] ?? []);
        return [
            'api_version' => 'torob_api_v3',
            'current_page' => $page,
            'total' => $total,
            'max_pages' => max(1, (int) ceil($total / 100)),
            'next_cursor' => null,
            'products' => $items,
        ];
    }

    private function lookupResponse(array $products): array
    {
        $items = $this->validateProducts($products);
        $total = count($items);
        return ['api_version' => 'torob_api_v3', 'current_page' => 1, 'total' => $total, 'max_pages' => max(1, (int) ceil($total / 100)), 'next_cursor' => null, 'products' => $items];
    }

    private function rejectUnknownKeys(array $input, array $allowed): void
    {
        $unknown = array_diff(array_keys($input), $allowed);
        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown request parameter: ' . (string) reset($unknown));
        }
    }

    private function stringList(mixed $value, string $name): array
    {
        if (!is_array($value) || $value === []) {
            throw new InvalidArgumentException("{$name} must be a non-empty array.");
        }
        foreach ($value as $item) {
            if (!is_string($item) || trim($item) === '') {
                throw new InvalidArgumentException("Every {$name} item must be a non-empty string.");
            }
        }
        return array_values($value);
    }

    private function validateProducts(array $products): array
    {
        foreach ($products as $index => $product) {
            if (!is_array($product)) {
                throw new RuntimeException("Product at index {$index} must be an array.");
            }
            foreach (['page_unique', 'page_url', 'title', 'current_price', 'availability', 'image_links', 'spec', 'date_added'] as $required) {
                if (!array_key_exists($required, $product)) {
                    throw new RuntimeException("Product at index {$index} is missing required field {$required}.");
                }
            }
            if (!is_string($product['page_unique']) || $product['page_unique'] === '' || strlen($product['page_unique']) > 200) throw new RuntimeException("Invalid page_unique for product {$index}.");
            if (!is_string($product['page_url']) || strlen($product['page_url']) > 1500 || !self::isHttpUrl($product['page_url'])) throw new RuntimeException("Invalid page_url for product {$index}.");
            if (!is_string($product['title']) || $product['title'] === '' || mbLen($product['title']) > 500) throw new RuntimeException("Invalid title for product {$index}.");
            if (!is_int($product['current_price']) || $product['current_price'] < 0) throw new RuntimeException("current_price must be a non-negative integer for product {$index}.");
            if (!is_bool($product['availability'])) throw new RuntimeException("availability must be a boolean for product {$index}.");
            if (!is_array($product['image_links'])) throw new RuntimeException("image_links must be an array for product {$index}.");
            foreach ($product['image_links'] as $url) if (!is_string($url) || strlen($url) > 1000 || !self::isHttpUrl($url)) throw new RuntimeException("Invalid image_links value for product {$index}.");
            if (!is_array($product['spec'])) throw new RuntimeException("spec must be an object/array for product {$index}.");
            foreach ($product['spec'] as $specValue) if (!is_string($specValue) && !is_int($specValue)) throw new RuntimeException("spec values must be strings or integers for product {$index}.");
            if (isset($product['old_price']) && (!is_int($product['old_price']) || $product['old_price'] < 0)) throw new RuntimeException("old_price must be a non-negative integer for product {$index}.");
            foreach (['seller_name', 'seller_city'] as $field) if (isset($product[$field]) && !is_string($product[$field])) throw new RuntimeException("{$field} must be a string for product {$index}.");
            if (!is_string($product['date_added']) || !self::isTimezoneDate($product['date_added'])) throw new RuntimeException("date_added must be a timezone-aware ISO 8601 date for product {$index}.");
            if (isset($product['date_updated']) && (!is_string($product['date_updated']) || !self::isTimezoneDate($product['date_updated']))) throw new RuntimeException("date_updated must be a timezone-aware ISO 8601 date for product {$index}.");
            foreach (['subtitle' => 500, 'product_group_id' => 200, 'category_name' => 200, 'short_desc' => 500, 'guarantee' => 200] as $field => $max) {
                if (isset($product[$field]) && (!is_string($product[$field]) || mbLen($product[$field]) > $max)) throw new RuntimeException("Invalid {$field} for product {$index}.");
            }
        }
        return array_values($products);
    }

    private static function isHttpUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true) && parse_url($url, PHP_URL_HOST) !== null;
    }

    private static function isTimezoneDate(string $date): bool
    {
        try { $parsed = new \DateTimeImmutable($date); } catch (\Throwable) { return false; }
        return preg_match('/(?:Z|[+-]\d{2}:?\d{2})$/i', $date) === 1;
    }
}

function mbLen(string $value): int
{
    if (function_exists('mb_strlen')) return mb_strlen($value, 'UTF-8');
    $count = preg_match_all('/./us', $value);
    return $count === false ? strlen($value) : $count;
}
