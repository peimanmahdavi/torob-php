<?php

declare(strict_types=1);

namespace Torob\Tracking;

use RuntimeException;

/** Framework-neutral handler for GET /torob/v1/orders. */
final class OrderTrackingService extends AbstractTrackingService
{
    public function __construct(private readonly OrderRepository $orders, bool $enabled = true, string $publicKey = TorobJwtAuthenticator::PUBLIC_KEY)
    {
        parent::__construct($enabled, $publicKey);
    }

    public function handle(array $query, ?string $token, ?string $version, string $host): array
    {
        $this->authorize($token, $version, $host);
        [$since, $limit] = $this->validateQuery($query, 'purchase_timestamp_gt');
        $records = $this->ensureSorted($this->orders->findSince($since, $limit), 'purchase_timestamp');
        $cutoff = new \DateTimeImmutable($since);
        $records = array_values(array_filter($records, static fn(array $record): bool => new \DateTimeImmutable($record['purchase_timestamp']) > $cutoff));
        $records = array_slice($records, 0, $limit);
        foreach ($records as $i => $order) {
            foreach (['purchase_timestamp', 'last_updated_timestamp', 'torob_clid', 'status'] as $field) {
                if (!isset($order[$field])) throw new RuntimeException("Order {$i} is missing required field {$field}.");
            }
            if (!is_string($order['torob_clid']) || $order['torob_clid'] === '') throw new RuntimeException("Order {$i} has an invalid torob_clid.");
            if (!in_array($order['status'], ['completed', 'cancelled'], true)) throw new RuntimeException("Order {$i} has an invalid status.");
            $this->validateTimestamp((string) $order['last_updated_timestamp'], 'last_updated_timestamp');
            foreach (['order_value', 'shipping_amount'] as $numericField) {
                if (isset($order[$numericField]) && !is_int($order[$numericField])) throw new RuntimeException("Order {$i} {$numericField} must be an integer.");
            }
            if (isset($order['phone_number']) && !is_string($order['phone_number'])) throw new RuntimeException("Order {$i} phone_number must be a string.");
            if (isset($order['psp']) && !is_string($order['psp'])) throw new RuntimeException("Order {$i} psp must be a string.");
            if (isset($order['products'])) {
                if (!is_array($order['products'])) throw new RuntimeException("Order {$i} products must be an array.");
                foreach ($order['products'] as $product) {
                    if (!is_array($product) || !isset($product['product_url'], $product['product_price'], $product['quantity']) || !is_string($product['product_url']) || !filter_var($product['product_url'], FILTER_VALIDATE_URL) || !is_int($product['product_price']) || !is_int($product['quantity']) || $product['quantity'] < 1 || $product['product_price'] < 0) {
                        throw new RuntimeException("Order {$i} contains an invalid product.");
                    }
                }
            }
        }
        return ['success' => true, 'data' => $records];
    }
}
