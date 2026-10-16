<?php

declare(strict_types=1);

namespace Torob\ProductSync;

/** Implement this contract with your own database/ORM. Products must use Torob's documented field names. */
interface ProductRepository
{
    /** @return array{products:list<array<string,mixed>>,total:int} */
    public function page(int $page, string $sort, int $perPage = 100): array;
    /** @param list<string> $urls @return list<array<string,mixed>> */
    public function findByUrls(array $urls): array;
    /** @param list<string> $uniques @return list<array<string,mixed>> */
    public function findByUniques(array $uniques): array;
}
