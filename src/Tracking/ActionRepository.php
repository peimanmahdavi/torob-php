<?php

declare(strict_types=1);

namespace Torob\Tracking;

interface ActionRepository
{
    /** Return Torob-attributed actions after the given UTC timestamp, oldest first. @return list<array<string,mixed>> */
    public function findSince(string $timestampGt, int $limit): array;
}
