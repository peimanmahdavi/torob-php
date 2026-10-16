<?php

declare(strict_types=1);

namespace Torob\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Torob\Client\ProductWebhookClient;
use Torob\Client\ShopGeneratorClient;

final class ClientValidationTest extends TestCase
{
    public function testWebhookRejectsNonHttpUrlBeforeMakingARequest(): void
    {
        $client = new ProductWebhookClient('test-token');
        $this->expectException(InvalidArgumentException::class);
        $client->notify([['page_url' => 'ftp://example.ir/product/1', 'page_unique' => 'p-1']]);
    }

    public function testWebhookRejectsMixedDomains(): void
    {
        $client = new ProductWebhookClient('test-token');
        $this->expectException(InvalidArgumentException::class);
        $client->notify([
            ['page_url' => 'https://example.ir/product/1', 'page_unique' => 'p-1'],
            ['page_url' => 'https://other.ir/product/2', 'page_unique' => 'p-2'],
        ]);
    }

    public function testConnectionRequiresSecureReturnUrl(): void
    {
        $client = new ShopGeneratorClient('test-token');
        $this->expectException(InvalidArgumentException::class);
        $client->requestConnection('example.ir', 'http://example.ir/callback');
    }

    public function testConnectionRequiresUuid(): void
    {
        $client = new ShopGeneratorClient('test-token');
        $this->expectException(InvalidArgumentException::class);
        $client->connection('not-a-uuid');
    }
}
