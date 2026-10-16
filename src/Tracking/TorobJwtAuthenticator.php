<?php

declare(strict_types=1);

namespace Torob\Tracking;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use RuntimeException;
use Throwable;

/** Validates Torob's X-Torob-Token JWT and exact Host/audience match. */
final class TorobJwtAuthenticator
{
    public const PUBLIC_KEY = "-----BEGIN PUBLIC KEY-----\nMCowBQYDK2VwAyEAt6Mu4T0pBORY11W+QeM35UsmLO3vsf+6yKpFDEImFk0=\n-----END PUBLIC KEY-----";

    public function __construct(private readonly string $publicKey = self::PUBLIC_KEY) {}

    public function authenticate(?string $token, ?string $version, string $host): void
    {
        if ($token === null || $token === '' || $version !== '1' || $host === '') {
            throw new RuntimeException('Unauthorized', 401);
        }
        try {
            $claims = (array) JWT::decode($token, new Key($this->publicKey, 'EdDSA'));
        } catch (Throwable $e) {
            throw new RuntimeException('Unauthorized', 401, $e);
        }
        if (($claims['aud'] ?? null) !== $host) {
            throw new RuntimeException('Unauthorized', 401);
        }
    }
}
