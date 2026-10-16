<?php

declare(strict_types=1);

namespace Torob\Tracking;

use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;

abstract class AbstractTrackingService
{
    protected readonly TorobJwtAuthenticator $authenticator;

    public function __construct(
        protected readonly bool $enabled = true,
        string $publicKey = TorobJwtAuthenticator::PUBLIC_KEY,
    ) {
        $this->authenticator = new TorobJwtAuthenticator($publicKey);
    }

    protected function authorize(?string $token, ?string $version, string $host): void
    {
        $this->authenticator->authenticate($token, $version, $host);
        if (!$this->enabled) {
            throw new RuntimeException('Tracking access is disabled for this shop.', 403);
        }
    }

    protected function validateQuery(array $query, string $timestampField): array
    {
        $timestamp = $query[$timestampField] ?? null;
        if (!is_string($timestamp) || !$this->isIsoTimestamp($timestamp)) {
            throw new InvalidArgumentException("{$timestampField} must be a valid ISO 8601 timestamp with timezone.");
        }
        $limit = filter_var($query['limit'] ?? null, FILTER_VALIDATE_INT);
        if ($limit === false || $limit === null || $limit < 1 || $limit > 1000) {
            throw new InvalidArgumentException('limit must be an integer between 1 and 1000.');
        }
        return [$timestamp, $limit];
    }

    protected function validateTimestamp(string $value, string $field): void
    {
        if (!$this->isIsoTimestamp($value)) {
            throw new RuntimeException("Repository returned an invalid {$field} timestamp.");
        }
    }

    protected function isIsoTimestamp(string $value): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?Z$/', $value)) return false;
        try { new DateTimeImmutable($value); return true; } catch (\Throwable) { return false; }
    }

    protected function ensureSorted(array $records, string $timestampField): array
    {
        foreach ($records as $record) {
            if (!is_array($record) || empty($record['torob_clid'])) {
                throw new RuntimeException('Tracking repositories must return only records with a torob_clid.');
            }
            if (!isset($record[$timestampField]) || !is_string($record[$timestampField])) {
                throw new RuntimeException("Tracking record is missing {$timestampField}.");
            }
            $this->validateTimestamp($record[$timestampField], $timestampField);
        }
        usort($records, static fn(array $a, array $b): int => (new DateTimeImmutable($a[$timestampField])) <=> (new DateTimeImmutable($b[$timestampField])));
        return array_values($records);
    }
}
