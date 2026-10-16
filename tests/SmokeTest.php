<?php

declare(strict_types=1);

namespace Torob\Tests;

use PHPUnit\Framework\TestCase;
use Torob\Tracking\TorobClickAttribution;

final class SmokeTest extends TestCase
{
    public function testCaptureClickId(): void
    {
        self::assertSame('click-123', TorobClickAttribution::capture(['torob_clid' => 'click-123']));
        self::assertNull(TorobClickAttribution::capture([]));
    }

    public function testOnlyAttributedRecordsAreReturned(): void
    {
        $rows = [['id' => 1, 'torob_clid' => 'abc'], ['id' => 2, 'torob_clid' => null]];
        self::assertCount(1, TorobClickAttribution::onlyAttributed($rows));
    }
}
