<?php

namespace App\Tests\Unit\Plan;

use App\Plan\RoomShape;
use PHPUnit\Framework\TestCase;

final class RoomShapeTest extends TestCase
{
    public function testAPolygonWithItsNameInTheMiddle(): void
    {
        $shape = RoomShape::parse('20,98 50,98 50,116 20,116');

        self::assertSame('20,98 50,98 50,116 20,116', $shape?->svgPoints());
        self::assertEquals([35.0, 107.0], $shape->label);
        self::assertFalse($shape->isUpright());
    }

    public function testTheNameCanBePlacedByHand(): void
    {
        self::assertEquals([45.5, 28.0], RoomShape::parse('20,21 71,21 71,51 45,36 20,51 @45.5,28')?->label);
    }

    public function testNonsenseIsNoShape(): void
    {
        self::assertNull(RoomShape::parse('salon'));
        self::assertNull(RoomShape::parse('1,2 3,4'));
        self::assertNull(RoomShape::parse(null));
    }

    public function testNarrowRoomsAreWrittenUpwards(): void
    {
        self::assertTrue(RoomShape::parse('20,81 25,81 25,97 20,97')?->isUpright());
        self::assertTrue(RoomShape::parse('58,92 71,92 71,97 58,97')?->isLow());
    }

    public function testALongNameGoesOnTwoLinesInASmallRoom(): void
    {
        $fit = RoomShape::parse('58,81 71,81 71,91 58,91')?->fit('Terrasse entrée');

        self::assertSame(['Terrasse', 'entrée'], $fit['lines'] ?? null);
        self::assertLessThanOrEqual(2.3, $fit['size']);
    }
}
