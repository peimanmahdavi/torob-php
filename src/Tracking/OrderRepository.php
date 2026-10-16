<?php

declare(strict_types=1);

namespace Torob\Tracking;

interface OrderRepository
{
    /** Return Torob-attributed orders after the given UTC timestamp, oldest first. @return list<array<string,mixed>> */
    public function findSince(string $purchaseTimestampGt, int $limit): array;
}
