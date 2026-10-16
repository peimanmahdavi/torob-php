<?php

declare(strict_types=1);

namespace Torob\Tracking;

/** Helpers for persisting Torob click attribution alongside a session/order/action. */
final class TorobClickAttribution
{
    public const QUERY_PARAMETER = 'torob_clid';
    public const ATTRIBUTION_WINDOW_DAYS = 7;

    public static function capture(array $query): ?string
    {
        $id = $query[self::QUERY_PARAMETER] ?? null;
        return is_string($id) && $id !== '' && strlen($id) <= 255 ? $id : null;
    }

    /** Use in your application before returning order/action records to Torob. */
    public static function onlyAttributed(array $records, string $clickIdField = 'torob_clid'): array
    {
        return array_values(array_filter($records, static fn(array $row): bool => !empty($row[$clickIdField])));
    }
}
