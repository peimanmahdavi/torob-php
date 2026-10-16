<?php

declare(strict_types=1);

namespace Torob\Exception;

use GuzzleHttp\Exception\GuzzleException;

final class TorobApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $statusCode = 0,
        private readonly array $responseBody = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public function statusCode(): int { return $this->statusCode; }
    public function responseBody(): array { return $this->responseBody; }
}
