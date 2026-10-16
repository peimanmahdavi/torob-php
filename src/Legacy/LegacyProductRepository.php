<?php

declare(strict_types=1);

namespace Torob\Legacy;

/** Adapter for stores that must keep an explicitly requested deprecated Product API version. */
interface LegacyProductRepository
{
    /** @return array{products:list<array<string,mixed>>,total:int} */
    public function page(int $page, int $perPage = 100): array;
    /** Return a single product or null if it is not available. */
    public function findByUnique(string $unique): ?array;
    /** Return a single product or null if it is not available. */
    public function findByUrl(string $url): ?array;
}
