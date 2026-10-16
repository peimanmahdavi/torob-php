<?php

declare(strict_types=1);

namespace Torob\ProductSync;

/** Optional capability required when Product API v3 cursor pagination is used. */
interface CursorProductRepository extends ProductRepository
{
    /** @return array{products:list<array<string,mixed>>,total:?int,next_cursor:?string} */
    public function cursorPage(?string $cursor, string $sort, int $perPage = 100): array;
}
