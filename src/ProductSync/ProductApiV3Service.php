<?php

declare(strict_types=1);

namespace Torob\ProductSync;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/** Framework-neutral Product API v3 handler. Wire authenticate() into your HTTP middleware/controller. */
final class ProductApiV3Service
{
    public const PUBLIC_KEY = "-----BEGIN PUBLIC KEY-----\nMCowBQYDK2VwAyEAt6Mu4T0pBORY11W+QeM35UsmLO3vsf+6yKpFDEImFk0=\n-----END PUBLIC KEY-----";

    public function __construct(private readonly ProductRepository $products, private readonly string $audience, private readonly string $publicKey = self::PUBLIC_KEY) {}

    public function authenticate(?string $token, ?string $version = null): void
    {
        if (!$token || $version !== '1') throw new \RuntimeException('Unauthorized', 401);
        try { JWT::decode($token, new Key($this->publicKey, 'EdDSA')); } catch (\Throwable $e) { throw new \RuntimeException('Unauthorized', 401, $e); }
        $claims = (array) JWT::decode($token, new Key($this->publicKey, 'EdDSA'));
        if (($claims['aud'] ?? null) !== $this->audience) throw new \RuntimeException('Unauthorized', 401);
    }

    public function handle(array $input): array
    {
        $lookupByUrls = $input['page_urls'] ?? null;
        $lookupByUniques = $input['page_uniques'] ?? null;
        if (is_array($lookupByUrls) && $lookupByUrls !== []) {
            $products = $this->products->findByUrls($lookupByUrls);
            return $this->response($products, 1, count($products));
        }
        if (is_array($lookupByUniques) && $lookupByUniques !== []) {
            $products = $this->products->findByUniques($lookupByUniques);
            return $this->response($products, 1, count($products));
        }
        if (!isset($input['sort']) || !is_string($input['sort'])) throw new \InvalidArgumentException('sort parameter is not provided');
        $sort = $input['sort'];
        if (!in_array($sort, ['date_added_desc', 'date_updated_desc', 'product_id_desc'], true)) throw new \InvalidArgumentException('Invalid sort parameter');
        if (isset($input['cursor'])) {
            if ($sort !== 'product_id_desc') throw new \InvalidArgumentException('Cursor pagination requires product_id_desc');
            // Cursor semantics are repository-specific; pass the cursor as an opaque page marker in a future adapter.
            throw new \InvalidArgumentException('Cursor pagination must be implemented by the repository adapter before enabling cursor mode.');
        }
        $page = filter_var($input['page'] ?? null, FILTER_VALIDATE_INT);
        if ($page === false || $page === null || $page < 1) throw new \InvalidArgumentException('page must be a positive integer');
        $result = $this->products->page($page, $sort, 100);
        return $this->response($result['products'], $page, $result['total']);
    }

    private function response(array $products, ?int $page, int $total): array
    {
        return [
            'api_version' => 'torob_api_v3',
            'current_page' => $page,
            'total' => $total,
            'max_pages' => max(1, (int) ceil($total / 100)),
            'next_cursor' => null,
            'products' => array_values($products),
        ];
    }
}
