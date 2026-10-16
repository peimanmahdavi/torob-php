<?php

declare(strict_types=1);

namespace Torob\Tracking;

use RuntimeException;

/** Framework-neutral handler for GET /torob/v1/actions. */
final class ActionTrackingService extends AbstractTrackingService
{
    public function __construct(private readonly ActionRepository $actions, bool $enabled = true, string $publicKey = TorobJwtAuthenticator::PUBLIC_KEY)
    {
        parent::__construct($enabled, $publicKey);
    }

    public function handle(array $query, ?string $token, ?string $version, string $host): array
    {
        $this->authorize($token, $version, $host);
        [$since, $limit] = $this->validateQuery($query, 'timestamp_gt');
        $records = $this->ensureSorted($this->actions->findSince($since, $limit), 'timestamp');
        $cutoff = new \DateTimeImmutable($since);
        $records = array_values(array_filter($records, static fn(array $record): bool => new \DateTimeImmutable($record['timestamp']) > $cutoff));
        $records = array_slice($records, 0, $limit);
        foreach ($records as $i => $action) {
            foreach (['timestamp', 'last_updated_timestamp', 'torob_clid', 'status', 'action_type'] as $field) {
                if (!isset($action[$field])) throw new RuntimeException("Action {$i} is missing required field {$field}.");
            }
            if (!is_string($action['torob_clid']) || $action['torob_clid'] === '' || !is_string($action['action_type']) || $action['action_type'] === '') throw new RuntimeException("Action {$i} has an invalid torob_clid or action_type.");
            if (!in_array($action['status'], ['completed', 'cancelled'], true)) throw new RuntimeException("Action {$i} has an invalid status.");
            $this->validateTimestamp((string) $action['last_updated_timestamp'], 'last_updated_timestamp');
        }
        return ['success' => true, 'data' => $records];
    }
}
