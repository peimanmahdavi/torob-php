<?php

declare(strict_types=1);

namespace Torob\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Torob\ProductSync\CursorProductRepository;
use Torob\ProductSync\ProductApiV3Service;

final class ProductApiV3ServiceTest extends TestCase
{
    public function testCursorPaginationReturnsCursorFieldsAndOmitsCurrentPage(): void
    {
        $repository = new class implements CursorProductRepository {
            public ?string $receivedCursor = null;
            public ?string $receivedSort = null;
            public int $receivedPageSize = 0;
            public function page(int $page, string $sort, int $perPage = 100): array { return ['products' => [], 'total' => 0]; }
            public function findByUrls(array $urls): array { return []; }
            public function findByUniques(array $uniques): array { return []; }
            public function cursorPage(?string $cursor, string $sort, int $perPage = 100): array
            {
                $this->receivedCursor = $cursor;
                $this->receivedSort = $sort;
                $this->receivedPageSize = $perPage;
                return ['products' => [], 'total' => null, 'next_cursor' => null];
            }
        };
        $service = new ProductApiV3Service($repository, 'shop.example');
        $response = $service->handle(['sort' => 'product_id_desc', 'cursor' => 'opaque-cursor']);
        self::assertSame('opaque-cursor', $repository->receivedCursor);
        self::assertSame('product_id_desc', $repository->receivedSort);
        self::assertSame(100, $repository->receivedPageSize);
        self::assertArrayNotHasKey('current_page', $response);
        self::assertNull($response['total']);
        self::assertNull($response['max_pages']);
        self::assertNull($response['next_cursor']);
    }

    public function testEmptyRequestIsRejected(): void
    {
        $repository = new class implements CursorProductRepository {
            public function page(int $page, string $sort, int $perPage = 100): array { return ['products' => [], 'total' => 0]; }
            public function findByUrls(array $urls): array { return []; }
            public function findByUniques(array $uniques): array { return []; }
            public function cursorPage(?string $cursor, string $sort, int $perPage = 100): array { return ['products' => [], 'total' => null, 'next_cursor' => null]; }
        };
        $this->expectException(InvalidArgumentException::class);
        (new ProductApiV3Service($repository, 'shop.example'))->handle([]);
    }
}
