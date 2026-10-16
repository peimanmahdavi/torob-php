<?php

declare(strict_types=1);

namespace Torob\Legacy;

use InvalidArgumentException;
use RuntimeException;

/** Handler for deprecated Product API v1: GET endpoint returning a JSON array. */
final class ProductApiV1Service
{
    public function __construct(private readonly LegacyProductRepository $products) {}

    public function handle(array $query): array
    {
        $page = filter_var($query['page'] ?? null, FILTER_VALIDATE_INT);
        if ($page === false || $page === null || $page < 1) throw new InvalidArgumentException('page must be a positive integer.');
        $result = $this->products->page($page, 100);
        $items = array_values($result['products'] ?? []);
        if (count($items) > 100) throw new RuntimeException('Repository returned more than 100 products.');
        foreach ($items as $index => $item) {
            foreach (['product_id', 'page_url', 'price', 'availability'] as $field) if (!array_key_exists($field, $item)) throw new RuntimeException("Product {$index} is missing {$field}.");
            if (!is_string($item['product_id']) || !is_string($item['page_url']) || !filter_var($item['page_url'], FILTER_VALIDATE_URL) || !is_int($item['price']) || !is_string($item['availability'])) throw new RuntimeException("Product {$index} does not match Product API v1.");
        }
        return $items;
    }
}
