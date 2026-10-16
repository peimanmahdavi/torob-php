<?php

declare(strict_types=1);

namespace Torob\Legacy;

use InvalidArgumentException;
use RuntimeException;

/** Handler for deprecated Product API v2: POST form-data endpoint. */
final class ProductApiV2Service
{
    public function __construct(private readonly LegacyProductRepository $products) {}

    public function handle(array $input): array
    {
        $hasUnique = isset($input['page_unique']) && $input['page_unique'] !== '';
        $hasUrl = isset($input['page_url']) && $input['page_url'] !== '';
        if ($hasUnique && $hasUrl) throw new InvalidArgumentException('Use page_unique or page_url, not both.');
        if ($hasUnique && !is_string($input['page_unique'])) throw new InvalidArgumentException('page_unique must be a string.');
        if ($hasUrl && (!is_string($input['page_url']) || !filter_var($input['page_url'], FILTER_VALIDATE_URL))) throw new InvalidArgumentException('page_url must be an absolute URL.');
        if ($hasUnique || $hasUrl) {
            $product = $hasUnique ? $this->products->findByUnique($input['page_unique']) : $this->products->findByUrl($input['page_url']);
            $items = $product === null ? [] : [$product];
            $total = count($items);
            return ['count' => $total, 'max_pages' => 1, 'products' => $items];
        }
        $page = filter_var($input['page'] ?? 1, FILTER_VALIDATE_INT);
        if ($page === false || $page === null || $page < 1) throw new InvalidArgumentException('page must be a positive integer.');
        $result = $this->products->page($page, 100);
        $items = array_values($result['products'] ?? []);
        $total = $result['total'] ?? null;
        if (!is_int($total) || $total < 0) throw new RuntimeException('Repository must return a non-negative integer total.');
        if (count($items) > 100) throw new RuntimeException('Repository returned more than 100 products.');
        return ['count' => $total, 'max_pages' => max(1, (int) ceil($total / 100)), 'products' => $items];
    }
}
